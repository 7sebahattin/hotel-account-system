<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Requests;

use App\Modules\CashBook\Http\Requests\Concerns\ParsesCurrencyAmounts;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Vardiya kapanışı: para birimi başına FİİLEN SAYILAN tutar.
 *
 * Beklenen tutar formdan ALINMAZ — sunucu kapanış anında yeniden hesaplar.
 * Aksi hâlde kullanıcı beklenen tutarı da göndererek farkı sıfırlayabilirdi.
 */
class CloseCashSessionRequest extends FormRequest
{
    use ParsesCurrencyAmounts;

    public function authorize(): bool
    {
        // Kayıt bazlı yetki controller'da `authorize('close', $session)` ile denetlenir.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->currencyAmountRules() + [
            'closing_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amounts' => 'sayılan tutar', 'closing_note' => 'not'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAmounts();
    }
}
