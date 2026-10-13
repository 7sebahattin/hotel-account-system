<?php

declare(strict_types=1);

namespace App\Modules\CariCleanup\Http\Requests;

use App\Modules\CariCleanup\Services\CariCleanupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CleanupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('execute', CariCleanupService::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:web'],
            'confirmation' => ['required', 'string', Rule::in(['CARİ GİRİŞLERİNİ SİL'])],
            'preflight_token' => ['required', 'string', 'max:4096'],
            'confirm_irreversible' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.required' => 'İşlemi onaylamak için Admin parolanızı girin.',
            'password.current_password' => 'Girdiğiniz parola doğru değil.',
            'confirmation.required' => 'Doğrulama ifadesini yazın.',
            'confirmation.in' => 'Doğrulama ifadesi CARİ GİRİŞLERİNİ SİL ile aynı olmalıdır.',
            'preflight_token.required' => 'Silme işleminden önce ön kontrolü çalıştırın.',
            'preflight_token.max' => 'Ön kontrol geçersiz. Ön kontrolü yeniden çalıştırın.',
            'confirm_irreversible.required' => 'Yedek aldığınızı ve silme kapsamını onaylayın.',
            'confirm_irreversible.accepted' => 'Yedek aldığınızı ve silme kapsamını onaylayın.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'password' => 'Admin parolası',
            'confirmation' => 'doğrulama ifadesi',
            'preflight_token' => 'ön kontrol',
            'confirm_irreversible' => 'yedek ve kapsam onayı',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('cari-cleanup.index');
    }
}
