<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Controllers;

use App\Modules\Personnel\Http\Requests\PersonnelRequest;
use App\Modules\Personnel\Models\PersonnelTerminal;
use App\Modules\Personnel\Services\PersonnelPermissionService;
use App\Modules\Personnel\Services\TerminalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TerminalController
{
    public function index(Request $request)
    {
        return response()->view('personnel::terminals.index', [
            'terminals' => PersonnelTerminal::query()->orderByDesc('id')->paginate(25),
            'newCredential' => $request->session()->pull('newCredential'),
            'permissions' => app(PersonnelPermissionService::class)->forRequest($request),
        ])->header('Cache-Control', 'no-store');
    }

    public function store(PersonnelRequest $request, TerminalService $service)
    {
        [, $credential] = $service->create($request->validated(), (int) $request->user()->id);

        return redirect()->route('personnel.terminals.index')->with('newCredential', $credential)->header('Cache-Control', 'no-store');
    }

    public function revoke(PersonnelRequest $request, PersonnelTerminal $terminal, TerminalService $service)
    {
        Gate::authorize('revoke', $terminal);
        $service->revoke($terminal, $request->validated()['reason']);

        return back()->with('success', 'Terminal iptal edildi.');
    }
}
