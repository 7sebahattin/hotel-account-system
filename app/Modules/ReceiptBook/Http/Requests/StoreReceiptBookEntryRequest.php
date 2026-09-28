<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Http\Requests;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\ReceiptBook\Enums\ReceiptPaymentMethod;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreReceiptBookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ReceiptBookEntry::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:200'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'exchange_rate_to_try' => [
                // bkz. StayRequest'teki AYNI yorum.
                'nullable',
                'prohibited_if:currency,TRY',
                'required_unless:currency,TRY',
                'numeric', 'gt:0', 'decimal:0,4', 'max:999999.9999',
            ],
            'payment_method' => ['nullable', Rule::enum(ReceiptPaymentMethod::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'receipt_date' => 'tarih',
            'customer_name' => 'müşteri / cari adı',
            'company_id' => 'seçilen cari',
            'amount' => 'tutar',
            'currency' => 'para birimi',
            'exchange_rate_to_try' => 'kur (TL karşılığı)',
            'payment_method' => 'ödeme şekli',
            'description' => 'açıklama',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'Tutar sıfırdan büyük olmalıdır.',
            'exchange_rate_to_try.required_unless' => 'TRY dışındaki para birimlerinde kur girilmesi zorunludur.',
            'exchange_rate_to_try.prohibited_if' => 'TRY için kur girilmez.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['amount', 'exchange_rate_to_try'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => Money::normalizeInput($value)]);
            }
        }
    }

    /**
     * Dialog (v2.1.1-B.1 desenin AYNISI, §5): doğrulama hatası Referer'a
     * DEĞİL, HER ZAMAN standalone "Yeni Makbuz" sayfasına yönlendirilir —
     * dialog herhangi bir sayfanın üzerinde açılabildiği için `back()`
     * kullanıcıyı formu göstermeyen bir sayfaya götürebilirdi.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('receipt-book.create')
                ->withInput($this->except('_token'))
                ->withErrors($validator)
        );
    }
}
