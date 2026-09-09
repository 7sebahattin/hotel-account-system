<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Requests;

use App\Modules\CashBook\Models\CashMovementCategory;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tek kasa hareketi — v2.1.1-B'de tür seçimi artık admin tanımlı bir
 * KATEGORİDİR, ham `CashMovementType` DEĞİL. `category_id` yalnızca AKTİF
 * kategoriler arasından geçerlidir; pasif bir kategori yeni hareket için
 * seçilemez (geçmiş hareketlerde görünmeye devam eder, bkz. model).
 *
 * `opening` tipi hâlâ kullanıcıdan ALINMAZ: açılış bakiyesini yalnızca
 * servis üretir. Yön (`direction`) de kullanıcıdan alınmaz; kategorinin
 * sabit `system_type`ının doğal yönüdür.
 */
class StoreCashMovementRequest extends FormRequest
{
    private ?CashMovementCategory $resolvedCategory = null;

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
            'category_id' => [
                'required',
                Rule::exists('cash_movement_categories', 'id')->where('is_active', true),
            ],
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            'counterparty' => [
                Rule::requiredIf(fn (): bool => $this->category()?->requires_recipient === true),
                'nullable', 'string', 'max:150',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'hareket türü',
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
            'category_id.exists' => 'Geçersiz ya da pasif hareket türü.',
            'counterparty.required' => 'Bu hareket türü için teslim alan bilgisi zorunludur.',
        ];
    }

    /** Aktif olsun olmasın — doğrulamadan sonra çağrılır, cache'lenir. */
    public function category(): ?CashMovementCategory
    {
        if ($this->resolvedCategory === null) {
            $id = $this->input('category_id');
            $this->resolvedCategory = is_numeric($id) ? CashMovementCategory::query()->find((int) $id) : null;
        }

        return $this->resolvedCategory;
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
