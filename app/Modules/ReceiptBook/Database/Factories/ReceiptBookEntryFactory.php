<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Database\Factories;

use App\Modules\ReceiptBook\Enums\ReceiptPaymentMethod;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptBookEntry>
 */
class ReceiptBookEntryFactory extends Factory
{
    protected $model = ReceiptBookEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_date' => now()->toDateString(),
            'customer_name' => $this->faker->company(),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'currency' => 'TRY',
            'exchange_rate_to_try' => null,
            'try_equivalent' => null,
            'payment_method' => ReceiptPaymentMethod::Cash->value,
            'description' => null,
        ];
    }
}
