<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashBook\Exceptions\CashBookException;
use App\Modules\CashBook\Http\Requests\CloseCashSessionRequest;
use App\Modules\CashBook\Http\Requests\OpenCashSessionRequest;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Core\Enums\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vardiya açma, kapatma, görüntüleme ve devir raporu.
 */
class CashSessionController extends Controller
{
    public function __construct(private readonly CashBookService $cash) {}

    /** Kasa açılış formu (ilk açılış — devir alınmıyorsa). */
    public function create(): View|RedirectResponse
    {
        $this->authorize('create', CashSession::class);

        if ($this->cash->activeSession() !== null) {
            return redirect()->route('cashbook.index')
                ->with('error', 'Zaten açık bir kasa vardiyası var.');
        }

        return view('cashbook::sessions.open', [
            'currencies' => Currency::active(),
        ]);
    }

    public function store(OpenCashSessionRequest $request): RedirectResponse
    {
        try {
            $this->cash->openSession(
                actor: $request->user(),
                openingAmounts: $request->currencyAmounts(),
                note: $request->validated()['opening_note'] ?? null,
            );
        } catch (CashBookException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cashbook.index')
            ->with('success', 'Kasa açıldı. Vardiyanız başladı.');
    }

    public function show(CashSession $session): View
    {
        $this->authorize('view', $session);

        return view('cashbook::sessions.show', [
            'summary' => $this->cash->summaryFor($session),
            'movements' => $this->cash->movementsFor($session),
        ]);
    }

    /** Yazdırılabilir devir raporu — koyu tema uygulanmaz (CLAUDE.md §11). */
    public function print(CashSession $session): View
    {
        $this->authorize('view', $session);

        return view('cashbook::sessions.print', [
            'summary' => $this->cash->summaryFor($session),
            'movements' => $this->cash->movementsFor($session),
        ]);
    }

    /** Sayım + kapanış formu. Beklenen tutarlar burada gösterilir. */
    public function closeForm(CashSession $session): View
    {
        $this->authorize('close', $session);

        return view('cashbook::sessions.close', [
            'summary' => $this->cash->summaryFor($session),
        ]);
    }

    public function close(CloseCashSessionRequest $request, CashSession $session): RedirectResponse
    {
        $this->authorize('close', $session);

        try {
            $this->cash->closeSession(
                session: $session,
                countedAmounts: $request->currencyAmounts(),
                actor: $request->user(),
                note: $request->validated()['closing_note'] ?? null,
            );
        } catch (CashBookException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cashbook.sessions.show', $session)
            ->with('success', 'Vardiya kapatıldı ve sayım kaydedildi.');
    }

    /** Günlük kasa defteri — tarih bazlı, tüm vardiyalar. */
    public function day(Request $request): View
    {
        $this->authorize('viewAll', CashSession::class);

        $date = $request->date('date') ?? now();

        $sessions = $this->cash->sessionsForDate($date);

        return view('cashbook::day', [
            'date' => $date,
            'sessions' => $sessions,
            // Vardiya sayısı ne olursa olsun sabit sorgu (N+1 yok).
            'summaries' => $this->cash->summariesFor($sessions),
        ]);
    }
}
