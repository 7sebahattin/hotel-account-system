<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Payments\Enums\AllocationMode;
use App\Modules\Payments\Enums\PayerType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    /**
     * `status` ve iptal alanları kullanıcıdan ALINMAZ; sunucu belirler.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payer_type' => ['sometimes', 'required', Rule::enum(PayerType::class)],
            'payer_name' => ['nullable', 'required_if:payer_type,individual', 'string', 'max:200'],
            'allocation_mode' => ['sometimes', 'required', Rule::enum(AllocationMode::class)],
            'stay_ids' => ['required_if:allocation_mode,selected', 'array', 'max:200'],
            'stay_ids.*' => ['integer', 'distinct', Rule::exists('stays', 'id')->whereNull('deleted_at')],
            'company_id' => ['required', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            /*
             * Kur yalnızca YENİ tahsilatta yakalanır (v2.1.1-C §2). Tahsilat
             * zaten tamamen değişmez olduğu için (§8/Payments) normal bir
             * "düzenleme" yolu hiç YOKTUR — UpdatePaymentRequest bu alanı
             * hiç tanımaz; eksik geçmiş kur yalnızca §3'teki ayrı araçla
             * doldurulur.
             */
            'exchange_rate_to_try' => [
                // bkz. StayRequest'teki AYNI yorum: alan gizliyken bile boş
                // değerle gönderilir, 'nullable' bunu 'required_unless'i
                // bastırmadan zararsız kılar.
                'nullable',
                'prohibited_if:currency,TRY',
                'required_unless:currency,TRY',
                'numeric', 'gt:0', 'decimal:0,4', 'max:999999.9999',
            ],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_id' => 'cari hesap',
            'payer_type' => 'ödeyen',
            'payer_name' => 'ödeyen kişinin adı',
            'stay_ids' => 'konaklama seçimi',
            'payment_date' => 'tahsilat tarihi',
            'amount' => 'tutar',
            'currency' => 'para birimi',
            'exchange_rate_to_try' => 'kur (TL karşılığı)',
            'payment_method' => 'ödeme yöntemi',
            'reference_no' => 'referans no',
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
            'amount.decimal' => 'Tutar en fazla iki ondalık basamak içerebilir.',
            'exchange_rate_to_try.required_unless' => 'TRY dışındaki para birimlerinde kur girilmesi zorunludur.',
            'exchange_rate_to_try.prohibited_if' => 'TRY için kur girilmez.',
        ];
    }

    /** Türkçe klavyede ondalık ayırıcı virgüldür; noktaya çevrilir. */
    protected function prepareForValidation(): void
    {
        foreach (['amount', 'exchange_rate_to_try'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => Money::normalizeInput($value)]);
            }
        }
    }
}
