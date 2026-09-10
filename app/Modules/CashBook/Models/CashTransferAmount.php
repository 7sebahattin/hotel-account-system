<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Models;

use App\Modules\CashBook\Exceptions\CashRecordIsImmutable;
use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bir devirde teslim edilen tek para birimlik tutar.
 *
 * Değişmezdir: devir kabul edildiğinde bu tutar YENİ vardiyanın açılış
 * bakiyesine zaten kopyalanmıştır (kullanıcı talimatı §1). Sonradan
 * değiştirilebilseydi, imzalanmış devir kaydı ile gerçek açılış bakiyesi
 * sessizce birbirinden kopardı.
 *
 * @property int $id
 * @property int $cash_transfer_id
 * @property Currency $currency
 * @property string $amount
 * @property Carbon $created_at
 * @property-read CashTransfer|null $transfer
 */
class CashTransferAmount extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cash_transfer_amounts';

    protected $fillable = ['cash_transfer_id', 'currency', 'amount'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw CashRecordIsImmutable::cannotUpdate('Devir tutarı');
        });

        static::deleting(function (): void {
            throw CashRecordIsImmutable::cannotDelete('Devir tutarı');
        });
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw CashRecordIsImmutable::cannotUpdate('Devir tutarı');
    }

    public function delete(): bool
    {
        throw CashRecordIsImmutable::cannotDelete('Devir tutarı');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'cash_transfer_id' => 'integer',
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(CashTransfer::class, 'cash_transfer_id');
    }

    public function amountMoney(): Money
    {
        return Money::fromDecimal($this->amount);
    }
}
