<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Exceptions\CashBookException;
use App\Modules\CashBook\Http\Requests\StoreCashMovementRequest;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Core\Enums\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kasa hareketi girişi ve ters kayıt.
 *
 * DÜZENLEME UÇLARI YOKTUR (ne route, ne servis metodu): yanlış hareket ters
 * kayıtla düzeltilir (kullanıcı talimatı §3).
 */
class CashMovementController extends Controller
{
    public function __construct(private readonly CashBookService $cash) {}

    public function create(Request $request): View|RedirectResponse
    {
        $session = $this->cash->activeSession();

        if ($session === null) {
            return redirect()->route('cashbook.index')
                ->with('error', 'Açık kasa vardiyası yok. Önce kasayı açın.');
        }

        $this->authorize('create', [CashMovement::class, $session]);

        return view('cashbook::movements.create', [
            'session' => $session,
            'types' => CashMovementType::selectable(),
            'currencies' => Currency::active(),
            'selectedType' => $request->query('type'),
        ]);
    }

    public function store(StoreCashMovementRequest $request): RedirectResponse
    {
        $session = $this->cash->activeSession();

        if ($session === null) {
            return redirect()->route('cashbook.index')
                ->with('error', 'Açık kasa vardiyası yok. Önce kasayı açın.');
        }

        $this->authorize('create', [CashMovement::class, $session]);

        try {
            $this->cash->recordMovement(
                session: $session,
                type: $request->movementType(),
                currency: $request->currency(),
                amount: $request->money(),
                actor: $request->user(),
                description: $request->validated()['description'] ?? null,
                counterparty: $request->validated()['counterparty'] ?? null,
            );
        } catch (CashBookException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Kaydettikten sonra kullanıcı kasa ekranına döner (kullanıcı talimatı §12).
        return redirect()->route('cashbook.index')
            ->with('success', 'Kasa hareketi kaydedildi.');
    }

    /** Düzeltme: hareketin tam tersini yazar. Orijinal kayıt silinmez. */
    public function reverse(Request $request, CashMovement $movement): RedirectResponse
    {
        $this->authorize('reverse', $movement);

        try {
            $this->cash->reverseMovement($movement, $request->user());
        } catch (CashBookException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cashbook.index')
            ->with('success', 'Hareket ters kayıtla iptal edildi. Orijinal kayıt defterde kalır.');
    }
}
