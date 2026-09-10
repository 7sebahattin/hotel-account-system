<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Database\Factories;

use App\Models\User;
use App\Modules\CashBook\Enums\CashSessionStatus;
use App\Modules\CashBook\Models\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opened_by' => User::factory(),
            'opened_at' => Carbon::now(),
            'status' => CashSessionStatus::Open,
            'is_open' => 1,
        ];
    }

    /**
     * Kapalı vardiya. `is_open` NULL olmalıdır: tek açık vardiya kısıtı
     * (UNIQUE(is_open)) aksi hâlde ikinci kaydı reddeder.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CashSessionStatus::Closed,
            'is_open' => null,
            'closed_at' => Carbon::now(),
            'closed_by' => $attributes['opened_by'] ?? User::factory(),
        ]);
    }
}
