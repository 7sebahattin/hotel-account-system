<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Requests;

use App\Modules\CashBook\Models\CashMovementCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Düzenleme yalnızca GÖRÜNTÜ adını ve durumu değiştirir — `system_type`
 * burada YOKTUR ve KASITLI olarak tanımlanmamıştır: bir kategorinin sabit
 * davranışı yalnızca oluşturulurken seçilir, sonradan değiştirilemez.
 * Aksi hâlde aynı ad geçmişte farklı bir davranışa (ör. önce gelir, sonra
 * gider) karşılık gelebilir ve rapor okuyan biri için sessizce yanıltıcı
 * olurdu (kullanıcı talimatı §2/§3).
 */
class UpdateCashMovementCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof CashMovementCategory && $this->user()->can('update', $category);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof CashMovementCategory ? $category->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('cash_movement_categories', 'name')->ignore($categoryId)],
            'is_active' => ['sometimes', 'boolean'],
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
            'is_active' => 'aktif',
            'requires_recipient' => 'teslim alan zorunlu',
            'sort_order' => 'sıra',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'Bu adla bir kategori zaten var.'];
    }
}
