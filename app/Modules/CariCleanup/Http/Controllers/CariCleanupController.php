<?php

declare(strict_types=1);

namespace App\Modules\CariCleanup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CariCleanup\Http\Requests\CleanupRequest;
use App\Modules\CariCleanup\Services\CariCleanupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CariCleanupController extends Controller
{
    public function __construct(private readonly CariCleanupService $cleanup) {}

    public function index(Request $request): View
    {
        $this->authorize('view', CariCleanupService::class);

        return view('cari-cleanup::index', [
            'report' => $this->cleanup->report(),
            'preflight' => false,
            'token' => $request->old('preflight_token'),
        ]);
    }

    public function preflight(Request $request): View
    {
        $this->authorize('view', CariCleanupService::class);
        $actor = $request->user();
        assert($actor instanceof User);
        $report = $this->cleanup->prepare($actor);

        return view('cari-cleanup::index', [
            'report' => $report,
            'preflight' => true,
            'token' => $report['token'] ?? null,
        ]);
    }

    public function execute(CleanupRequest $request): RedirectResponse
    {
        $this->authorize('execute', CariCleanupService::class);
        $actor = $request->user();
        assert($actor instanceof User);

        try {
            $this->cleanup->execute($actor, $request->validated('preflight_token'));
        } catch (ValidationException $exception) {
            // Ön kontrol POST görünümüdür; hatalar GET index'e dönmelidir.
            throw $exception->redirectTo(route('cari-cleanup.index'));
        }

        return redirect()->route('cari-cleanup.index')
            ->with('success', 'Cari girişleri temizlendi. Cari hesap kartları ve kapsam dışındaki bütün veriler korundu.');
    }
}
