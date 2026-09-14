<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Models;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\MonthlyAccount\Concerns\HasImmutableHistory;
use App\Modules\MonthlyAccount\Enums\ConversionMode;
use App\Modules\MonthlyAccount\Enums\MonthlyPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $revision_id
 * @property MonthlyPaymentMethod $method
 * @property Currency $currency
 * @property string|null $bank_name
 * @property string $amount
 * @property ConversionMode|null $conversion_mode
 * @property string|null $exchange_rate_to_try
 * @property string $try_equivalent
 * @property string|null $note
 */
class MonthlyAccountItem extends Model
{
    use HasImmutableHistory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'revision_id', 'method', 'currency', 'bank_name', 'amount',
        'conversion_mode', 'exchange_rate_to_try', 'try_equivalent', 'note',
    ];

    protected function casts(): array
    {
        return [
            'revision_id' => 'integer',
            'method' => MonthlyPaymentMethod::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'conversion_mode' => ConversionMode::class,
            'exchange_rate_to_try' => 'decimal:4',
            'try_equivalent' => 'decimal:2',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(MonthlyAccountRevision::class, 'revision_id');
    }

    public function amountMoney(): Money
    {
        return Money::fromDecimal($this->amount);
    }

    public function tryEquivalentMoney(): Money
    {
        return Money::fromDecimal($this->try_equivalent);
    }
}
