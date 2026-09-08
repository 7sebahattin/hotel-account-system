<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\CashBook\Exceptions\CashBookException;
use App\Modules\CashBook\Http\Requests\StoreCashTransferRequest;
use App\Modules\CashBook\Models\CashTransfer;
use App\Modules\CashBook\Services\CashBookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kasa devri — İKİ TARAFLI onay.
 *
 * Teslim eden devri başlatır; teslim alan kendi hesabıyla kabul edene kadar
 * devir tamamlanmaz ve kasa teslim edenin vardiyasında görünmeye devam eder.
 */
class CashTransferController extends Controller
{
    public function __construct(private readonly CashBookService $cash) {}

    public function create(): View|RedirectResponse
    {
        $session = $this->cash->activeSession();

        if ($session === null) {
            return redirect()->route('cashbook.index')
                ->with('error', 'Açık kasa vardiyası yok.');
        }

        $this->authorize('handover', $session);

        return view('cashbook::transfers.create', [
            'summary' => $this->cash->summaryFor($session),
            'users' => $this->handoverCandidates($session->opened_by),
        ]);
    }

    public function store(StoreCashTransferRequest $request): RedirectResponse
    {
        $session = $this->cash->activeSession();

        if ($session === null) {
            return redirect()->route('cashbook.index')->with('error', 'Açık kasa vardiyası yok.');
        }

        $this->authorize('handover', $session);

        $toUser = User::query()->findOrFail($request->toUserId());

        try {
            $this->cash->startHandover(
                session: $session,
                toUser: $toUser,
                amounts: $request->currencyAmounts(),
                actor: $request->user(),
                note: $request->validated()['note'] ?? null,
            );
        } catch (CashBookException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cashbook.index')->with(
            'success',
            $toUser->name.' devri kabul ettiğinde vardiyanız kapanacak. Devir kabul edilene kadar kasa sizde görünür.'
        );
    }

    /** Devri KABUL eden, devrin hedefidir; policy bunu doğrular. */
    public function accept(Request $request, CashTransfer $transfer): RedirectResponse
    {
        $this->authorize('accept', $transfer);

        try {
            $this->cash->acceptHandover($transfer, $request->user());
        } catch (CashBookException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cashbook.index')
            ->with('success', 'Devir kabul edildi. Kasa artık sizde ve yeni vardiyanız açıldı.');
    }

    public function cancel(Request $request, CashTransfer $transfer): RedirectResponse
    {
        $this->authorize('cancel', $transfer);

        try {
            $this->cash->cancelHandover($transfer, $request->user());
        } catch (CashBookException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cashbook.index')
            ->with('success', 'Devir iptal edildi. Kasa sizde kalmaya devam ediyor.');
    }

    /**
     * Devir yapılabilecek kullanıcılar: aktif olan ve kasa yetkisi bulunanlar.
     * Devreden kendisi listede YER ALMAZ.
     *
     * @return array<int, string>
     */
    private function handoverCandidates(int $currentUserId): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($currentUserId)
            ->permission(Permission::CashBookOperate->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
