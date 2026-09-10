<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Requests;

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Dakikada izin verilen başarısız deneme sayısı (CLAUDE.md §8). */
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Kimlik doğrulama.
     *
     * Şifre ÖNCE doğrulanır, ardından hesabın aktifliğine bakılır. Bu sıra
     * bilinçlidir: aksi halde yalnızca e-posta girerek bir hesabın var olup
     * olmadığı öğrenilebilirdi.
     *
     * Her başarısız deneme denetim kaydına yazılır (CLAUDE.md §8).
     *
     * @throws ValidationException
     */
    public function authenticate(AuditLogger $auditLogger): void
    {
        $email = $this->string('email')->value();

        $this->ensureIsNotRateLimited($auditLogger, $email);

        $credentials = $this->only('email', 'password');

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey());
            $auditLogger->logFailedLogin($email);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        if (! $user->is_active) {
            RateLimiter::hit($this->throttleKey());
            $auditLogger->logFailedLogin($email);

            throw ValidationException::withMessages([
                'email' => __('auth.inactive'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(AuditLogger $auditLogger, string $email): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $auditLogger->logFailedLogin($email);

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /** Kısıtlama anahtarı: e-posta + IP (CLAUDE.md §8). */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->value()).'|'.$this->ip());
    }
}
