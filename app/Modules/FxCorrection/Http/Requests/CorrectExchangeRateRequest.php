<?php

declare(strict_types=1);

namespace App\Modules\FxCorrection\Http\Requests;

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Core\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Geçmiş Döviz Düzeltme — yalnızca EKSİK kaydın kurunu doldurmak için
 * (v2.1.1-C §3). Tek alan: operatör tarihi, tutarı, para birimini DEĞİL,
 * yalnızca o günkü kuru girer.
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
            'exchange_rate_to_try' => ['required', 'numeric', 'gt:0', 'decimal:0,4', 'max:999999.9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['exchange_rate_to_try' => 'kur (TL karşılığı)'];
    }

    protected function prepareForValidation(): void
    {
        $rate = $this->input('exchange_rate_to_try');

        if (is_string($rate)) {
            $this->merge(['exchange_rate_to_try' => Money::normalizeInput($rate)]);
        }
    }
}
