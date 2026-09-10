<?php

declare(strict_types=1);

namespace App\Modules\FxCorrection\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\FxCorrection\Http\Requests\CorrectExchangeRateRequest;
use App\Modules\Payments\Exceptions\ExchangeRateAlreadyRecorded as PaymentExchangeRateAlreadyRecorded;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Exceptions\ExchangeRateAlreadyRecorded as StayExchangeRateAlreadyRecorded;
use App\Modules\Stays\Services\StayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Yönetim → Geçmiş Döviz Düzeltme (v2.1.1-C §3).
 *
 * Bu modül `Stay`/`Payment` modellerini HİÇ tanımaz — yalnızca ilgili
 * modüllerin Service katmanı üzerinden çalışır (CLAUDE.md §4.3/1). İki ayrı
 * modülün verisini TEK ekranda birleştirdiği için (Ledger ekstre ekranlarının
 * Companies'te barınmasıyla AYNI gerekçe, §4.3/3) kendi modülüne sahiptir.
 *
 * Yalnızca EKSİK kaydı doldurur: orijinal tutar/para birimi asla değişmez,
 * ikinci bir finansal işlem oluşmaz, cari hareketi ASLA ters kaydedilmez/
 * yeniden işlenmez.
 */
class FxCorrectionController extends Controller
{
    public function __construct(
        private readonly StayService $stays,
        private readonly PaymentService $payments,
    ) {}

    public function index(): View
    {
        $this->authorize(Permission::FxCorrectHistorical->value);

        return view('fx-correction::index', [
            'stays' => $this->stays->missingExchangeRate(),
            'payments' => $this->payments->missingExchangeRate(),
        ]);
    }

    public function updateStay(CorrectExchangeRateRequest $request, int $stay): RedirectResponse
    {
        $this->authorize(Permission::FxCorrectHistorical->value);

        try {
            $this->stays->correctHistoricalExchangeRate(
                $this->stays->findOrFail($stay),
                (string) $request->validated('exchange_rate_to_try'),
                $request->user(),
            );
        } catch (StayExchangeRateAlreadyRecorded $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Konaklamanın kur bilgisi kaydedildi.');
    }

    public function updatePayment(CorrectExchangeRateRequest $request, int $payment): RedirectResponse
    {
        $this->authorize(Permission::FxCorrectHistorical->value);

        try {
            $this->payments->correctHistoricalExchangeRate(
                $this->payments->findOrFail($payment),
                (string) $request->validated('exchange_rate_to_try'),
                $request->user(),
            );
        } catch (PaymentExchangeRateAlreadyRecorded $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tahsilatın kur bilgisi kaydedildi.');
    }
}
