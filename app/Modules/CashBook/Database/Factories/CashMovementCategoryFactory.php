<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Database\Factories;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashMovementCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovementCategory>
 */
class CashMovementCategoryFactory extends Factory
{
    protected $model = CashMovementCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test Kategorisi',
            'system_type' => CashMovementType::ExtraIncome,
            'is_active' => true,
            'requires_recipient' => false,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function ofType(CashMovementType $type): static
    {
        return $this->state(fn (): array => ['system_type' => $type]);
    }
}
