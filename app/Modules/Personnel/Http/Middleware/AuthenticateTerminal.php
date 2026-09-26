<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Middleware;

use App\Modules\Personnel\Services\TerminalService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTerminal
{
    public function __construct(private readonly TerminalService $terminals) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ipKey = 'personnel:ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, (int) config('personnel.ip_rate_per_minute', 120))) {
            return response()->json(['message' => 'Çok fazla istek.'], 429)->header('Cache-Control', 'no-store');
        }
        RateLimiter::hit($ipKey, 60);
        $terminal = $this->terminals->authenticate($request->header('Authorization'));
        if ($terminal === null || $terminal->platform !== 'android') {
            return response()->json(['message' => 'Yetkisiz terminal.'], 401)->header('Cache-Control', 'no-store');
        }
        $keys = ['personnel:terminal:'.$terminal->id => (int) config('personnel.terminal_rate_per_minute', 60)];
        foreach ($keys as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                return response()->json(['message' => 'Çok fazla istek.'], 429)->header('Cache-Control', 'no-store');
            }
        }
        foreach (array_keys($keys) as $key) {
            RateLimiter::hit($key, 60);
        }
        $request->attributes->set('personnel_terminal', $terminal);

        return $next($request)->header('Cache-Control', 'no-store');
    }
}
