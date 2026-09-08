<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Pano. İnce controller: yetki → servis → view (CLAUDE.md §13).
 *
 * Burada hiçbir finansal hesap yapılmaz ve hiçbir sorgu yazılmaz.
 */
class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): View
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $today = Carbon::today();

        // Personel için finansal veri HİÇ SORGULANMAZ — gizlenmez (CLAUDE.md §7.4).
        if (! $dashboard->canSeeFinancials($user)) {
            return view('dashboard::personnel', [
                'summary' => $dashboard->personnel($user, $today),
            ]);
        }

        return view('dashboard::financial', [
            'summary' => $dashboard->financial($today),
            'today' => $today,
        ]);
    }
}
