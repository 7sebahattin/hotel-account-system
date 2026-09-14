<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Stays\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * K-110 — dört döviz girişli ekranın da AYNI iki modlu bloğu
 * (`<x-ui.fx-input>`) render ettiğini kilitler. İşaretleme dört yere
 * kopyalanırsa ya da bir ekran güncellenmeden kalırsa bu test kırılır.
 */
class ExchangeRateScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dort_ekran_da_iki_modlu_blogu_render_eder(): void
    {
        $this->seedRoles();
        $admin = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        $stay = Stay::factory()->create([
            'company_id' => $company->id, 'amount' => '500.00', 'currency' => 'EUR',
            'exchange_rate_to_try' => null, 'try_equivalent' => null,
        ]);
        $edit = Stay::factory()->create([
            'company_id' => $company->id, 'amount' => '500.00', 'currency' => 'EUR',
            'exchange_rate_to_try' => '51.2000', 'try_equivalent' => '25600.00',
        ]);

        foreach ([
            'konaklama oluştur' => route('stays.create'),
            'konaklama düzenle' => route('stays.edit', $edit),
            'tahsilat oluştur' => route('payments.create'),
            'makbuz defteri' => route('receipt-book.create'),
            'geçmiş düzeltme' => route('fx-correction.index'),
        ] as $label => $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            foreach (['data-fx-input', 'data-fx-mode', 'name="try_equivalent_input"', 'name="exchange_rate_to_try"'] as $hook) {
                $this->assertStringContainsString($hook, $html, "{$label}: {$hook} yok");
            }

            // Blade yorumu HTML'e sızmamalı (K-105'te yaşanan hata).
            $this->assertStringNotContainsString('--}}', $html, "{$label}: Blade yorumu sızmış");
        }

        // Düzenleme formu mevcut kuru ÖN DOLU getirir.
        $html = $this->actingAs($admin)->get(route('stays.edit', $edit))->getContent();
        $this->assertStringContainsString('value="51.2000"', $html);
        $this->assertStringContainsString('value="25600.00"', $html);

        // Düzeltme ekranı her satıra BENZERSİZ id verir.
        $html = $this->actingAs($admin)->get(route('fx-correction.index'))->getContent();
        $this->assertStringContainsString('id="fx-stay-'.$stay->id.'-mode"', $html);
    }
}
