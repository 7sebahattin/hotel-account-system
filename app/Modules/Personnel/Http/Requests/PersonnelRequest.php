<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Http\Requests;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonnelRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route()?->getName() === 'personnel.attendance-corrections.store' && $this->boolean('clear_day')) {
            $this->merge(['sessions' => []]);
        }
        if (is_string($this->input('amount'))) {
            $this->merge(['amount' => Money::normalizeInput($this->input('amount'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $name = (string) $this->route()?->getName();
        $currency = Rule::in(array_column(Currency::cases(), 'value'));

        return match ($name) {
            'personnel.employees.store', 'personnel.employees.update' => [
                'employee_code' => ['required', 'string', 'max:30', Rule::unique('personnel_employees')->ignore($this->route('employee')?->id)],
                'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
                'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
                'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:150'],
                'position' => ['nullable', 'string', 'max:100'], 'department' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:10000'],
            ],
            'personnel.employees.active', 'personnel.cards.active', 'personnel.shifts.active' => [
                'is_active' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:500'],
            ],
            'personnel.shifts.store', 'personnel.shifts.versions.store' => [
                'name' => [$name === 'personnel.shifts.store' ? 'required' : 'sometimes', 'string', 'max:100'],
                'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i'],
                'ends_next_day' => ['required', 'boolean'], 'break_minutes' => ['required', 'integer', 'min:0', 'max:1439'],
                'workdays' => ['required', 'array', 'min:1'], 'workdays.*' => ['integer', 'between:1,7'],
                'late_grace_minutes' => ['nullable', 'integer', 'between:0,1440'],
                'early_grace_minutes' => ['nullable', 'integer', 'between:0,1440'],
            ],
            'personnel.shift-assignments.store' => ['shift_version_id' => ['required', 'integer', 'exists:personnel_shift_versions,id'], 'effective_from' => ['required', 'date_format:Y-m-d']],
            'personnel.wage-assignments.store' => [
                'effective_from' => ['required', 'date_format:Y-m-d'], 'wage_type' => ['required', Rule::in(['monthly', 'daily', 'hourly'])],
                'amount' => ['required', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/D'], 'currency' => ['required', $currency],
                'standard_daily_minutes' => ['required', 'integer', 'between:1,1440'],
                'overtime_multiplier_bp' => ['required', 'integer', 'between:0,100000'], 'overtime_enabled' => ['required', 'boolean'],
            ],
            'personnel.cards.store' => ['uid' => ['required', 'string', 'regex:/^(?:[0-9a-fA-F]{2}){4,20}$/D'], 'label' => ['nullable', 'string', 'max:100'], 'technology' => ['nullable', 'string', 'max:100']],
            'personnel.cards.release', 'personnel.terminals.revoke' => ['reason' => ['required', 'string', 'max:500']],
            'personnel.kiosk.scan', 'personnel.api.scans.store' => [
                'request_id' => ['required', 'uuid'], 'card_uid' => ['required', 'string', 'regex:/^(?:[0-9a-fA-F]{2}){4,20}$/D'],
                'action' => ['nullable', Rule::in(['auto', 'in', 'out'])], 'technology' => ['nullable', 'string', 'max:100'],
            ],
            'personnel.attendance-corrections.store' => [
                'work_date' => ['required', 'date_format:Y-m-d'], 'expected_revision' => ['required', 'integer', 'min:0'],
                'clear_day' => ['nullable', 'boolean'],
                'sessions' => ['present', 'array', 'max:12'], 'sessions.*.in_at' => ['required', 'date'], 'sessions.*.out_at' => ['required', 'date'],
                'reason' => ['required', 'string', 'max:500'],
            ],
            'personnel.payroll.calculate' => ['month' => ['required', 'date_format:Y-m'], 'request_id' => ['required', 'uuid'], 'input_digest' => ['required', 'string', 'size:64']],
            'personnel.payroll.entries.store' => [
                'type' => ['required', Rule::in(['bonus', 'deduction', 'payment'])], 'entry_date' => ['required', 'date_format:Y-m-d'],
                'amount' => ['required', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/D'], 'currency' => ['required', $currency],
                'payment_method' => ['required_if:type,payment', 'nullable', Rule::in(['cash', 'bank_transfer', 'other'])],
                'description' => ['required', 'string', 'max:500'], 'request_id' => ['required', 'uuid'],
            ],
            'personnel.payroll.entries.reverse' => ['reason' => ['required', 'string', 'max:500'], 'request_id' => ['required', 'uuid']],
            'personnel.terminals.store' => ['name' => ['required', 'string', 'max:100'], 'platform' => ['required', Rule::in(['android', 'kiosk'])], 'device_uuid' => ['required', 'uuid']],
            default => [],
        };
    }
}
