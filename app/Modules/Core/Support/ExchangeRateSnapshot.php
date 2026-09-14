<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use Illuminate\Validation\ValidationException;

/**
 * Bir işlemin kur ANLIK GÖRÜNTÜSÜ — kur + TL karşılığı çifti (v2.1.1-F, K-110).
 *
 * Bu mantık daha önce BEŞ yerde kopyalanmıştı (`StayService`, `PaymentService`,
 * `ReceiptBookService` ve iki `correctHistoricalExchangeRate()`); CLAUDE.md
 * §4.2/4 gereği Core'a taşındı. Modüller bunu yalnızca `fromInput()` ile
 * çağırır, kendi aritmetiğini yazmaz.
 *
 * **İKİ GİRİŞ MODU vardır (kullanıcı talimatı, 14 Eylül 2026):**
 *
 * - `rate` — operatör KURU girer, TL karşılığı `Money::multiplyByRate()` ile
 *   hesaplanır. v2.1.1-C'den beri var olan davranış, aynen korunur.
 * - `total` — operatör doğrudan TL TOPLAMINI girer (ör. bankaya gerçekte
 *   düşen tutar), kur `Money::rateAgainst()` ile TÜRETİLİR.
 *
 * **`total` modunda girilen TL AYNEN saklanır, kurdan yeniden hesaplanmaz.**
 * Kur kolonu `DECIMAL(10,4)` olduğu için her toplam dört ondalıkla tam ifade
 * edilemez (1.000 EUR ↔ 41.234,57 ₺ → kur 41,2346 → geri çarpım 41.234,60 ₺).
 * Operatörün yazdığı rakamı üç kuruş kaydırmak yerine, TÜRETİLMİŞ olan
 * değerin (kur) yaklaşık olması tercih edilmiştir — kullanıcı kararı.
 *
 * Veritabanı bu ikisini birbirine BAĞLAMAZ: üç tablodaki CHECK kısıtları
 * yalnızca "TRY hiç kur taşımaz", "ikisi birlikte dolu ya da birlikte boş" ve
 * "ikisi de pozitif" der — "karşılık = tutar × kur" diye bir kısıt YOKTUR.
 * Bu yüzden iki modlu giriş MİGRATION GEREKTİRMEZ.
 *
 * Hiçbir modda cari deftere bir şey yazılmaz: tutar her zaman SEÇİLEN PARA
 * BİRİMİNDE işlenir, TL karşılığı salt bilgi amaçlıdır (CLAUDE.md §6/L5).
 */
final readonly class ExchangeRateSnapshot
{
    public const MODE_RATE = 'rate';

    public const MODE_TOTAL = 'total';

    /** `exchange_rate_to_try` DECIMAL(10,4) üst sınırı, 10.000 ölçeğinde. */
    private const MAX_SCALED_RATE = 9_999_999_999;

    private function __construct(
        public string $rate,
        public string $tryEquivalent,
    ) {}

    /**
     * Doğrulanmış istek verisinden çözer. Kur bilgisi hiç gönderilmemişse
     * `null` döner — çağıran taraf o zaman mevcut değeri KORUR.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromInput(Money $amount, array $data): ?self
    {
        $mode = $data['fx_input_mode'] ?? null;

        // Mod gönderilmediyse eski (tek alanlı) sözleşmeye düşülür: yalnızca
        // kur varsa o kullanılır. Böylece kur alanını tek başına gönderen
        // çağrılar (ör. testler, CLI import) çalışmaya devam eder.
        if ($mode === null) {
            $rate = $data['exchange_rate_to_try'] ?? null;

            return $rate === null ? null : self::fromRate($amount, (string) $rate);
        }

        if ($mode === self::MODE_TOTAL) {
            $total = $data['try_equivalent_input'] ?? null;

            return $total === null ? null : self::fromTryTotal($amount, (string) $total);
        }

        $rate = $data['exchange_rate_to_try'] ?? null;

        return $rate === null ? null : self::fromRate($amount, (string) $rate);
    }

    /** Operatör kuru girdi: TL karşılığı hesaplanır. */
    public static function fromRate(Money $amount, string $rate): self
    {
        $rate = Money::normalizeInput($rate);

        return new self($rate, $amount->multiplyByRate($rate)->toDecimal());
    }

    /**
     * Operatör TL toplamını girdi: kur TÜRETİLİR, toplam AYNEN korunur.
     *
     * Türetilen kur `DECIMAL(10,4)` aralığının dışına düşerse (sıfıra
     * yuvarlanır ya da 999.999,9999'u aşar) kayıt veritabanı CHECK'ine
     * takılırdı. Bu pratikte bir yazım hatasıdır (bir avroya iki milyon lira,
     * ya da milyon avroya bir kuruş), bu yüzden 500 yerine alanın KENDİSİNE
     * bağlı bir doğrulama hatası üretilir — Laravel bunu kendiliğinden forma
     * geri döndürür, hiçbir controller'a `try/catch` eklemek gerekmez (§13:
     * controller ince kalır).
     */
    public static function fromTryTotal(Money $amount, string $total): self
    {
        $total = Money::fromDecimal(Money::normalizeInput($total));
        $rate = $total->rateAgainst($amount);
        $scaled = (int) str_replace('.', '', $rate);

        if ($scaled <= 0 || $scaled > self::MAX_SCALED_RATE) {
            throw ValidationException::withMessages([
                'try_equivalent_input' => 'Girilen TL karşılığı bu tutar için geçerli bir kura dönüşmüyor. Tutarı ve TL karşılığını kontrol ediniz.',
            ]);
        }

        return new self($rate, $total->toDecimal());
    }

    /**
     * Türetilen kur, girilen toplamı TAM olarak ifade ediyor mu?
     *
     * `false` ise saklanan çift bilerek birbirinden birkaç kuruş ayrıdır
     * (yukarıdaki sınıf açıklaması). Ekranlar bunu operatöre gösterir;
     * hiçbir yerde sessizce düzeltilmez.
     */
    public function isExact(Money $amount): bool
    {
        return $amount->multiplyByRate($this->rate)->toDecimal() === $this->tryEquivalent;
    }
}
