<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Http\Requests\CompanyRequest;
use App\Modules\Companies\Services\CompanyService;
use App\Modules\Core\Enums\Currency;
use App\Modules\ReceiptBook\Enums\ReceiptPaymentMethod;
use App\Modules\ReceiptBook\Http\Requests\StoreReceiptBookEntryRequest;
use App\Modules\ReceiptBook\Http\Requests\UpdateReceiptBookEntryRequest;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use App\Modules\ReceiptBook\Services\ReceiptBookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tahsilat Makbuzu Defteri (v2.1.1-C §4) — mevcut muhasebe Tahsilatları'ndan
 * (`Payments`) TAMAMEN AYRI, yazdırma amaçlı bir modül. Bkz. `ReceiptBookService`
 * dosya başı yorumu: hiçbir muhasebe/kasa yan etkisi üretmez.
 */
class ReceiptBookController extends Controller
{
    public function __construct(
        private readonly ReceiptBookService $receiptBook,
        private readonly CompanyService $companies,
    ) {}

    public function customerSearch(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $term = trim((string) $request->query('q', ''));

        return response()->json(['results' => mb_strlen($term) < 2 ? [] : $this->companies->searchActive($term)]);
    }

    public function customerCreate(CompanyRequest $request): JsonResponse
    {
        $account = $this->companies->create($request->validated());

        return response()->json([
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->account_type->value,
        ], 201);
    }

    public function index(): View
    {
        $this->authorize('viewAny', ReceiptBookEntry::class);

        return view('receipt-book::index', [
            'entries' => $this->receiptBook->paginate(),
        ]);
    }

    /**
     * "Yeni Makbuz" — modal/dialog içeriği (v2.1.1-C §5). Kasa Hareketi
     * dialog'unun (v2.1.1-B.1) AYNI desenini kullanır: `$request->ajax()`
     * iken yalnızca içerik parçası döner, normal gezinmede TAM sayfa döner —
     * ikisi de AYNI `_form` partial'ını kullanır (iki farklı form YOKTUR).
     */
    public function create(Request $request): View
    {
        $this->authorize('create', ReceiptBookEntry::class);

        $data = [
            'currencies' => Currency::active(),
            'methods' => ReceiptPaymentMethod::cases(),
        ];

        return $request->ajax()
            ? view('receipt-book::_dialog-fragment', $data)
            : view('receipt-book::create', $data);
    }

    public function store(StoreReceiptBookEntryRequest $request): RedirectResponse
    {
        $entry = $this->receiptBook->create($request->validated(), $request->user());

        return redirect()->route('receipt-book.show', $entry)
            ->with('success', 'Makbuz kaydedildi.');
    }

    public function show(ReceiptBookEntry $entry): View
    {
        $this->authorize('view', $entry);

        return view('receipt-book::show', ['entry' => $entry->load('creator')]);
    }

    public function print(ReceiptBookEntry $entry): View
    {
        $this->authorize('view', $entry);

        return view('receipt-book::print', ['entry' => $entry->load('creator')]);
    }

    public function edit(ReceiptBookEntry $entry): View
    {
        $this->authorize('update', $entry);

        // Aktif listeden çıkarılmış bir para birimindeki makbuz da düzenlenebilsin.
        $currencies = Currency::active();

        if (! in_array($entry->currency, $currencies, true)) {
            $currencies[] = $entry->currency;
        }

        return view('receipt-book::edit', [
            'entry' => $entry,
            'currencies' => $currencies,
            'methods' => ReceiptPaymentMethod::cases(),
        ]);
    }

    public function update(UpdateReceiptBookEntryRequest $request, ReceiptBookEntry $entry): RedirectResponse
    {
        $this->receiptBook->update($entry, $request->validated(), $request->user());

        return redirect()->route('receipt-book.show', $entry)
            ->with('success', 'Makbuz güncellendi.');
    }

    public function destroy(ReceiptBookEntry $entry): RedirectResponse
    {
        $this->authorize('delete', $entry);

        $this->receiptBook->delete($entry);

        return redirect()->route('receipt-book.index')
            ->with('success', 'Makbuz silindi.');
    }
}
