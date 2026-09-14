<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Http\Requests;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\ExchangeRateInput;
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
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'payment_method' => ['nullable', Rule::enum(ReceiptPaymentMethod::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            /*
             * Kur / TL karşılığı — iki modlu giriş (K-110). Kurallar
             * `Core\Support\ExchangeRateInput`ta TEK yerdedir; dört döviz
             * girişli ekran da aynı sözleşmeyi kullanır (CLAUDE.md §4.2/4).
             */
        ] + ExchangeRateInput::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'receipt_date' => 'tarih',
            'customer_name' => 'müşteri / cari adı',
            'amount' => 'tutar',
            'currency' => 'para birimi',
            'payment_method' => 'ödeme şekli',
            'description' => 'açıklama',
        ] + ExchangeRateInput::attributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'Tutar sıfırdan büyük olmalıdır.',
        ] + ExchangeRateInput::messages();
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('amount');

        if (is_string($value)) {
            $this->merge(['amount' => Money::normalizeInput($value)]);
        }

        ExchangeRateInput::normalize($this);
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
