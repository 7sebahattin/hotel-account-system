<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Services;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\MonthlyAccount\Enums\MonthlyAccountStatus;
use App\Modules\MonthlyAccount\Enums\MonthlyPaymentMethod;
use App\Modules\MonthlyAccount\Models\MonthlyAccountDay;
use App\Modules\MonthlyAccount\Models\MonthlyAccountItem;
use App\Modules\MonthlyAccount\Models\MonthlyAccountRevision;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Kullanıcıya ait günlük manuel kayıt. Bu servis cari, tahsilat, konaklama
 * veya başka finans modüllerine yazmaz; ayrı bir kontrol defteri oluşturur.
 */
class MonthlyAccountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function ownerFor(User $actor, int $ownerId, bool $creating = false): User
    {
        Gate::forUser($actor)->authorize($creating ? 'createForOwner' : 'viewOwner', [MonthlyAccountDay::class, $ownerId]);

        return User::withTrashed()->findOrFail($ownerId);
    }

    /** @return Collection<int, User> */
    public function owners(User $actor): Collection
    {
        if (Gate::forUser($actor)->denies('viewAll', MonthlyAccountDay::class)) {
            return new Collection;
        }

        return User::withTrashed()->orderBy('name')->get(['id', 'name', 'is_active', 'deleted_at']);
    }

    /** @return Collection<string, MonthlyAccountDay> */
    public function month(User $owner, CarbonImmutable $month): Collection
    {
        return MonthlyAccountDay::query()->where('owner_id', $owner->getKey())
            ->whereBetween('account_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->with(['owner', 'updater', 'currentRevision.items'])
            ->orderBy('account_date')->get()
            ->keyBy(fn (MonthlyAccountDay $day): string => $day->account_date->toDateString());
    }

    /** @param iterable<MonthlyAccountDay> $days */
    public function summarize(iterable $days): array
    {
        $revisions = [];
        foreach ($days as $day) {
            if (! $day->isDeleted() && $day->currentRevision !== null) {
                $revisions[] = $day->currentRevision;
            }
        }

        return MonthlyAccountSummary::fromRevisions($revisions);
    }

    /**
     * Yeni satırlar için aktif ortak tanımlar; düzenlemede mevcut sürümün
     * pasifleşmiş kuruluşu da korunur.
     *
     * @return array<int, string>
     */
    public function bankNames(?MonthlyAccountDay $day = null): array
    {
        $names = MonthlyPaymentProvider::query()->where('is_active', true)->pluck('name');

        if ($day !== null) {
            $legacyNames = $day->loadMissing('currentRevision.items')->currentRevision?->items
                ->pluck('bank_name')->filter()->values() ?? collect();
            $names = $names->merge($legacyNames);
        }

        return $names->unique()->sort(fn (string $left, string $right): int => strnatcasecmp($left, $right))->values()->all();
    }

    public function create(array $data, User $actor): MonthlyAccountDay
    {
        $owner = $this->ownerFor($actor, (int) $data['owner_id'], true);

        return DB::transaction(function () use ($data, $actor, $owner): MonthlyAccountDay {
            $this->lockOwner($owner->getKey());
            if ($owner->trashed()) {
                throw ValidationException::withMessages(['owner_id' => 'Silinmiş kullanıcı için yeni günlük kayıt açılamaz.']);
            }
            if (MonthlyAccountDay::query()->where('owner_id', $owner->getKey())->whereDate('account_date', $data['account_date'])->exists()) {
                throw ValidationException::withMessages(['account_date' => 'Bu kullanıcı ve gün için bir kayıt zaten var. Mevcut kaydı düzenleyin.']);
            }

            $snapshot = $this->snapshot($data);
            $this->assertMonthCapacity($owner, $data['account_date'], $snapshot);
            $day = MonthlyAccountDay::query()->create([
                'owner_id' => $owner->getKey(), 'account_date' => $data['account_date'],
                'current_version' => 0, 'status' => MonthlyAccountStatus::Active,
                'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey(),
            ]);
            $this->appendRevision($day, $snapshot, MonthlyAccountStatus::Active, $actor);
            $this->audit->log(AuditEvent::Created, $day, newValues: $this->auditValues($day), user: $actor);

            return $day->load(['owner', 'currentRevision.items']);
        }, 3);
    }

    public function update(MonthlyAccountDay $day, array $data, User $actor): MonthlyAccountDay
    {
        Gate::forUser($actor)->authorize('update', $day);

        return DB::transaction(function () use ($day, $data, $actor): MonthlyAccountDay {
            $owner = $this->lockOwner($day->owner_id);
            $locked = MonthlyAccountDay::query()->lockForUpdate()->findOrFail($day->getKey());
            Gate::forUser($actor)->authorize('update', $locked);
            $this->assertVersion($locked, (int) $data['version']);
            if ((int) $data['owner_id'] !== $locked->owner_id) {
                throw ValidationException::withMessages(['owner_id' => 'Kayıt sahibi sonradan değiştirilemez.']);
            }
            if ($data['account_date'] !== $locked->account_date->toDateString()) {
                throw ValidationException::withMessages(['account_date' => 'Kaydın tarihi sonradan değiştirilemez.']);
            }

            $snapshot = $this->snapshot($data);
            $this->assertMonthCapacity($owner, $data['account_date'], $snapshot, $locked->getKey());
            $old = $this->auditValues($locked);
            $restoring = $locked->isDeleted();
            $this->appendRevision($locked, $snapshot, MonthlyAccountStatus::Active, $actor);
            $this->audit->log($restoring ? AuditEvent::Restored : AuditEvent::Updated, $locked, $old, $this->auditValues($locked), $actor);

            return $locked->load(['owner', 'currentRevision.items']);
        }, 3);
    }

    public function delete(MonthlyAccountDay $day, int $version, User $actor): MonthlyAccountDay
    {
        Gate::forUser($actor)->authorize('delete', $day);

        return DB::transaction(function () use ($day, $version, $actor): MonthlyAccountDay {
            $this->lockOwner($day->owner_id);
            $locked = MonthlyAccountDay::query()->lockForUpdate()->findOrFail($day->getKey());
            $this->assertVersion($locked, $version);
            Gate::forUser($actor)->authorize('delete', $locked);
            $locked->load('currentRevision.items');
            $revision = $locked->currentRevision;
            $old = $this->auditValues($locked);
            $snapshot = [
                'daily_total' => $revision->daily_total,
                'note' => $revision->note,
                'items' => $revision->items->map(fn (MonthlyAccountItem $item): array => $item->only([
                    'method', 'currency', 'bank_name', 'amount', 'conversion_mode', 'exchange_rate_to_try', 'try_equivalent', 'note',
                ]))->all(),
            ];
            $this->appendRevision($locked, $snapshot, MonthlyAccountStatus::Deleted, $actor);
            $this->audit->log(AuditEvent::Deleted, $locked, $old, $this->auditValues($locked), $actor);

            return $locked->load(['owner', 'currentRevision.items']);
        }, 3);
    }

    private function lockOwner(int $ownerId): User
    {
        // Bütün yazmalar önce aynı kullanıcıyı, sonra günü kilitler. Hem
        // kullanıcı/gün tekilliği hem ay toplamı taşma kontrolü seri çalışır.
        return User::withTrashed()->whereKey($ownerId)->lockForUpdate()->firstOrFail();
    }

    private function snapshot(array $data): array
    {
        $items = [];
        foreach ($data['items'] as $index => $item) {
            try {
                $converted = MonthlyAccountConversion::snapshot($item);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['items.'.$index.'.amount' => $exception->getMessage()]);
            }
            $method = MonthlyPaymentMethod::from($item['method']);
            $items[] = array_merge($converted, [
                'method' => $method->value,
                'currency' => $item['currency'],
                'bank_name' => $method->requiresBank() ? trim($item['bank_name']) : null,
                'note' => $item['note'] ?? null,
            ]);
        }

        return [
            'daily_total' => MonthlyAccountConversion::money($data['daily_total'])->toDecimal(),
            'note' => $data['note'] ?? null,
            'items' => $items,
        ];
    }

    private function appendRevision(MonthlyAccountDay $day, array $snapshot, MonthlyAccountStatus $status, User $actor): void
    {
        $revision = MonthlyAccountRevision::query()->create([
            'day_id' => $day->getKey(), 'version' => $day->current_version + 1,
            'status' => $status, 'daily_total' => $snapshot['daily_total'],
            'note' => $snapshot['note'], 'created_by' => $actor->getKey(),
        ]);
        $revision->items()->createMany($snapshot['items']);
        $day->current_version = $revision->version;
        $day->status = $status;
        $day->updated_by = $actor->getKey();
        $day->save();
        $day->unsetRelation('currentRevision');
    }

    private function assertVersion(MonthlyAccountDay $day, int $version): void
    {
        if ($day->current_version !== $version) {
            throw ValidationException::withMessages([
                'version' => 'Bu kayıt siz formu açtıktan sonra değiştirildi. Güncel kaydı yeniden açıp değişikliklerinizi kontrol edin.',
            ]);
        }
    }

    private function assertMonthCapacity(User $owner, string $date, array $snapshot, ?int $exceptDayId = null): void
    {
        $month = CarbonImmutable::parse($date)->startOfMonth();
        $days = $this->month($owner, $month);
        $revisions = [];
        foreach ($days as $day) {
            if ($day->getKey() !== $exceptDayId && ! $day->isDeleted() && $day->currentRevision !== null) {
                $revisions[] = $day->currentRevision;
            }
        }
        $candidate = new MonthlyAccountRevision(['daily_total' => $snapshot['daily_total']]);
        $candidate->setRelation('items', new Collection(array_map(
            fn (array $item): MonthlyAccountItem => new MonthlyAccountItem($item), $snapshot['items']
        )));
        $revisions[] = $candidate;

        try {
            MonthlyAccountSummary::fromRevisions($revisions);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'daily_total' => 'Bu kayıtla aylık toplam desteklenen 9.999.999.999.999,99 sınırını aşıyor. Tutarları kontrol edin.',
                'items' => 'Günlük ve aylık toplamlar desteklenen tutar sınırında kalmalıdır.',
            ]);
        }
    }

    private function auditValues(MonthlyAccountDay $day): array
    {
        return [
            'owner_id' => $day->owner_id, 'account_date' => $day->account_date->toDateString(),
            'version' => $day->current_version, 'status' => $day->status->value,
        ];
    }
}
