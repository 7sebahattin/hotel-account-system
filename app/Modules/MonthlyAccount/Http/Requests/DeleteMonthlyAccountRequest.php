<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteMonthlyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('day'));
    }

    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1', 'max:4294967294']];
    }

    public function attributes(): array
    {
        return ['version' => 'kayıt sürümü'];
    }
}
