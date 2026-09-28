<?php

declare(strict_types=1);

namespace App\Modules\Companies\Services;

use App\Models\User;
use App\Modules\Companies\Enums\AccountType;
use App\Modules\Companies\Exceptions\CompanyHasLedgerEntries;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Firma modülünün dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * Cari hareketi olan firma silinemez (CLAUDE.md §5.2). Bu kontrol Policy'de
 * DEĞİL burada zorunludur: Gate::before admin için Policy'leri kısa devre
 * yapabilir ve servis, HTTP dışındaki çağrı yollarını da (konsol komutu,
 * gelecekteki import) kapsar. Policy aynı kuralı yalnızca arayüzde butonu
 * gizlemek için tekrarlar.
 */
class CompanyService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** Finansal yazmalarda aynı hesabın işlemleri tek sırada yürür.
     * @param  array<int, int>  $ids
     */
    public function lockAccounts(array $ids): void
    {
        Company::query()->whereIn('id', array_unique($ids))->orderBy('id')->lockForUpdate()->get();
    }

    /** @return array<int, string> */
    public function options(?AccountType $type = null, ?int $currentId = null): array
    {
        return Company::query()
            ->when($type !== null, fn ($query) => $query->where('account_type', $type))
            ->when($type !== null, fn ($query) => $query->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $currentId)))
            ->orderBy('name')->orderBy('id')->get()
            ->mapWithKeys(fn (Company $account) => [$account->id => $account->account_type === AccountType::Individual
                ? $account->name.' · #'.$account->id.($account->phone ? ' · '.$account->phone : '')
                : $account->name])->all();
    }

    public function isIndividual(int $id): bool
    {
        return Company::query()->whereKey($id)->where('account_type', AccountType::Individual)->exists();
    }

    /** Çağıran konaklama transaction'ı hesabı ve cari hareketini birlikte korur. */
    public function individualForStay(?int $id, string $name, User $actor, ?int $currentId = null): int
    {
        if ($id !== null) {
            $account = Company::query()->whereKey($id)->lockForUpdate()->first();
            if ($account === null || $account->account_type !== AccountType::Individual || (! $account->is_active && $id !== $currentId)) {
                throw ValidationException::withMessages(['individual_account_id' => 'Geçerli, aktif bir bireysel hesap seçin.']);
            }

            return $account->id;
        }

        // İsim eşleştirmesi yapılmaz: aynı isimde farklı kişiler olabilir.
        $account = new Company(['name' => $name, 'account_type' => AccountType::Individual, 'is_active' => true]);
        $account->created_by = $actor->id;
        $account->updated_by = $actor->id;
        $account->save();

        return $account->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Company
    {
        return DB::transaction(fn (): Company => Company::query()->create($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): void
    {
        $type = $data['account_type'] ?? $company->account_type;
        $typeValue = $type instanceof AccountType ? $type->value : $type;
        if ($typeValue !== $company->account_type->value) {
            throw ValidationException::withMessages(['account_type' => 'Hesap türü sonradan değiştirilemez.']);
        }

        DB::transaction(function () use ($company, $data): void {
            $company->update($data);
        });
    }

    /**
     * Soft delete. Firma kaydı hiçbir zaman fiziksel olarak silinmez.
     *
     * @throws CompanyHasLedgerEntries cari hareketi olan firma için
     */
    public function delete(Company $company): void
    {
        if ($this->ledger->hasEntriesForCompany($company->getKey())) {
            throw CompanyHasLedgerEntries::make($company->name);
        }

        DB::transaction(function () use ($company): void {
            $company->delete();
        });
    }

    public function restore(Company $company): void
    {
        DB::transaction(function () use ($company): void {
            $company->restore();
        });
    }

    /**
     * Aktif firma sayısı.
     *
     * Soft delete edilmiş firmalar Eloquent'in global scope'u sayesinde zaten
     * dışarıdadır; burada ek olarak pasifleştirilmiş firmalar da sayılmaz.
     */
    public function activeCount(): int
    {
        return Company::query()->where('is_active', true)->count();
    }

    /**
     * Aktif ve pasif cari hesap sayısı — TEK sorguda (pano "Cari Özeti").
     * Silinmiş hesaplar sayılmaz.
     *
     * @return array{active: int, passive: int}
     */
    public function activityCounts(): array
    {
        $row = Company::query()
            ->selectRaw('COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_active = 1 THEN 0 ELSE 1 END), 0) as passive_count')
            ->toBase()
            ->first();

        return [
            'active' => (int) ($row->active_count ?? 0),
            'passive' => (int) ($row->passive_count ?? 0),
        ];
    }

    /**
     * Verilen kimlikler için firma adları — TEK sorguda.
     *
     * Başka modüller firma adını göstermek için Company modeline uzanmaz
     * (CLAUDE.md §4.3/1); bu kapıdan toplu olarak çözerler ve N+1 oluşmaz.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function namesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Company::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->pluck('name', 'id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }

    /** @return array<int, array{id: int, name: string, type: string}> */
    public function searchActive(string $term, int $limit = 10): array
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($term));

        return Company::query()->where('is_active', true)
            ->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", ['%'.$escaped.'%'])
            ->orderBy('name')->orderBy('id')->limit($limit)
            ->get(['id', 'name', 'account_type'])
            ->map(fn (Company $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->account_type->value,
            ])->all();
    }

    public function activeName(int $id): ?string
    {
        $name = Company::query()->whereKey($id)->where('is_active', true)->value('name');

        return $name === null ? null : (string) $name;
    }
}
