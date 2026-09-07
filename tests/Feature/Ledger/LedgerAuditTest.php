<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cari hareketleri değiştirilemez olduğu için klasik updated/deleted olayları
 * yoktur; yalnızca oluşturma ve ters kayıt denetlenir.
 *
 * Tutarlar denetim kaydında maskelenmez ve kısaltılmaz — finansal denetim için
 * görünür kalmalıdır.
 */
class LedgerAuditTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private Company $company;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LedgerService::class);
        $this->company = Company::factory()->create();
        $this->actor = User::factory()->create(['name' => 'Muhasebe Kullanıcısı']);
        AuditLog::query()->delete();
    }

    #[Test]
    public function hareket_olusturma_denetim_kaydina_yazilir(): void
    {
        $entry = $this->ledger->debit(
            $this->company->id, Money::fromDecimal('1250.75'), Currency::TRY,
            '2026-03-15', 'Konaklama bedeli', 'stay', 42, $this->actor,
        );

        $log = AuditLog::query()
            ->where('event', AuditEvent::Created->value)
            ->where('auditable_type', 'ledger_entry')
            ->firstOrFail();

        $this->assertSame($entry->id, $log->auditable_id);
        $this->assertSame($this->actor->id, $log->user_id);
        $this->assertSame('Muhasebe Kullanıcısı', $log->user_name);

        // Tutar denetim kaydında AÇIK olmalı.
        $this->assertSame('1250.75', $log->new_values['debit']);
        $this->assertSame('0.00', $log->new_values['credit']);
        $this->assertSame('TRY', $log->new_values['currency']);
        $this->assertSame('2026-03-15', $log->new_values['entry_date']);
        $this->assertSame('stay', $log->new_values['source_type']);
        $this->assertSame(42, $log->new_values['source_id']);
        $this->assertSame($this->company->id, $log->new_values['company_id']);
    }

    #[Test]
    public function ters_kayit_reversed_olayi_olarak_yazilir(): void
    {
        $entry = $this->ledger->debit(
            $this->company->id, Money::fromDecimal('1000.00'), Currency::TRY,
            now(), 'Konaklama bedeli', actor: $this->actor,
        );
        AuditLog::query()->delete();

        $reversal = $this->ledger->reverse($entry, actor: $this->actor);

        $log = AuditLog::query()->where('event', AuditEvent::Reversed->value)->firstOrFail();

        $this->assertSame($entry->id, $log->auditable_id, 'Ters kayıt olayı orijinal harekete bağlanır');
        $this->assertSame('1000.00', $log->old_values['debit']);
        $this->assertSame('0.00', $log->old_values['credit']);
        $this->assertSame('0.00', $log->new_values['debit']);
        $this->assertSame('1000.00', $log->new_values['credit']);
        $this->assertSame($reversal->id, $log->new_values['reversal_entry_id']);
        $this->assertTrue($log->new_values['is_reversal']);
    }

    #[Test]
    public function ters_kayit_icin_ayrica_created_kaydi_acilmaz(): void
    {
        $entry = $this->ledger->debit(
            $this->company->id, Money::fromDecimal('500.00'), Currency::TRY,
            now(), 'Borç', actor: $this->actor,
        );
        AuditLog::query()->delete();

        $this->ledger->reverse($entry, actor: $this->actor);

        $this->assertSame(1, AuditLog::query()->count(), 'Bir ters kayıt işlemi tek denetim kaydı üretir');
        $this->assertSame(AuditEvent::Reversed, AuditLog::query()->firstOrFail()->event);
    }

    #[Test]
    public function tutarlar_maskelenmez_ve_kisaltilmaz(): void
    {
        $this->ledger->debit(
            $this->company->id, Money::fromDecimal('9999999.99'), Currency::EUR,
            now(), 'Büyük tutar', actor: $this->actor,
        );

        $log = AuditLog::query()->firstOrFail();
        $encoded = json_encode($log->new_values, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('9999999.99', $encoded);
        $this->assertStringNotContainsString('***', $encoded);
    }

    #[Test]
    public function her_hareket_kendi_denetim_kaydini_uretir(): void
    {
        foreach (['100.00', '200.00', '300.00'] as $amount) {
            $this->ledger->debit(
                $this->company->id, Money::fromDecimal($amount), Currency::TRY,
                now(), 'Borç', actor: $this->actor,
            );
        }

        $this->assertSame(3, AuditLog::query()->where('auditable_type', 'ledger_entry')->count());
        $this->assertSame(3, LedgerEntry::query()->count());
    }
}
