<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Http\Requests;

use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MonthlyPaymentProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $provider = $this->route('provider');

        return $provider instanceof MonthlyPaymentProvider
            ? $this->user()->can('update', $provider)
            : $this->user()->can('create', MonthlyPaymentProvider::class);
    }

    public function rules(): array
    {
        $provider = $this->route('provider');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('monthly_payment_providers', 'name')->ignore($provider instanceof MonthlyPaymentProvider ? $provider->getKey() : null),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => preg_replace('/\s+/u', ' ', trim($this->string('name')->toString()))]);
        }
    }

    public function attributes(): array
    {
        return ['name' => 'banka / ödeme kuruluşu adı'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Bu banka / ödeme kuruluşu zaten tanımlı.'];
    }
}
