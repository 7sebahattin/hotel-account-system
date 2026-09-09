<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Requests;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashMovementCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `system_type` yalnızca `CashMovementType::selectable()`'dan seçilebilir —
 * `opening`/`company_collection` admin tarafından ASLA atanamaz (kullanıcı
 * talimatı §2: "Admin must NOT be able to invent arbitrary accounting
 * behaviour").
 */
class StoreCashMovementCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CashMovementCategory::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:cash_movement_categories,name'],
            'system_type' => ['required', Rule::in(array_column(CashMovementType::selectable(), 'value'))],
            'requires_recipient' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ad',
            'system_type' => 'sistem davranışı',
            'requires_recipient' => 'teslim alan zorunlu',
            'sort_order' => 'sıra',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Bu adla bir kategori zaten var.',
            'system_type.in' => 'Geçersiz sistem davranışı.',
        ];
    }

    public function systemType(): CashMovementType
    {
        return CashMovementType::from($this->validated()['system_type']);
    }
}
