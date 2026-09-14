<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Http\Requests;

use App\Modules\MonthlyAccount\Models\MonthlyAccountDay;
use Illuminate\Foundation\Http\FormRequest;

class MonthlyAccountFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('monthly-account.create') ? 'create' : 'viewAny', MonthlyAccountDay::class);
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:1000-01', 'before_or_equal:9999-12'],
            'owner_id' => ['nullable', 'integer', 'min:1'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
        ];
    }

    public function attributes(): array
    {
        return ['month' => 'ay', 'owner_id' => 'hesap sahibi', 'date' => 'tarih'];
    }
}
