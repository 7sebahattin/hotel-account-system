<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Auth\Http\Requests\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(): View
    {
        return view('auth::login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate($this->auditLogger);

        $request->session()->regenerate();

        $user = $request->user();

        // Denetim alanı; kaydın updated_at'ini kirletmemek için sessiz kaydedilir.
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->auditLogger->log(AuditEvent::Login, $user);

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Çıkıştan ÖNCE kaydedilir; sonrasında oturum kullanıcısı kalmaz.
        $this->auditLogger->log(AuditEvent::Logout, $request->user());

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
