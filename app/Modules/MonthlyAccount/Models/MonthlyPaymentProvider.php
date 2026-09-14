<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Models;

use App\Modules\Core\Concerns\Auditable;
use App\Modules\Core\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Model;

/**
 * Aylık Hesap ödeme satırlarında seçilebilen banka / ödeme kuruluşu tanımı.
 * Hesap numarası veya banka entegrasyonu taşımaz; yalnız ortak ad listesidir.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class MonthlyPaymentProvider extends Model
{
    use Auditable, HasCreator;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }
}
