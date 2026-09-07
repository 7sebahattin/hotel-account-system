<?php

declare(strict_types=1);
use App\Modules\Core\Enums\Currency;

return [

    /*
    |--------------------------------------------------------------------------
    | Personel düzenleme penceresi
    |--------------------------------------------------------------------------
    |
    | Personel rolündeki kullanıcı, yalnızca KENDİ oluşturduğu kaydı ve
    | yalnızca bu süre içinde düzenleyebilir (CLAUDE.md §7.4).
    | Süre dolduğunda düzeltme yetkisi Admin/Patron'a geçer.
    |
    */

    'personnel_edit_window_hours' => (int) env('HOTEL_PERSONNEL_EDIT_WINDOW_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Para birimleri
    |--------------------------------------------------------------------------
    |
    | V1'de kur çevrimi YOKTUR. Her para birimi kendi bakiyesini üretir ve
    | bakiyeler asla tek toplamda birleştirilmez (CLAUDE.md §6/L5).
    |
    */

    'default_currency' => env('HOTEL_DEFAULT_CURRENCY', 'TRY'),

    // Tek doğruluk kaynağı App\Modules\Core\Enums\Currency enum'udur.
    'currencies' => Currency::class,

    /*
    |--------------------------------------------------------------------------
    | İlk yönetici hesabı (seeder)
    |--------------------------------------------------------------------------
    |
    | env() yalnızca config dosyalarında çağrılır; config önbelleğe alındığında
    | uygulama kodundan env() null döner.
    |
    */

    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
        'admin_name' => env('SEED_ADMIN_NAME', 'Sistem Yöneticisi'),
        'admin_password' => env('SEED_ADMIN_PASSWORD', 'degistir-beni'),
    ],

];
