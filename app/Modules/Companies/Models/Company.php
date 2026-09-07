<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use App\Models\User;
use App\Modules\Companies\Database\Factories\CompanyFactory;
use App\Modules\Core\Concerns\Auditable;
use App\Modules\Core\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Firma (cari kart).
 *
 * @property int $id
 * @property string $name
 * @property string|null $tax_office
 * @property string|null $tax_number
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $city
 * @property string|null $contact_person
 * @property string|null $notes
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasCreator, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'tax_office',
        'tax_number',
        'phone',
        'email',
        'address',
        'city',
        'contact_person',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * updated_by denetim kaydına girmez: "kim değiştirdi" bilgisi zaten
     * audit_logs.user_id alanında tutulur, tekrarı gürültüdür.
     *
     * @return array<int, string>
     */
    protected function auditIgnored(): array
    {
        return ['created_at', 'updated_at', 'updated_by'];
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
