<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Database\Seeders;

use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\CashBook\Models\CashMovementCategory;
use Illuminate\Database\Seeder;

/**
 * Minimal, mantıklı varsayılan kategori kümesi (v2.1.1-B §4).
 *
 * `firstOrCreate(name)` ile yeniden çalıştırılabilir: mevcut kategoriler
 * (admin tarafından yeniden adlandırılmış olsalar bile) etkilenmez, yalnızca
 * eksik olanlar eklenir. "Cari Tahsilat" (`company_collection`) kasıtlı
 * olarak burada YOKTUR — o sistem üretimlidir, admin tarafından seçilemez.
 */
class CashMovementCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Oda Geliri', 'system_type' => CashMovementType::RoomIncome, 'sort_order' => 10],
            ['name' => 'Ek Gelir', 'system_type' => CashMovementType::ExtraIncome, 'sort_order' => 20],
            ['name' => 'Gider', 'system_type' => CashMovementType::Expense, 'sort_order' => 30],
            ['name' => 'Kasa Girişi', 'system_type' => CashMovementType::CashIn, 'sort_order' => 40],
            [
                'name' => 'Kasadan Teslim', 'system_type' => CashMovementType::CashOut,
                'sort_order' => 50, 'requires_recipient' => true,
            ],
        ];

        foreach ($defaults as $category) {
            CashMovementCategory::query()->firstOrCreate(
                ['name' => $category['name']],
                [
                    'system_type' => $category['system_type'],
                    'sort_order' => $category['sort_order'],
                    'requires_recipient' => $category['requires_recipient'] ?? false,
                    'is_active' => true,
                ],
            );
        }
    }
}
