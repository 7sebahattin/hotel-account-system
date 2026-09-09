<?php

declare(strict_types=1);

namespace App\Modules\Rooms\Database\Factories;

use App\Modules\Rooms\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_no' => (string) fake()->unique()->numberBetween(100, 999),
            'room_name' => null,
            'room_type' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
