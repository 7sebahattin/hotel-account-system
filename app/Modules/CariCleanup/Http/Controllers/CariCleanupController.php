<?php

declare(strict_types=1);

namespace App\Modules\CariCleanup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CariCleanup\Services\CariCleanupService;
use Illuminate\Contracts\View\View;

class CariCleanupController extends Controller
{
    public function __construct(private readonly CariCleanupService $cleanup) {}

    public function index(): View
    {
        return $this->show(false);
    }

    public function preflight(): View
    {
        return $this->show(true);
    }

    private function show(bool $preflight): View
    {
        $this->authorize('view', CariCleanupService::class);

        return view('cari-cleanup::index', ['report' => $this->cleanup->report(), 'preflight' => $preflight]);
    }
}
