<?php

declare(strict_types=1);

namespace App\Modules\Companies\Http\Requests;

use App\Modules\Companies\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            ? $this->user()->can('update', $company)
            : $this->user()->can('create', Company::class);
    }

    /**
     * Yalnızca name zorunludur. tax_number gibi alanlara biçim kuralı
     * konmaz: gerçek firma verisi düzensizdir ve katı kural veri girişini
     * engeller (CLAUDE.md Gün 3 kararı).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'account_type' => $this->route('company') instanceof Company
                ? ['prohibited'] : ['sometimes', 'required', 'in:company,individual'],
            'name' => ['required', 'string', 'max:200'],
            'tax_office' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ünvan / ad soyad',
            'account_type' => 'hesap türü',
            'tax_office' => 'vergi dairesi',
            'tax_number' => 'vergi numarası',
            'phone' => 'telefon',
            'email' => 'e-posta',
            'address' => 'adres',
            'city' => 'şehir',
            'contact_person' => 'yetkili kişi',
            'notes' => 'notlar',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
