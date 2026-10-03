<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Users\Models\UserQuickMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function shortcut(string $route, string $label, bool $active = true, string $icon = 'plus'): array
    {
        return compact('route', 'label', 'active', 'icon');
    }

    #[Test]
    public function floating_menu_is_desktop_only_and_uses_accessible_toggle_contract(): void
    {
        $html = $this->actingAs($this->userWithRole(RoleName::Admin))->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-quick-action class="fixed bottom-6 right-6 z-30 hidden lg:block"', $html);
        $this->assertStringContainsString('data-quick-action-panel hidden', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="hizli-islem-menu"', $html);
        $this->assertStringContainsString('data-quick-action-toggle', $html);
        $this->assertStringContainsString(route('quick-menu.edit'), $html);
    }

    #[Test]
    public function defaults_follow_current_permissions(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $html = $this->actingAs($admin)->get(route('home'))->getContent();

        foreach (['payments.create', 'stays.create', 'companies.create'] as $route) {
            $this->assertStringContainsString(route($route), $html);
        }
        $this->assertStringNotContainsString(route('offers.create'), $html);
        $this->assertDatabaseMissing('user_quick_menus', ['user_id' => $admin->id]);

        $plain = User::factory()->create();
        $plainHtml = $this->actingAs($plain)->get(route('home'))->getContent();
        $this->assertStringContainsString('Henüz etkin kısayol yok.', $plainHtml);
        $this->assertStringNotContainsString(route('payments.create'), $plainHtml);
    }

    #[Test]
    public function saved_menu_respects_order_inactive_state_and_empty_state(): void
    {
        $user = $this->userWithRole(RoleName::Admin);
        UserQuickMenu::query()->create(['user_id' => $user->id, 'shortcuts' => [
            $this->shortcut('stays.create', 'İlk'),
            $this->shortcut('payments.create', 'Gizli', false),
            $this->shortcut('companies.create', 'Son'),
        ]]);

        $html = $this->actingAs($user)->get(route('home'))->getContent();
        $this->assertLessThan(strpos($html, 'Son'), strpos($html, 'İlk'));
        $this->assertStringNotContainsString('Gizli', $html);

        UserQuickMenu::query()->where('user_id', $user->id)->update(['shortcuts' => '[]']);
        $this->actingAs($user)->get(route('home'))->assertSee('Henüz etkin kısayol yok.');
    }

    #[Test]
    public function revoked_permission_hides_saved_shortcut_and_denies_target(): void
    {
        $user = $this->userWithRole(RoleName::Personel);
        UserQuickMenu::query()->create(['user_id' => $user->id, 'shortcuts' => [
            $this->shortcut('payments.create', 'Özel tahsilat'),
        ]]);
        $this->actingAs($user)->get(route('home'))->assertSee('Özel tahsilat');

        $user->removeRole(RoleName::Personel->value);
        $this->actingAs($user->fresh())->get(route('home'))->assertDontSee('Özel tahsilat');
        $this->actingAs($user->fresh())->get(route('payments.create'))->assertForbidden();
    }

    #[Test]
    public function settings_save_supports_add_edit_delete_active_and_order_per_user(): void
    {
        $first = $this->userWithRole(RoleName::Admin);
        $second = $this->userWithRole(RoleName::Patron);

        $this->actingAs($first)->put(route('quick-menu.update'), ['shortcuts' => [
            $this->shortcut('stays.create', 'Konaklama', true, 'bed'),
            $this->shortcut('companies.create', 'Cari', false, 'building'),
        ]])->assertRedirect(route('quick-menu.edit'));

        $this->assertSame(['Konaklama', 'Cari'], array_column(UserQuickMenu::query()->where('user_id', $first->id)->firstOrFail()->shortcuts, 'label'));
        $this->assertDatabaseMissing('user_quick_menus', ['user_id' => $second->id]);

        $this->actingAs($first)->put(route('quick-menu.update'), ['shortcuts' => [
            $this->shortcut('companies.create', 'Yeni ad', true, 'wallet'),
            $this->shortcut('stays.create', 'Konaklama', true, 'bed'),
        ]])->assertRedirect(route('quick-menu.edit'));
        $saved = UserQuickMenu::query()->where('user_id', $first->id)->firstOrFail()->shortcuts;
        $this->assertSame(['Yeni ad', 'Konaklama'], array_column($saved, 'label'));
        $this->assertSame('wallet', $saved[0]['icon']);
        $this->assertTrue($saved[0]['active']);

        $this->actingAs($first)->put(route('quick-menu.update'), [])->assertRedirect(route('quick-menu.edit'));
        $this->assertSame([], UserQuickMenu::query()->where('user_id', $first->id)->firstOrFail()->shortcuts);
        $this->assertDatabaseMissing('user_quick_menus', ['user_id' => $second->id]);
    }

    #[Test]
    public function another_users_shortcuts_cannot_be_changed_through_settings(): void
    {
        $first = $this->userWithRole(RoleName::Admin);
        $second = $this->userWithRole(RoleName::Patron);
        UserQuickMenu::query()->create(['user_id' => $first->id, 'shortcuts' => [$this->shortcut('stays.create', 'Birinci')]]);

        $this->actingAs($second)->put(route('quick-menu.update'), ['user_id' => $first->id, 'shortcuts' => [
            $this->shortcut('companies.create', 'İkinci'),
        ]])->assertRedirect(route('quick-menu.edit'));

        $this->assertSame('Birinci', UserQuickMenu::query()->where('user_id', $first->id)->firstOrFail()->shortcuts[0]['label']);
        $this->assertSame('İkinci', UserQuickMenu::query()->where('user_id', $second->id)->firstOrFail()->shortcuts[0]['label']);
    }

    #[Test]
    public function external_urls_unknown_routes_and_unknown_icons_are_rejected(): void
    {
        $user = $this->userWithRole(RoleName::Admin);
        foreach ([
            $this->shortcut('https://example.com', 'Dış URL'),
            $this->shortcut('javascript:alert(1)', 'Script'),
            $this->shortcut('users.create', 'Kayıt'),
            $this->shortcut('stays.create', 'İkon', true, '<script>'),
        ] as $invalid) {
            $this->actingAs($user)->put(route('quick-menu.update'), ['shortcuts' => [$invalid]])->assertSessionHasErrors();
        }
        $this->assertDatabaseMissing('user_quick_menus', ['user_id' => $user->id]);
    }

    #[Test]
    public function unauthorized_route_is_rejected_and_labels_are_escaped(): void
    {
        $user = $this->userWithRole(RoleName::Personel);
        $this->actingAs($user)->put(route('quick-menu.update'), ['shortcuts' => [
            $this->shortcut('offers.create', 'Teklif'),
        ]])->assertSessionHasErrors();

        $this->actingAs($user)->put(route('quick-menu.update'), ['shortcuts' => [
            $this->shortcut('stays.create', '<script>alert(1)</script>'),
        ]])->assertRedirect(route('quick-menu.edit'));
        $html = $this->actingAs($user)->get(route('home'))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    #[Test]
    public function offer_target_requires_both_view_and_create_permissions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('offers.create');

        $this->actingAs($user)->put(route('quick-menu.update'), ['shortcuts' => [
            $this->shortcut('offers.create', 'Teklif'),
        ]])->assertSessionHasErrors();
        $this->assertDatabaseMissing('user_quick_menus', ['user_id' => $user->id]);

        $user->givePermissionTo('offers.view');
        $this->actingAs($user->fresh())->put(route('quick-menu.update'), ['shortcuts' => [
            $this->shortcut('offers.create', 'Teklif'),
        ]])->assertRedirect(route('quick-menu.edit'));
        $this->actingAs($user->fresh())->get(route('home'))->assertSee('Teklif');
    }
}
