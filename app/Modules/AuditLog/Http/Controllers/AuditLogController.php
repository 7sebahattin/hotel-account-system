<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Core\Filters\QueryFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Denetim kaydı yalnızca okunur. Yazma, güncelleme ve silme uçları yoktur.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')->value()))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit::index', [
            'logs' => $logs,
            'events' => AuditEvent::cases(),
            'activeFilterCount' => QueryFilter::countActiveFilters($request),
        ]);
    }

    /**
     * Tek denetim kaydının detayı (kullanıcı talimatı, 13 Eylül 2026).
     *
     * Listedeki "Değişiklik" kolonu yalnızca alan ADLARINI gösterebiliyordu;
     * bu ekran aynı kaydın eski/yeni DEĞERLERİNİ alan alan açar. Salt
     * okunurdur — yazma, düzenleme ve silme uçları burada da yoktur.
     */
    public function show(AuditLog $auditLog): View
    {
        $this->authorize('view', $auditLog);

        return view('audit::show', [
            'log' => $auditLog->load('user'),
        ]);
    }
}
