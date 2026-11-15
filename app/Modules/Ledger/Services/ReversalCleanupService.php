<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Core\Trash\FinancialPurgeGate;
use App\Modules\Core\Trash\TrashActionFailed;
use App\Modules\Ledger\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GEÇİCİ "Ters Kayıt Temizliği" (15 Kasım 2026, kullanıcı talimatı — CLAUDE.md).
 *
 * Çöp kutusundan ÖNCEKİ dönemde ters kayıtla düzeltilmiş hareketler ekstrede
 * "orijinal + [İPTAL]" çifti olarak kalır; kaynak kaydı silinmiş olanlar ne
 * tıklanabilir ne çöpe taşınabilir. Bu servis yalnızca böyle bir ÇİFTİ (orijinal
 * hareket + onun ters kaydı) birlikte siler: çift tanım gereği net sıfırdır,
 * bu yüzden hiçbir bakiye DEĞİŞMEZ. Tek başına satır silinemez.
 *
 * DELETE tetikleyicisi yalnızca `FinancialPurgeGate` (aktif Admin, sayılan
 * kimlikler, 60 sn) açıkken izin verir; UPDATE korumaları değişmez.
 */
class ReversalCleanupService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Ters kaydı alınmış çiftler (en yeni üstte). Çöp kutusundaki kaynağın
     * satırları dahil DEĞİLDİR — onlar çöp kutusunun kendi akışıyla silinir.
     *
     * @return Collection<int, array{original: LedgerEntry, reversal: LedgerEntry, source_exists: bool}>
     */
    public function pairs(): Collection
    {
        $reversals = LedgerEntry::query()
            ->where('is_reversal', true)
            ->whereNotNull('reverses_entry_id')
            ->with('reversedEntry')
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->get()
            ->filter(fn (LedgerEntry $reversal): bool => $reversal->reversedEntry !== null)
            ->values();

        $existing = $this->existingSources($reversals);

        return $reversals->map(function (LedgerEntry $reversal) use ($existing): array {
            /** @var LedgerEntry $original */
            $original = $reversal->reversedEntry;

            return [
                'original' => $original,
                'reversal' => $reversal,
                'source_exists' => $original->source_id !== null
                    && in_array($original->source_id, $existing[(string) $original->source_type] ?? [], true),
            ];
        });
    }

    /**
     * Verilen ters kayıtları, ters aldıkları orijinal hareketle BİRLİKTE siler.
     * Hepsi tek transaction: biri reddedilirse hiçbiri silinmez.
     *
     * @param  array<int, int>  $reversalIds
     * @return int silinen çift sayısı
     *
     * @throws TrashActionFailed
     */
    public function purgePairs(array $reversalIds, User $actor): int
    {
        $reversalIds = array_values(array_unique(array_map('intval', $reversalIds)));
        if ($reversalIds === []) {
            throw new TrashActionFailed('Silinecek ters kayıt seçilmedi.');
        }

        return DB::transaction(function () use ($reversalIds, $actor): int {
            $reversals = LedgerEntry::query()->whereIn('id', $reversalIds)->lockForUpdate()->get()->keyBy('id');
            $originalIds = $reversals->pluck('reverses_entry_id')->filter()->map(fn ($id): int => (int) $id)->all();
            $originals = LedgerEntry::query()->whereIn('id', $originalIds)->lockForUpdate()->get()->keyBy('id');

            $ledgerIds = [];
            $snapshots = [];

            foreach ($reversalIds as $id) {
                $reversal = $reversals->get($id);
                $original = $reversal === null ? null : $originals->get($reversal->reverses_entry_id);

                if ($reversal === null || ! $reversal->is_reversal || $original === null || $original->is_reversal) {
                    throw new TrashActionFailed("#{$id} numaralı hareket silinebilir bir ters kayıt çifti değil; hiçbir kayıt silinmedi.");
                }

                $pairIds = [$original->id, $reversal->id];
                if ($original->company_id !== $reversal->company_id
                    || $original->currency !== $reversal->currency
                    || ! $this->ledger->entriesNetToZero($pairIds)) {
                    throw new TrashActionFailed("#{$id} numaralı ters kayıt orijinaliyle net sıfır değil; silme bakiyeyi bozacağı için reddedildi.");
                }

                array_push($ledgerIds, ...$pairIds);
                $snapshots[] = ['original' => $this->snapshot($original), 'reversal' => $this->snapshot($reversal)];
            }

            app(FinancialPurgeGate::class)->run($actor, $ledgerIds, [], function () use ($ledgerIds): void {
                $this->ledger->purgeEntries($ledgerIds);
            });

            $this->auditLogger->log(
                event: AuditEvent::Deleted,
                oldValues: ['purged_reversal_pairs' => $snapshots],
                newValues: ['permanently_deleted' => true, 'purged_ledger_entry_ids' => $ledgerIds],
                user: $actor,
            );

            return count($snapshots);
        });
    }

    /**
     * Kaynak tipi başına tek sorguyla var olan kaynak kimlikleri (çöptekiler dahil).
     * Ledger modülleri tanımaz; yalnızca morph map'i kullanır.
     *
     * @param  Collection<int, LedgerEntry>  $reversals
     * @return array<string, array<int, int>>
     */
    private function existingSources(Collection $reversals): array
    {
        $existing = [];

        foreach ($reversals->groupBy(fn (LedgerEntry $entry): string => (string) $entry->source_type) as $type => $entries) {
            $class = Relation::getMorphedModel($type);
            if ($type === '' || $class === null) {
                continue;
            }

            // withoutGlobalScopes() SoftDeletes kapsamını da kaldırır: çöpteki kaynak "var" sayılır.
            $existing[$type] = $class::query()->withoutGlobalScopes()->whereIn('id', $entries->pluck('source_id')->filter()->unique()->all())
                ->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $existing;
    }

    /** @return array<string, mixed> */
    private function snapshot(LedgerEntry $entry): array
    {
        return [
            'ledger_entry_id' => $entry->id,
            'company_id' => $entry->company_id,
            'entry_date' => $entry->entry_date->toDateString(),
            'description' => $entry->description,
            'debit' => $entry->debit,
            'credit' => $entry->credit,
            'currency' => $entry->currency->value,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'reverses_entry_id' => $entry->reverses_entry_id,
            'created_by' => $entry->created_by,
            'created_at' => $entry->created_at->toDateTimeString(),
        ];
    }
}
