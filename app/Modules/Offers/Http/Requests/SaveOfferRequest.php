<?php

declare(strict_types=1);

namespace App\Modules\Offers\Http\Requests;

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Core\Enums\Currency;
use App\Modules\Offers\Enums\OfferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('post')
            ? Permission::OffersCreate->value
            : Permission::OffersUpdate->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'customer_name_snapshot' => ['nullable', 'string', 'max:200', 'required_without:company_id'],
            'title' => ['required', 'string', 'max:200'],
            'offer_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:offer_date'],
            'currency' => ['required', Rule::in(Currency::values())],
            'status' => ['required', Rule::in(array_column(OfferStatus::cases(), 'value'))],
            'document_heading' => ['required', 'string', 'max:200'],
            'document_year' => ['required', 'integer', 'between:2000,2100'],
            'intro_text' => ['nullable', 'string', 'max:3000'],
            'general_notes' => ['nullable', 'string', 'max:5000'],
            'cancellation_terms' => ['nullable', 'string', 'max:5000'],
            'footer_notes' => ['nullable', 'string', 'max:3000'],
            'hotel_name_snapshot' => ['nullable', 'string', 'max:200'],
            'legal_name_snapshot' => ['nullable', 'string', 'max:255'],
            'tax_office_snapshot' => ['nullable', 'string', 'max:100'],
            'tax_number_snapshot' => ['nullable', 'string', 'max:30'],
            'address_snapshot' => ['nullable', 'string', 'max:2000'],
            'phone_snapshot' => ['nullable', 'string', 'max:60'],
            'email_snapshot' => ['nullable', 'email', 'max:200'],
            'bank_account_holder' => ['nullable', 'string', 'max:200'],
            'bank_iban_try' => ['nullable', 'string', 'max:50'],
            'bank_iban_eur' => ['nullable', 'string', 'max:50'],
            'periods' => ['required', 'array', 'min:1', 'max:12'],
            'periods.*.label' => ['required', 'string', 'max:160'],
            'periods.*.start_date' => ['nullable', 'date'],
            'periods.*.end_date' => ['nullable', 'date'],
            'room_types' => ['required', 'array', 'min:1', 'max:20'],
            'room_types.*.name' => ['required', 'string', 'max:100'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['array'],
            'prices.*.*' => ['nullable', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
        ];
    }
}
