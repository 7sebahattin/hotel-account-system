<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Requests;

use App\Modules\Core\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonnelFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $base = [
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'month' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'employee_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
            'active' => ['sometimes', 'nullable', Rule::in(['all', '1', '0'])],
            'currency' => ['sometimes', 'nullable', Rule::in(array_column(Currency::cases(), 'value'))],
        ];

        if ($this->route()?->getName() === 'personnel.employees.index') {
            return $base + [
                'q' => ['sometimes', 'nullable', 'string', 'max:100'],
                'sort' => ['sometimes', 'nullable', Rule::in(['full_name', 'employee_code', 'start_date'])],
                'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
                'per_page' => ['sometimes', 'nullable', Rule::in([25, 50, 100])],
            ];
        }

        return $base;
    }
}
