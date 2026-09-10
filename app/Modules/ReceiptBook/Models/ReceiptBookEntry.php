<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Models;

use App\Models\User;
use App\Modules\Core\Concerns\Auditable;
use App\Modules\Core\Concerns\HasCreator;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\TurkishNumberToWords;
use App\Modules\ReceiptBook\Database\Factories\ReceiptBookEntryFactory;
use App\Modules\ReceiptBook\Enums\ReceiptPaymentMethod;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tahsilat Makbuzu Defteri kaydı (v2.1.1-C §4) — DOKÜMANTASYON/yazdırma
 * amaçlıdır, muhasebe kaydı DEĞİLDİR.
 *
 * Bu modelin `LedgerService`/`PaymentService`/`CashBookService` ile HİÇBİR
 * bağlantısı yoktur ve OLAMAZ: bir satırın var olması firma bakiyesini,
 * fiziksel kasayı ya da herhangi bir cari hareketi ETKİLEMEZ.
 *
 * @property int $id
 * @property string|null $receipt_no
 * @property Carbon $receipt_date
 * @property string $customer_name
 * @property string $amount
 * @property Currency $currency
 * @property string|null $exchange_rate_to_try
 * @property string|null $try_equivalent
 * @property ReceiptPaymentMethod|null $payment_method
 * @property string|null $description
 * @property int $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class ReceiptBookEntry extends Model
{
    /** @use HasFactory<ReceiptBookEntryFactory> */
    use Auditable, HasCreator, HasFactory;

    protected $table = 'receipt_book_entries';

    protected $fillable = [
        'receipt_date',
        'customer_name',
        'amount',
        'currency',
        'payment_method',
        'description',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'receipt_date' => 'date',
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'exchange_rate_to_try' => 'decimal:4',
            'try_equivalent' => 'decimal:2',
            'payment_method' => ReceiptPaymentMethod::class,
        ];
    }

    public function amountMoney(): Money
    {
        return Money::fromDecimal($this->amount);
    }

    public function tryEquivalentMoney(): ?Money
    {
        return $this->try_equivalent === null ? null : Money::fromDecimal($this->try_equivalent);
    }

    public function hasExchangeRate(): bool
    {
        return $this->exchange_rate_to_try !== null;
    }

    /**
     * Makbuz numarası — `Payment::receiptNumberFor()` ile AYNI desen (`id`
     * üzerinden türetilir, ayrı sıra tablosu yok) ama BİLEREK FARKLI bir
     * önek taşır: gerçek muhasebe makbuzuyla (`TM-…`) karıştırılmamalı (§4/§9).
     */
    public static function receiptNumberFor(int $id, DateTimeInterface $issuedOn): string
    {
        return sprintf('MKB-%s-%06d', $issuedOn->format('Y'), $id);
    }

    /** "Yalnız: …" alanı — yalnızca TRY için üretilir (Payment ile AYNI ilke). */
    public function amountInWords(): ?string
    {
        if ($this->currency !== Currency::TRY) {
            return null;
        }

        $minorUnits = $this->amountMoney()->minorUnits;
        $lira = intdiv($minorUnits, 100);
        $kurus = $minorUnits % 100;

        $words = TurkishNumberToWords::convert($lira).' türk lirası';

        if ($kurus > 0) {
            $words .= ', '.TurkishNumberToWords::convert($kurus).' kuruş';
        }

        return TurkishNumberToWords::titleCase($words);
    }

    protected static function newFactory(): ReceiptBookEntryFactory
    {
        return ReceiptBookEntryFactory::new();
    }
}
