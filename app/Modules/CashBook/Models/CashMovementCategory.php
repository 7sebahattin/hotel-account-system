<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Models;

use App\Models\User;
use App\Modules\CashBook\Database\Factories\CashMovementCategoryFactory;
use App\Modules\CashBook\Enums\CashMovementType;
use App\Modules\Core\Concerns\Auditable;
use App\Modules\Core\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Admin tarafından yönetilen kasa hareketi TÜRÜ (v2.1.1-B §3).
 *
 * Bir kategori yalnızca bir AD/SEBEPtir — sabit sistem davranışına
 * (`system_type`) eşlenir. Admin yeni bir aritmetik/yön İCAT EDEMEZ; bu
 * kısıt veritabanı CHECK'i ile de zorlanır (yalnızca `CashMovementType::
 * selectable()` değerleri).
 *
 * Silme YOKTUR — geçmişi olan bir kategori asla hard-delete edilmez,
 * yalnızca `is_active = false` yapılır (companies ile aynı ilke).
 *
 * @property int $id
 * @property string $name
 * @property CashMovementType $system_type
 * @property bool $is_active
 * @property bool $requires_recipient
 * @property int $sort_order
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class CashMovementCategory extends Model
{
    /** @use HasFactory<CashMovementCategoryFactory> */
    use Auditable, HasCreator, HasFactory;

    protected $table = 'cash_movement_categories';

    protected $fillable = ['name', 'system_type', 'is_active', 'requires_recipient', 'sort_order'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'system_type' => CashMovementType::class,
            'is_active' => 'boolean',
            'requires_recipient' => 'boolean',
            'sort_order' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /** Bu kategoriyle kaydedilmiş herhangi bir kasa hareketi var mı — silme/pasifleştirme kararı için. */
    public function hasHistory(): bool
    {
        return CashMovement::query()->where('category_id', $this->getKey())->exists();
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'category_id');
    }

    protected static function newFactory(): CashMovementCategoryFactory
    {
        return CashMovementCategoryFactory::new();
    }
}
