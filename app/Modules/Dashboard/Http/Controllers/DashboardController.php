<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Pano. İnce controller: yetki → servis → view (CLAUDE.md §13).
 *
 * Burada hiçbir finansal hesap yapılmaz ve hiçbir sorgu yazılmaz.
 *
 * GÜVENLİK SIRASI BAĞLAYICIDIR (kullanıcı talimatı §18): `canSeeFinancials()`
 * kontrolü `?view=` query string'i OKUNMADAN ÖNCE yapılır. Personel bu yüzden
 * `/dashboard?view=finance` yazarak Operasyon/Finans dallarına hiçbir zaman
 * giremez — kontrol UI'da gizleme değil, dal seçiminin KENDİSİDİR.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $today = Carbon::today();

        // Personel için finansal/kasa raporlama verisi HİÇ SORGULANMAZ.
        if (! $dashboard->canSeeFinancials($user)) {
            return view('dashboard::personnel', [
                'summary' => $dashboard->personnel($user, $today),
            ]);
        }

        $view = $request->query('view') === 'finance' ? 'finance' : 'operations';

        if ($view === 'finance') {
            return view('dashboard::finance', [
                'summary' => $dashboard->finance($today),
                'today' => $today,
                'activeView' => $view,
            ]);
        }

        return view('dashboard::operations', [
            'summary' => $dashboard->operations($today),
            'today' => $today,
            'activeView' => $view,
        ]);
    }
}
