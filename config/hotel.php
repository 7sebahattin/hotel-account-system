<?php

declare(strict_types=1);

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

    'currencies' => ['TRY', 'EUR', 'GBP'],

];
