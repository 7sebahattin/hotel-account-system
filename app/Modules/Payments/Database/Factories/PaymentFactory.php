<?php

declare(strict_types=1);

namespace App\Modules\Payments\Database\Factories;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Yalnızca test amaçlıdır. Üretimde tahsilatlar PaymentService üzerinden
 * yazılır ve cari hareketi de o sırada oluşur; bu fabrika cari hareketi ÜRETMEZ.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'payment_date' => Carbon::parse(fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'))->toDateString(),
            'amount' => (string) fake()->numberBetween(500, 20000).'.00',
            'currency' => Currency::TRY->value,
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference_no' => strtoupper(fake()->bothify('??######')),
            'description' => null,
            'status' => PaymentStatus::Active->value,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * İptal edilmiş tahsilat. Bu durumdaki kayıt, veritabanı kısıtı gereği
     * iptal alanlarını da taşımak zorundadır.
     */
    public function cancelled(string $reason = 'Yanlış firmaya işlendi'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Cancelled->value,
            'cancelled_at' => Carbon::now(),
            'cancelled_by' => $attributes['created_by'] ?? User::factory(),
            'cancellation_reason' => $reason,
        ]);
    }
}
