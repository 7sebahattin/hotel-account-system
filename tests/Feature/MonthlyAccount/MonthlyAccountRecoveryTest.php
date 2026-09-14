<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Modules\Authorization\Enums\RoleName;
use PHPUnit\Framework\Attributes\Test;

class MonthlyAccountRecoveryTest extends MonthlyAccountTestCase
{
    #[Test]
    public function silinen_gunu_yalniz_admin_yeni_surume_geri_getirir(): void
    {
        $owner = $this->userWithRole(RoleName::Personel);
        $patron = $this->userWithRole(RoleName::Patron);
        $day = $this->createDay($owner);
        $this->delete(route('monthly-account.destroy', $day), ['version' => 1])
            ->assertSessionDoesntHaveErrors();

        foreach ([$owner, $patron] as $actor) {
            $this->actingAs($actor)->get(route('monthly-account.edit', $day))->assertForbidden();
            $this->put(route('monthly-account.update', $day), $this->payload($owner, ['version' => 2]))
                ->assertForbidden();
        }

        $this->actingAs($this->admin)->put(route('monthly-account.update', $day), $this->payload($owner, ['version' => 2]))
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('monthly_account_days', ['id' => $day->id, 'status' => 'active', 'current_version' => 3]);
        $this->assertDatabaseHas('monthly_account_revisions', ['day_id' => $day->id, 'version' => 2, 'status' => 'deleted']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'monthly_account_day', 'auditable_id' => $day->id, 'event' => 'restored']);
        $this->assertDatabaseCount('monthly_account_items', 3);
        $this->get(route('monthly-account.index', ['owner_id' => $owner->id, 'month' => '2026-09']))
            ->assertOk()->assertViewHas('summary', fn (array $summary): bool => $summary['receipt_total']->toDecimal() === '3000.00');
    }

    #[Test]
    public function ay_toplamini_tasiran_yeni_gun_reddedilir_mevcut_ay_acilabilir_kalir(): void
    {
        $this->createDay($this->admin, [
            'daily_total' => '9999999999999.99',
            'items' => [$this->item(['amount' => '9999999999999.99'])],
        ]);

        $this->post(route('monthly-account.store'), $this->payload($this->admin, [
            'account_date' => '2026-09-15', 'daily_total' => '0',
            'items' => [$this->item(['amount' => '0.01'])],
        ]))->assertSessionHasErrors('items');

        $this->assertDatabaseCount('monthly_account_days', 1);
        $this->assertDatabaseCount('monthly_account_revisions', 1);
        $this->get(route('monthly-account.index', ['month' => '2026-09']))->assertOk();
    }
}
