<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Database\Factories;

use App\Models\User;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\Core\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = CashMovementType::RoomIncome;

        return [
            'cash_session_id' => CashSession::factory(),
            'type' => $type,
            'direction' => $type->direction(),
            'amount' => '1000.00',
            'currency' => Currency::TRY,
            'entry_at' => Carbon::now(),
            'description' => 'Test hareketi',
            'is_reversal' => false,
            'created_by' => User::factory(),
        ];
    }

    /** Yön her zaman tipin doğal yönüdür; testler tipi tek yerden değiştirir. */
    public function ofType(CashMovementType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'direction' => $type->direction(),
        ]);
    }
}
