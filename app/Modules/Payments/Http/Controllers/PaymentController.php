<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Filters\QueryFilter;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\CannotUpdateCancelledPayment;
use App\Modules\Payments\Exceptions\PaymentAlreadyCancelled;
use App\Modules\Payments\Exceptions\PaymentFinancialFieldsAreImmutable;
use App\Modules\Payments\Filters\PaymentFilter;
use App\Modules\Payments\Http\Requests\AllocationOptionsRequest;
use App\Modules\Payments\Http\Requests\CancelPaymentRequest;
use App\Modules\Payments\Http\Requests\StorePaymentRequest;
use App\Modules\Payments\Http\Requests\UpdatePaymentRequest;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentAllocationService;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(PaymentFilter $filter): View
    {
        $this->authorize('viewAny', Payment::class);

        $payments = $filter->apply(Payment::query()->with(['company', 'creator']))
            ->paginate($filter->perPage())
            ->withQueryString();

        return view('payments::index', [
            'payments' => $payments,
            'filter' => $filter,
            'companies' => $this->companyOptions(),
            'currencies' => Currency::cases(),
            'methods' => PaymentMethod::cases(),
            'statuses' => PaymentStatus::cases(),
            'perPageOptions' => QueryFilter::PER_PAGE_OPTIONS,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Payment::class);

        return view('payments::create', [
            'companies' => $this->companyOptions(),
            'currencies' => Currency::active(),
            'methods' => PaymentMethod::primary(),
            'selectedCompanyId' => $request->integer('company_id') ?: null,
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $payment = $this->payments->create($request->validated(), $request->user());

        return redirect()->route('payments.show', $payment)
            ->with('success', 'Tahsilat kaydedildi ve cari hesaba işlendi.');
    }

    public function allocationOptions(AllocationOptionsRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['stays' => app(PaymentAllocationService::class)->options(
            (int) $data['company_id'], Currency::from($data['currency']),
        )]);
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);

        return view('payments::show', [
            'allocations' => app(PaymentAllocationService::class)->paymentDetails($payment),
            'availableAdvance' => app(PaymentAllocationService::class)->available($payment),
            'payment' => $payment->load(['company', 'creator', 'updater', 'canceller']),
        ]);
    }

    /**
     * Yazdırılabilir Tahsilat Makbuzu (v2.1.1-B §12).
     *
     * `receipt_no` yoksa (bu özellikten ÖNCEKİ eski kayıt) makbuz üretilmez —
     * geçmiş veri tahmin edilmez (K-094/K-095 ile aynı ilke).
     */
    public function receipt(Payment $payment): View
    {
        $this->authorize('view', $payment);

        abort_if($payment->receipt_no === null, 404, 'Bu tahsilat için makbuz numarası yok (eski kayıt).');

        return view('payments::receipt', [
            'payment' => $payment->load(['company', 'creator']),
        ]);
    }

    public function edit(Payment $payment): View
    {
        $this->authorize('update', $payment);

        return view('payments::edit', ['payment' => $payment->load('company')]);
    }

    /** Yalnızca metadata düzeltmesi; finansal alanlar isteğe girse bile alınmaz. */
    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        try {
            $this->payments->updateMetadata($payment, $request->validated(), $request->user());
        } catch (CannotUpdateCancelledPayment|PaymentFinancialFieldsAreImmutable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('payments.show', $payment)
            ->with('success', 'Tahsilat bilgileri güncellendi. Finansal alanlar değişmez.');
    }

    public function cancel(CancelPaymentRequest $request, Payment $payment): RedirectResponse
    {
        try {
            $this->payments->cancel(
                $payment,
                (string) $request->validated()['cancellation_reason'],
                $request->user(),
            );
        } catch (PaymentAlreadyCancelled $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payments.show', $payment)
            ->with('success', 'Tahsilat iptal edildi ve cari hareketi ters kaydedildi.');
    }

    /**
     * Firma seçenekleri. Silinmiş firmalar listelenmez.
     *
     * @return array<int, string>
     */
    private function companyOptions(): array
    {
        return app(CompanyService::class)->options();
    }
}
