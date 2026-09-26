<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Controllers;

use App\Modules\Personnel\Http\Requests\PersonnelRequest;
use App\Modules\Personnel\Services\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class ScanController
{
    public function api(PersonnelRequest $request, ScanService $scans): JsonResponse
    {
        $terminal = $request->attributes->get('personnel_terminal');

        return response()->json($scans->record($request->validated(), $terminal, null))->header('Cache-Control', 'no-store');
    }

    public function kiosk(PersonnelRequest $request, ScanService $scans): JsonResponse
    {
        if (! $request->expectsJson()) {
            return response()->json(['message' => 'JSON isteği gerekli.'], 406);
        }
        if (! $this->allow('personnel:kiosk:'.$request->user()->id, (int) config('personnel.kiosk_rate_per_minute', 60))) {
            return response()->json(['message' => 'Çok fazla istek.'], 429);
        }

        return response()->json($scans->record($request->validated(), null, (int) $request->user()->id))->header('Cache-Control', 'no-store');
    }

    private function allow(string $key, int $limit): bool
    {
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }
}
