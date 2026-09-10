<?php

declare(strict_types=1);

namespace App\Modules\Companies\Database\Factories;

use App\Modules\Companies\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'tax_office' => fake()->city(),
            'tax_number' => (string) fake()->numerify('##########'),
            'phone' => fake()->numerify('05## ### ## ##'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'contact_person' => fake()->name(),
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
