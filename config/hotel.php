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
    | İki farklı sorumluluk ayrılır:
    |
    |   - App\Modules\Core\Enums\Currency: TEKNİK geçerlilik kümesi. Hangi ISO
    |     4217 kodları veritabanına yazılabilir, Money::format() ile
    |     biçimlendirilebilir. Yeni bir kod eklemek TEK bu dosyayı değiştirmeyi
    |     gerektirir — Stays/Payments/Ledger/Dashboard/Statement'ların hiçbiri
    |     currency'yi tek tek bilmez, hepsi Currency::cases()/active() üzerinden
    |     dolaylı çalışır (CurrencyTest bunu doğrular).
    |
    |   - Aşağıdaki `currencies` listesi: İŞ kararı. Formlarda (yeni konaklama/
    |     tahsilat) hangi para birimlerinin, hangi SIRAYLA sunulacağını belirler.
    |     Enum'daki kümenin bir ALT KÜMESİ olmak zorundadır — enum'da olmayan
    |     bir kod buraya eklenemez (Currency::active() bunu doğrular, geçersiz
    |     kod varsa görmezden gelir). Bir para birimini yeni kayıtlar için
    |     GEÇİCİ olarak listeden çıkarmak — geçmiş kayıtları/bakiyesini
    |     etkilemeden — burada tek satır silmektir; kod değişikliği gerekmez.
    |     Pano, cari ekstre filtresi ve raporlama BU listeyi kullanmaz —
    |     Currency::cases() (enum'daki TÜM kodlar) kullanır, çünkü geçmişte
    |     bu para biriminde gerçek borç/alacak oluşmuş olabilir.
    |
    */

    'default_currency' => env('HOTEL_DEFAULT_CURRENCY', 'TRY'),

    'currencies' => ['TRY', 'EUR', 'GBP', 'USD', 'RUB', 'GEL'],

    /*
    |--------------------------------------------------------------------------
    | Marka
    |--------------------------------------------------------------------------
    |
    | Kenar çubuğundaki marka alanı. `logo` public/ altındaki bir yola işaret
    | eder; dosya yoksa arayüz monogram çizer ve kırık görsel göstermez.
    |
    */

    'brand' => [
        'name' => env('HOTEL_BRAND_NAME', 'Lemon'),
        'tagline' => env('HOTEL_BRAND_TAGLINE', 'Cari Takip'),
        'logo' => env('HOTEL_BRAND_LOGO', 'images/logo.svg'),
    ],

    /*
    |--------------------------------------------------------------------------
    | İlk yönetici hesabı (seeder)
    |--------------------------------------------------------------------------
    |
    | env() yalnızca config dosyalarında çağrılır; config önbelleğe alındığında
    | uygulama kodundan env() null döner.
    |
    | E-posta ve parola için BURADA VARSAYILAN VERİLMEZ; değer yoksa null kalır.
    | Geliştirme kolaylığı sağlayan varsayılanlar DatabaseSeeder'dadır ve orada
    | yalnızca local/testing ortamında uygulanır. Üretimde eksik ya da varsayılan
    | kimlik bilgisi seeder'ı durdurur (DatabaseSeeder::guardProductionCredentials).
    |
    */

    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL'),
        'admin_name' => env('SEED_ADMIN_NAME', 'Sistem Yöneticisi'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
    ],

];
