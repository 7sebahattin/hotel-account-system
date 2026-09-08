<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Requests;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tek kasa hareketi.
 *
 * `opening` tipi kullanıcıdan ALINMAZ: açılış bakiyesini yalnızca servis
 * (vardiya açılışı ve devir kabulü) üretir. Aksi hâlde personel vardiya
 * ortasında açılış bakiyesi yazarak beklenen kasayı bozabilirdi.
 *
 * Yön (`direction`) de kullanıcıdan alınmaz; tipin doğal yönüdür.
 */
class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kayıt bazlı yetki (açık + kendi vardiyası) controller'da denetlenir;
        // burada oturumun varlığına bakılmaz, çünkü hedef vardiya servisten gelir.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_column(CashMovementType::selectable(), 'value'))],
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'hareket türü',
            'currency' => 'para birimi',
            'amount' => 'tutar',
            'description' => 'açıklama',
            'counterparty' => 'teslim alan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'Tutar sıfırdan büyük olmalıdır.',
            'amount.decimal' => 'Tutar en fazla iki ondalık basamak içerebilir.',
            'type.in' => 'Geçersiz hareket türü.',
        ];
    }

    public function movementType(): CashMovementType
    {
        return CashMovementType::from($this->validated()['type']);
    }

    public function currency(): Currency
    {
        return Currency::from($this->validated()['currency']);
    }

    public function money(): Money
    {
        return Money::fromDecimal((string) $this->validated()['amount']);
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        if (is_string($amount)) {
            $this->merge(['amount' => Money::normalizeInput($amount)]);
        }
    }
}
