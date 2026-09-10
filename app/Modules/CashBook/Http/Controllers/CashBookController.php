<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Enums\Permission;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Services\CashBookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Kasa defterinin ana ekranı. İnce controller: yetki → servis → view.
 *
 * GÖRÜNÜRLÜK KURALI: açık vardiyanın VARLIĞINI ve sahibini `cashbook.view`
 * yetkisi olan herkes görür — personelin kasanın kimde olduğunu bilmesi
 * operasyonel bir ihtiyaçtır. TUTARLARI ve hareketleri ise yalnızca vardiya
 * sahibi ve `cashbook.view_all` yetkisi olanlar görür.
 */
class CashBookController extends Controller
{
    public function __invoke(Request $request, CashBookService $cash): View
    {
        $this->authorize('viewAny', CashSession::class);

        $user = $request->user();
        $session = $cash->activeSession();

        $canSeeDetail = $session !== null && $user->can('view', $session);

        return view('cashbook::index', [
            'session' => $session,
            'summary' => $canSeeDetail ? $cash->summaryFor($session) : null,
            'movements' => $canSeeDetail ? $cash->movementsFor($session, 15) : new Collection,
            'incomingTransfer' => $cash->pendingTransferFor($user),
            'isOwner' => $session !== null && $session->opened_by === $user->getKey(),
            'canViewAll' => $user->can(Permission::CashBookViewAll->value),
        ]);
    }
}
