<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
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

    /** @param array<int, int> $stayIds
     * @return array<int, int>
     */
    public function paidMinorByStay(array $stayIds): array
    {
        return $this->active()->whereIn('a.stay_id', $stayIds)->groupBy('a.stay_id')
            ->selectRaw('a.stay_id, SUM(a.amount) as total')->get()
            ->mapWithKeys(fn ($row) => [(int) $row->stay_id => Money::fromDecimal($row->total)->minorUnits])->all();
    }

    /**
     * Bir konaklamaya geçerli (ters kaydedilmemiş) dağılımlarla ödenen tutarın
     * korelasyonlu alt sorgusu — liste filtresi için ("Ödenmeyenler").
     * `summaries()` ile AYNI "etkin dağılım" tanımını kullanır; tutar borç
     * para birimindedir. Satır yoksa NULL döner, çağıran COALESCE uygular.
     */
    public function paidAmountSubquery(string $stayIdColumn): Builder
    {
        return $this->active()->selectRaw('SUM(a.amount)')->whereColumn('a.stay_id', $stayIdColumn);
    }

    public function available(Payment $payment): Money
    {
        if ($payment->isCancelled()) {
            return Money::zero();
        }

        return $payment->amountMoney()->subtract(Money::fromDecimal((string) $this->active()->where('a.payment_id', $payment->id)->sum('a.amount')));
    }

    /** @param array<int, int> $selectedIds */
    public function allocate(Payment $payment, array $selectedIds, User $actor): void
    {
        $selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));
        $candidates = app(StayService::class)->allocationCandidates($payment->company_id, $payment->currency);
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
            $part = min($remaining, max(0, Money::fromDecimal($stay['amount'])->minorUnits - ($paid[$stay['id']] ?? 0)));
            if ($part > 0) {
                $this->write($payment->id, $stay['id'], $part, $actor);
                $allocated += $part;
                $remaining -= $part;
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
        $payments = Payment::query()->where('company_id', $stay['company_id'])->where('currency', $stay['currency'])
            ->where('status', PaymentStatus::Active)->whereNotNull('allocation_mode')->orderBy('payment_date')->orderBy('id')->get();
        $used = $this->active()->whereIn('a.payment_id', $payments->modelKeys())->groupBy('a.payment_id')
            ->selectRaw('a.payment_id, SUM(a.amount) as total')->get()
            ->mapWithKeys(fn ($row) => [(int) $row->payment_id => Money::fromDecimal($row->total)->minorUnits])->all();
        foreach ($payments as $payment) {
            $part = min($remaining, $payment->amountMoney()->minorUnits - ($used[$payment->id] ?? 0));
            if ($part > 0) {
                $this->write($payment->id, $stayId, $part, $actor);
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
            $keep = $payment->company_id === $stay['company_id'] && $payment->currency->value === $stay['currency']
                ? min($remaining, $amount) : 0;
            if ($keep < $amount) {
                $this->reverse($row, $actor, 'Konaklama düzeltildi');
                if ($keep > 0) {
                    $this->write($payment->id, $stayId, $keep, $actor);
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
        DB::table('payment_allocations')->insert([
            'payment_id' => $row->payment_id, 'stay_id' => $row->stay_id, 'amount' => $row->amount,
            'reverses_allocation_id' => $row->id, 'reason' => $reason,
            'created_by' => $actor->id, 'created_at' => now(),
        ]);
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
