<?php

declare(strict_types=1);

namespace Tests\Feature\MonthlyAccount;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\MonthlyAccount\Models\MonthlyAccountItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class MonthlyAccountRecordsTest extends MonthlyAccountTestCase
{
    #[Test]
    public function odeme_satiri_olmadan_yalniz_elle_gunluk_liste_tutari_kaydedilebilir(): void
    {
        $day = $this->createDay($this->admin, ['daily_total' => '0', 'items' => []]);

        $this->assertDatabaseHas('monthly_account_revisions', ['day_id' => $day->id, 'daily_total' => '0.00']);
        $this->assertDatabaseCount('monthly_account_items', 0);
    }

    #[Test]
    public function gunluk_liste_elle_girilen_tek_tl_tutari_ve_yontem_doviz_satirlari_olarak_saklanir(): void
    {
        $day = $this->createDay($this->admin, ['items' => [
            $this->item(),
            $this->item(['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'amount' => '1.250,50']),
            $this->item(['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'currency' => 'USD', 'amount' => '300', 'conversion_mode' => 'rate', 'exchange_rate_to_try' => '34,5678', 'note' => '209 nolu oda']),
            $this->item(['method' => 'link', 'bank_name' => 'İş Bankası', 'currency' => 'EUR', 'amount' => '200', 'conversion_mode' => 'rate', 'exchange_rate_to_try' => '38,50']),
            $this->item(['method' => 'bank_transfer', 'bank_name' => 'Ziraat Bankası', 'currency' => 'GBP', 'amount' => '100', 'conversion_mode' => 'rate', 'exchange_rate_to_try' => '44,25']),
            $this->item(['method' => 'extra', 'amount' => '25,25', 'note' => 'Ek tahsilat']),
        ]]);

        $this->assertDatabaseHas('monthly_account_days', ['id' => $day->id, 'owner_id' => $this->admin->id, 'account_date' => '2026-09-14', 'current_version' => 1, 'status' => 'active']);
        $this->assertDatabaseHas('monthly_account_revisions', ['day_id' => $day->id, 'version' => 1, 'daily_total' => '3000.00', 'note' => 'Akşam günlük kontrolü']);
        $this->assertDatabaseCount('monthly_account_items', 6);
        $this->assertDatabaseHas('monthly_account_items', ['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'currency' => 'TRY', 'amount' => '1250.50', 'try_equivalent' => '1250.50']);
        $this->assertDatabaseHas('monthly_account_items', ['method' => 'credit_card', 'bank_name' => 'Yapı Kredi', 'currency' => 'USD', 'amount' => '300.00', 'exchange_rate_to_try' => '34.5678', 'try_equivalent' => '10370.34', 'note' => '209 nolu oda']);
        $this->assertDatabaseHas('monthly_account_items', ['method' => 'bank_transfer', 'currency' => 'GBP', 'amount' => '100.00', 'try_equivalent' => '4425.00']);
        $this->assertDatabaseHas('monthly_account_items', ['method' => 'link', 'currency' => 'EUR', 'amount' => '200.00', 'try_equivalent' => '7700.00']);
    }

    #[Test]
    public function kur_modu_kurus_hassasiyetinde_yuvarlar_ve_istemcinin_tl_toplamini_yok_sayar(): void
    {
        $this->createDay($this->admin, ['items' => [$this->item([
            'currency' => 'USD', 'amount' => '300,01', 'conversion_mode' => 'rate',
            'exchange_rate_to_try' => '34,5678', 'try_equivalent' => '1,00',
        ])]]);

        $this->assertDatabaseHas('monthly_account_items', [
            'currency' => 'USD', 'amount' => '300.01',
            'exchange_rate_to_try' => '34.5678', 'try_equivalent' => '10370.69',
        ]);
    }

    #[Test]
    public function toplam_modu_girilen_tl_tutarini_yuvarlanan_kurdan_yeniden_uretmez(): void
    {
        $this->createDay($this->admin, ['items' => [$this->item([
            'currency' => 'USD', 'amount' => '300', 'conversion_mode' => 'total',
            'exchange_rate_to_try' => '99', 'try_equivalent' => '10.000,00',
        ])]]);

        // 300 × 33,3333 = 9.999,99; buna rağmen kullanıcının 10.000,00 TL'si korunur.
        $this->assertDatabaseHas('monthly_account_items', [
            'currency' => 'USD', 'amount' => '300.00', 'conversion_mode' => 'total',
            'exchange_rate_to_try' => '33.3333', 'try_equivalent' => '10000.00',
        ]);
    }

    #[Test]
    public function tl_satirinda_gonderilen_kur_ve_farkli_tl_esdegeri_tutari_degistiremez(): void
    {
        $this->createDay($this->admin, ['items' => [$this->item([
            'amount' => '3000', 'conversion_mode' => 'total',
            'exchange_rate_to_try' => '45', 'try_equivalent' => '5',
        ])]]);

        $this->assertDatabaseHas('monthly_account_items', [
            'currency' => 'TRY', 'amount' => '3000.00', 'conversion_mode' => null,
            'exchange_rate_to_try' => null, 'try_equivalent' => '3000.00',
        ]);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidFields(): array
    {
        return [
            'eksi günlük tutar' => ['daily_total', '-1'],
            'üç ondalıklı tutar' => ['daily_total', '1.234'],
            'bozuk liste binlik gruplaması' => ['daily_total', '3.0.0,00'],
            'bozuk ödeme binlik gruplaması' => ['items.0.amount', '3.0.0,00'],
            'yazı içeren tutar' => ['items.0.amount', '3bin'],
            'sıfır ödeme' => ['items.0.amount', '0'],
            'eksi ödeme' => ['items.0.amount', '-10'],
            'desteklenmeyen döviz' => ['items.0.currency', 'XYZ'],
            'tanımsız yöntem' => ['items.0.method', 'cheque'],
            'geçersiz gün' => ['account_date', '2026-02-30'],
        ];
    }

    #[Test]
    #[DataProvider('invalidFields')]
    public function hatali_girdi_hicbir_kismi_kayit_olusturmaz(string $field, mixed $value): void
    {
        $payload = $this->payload($this->admin);
        data_set($payload, $field, $value);

        $this->actingAs($this->admin)->post(route('monthly-account.store'), $payload)->assertSessionHasErrors($field);

        $this->assertDatabaseCount('monthly_account_days', 0);
        $this->assertDatabaseCount('monthly_account_revisions', 0);
        $this->assertDatabaseCount('monthly_account_items', 0);
    }

    /** @return array<string, array{string}> */
    public static function bankMethods(): array
    {
        return ['kredi kartı' => ['credit_card'], 'link' => ['link'], 'havale' => ['bank_transfer']];
    }

    #[Test]
    #[DataProvider('bankMethods')]
    public function banka_yontemlerinde_banka_adi_zorunludur(string $method): void
    {
        $this->actingAs($this->admin)->post(route('monthly-account.store'), $this->payload($this->admin, [
            'items' => [$this->item(['method' => $method, 'bank_name' => ''])],
        ]))->assertSessionHasErrors('items.0.bank_name');

        $this->assertDatabaseCount('monthly_account_days', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidFx(): array
    {
        return [
            'eksik kur seçimi' => [['conversion_mode' => null], 'items.0.conversion_mode'],
            'eksik kur' => [['exchange_rate_to_try' => null], 'items.0.exchange_rate_to_try'],
            'sıfır kur' => [['exchange_rate_to_try' => '0'], 'items.0.exchange_rate_to_try'],
            'kur karşılığı sıfır kuruşa yuvarlanır' => [['amount' => '0.01', 'exchange_rate_to_try' => '0.0001'], 'items.0.exchange_rate_to_try'],
            'eksik TL toplamı' => [['conversion_mode' => 'total', 'try_equivalent' => null], 'items.0.try_equivalent'],
            'sıfır TL toplamı' => [['conversion_mode' => 'total', 'try_equivalent' => '0'], 'items.0.try_equivalent'],
        ];
    }

    #[Test]
    #[DataProvider('invalidFx')]
    public function dovizde_secilen_hesaplama_modunun_degeri_zorunludur(array $changes, string $error): void
    {
        $fx = $this->item(array_replace(['currency' => 'USD', 'amount' => '300', 'conversion_mode' => 'rate', 'exchange_rate_to_try' => '34'], $changes));
        $this->actingAs($this->admin)->post(route('monthly-account.store'), $this->payload($this->admin, ['items' => [$fx]]))
            ->assertSessionHasErrors($error);

        $this->assertDatabaseCount('monthly_account_days', 0);
        $this->assertDatabaseCount('monthly_account_items', 0);
    }

    #[Test]
    public function ayni_kullanicinin_ayni_gune_ikinci_kaydetmesi_ilk_kaydi_degistirmez(): void
    {
        $day = $this->createDay($this->admin);

        $this->post(route('monthly-account.store'), $this->payload($this->admin, ['daily_total' => '999']))
            ->assertSessionHasErrors('account_date');

        $this->assertDatabaseCount('monthly_account_days', 1);
        $this->assertDatabaseCount('monthly_account_revisions', 1);
        $this->assertDatabaseCount('monthly_account_items', 1);
        $this->assertDatabaseHas('monthly_account_revisions', ['day_id' => $day->id, 'daily_total' => '3000.00']);

        $otherOwner = $this->userWithRole(RoleName::Personel);
        $this->createDay($otherOwner);
        $this->assertDatabaseCount('monthly_account_days', 2);
    }

    #[Test]
    public function ayni_kullanici_ve_gun_tekilligi_ham_sql_ile_de_korunur(): void
    {
        $day = $this->createDay($this->admin);
        $duplicate = (array) DB::table('monthly_account_days')->where('id', $day->id)->first();
        unset($duplicate['id']);

        try {
            DB::table('monthly_account_days')->insert($duplicate);
            $this->fail('Aynı kullanıcı ve gün ikinci defa kaydedilmemelidir.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', (string) $exception->getCode());
        }
        $this->assertDatabaseCount('monthly_account_days', 1);
    }

    #[Test]
    public function duzenleme_yeni_surume_yazar_eski_tutarlari_ve_notlari_korur(): void
    {
        $day = $this->createDay($this->admin);
        $oldRevision = (array) DB::table('monthly_account_revisions')->first();
        $oldItem = (array) DB::table('monthly_account_items')->first();

        $this->put(route('monthly-account.update', $day), $this->payload($this->admin, [
            'version' => 1, 'daily_total' => '4500', 'note' => 'Düzeltilen günlük tutar',
            'items' => [$this->item(['amount' => '4.500,00', 'note' => 'Düzeltilen ödeme'])],
        ]))->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame(2, $day->fresh()->current_version);
        $this->assertDatabaseCount('monthly_account_revisions', 2);
        $this->assertDatabaseCount('monthly_account_items', 2);
        $this->assertSame($oldRevision, (array) DB::table('monthly_account_revisions')->where('id', $oldRevision['id'])->first());
        $this->assertSame($oldItem, (array) DB::table('monthly_account_items')->where('id', $oldItem['id'])->first());
        $this->assertDatabaseHas('monthly_account_revisions', ['day_id' => $day->id, 'version' => 2, 'daily_total' => '4500.00', 'note' => 'Düzeltilen günlük tutar']);
    }

    #[Test]
    public function eski_surumu_gonderen_ikinci_form_son_kaydi_ezemez_ve_silemez(): void
    {
        $day = $this->createDay($this->admin);
        $this->put(route('monthly-account.update', $day), $this->payload($this->admin, ['version' => 1, 'daily_total' => '4500']))
            ->assertSessionDoesntHaveErrors();

        $this->put(route('monthly-account.update', $day), $this->payload($this->admin, ['version' => 1, 'daily_total' => '999']))
            ->assertSessionHasErrors('version');
        $this->delete(route('monthly-account.destroy', $day), ['version' => 1])->assertSessionHasErrors('version');

        $this->assertDatabaseHas('monthly_account_days', ['id' => $day->id, 'current_version' => 2, 'status' => 'active']);
        $this->assertDatabaseCount('monthly_account_revisions', 2);
        $this->assertDatabaseHas('monthly_account_revisions', ['day_id' => $day->id, 'version' => 2, 'daily_total' => '4500.00']);
    }

    #[Test]
    public function duzenleme_ile_gun_veya_sahip_baska_alana_tasinamaz(): void
    {
        $day = $this->createDay($this->admin);
        $other = $this->userWithRole(RoleName::Personel);

        $this->put(route('monthly-account.update', $day), $this->payload($other, ['version' => 1]))->assertSessionHasErrors('owner_id');
        $this->put(route('monthly-account.update', $day), $this->payload($this->admin, ['version' => 1, 'account_date' => '2026-09-15']))->assertSessionHasErrors('account_date');

        $this->assertDatabaseHas('monthly_account_days', ['id' => $day->id, 'owner_id' => $this->admin->id, 'account_date' => '2026-09-14', 'current_version' => 1]);
        $this->assertDatabaseCount('monthly_account_revisions', 1);
    }

    #[Test]
    public function admin_silmesi_kaydi_ve_odeme_gecmisini_fiziksel_silmez(): void
    {
        $day = $this->createDay($this->admin);
        $revisionId = DB::table('monthly_account_revisions')->value('id');
        $itemId = DB::table('monthly_account_items')->value('id');

        $this->delete(route('monthly-account.destroy', $day), ['version' => 1])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('monthly_account_days', ['id' => $day->id, 'current_version' => 2, 'status' => 'deleted']);
        $this->assertDatabaseHas('monthly_account_revisions', ['id' => $revisionId, 'version' => 1, 'status' => 'active', 'daily_total' => '3000.00']);
        $this->assertDatabaseHas('monthly_account_items', ['id' => $itemId, 'amount' => '3000.00']);
        $this->assertDatabaseHas('monthly_account_revisions', ['day_id' => $day->id, 'version' => 2, 'status' => 'deleted']);
        $this->assertDatabaseCount('monthly_account_revisions', 2);
    }

    /** @return array<string, array{string, string}> */
    public static function forbiddenHistoryMutations(): array
    {
        return [
            'revizyon update' => ['monthly_account_revisions', 'update'],
            'revizyon delete' => ['monthly_account_revisions', 'delete'],
            'ödeme update' => ['monthly_account_items', 'update'],
            'ödeme delete' => ['monthly_account_items', 'delete'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenHistoryMutations')]
    public function finansal_gecmis_ham_sql_ile_degistirilemez_ve_silinemez(string $table, string $operation): void
    {
        $this->createDay($this->admin);
        $original = (array) DB::table($table)->first();

        try {
            $query = DB::table($table)->where('id', $original['id']);
            if ($operation === 'update') {
                $query->update(['note' => 'Geçmişi değiştirme denemesi']);
            } else {
                $query->delete();
            }
            $this->fail('Finansal geçmişte '.$operation.' veritabanında engellenmelidir.');
        } catch (QueryException $exception) {
            // SQLSTATE 45000: uygulama tetikleyicisi; tesadüfi bir FK hatası değil.
            $this->assertSame('45000', (string) $exception->getCode());
        }

        $this->assertSame($original, (array) DB::table($table)->where('id', $original['id'])->first());
    }

    #[Test]
    public function ikinci_odeme_yazilirken_hata_olursa_gun_ve_tum_satirlar_geri_alinir(): void
    {
        $createdItems = 0;
        MonthlyAccountItem::created(function () use (&$createdItems): void {
            if (++$createdItems === 2) {
                throw new RuntimeException('Test: ikinci satır yazılamadı.');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->admin)->post(route('monthly-account.store'), $this->payload($this->admin, [
                'items' => [$this->item(), $this->item(['amount' => '125'])],
            ]));
            $this->fail('İkinci ödeme hatası test transactionını durdurmalıydı.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Test: ikinci satır yazılamadı.', $exception->getMessage());
        }

        $this->assertSame(2, $createdItems);
        $this->assertDatabaseCount('monthly_account_days', 0);
        $this->assertDatabaseCount('monthly_account_revisions', 0);
        $this->assertDatabaseCount('monthly_account_items', 0);
    }

    #[Test]
    public function ekleme_duzenleme_ve_silme_cari_tahsilat_ve_vardiya_kasasina_ikinci_kayit_yazmaz(): void
    {
        $tables = ['ledger_entries', 'payments', 'cash_movements', 'stays'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $day = $this->createDay($this->admin);
        $this->put(route('monthly-account.update', $day), $this->payload($this->admin, ['version' => 1, 'daily_total' => '3500']))
            ->assertSessionDoesntHaveErrors();
        $this->delete(route('monthly-account.destroy', $day), ['version' => 2])->assertSessionDoesntHaveErrors();

        foreach ($tables as $table) {
            $this->assertDatabaseCount($table, $before[$table]);
        }
    }
}
