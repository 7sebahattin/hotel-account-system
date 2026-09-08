<?php

declare(strict_types=1);

namespace Tests\Feature\CashBook;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashMovement;
use App\Modules\CashBook\Models\CashSession;
use App\Modules\CashBook\Services\CashBookService;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kasa defteri testlerinin ortak zemini.
 *
 * Kurulum her testte tekrarlanacak kadar uzun (rol + kullanıcı + vardiya +
 * çok para birimli tutar dönüşümü); tekrar yerine tek yerde toplandı.
 */
abstract class CashBookTestCase extends TestCase
{
    use RefreshDatabase;

    protected CashBookService $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->cash = app(CashBookService::class);
    }

    protected function personel(string $name = 'Personel'): User
    {
        return $this->userWithRole(RoleName::Personel, ['name' => $name]);
    }

    protected function patron(string $name = 'Patron'): User
    {
        return $this->userWithRole(RoleName::Patron, ['name' => $name]);
    }

    protected function admin(string $name = 'Admin'): User
    {
        return $this->userWithRole(RoleName::Admin, ['name' => $name]);
    }

    /**
     * `['TRY' => '7000.00']` → `['TRY' => Money]`.
     *
     * @param  array<string, string>  $amounts
     * @return array<string, Money>
     */
    protected function amounts(array $amounts): array
    {
        return array_map(
            static fn (string $value): Money => Money::fromDecimal($value),
            $amounts,
        );
    }

    /** @param array<string, string> $opening */
    protected function openSession(User $user, array $opening = []): CashSession
    {
        return $this->cash->openSession($user, $this->amounts($opening));
    }

    protected function record(
        CashSession $session,
        CashMovementType $type,
        string $amount,
        User $actor,
        Currency $currency = Currency::TRY,
        ?string $counterparty = null,
    ): CashMovement {
        return $this->cash->recordMovement(
            session: $session,
            type: $type,
            currency: $currency,
            amount: Money::fromDecimal($amount),
            actor: $actor,
            description: null,
            counterparty: $counterparty,
        );
    }

    /** Beklenen kasayı ondalık metin olarak döndürür; yoksa '0.00'. */
    protected function expected(CashSession $session, Currency $currency = Currency::TRY): string
    {
        $expected = $this->cash->expectedFor($session->refresh());

        return ($expected[$currency->value] ?? Money::zero())->toDecimal();
    }
}
