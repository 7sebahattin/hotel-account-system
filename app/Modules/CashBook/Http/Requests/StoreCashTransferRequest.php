<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Requests;

use App\Modules\CashBook\Http\Requests\Concerns\ParsesCurrencyAmounts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kasa devri: teslim alınacak kişi + teslim edilen tutarlar.
 *
 * Teslim edilen tutar aynı zamanda kapanış SAYIMIdır: personel elindekini
 * devreder, beklenen ile farkı sistem hesaplar (kullanıcı talimatı §10).
 */
class StoreCashTransferRequest extends FormRequest
{
    use ParsesCurrencyAmounts;

    public function authorize(): bool
    {
        // Kayıt bazlı yetki controller'da `authorize('handover', $session)` ile denetlenir.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->currencyAmountRules() + [
            'to_user_id' => [
                'required',
                'integer',
                // Kendine devir yasak — veritabanında da CHECK ile kısıtlıdır.
                'different:__self__',
                Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['to_user_id' => 'teslim alan', 'amounts' => 'teslim edilen tutar', 'note' => 'not'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_user_id.different' => 'Kasa kendi kendine devredilemez.',
            'to_user_id.exists' => 'Seçilen kullanıcı bulunamadı veya pasif.',
        ];
    }

    public function toUserId(): int
    {
        return (int) $this->validated()['to_user_id'];
    }

    /**
     * `different:__self__` kuralının karşılaştıracağı alanı isteğe ekler:
     * kullanıcı kimliği istek gövdesinden değil oturumdan gelir, böylece
     * gönderilen değerle oynanamaz.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeAmounts();
        $this->merge(['__self__' => (string) $this->user()?->getKey()]);
    }
}
