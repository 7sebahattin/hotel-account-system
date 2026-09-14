<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\MonthlyAccount\Models\MonthlyAccountDay;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class MonthlyAccountTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->userWithRole(RoleName::Admin);
        MonthlyPaymentProvider::query()->insert(array_map(
            fn (string $name): array => [
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['Garanti BBVA', 'İş Bankası', 'Yapı Kredi', 'Ziraat Bankası'],
        ));
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function payload(User $owner, array $overrides = []): array
    {
        return array_replace([
            'owner_id' => $owner->id,
            'account_date' => '2026-09-14',
            'daily_total' => '3.000,00',
            'note' => 'Akşam günlük kontrolü',
            'items' => [$this->item()],
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function item(array $overrides = []): array
    {
        return array_replace([
            'method' => 'cash',
            'currency' => 'TRY',
            'bank_name' => null,
            'amount' => '3.000,00',
            'conversion_mode' => null,
            'exchange_rate_to_try' => null,
            'try_equivalent' => null,
            'note' => 'Tek çekildi',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    protected function createDay(User $owner, array $overrides = []): MonthlyAccountDay
    {
        $this->actingAs($this->admin)
            ->post(route('monthly-account.store'), $this->payload($owner, $overrides))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        return MonthlyAccountDay::query()->latest('id')->firstOrFail();
    }
}
