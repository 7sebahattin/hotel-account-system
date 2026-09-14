<?php

declare(strict_types=1);

namespace App\Modules\FxCorrection\Http\Requests;

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Core\Support\ExchangeRateInput;
use App\Modules\Core\Support\ExchangeRateSnapshot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Geçmiş Döviz Düzeltme — yalnızca EKSİK kaydın kur bilgisini doldurmak için
 * (v2.1.1-C §3). Operatör tarihi, tutarı, para birimini DEĞİL, yalnızca o
 * günkü kur bilgisini girer.
 *
 * K-110'dan beri burada da İKİ MOD vardır: kur ya da doğrudan TL karşılığı.
 * Kayıtlar zaten TRY DIŞI olduğu için (`missingExchangeRate()` yalnızca
 * onları döner) `Core\Support\ExchangeRateInput`un `currency` koşullu
 * kuralları yerine sabit "zorunlu" kurallar kullanılır — bu ekranda
 * `currency` alanı hiç gönderilmez.
 */
class CorrectExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::FxCorrectHistorical->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Mod isteğe bağlıdır; yokluğu "kur" demektir (bkz.
            // `ExchangeRateInput::rules()` içindeki AYNI gerekçe).
            'fx_input_mode' => ['nullable', Rule::in([ExchangeRateSnapshot::MODE_RATE, ExchangeRateSnapshot::MODE_TOTAL])],
            'exchange_rate_to_try' => [
                'exclude_if:fx_input_mode,'.ExchangeRateSnapshot::MODE_TOTAL,
                'required', 'numeric', 'gt:0', 'decimal:0,4', 'max:999999.9999',
            ],
            'try_equivalent_input' => [
                'exclude_unless:fx_input_mode,'.ExchangeRateSnapshot::MODE_TOTAL,
                'required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ExchangeRateInput::attributes();
    }

    protected function prepareForValidation(): void
    {
        ExchangeRateInput::normalize($this);
    }
}
