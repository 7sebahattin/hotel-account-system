<?php

declare(strict_types=1);

namespace App\Modules\Stays\Database\Factories;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Rooms\Models\Room;
use App\Modules\Stays\Enums\StayPricingMode;
use App\Modules\Stays\Enums\StayStatus;
use App\Modules\Stays\Models\Stay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Yalnızca test amaçlıdır. Üretimde konaklamalar StayService üzerinden yazılır
 * ve cari hareketi de o sırada oluşur; bu fabrika cari hareketi ÜRETMEZ.
 *
 * @extends Factory<Stay>
 */
class StayFactory extends Factory
{
    protected $model = Stay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = Carbon::parse(fake()->dateTimeBetween('-3 months', '+1 month')->format('Y-m-d'));
        $checkOut = $checkIn->copy()->addDays(fake()->numberBetween(1, 7));

        return [
            'company_id' => Company::factory(),
            'guest_name' => fake()->name(),
            'room_no' => (string) fake()->numberBetween(101, 420),
            'room_id' => null,
            'check_in_date' => $checkIn->toDateString(),
            'check_out_date' => $checkOut->toDateString(),
            'nights' => Stay::calculateNights($checkIn, $checkOut),
            'amount' => (string) fake()->numberBetween(500, 20000).'.00',
            'pricing_mode' => StayPricingMode::Total->value,
            'daily_rate' => null,
            'currency' => Currency::TRY->value,
            'notes' => null,
            'status' => StayStatus::Active->value,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /** Günlük ücretlendirilmiş kayıt; toplam gece sayısıyla tutarlı tutulur. */
    public function daily(string $dailyRate = '2500.00'): static
    {
        return $this->state(function (array $attributes) use ($dailyRate) {
            $nights = (int) $attributes['nights'];

            return [
                'pricing_mode' => StayPricingMode::Daily->value,
                'daily_rate' => $dailyRate,
                'amount' => Money::fromDecimal($dailyRate)->multiply($nights)->toDecimal(),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => StayStatus::Cancelled->value]);
    }

    /** Yeni Room Registry akışı: oda serbest metin DEĞİL, `room_id` ile bağlıdır. */
    public function forRoom(Room $room): static
    {
        return $this->state(fn () => ['room_id' => $room->getKey(), 'room_no' => null]);
    }
}
