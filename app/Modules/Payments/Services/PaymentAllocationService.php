<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payments\Enums\AllocationMode;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Stays\Services\StayService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dağılımlar cari/kasa hareketi üretmez; var olan tahsilatın hangi borcu
 * karşıladığını gösterir. Yazmalar çağıranın transaction ve hesap kilidi altında
 * yapılır. Değiştirme/silme yerine ters dağılım eklenir.
 */
class PaymentAllocationService
{
    private function active(): Builder
    {
        return DB::table('payment_allocations as a')->whereNull('a.reverses_allocation_id')
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('payment_allocations as r')->whereColumn('r.reverses_allocation_id', 'a.id'));
    }

    /** Dövizde kalan TL karşılığını raporlayan sorgular için; aynı para birimli dağılımlar hariçtir. */
    public function conversionTotalsQuery(): Builder
    {
        return $this->active()->whereNotNull('a.payment_amount')->groupBy('a.payment_id')
            ->selectRaw('a.payment_id, SUM(a.amount) as converted_total');
    }

    /** @param array<int, int> $ids
     * @return array<int, array{rate: ?string, equivalent: ?string}>
     */
    public function exchangeRateSnapshotsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')
            ->whereIn('a.id', $ids)->get(['a.id', 'a.amount', 'p.exchange_rate_to_try'])
            ->mapWithKeys(fn ($row) => [(int) $row->id => ['rate' => $row->exchange_rate_to_try, 'equivalent' => $row->amount]])->all();
    }

    /** @param array<int, int> $ids
     * @return array<int, int>
     */
    public function paymentIdsForAllocations(array $ids): array
    {
        return $ids === [] ? [] : DB::table('payment_allocations')->whereIn('id', $ids)->pluck('payment_id', 'id')->map(fn ($id) => (int) $id)->all();
    }

    /** @param array<int, int> $stayIds
     * @return array<int, int>
     */
    public function paidMinorByStay(array $stayIds): array
    {
        return $this->active()->whereIn('a.stay_id', $stayIds)->groupBy('a.stay_id')
            ->selectRaw('a.stay_id, SUM(a.amount) as total')->get()
            ->mapWithKeys(fn ($row) => [(int) $row->stay_id => Money::fromDecimal($row->total)->minorUnits])->all();
    }

    public function available(Payment $payment): Money
    {
        if ($payment->isCancelled()) {
            return Money::zero();
        }

        return $payment->amountMoney()->subtract(Money::fromDecimal((string) $this->active()->where('a.payment_id', $payment->id)->sum(DB::raw('COALESCE(a.payment_amount, a.amount)'))));
    }

    /** @param array<int, int> $selectedIds */
    public function allocate(Payment $payment, array $selectedIds, User $actor): void
    {
        $selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));
        $candidates = app(StayService::class)->allocationCandidates($payment->company_id, $payment->settlement_currency ?? $payment->currency);
        if ($payment->allocation_mode === AllocationMode::Selected) {
            $candidates = array_values(array_filter($candidates, fn ($stay) => in_array($stay['id'], $selectedIds, true)));
            if ($selectedIds === [] || count($candidates) !== count($selectedIds)) {
                throw ValidationException::withMessages(['stay_ids' => 'Seçilen konaklamalar aynı cari hesaba ve para birimine ait, aktif kayıtlar olmalıdır.']);
            }
        } elseif ($selectedIds !== []) {
            throw ValidationException::withMessages(['stay_ids' => 'Konaklama seçmek için belirli konaklamalara dağıt seçeneğini kullanın.']);
        }

        $paid = $this->paidMinorByStay(array_column($candidates, 'id'));
        $remaining = $this->available($payment)->minorUnits;
        $allocated = 0;
        foreach ($candidates as $stay) {
            [$part, $used] = $this->portion($payment, $remaining, max(0, Money::fromDecimal($stay['amount'])->minorUnits - ($paid[$stay['id']] ?? 0)));
            if ($part > 0) {
                $this->writePortion($payment, $stay['id'], $part, $used, $actor);
                $allocated += $part;
                $remaining -= $used;
            }
            if ($remaining === 0) {
                break;
            }
        }
        if ($payment->allocation_mode === AllocationMode::Selected && $allocated === 0) {
            throw ValidationException::withMessages(['stay_ids' => 'Seçilen konaklamalarda ödenecek borç kalmamış. Listeyi yenileyin.']);
        }
    }

    /** Yeni konaklama, daha önce alınan kullanılabilir avansı aynı para biriminde kullanır. */
    public function applyAdvancesToStay(int $stayId, User $actor): void
    {
        $stay = app(StayService::class)->allocationDetails([$stayId])[$stayId];
        $paid = $this->paidMinorByStay([$stayId]);
        $remaining = Money::fromDecimal($stay['amount'])->minorUnits - ($paid[$stayId] ?? 0);
        $payments = Payment::query()->where('company_id', $stay['company_id'])
            ->where(fn ($q) => $q->where('settlement_currency', $stay['currency'])
                ->orWhere(fn ($same) => $same->whereNull('settlement_currency')->where('currency', $stay['currency'])))
            ->where('status', PaymentStatus::Active)->whereNotNull('allocation_mode')->orderBy('payment_date')->orderBy('id')->get();
        $used = $this->active()->whereIn('a.payment_id', $payments->modelKeys())->groupBy('a.payment_id')
            ->selectRaw('a.payment_id, SUM(COALESCE(a.payment_amount, a.amount)) as total')->get()
            ->mapWithKeys(fn ($row) => [(int) $row->payment_id => Money::fromDecimal($row->total)->minorUnits])->all();
        foreach ($payments as $payment) {
            [$part, $consumed] = $this->portion($payment, $payment->amountMoney()->minorUnits - ($used[$payment->id] ?? 0), $remaining);
            if ($part > 0) {
                $this->writePortion($payment, $stayId, $part, $consumed, $actor);
                $remaining -= $part;
            }
            if ($remaining === 0) {
                break;
            }
        }
    }

    /** Tutar azalınca yalnızca fazlalık avansa döner; hesap/döviz değişince eski dağılımlar çözülür. */
    public function reconcileStay(int $stayId, User $actor): void
    {
        $stay = app(StayService::class)->allocationDetails([$stayId])[$stayId];
        $remaining = Money::fromDecimal($stay['amount'])->minorUnits;
        $rows = $this->active()->where('a.stay_id', $stayId)->orderBy('a.id')->get();
        $payments = Payment::query()->whereIn('id', $rows->pluck('payment_id'))->get()->keyBy('id');
        foreach ($rows as $row) {
            $payment = $payments[$row->payment_id];
            $amount = Money::fromDecimal($row->amount)->minorUnits;
            $keep = $payment->company_id === $stay['company_id'] && ($row->debt_currency ?? $payment->currency->value) === $stay['currency']
                ? min($remaining, $amount) : 0;
            if ($keep < $amount) {
                $this->reverse($row, $actor, 'Konaklama düzeltildi');
                if ($keep > 0) {
                    [$keep, $consumed] = $this->portion($payment, Money::fromDecimal($row->payment_amount ?? $row->amount)->minorUnits, $keep);
                    if ($keep > 0) {
                        $this->writePortion($payment, $stayId, $keep, $consumed, $actor);
                    }
                }
            }
            $remaining -= $keep;
        }
    }

    public function releaseStay(int $stayId, User $actor): void
    {
        foreach ($this->active()->where('a.stay_id', $stayId)->get() as $row) {
            $this->reverse($row, $actor, 'Konaklama iptal edildi');
        }
    }

    public function releasePayment(int $paymentId, User $actor): void
    {
        foreach ($this->active()->where('a.payment_id', $paymentId)->get() as $row) {
            $this->reverse($row, $actor, 'Tahsilat iptal edildi');
        }
    }

    private function write(int $paymentId, int $stayId, int $minor, User $actor): void
    {
        DB::table('payment_allocations')->insert([
            'payment_id' => $paymentId, 'stay_id' => $stayId,
            'amount' => Money::fromMinorUnits($minor)->toDecimal(),
            'created_by' => $actor->id, 'created_at' => now(),
        ]);
    }

    private function reverse(object $row, User $actor, string $reason): void
    {
        if ($row->payment_amount !== null) {
            $ledger = app(LedgerService::class);
            foreach ($ledger->activeEntriesForSource('payment_allocation', (int) $row->id) as $entry) {
                $ledger->reverse($entry, actor: $actor);
            }
        }
        DB::table('payment_allocations')->insert([
            'payment_id' => $row->payment_id, 'stay_id' => $row->stay_id, 'amount' => $row->amount,
            'payment_amount' => $row->payment_amount, 'debt_currency' => $row->debt_currency,
            'reverses_allocation_id' => $row->id, 'reason' => $reason,
            'created_by' => $actor->id, 'created_at' => now(),
        ]);
    }

    /** Borcu aşmayan en yüksek döviz kuruşunu bulur; farkı silmez. @return array{int, int} */
    private function portion(Payment $payment, int $available, int $debt): array
    {
        if ($payment->settlement_currency === null) {
            $part = min($available, $debt);

            return [$part, $part];
        }
        $low = 0;
        $high = $available;
        $rate = (string) $payment->exchange_rate_to_try;
        // Kalan tutarın karşılığından düş: parçalı dağılımda kuruşlar birikmez.
        $equivalent = Money::fromMinorUnits($available)->multiplyByRate($rate)->minorUnits;
        while ($low < $high) {
            $mid = $low + intdiv($high - $low + 1, 2);
            if ($equivalent - Money::fromMinorUnits($available - $mid)->multiplyByRate($rate)->minorUnits <= $debt) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }
        $covered = $equivalent - Money::fromMinorUnits($available - $low)->multiplyByRate($rate)->minorUnits;

        return $covered > 0 ? [$covered, $low] : [0, 0];
    }

    private function writePortion(Payment $payment, int $stayId, int $debt, int $received, User $actor): void
    {
        if ($payment->settlement_currency === null) {
            $this->write($payment->id, $stayId, $debt, $actor);

            return;
        }
        $debtMoney = Money::fromMinorUnits($debt);
        $receivedMoney = Money::fromMinorUnits($received);
        $id = DB::table('payment_allocations')->insertGetId([
            'payment_id' => $payment->id, 'stay_id' => $stayId,
            'amount' => $debtMoney->toDecimal(), 'payment_amount' => $receivedMoney->toDecimal(),
            'debt_currency' => Currency::TRY->value, 'created_by' => $actor->id, 'created_at' => now(),
        ]);
        $description = 'Dövizle borç kapama · '.$payment->receipt_no.' · Konaklama #'.$stayId.' · Kur '.$payment->exchange_rate_to_try;
        $ledger = app(LedgerService::class);
        $ledger->debit($payment->company_id, $receivedMoney, $payment->currency, $payment->payment_date, $description, 'payment_allocation', $id, $actor);
        $ledger->credit($payment->company_id, $debtMoney, Currency::TRY, $payment->payment_date, $description, 'payment_allocation', $id, $actor);
    }

    /** @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function summaries(array $ids): array
    {
        $stays = app(StayService::class)->allocationDetails($ids);
        $paid = $this->paidMinorByStay($ids);
        $legacy = Payment::query()->whereIn('company_id', array_column($stays, 'company_id'))
            ->whereNull('allocation_mode')->where('status', PaymentStatus::Active)->get(['company_id', 'currency'])
            ->mapWithKeys(fn (Payment $payment) => [$payment->company_id.':'.$payment->currency->value => true])->all();
        $result = [];
        foreach ($stays as $id => $stay) {
            $total = Money::fromDecimal($stay['amount'])->minorUnits;
            $settled = $paid[$id] ?? 0;
            $historic = $legacy[$stay['company_id'].':'.$stay['currency']] ?? false;
            $result[$id] = [
                'paid' => Money::fromMinorUnits($settled),
                'remaining' => Money::fromMinorUnits($stay['cancelled'] ? 0 : max(0, $total - $settled)),
                'legacy' => $historic,
                'label' => $stay['cancelled'] ? 'İptal' : ($historic && $settled < $total ? 'Geçmiş eşleştirme bekliyor' : ($settled >= $total ? 'Ödendi' : ($settled > 0 ? 'Kısmen ödendi' : 'Ödenmedi'))),
            ];
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function paymentDetails(Payment $payment): array
    {
        $rows = $this->active()->where('a.payment_id', $payment->id)->orderBy('a.id')->get();
        $stays = app(StayService::class)->allocationDetails($rows->pluck('stay_id')->all());

        return $rows->map(fn ($row) => [
            'stay_id' => (int) $row->stay_id,
            'guest_name' => $stays[$row->stay_id]['guest_name'] ?? 'Konaklama kaydı bulunamadı',
            'amount' => Money::fromDecimal($row->amount),
            'currency' => $row->debt_currency === null ? $payment->currency : Currency::from($row->debt_currency),
            'payment_amount' => $row->payment_amount === null ? null : Money::fromDecimal($row->payment_amount),
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function options(int $companyId, Currency $currency): array
    {
        $stays = app(StayService::class)->allocationCandidates($companyId, $currency);
        $summaries = $this->summaries(array_column($stays, 'id'));

        return array_values(array_map(fn ($stay) => [
            'id' => $stay['id'], 'guest_name' => $stay['guest_name'], 'check_in_date' => $stay['check_in_date'],
            'remaining' => $summaries[$stay['id']]['remaining']->toDecimal(),
            'remaining_formatted' => $summaries[$stay['id']]['remaining']->format($currency),
            'label' => $summaries[$stay['id']]['label'],
        ], array_filter($stays, fn ($stay) => $summaries[$stay['id']]['remaining']->isPositive())));
    }
}
