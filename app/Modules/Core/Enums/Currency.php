<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

/**
 * Desteklenen para birimleri — TEKNİK geçerlilik kümesi.
 *
 * Bu enum tek doğruluk kaynağıdır: veritabanına yazılabilecek, `Money`
 * tarafından biçimlendirilebilecek ISO 4217 kodları budur. Yeni bir kod
 * eklemek yalnızca bu dosyayı değiştirmeyi gerektirir — Stays/Payments/
 * Ledger/Dashboard/Statement modüllerinin hiçbiri belirli bir para birimini
 * adıyla bilmez; hepsi `Currency::cases()` (raporlama, geçmiş veriyle
 * çalışan her yer) veya `Currency::active()` (yeni kayıt formları) üzerinden
 * dolaylı çalışır. `config/hotel.php`'deki `currencies` listesi bunun
 * ÜZERİNE binen bir iş kararıdır — hangilerinin yeni kayıtlarda hangi
 * sırayla sunulacağını belirler, teknik kümeyi GENİŞLETEMEZ.
 *
 * CLAUDE.md §6/L5: farklı para birimleri asla tek toplamda birleştirilmez ve
 * V1'de kur çevrimi yoktur.
 */
enum Currency: string
{
    case TRY = 'TRY';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case USD = 'USD';
    case RUB = 'RUB';
    case GEL = 'GEL';

    /**
     * Sembol yalnızca yaygın ve tek anlamlı olduğunda kullanılır. RUB (₽) ve
     * GEL (₾) sembolleri Türkçe finansal bağlamda ₺/€/£/$ kadar tanınmıyor ve
     * bazı yazı tiplerinde düzgün görüntülenmiyor — CLAUDE.md §7 "sembole
     * güvenme, ISO kodu açıkça anlaşılabilir olsun" kuralı gereği bu ikisi
     * doğrudan ISO kodunu döner: "35.000,00 RUB" gibi.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::TRY => '₺',
            self::EUR => '€',
            self::GBP => '£',
            self::USD => '$',
            self::RUB, self::GEL => $this->value,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TRY => 'Türk Lirası',
            self::EUR => 'Euro',
            self::GBP => 'İngiliz Sterlini',
            self::USD => 'ABD Doları',
            self::RUB => 'Rus Rublesi',
            self::GEL => 'Gürcistan Larisi',
        };
    }

    /**
     * `Money`'nin kuruş (minor unit) varsayımının ondalık basamak sayısı.
     *
     * V1'de desteklenen ALTI para biriminin tamamı ISO 4217'de 2 ondalık
     * basamaklıdır (JPY=0, BHD=3 gibi istisnalar yoktur), bu yüzden `Money`
     * hâlâ sabit `× 100` varsayımıyla çalışır (CLAUDE.md §13). Bu metot o
     * varsayımı ADLANDIRIR ve TEST EDER: ileride 0 veya 3 ondalıklı bir para
     * birimi eklenirse, `Money`'nin nereden genişletilmesi gerektiği burada
     * görünür hâle gelir — sabit bir `100` yerine bu tek nokta değişir.
     * `CurrencyTest::tum_para_birimleri_iki_ondalik_varsayar` bunu doğrular.
     */
    public function decimalPlaces(): int
    {
        return 2;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Yeni kayıt formlarında (konaklama/tahsilat) sunulacak para birimleri,
     * `config('hotel.currencies')` listesindeki SIRAYLA.
     *
     * Bu bir İŞ KARARIDIR, teknik geçerlilik değil: geçmiş kayıtlar ve
     * raporlama (Dashboard, Statement filtresi) bu listeyi KULLANMAZ —
     * `cases()` ile TÜM enum kümesini görür, çünkü bir para birimi burada
     * listelenmese bile geçmişte gerçek borç/alacak üretmiş olabilir.
     *
     * Config'te enum'da olmayan bir kod varsa sessizce atlanır (yapılandırma
     * hatası formu bozmaz); config boş/eksikse tüm kümeye düşer (dev güvenliği).
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        $configured = config('hotel.currencies');

        if (! is_array($configured) || $configured === []) {
            return self::cases();
        }

        $active = array_values(array_filter(array_map(
            static fn (mixed $code): ?self => is_string($code) ? self::tryFrom($code) : null,
            $configured,
        )));

        return $active === [] ? self::cases() : $active;
    }
}
