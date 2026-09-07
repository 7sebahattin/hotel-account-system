<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Models;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Core\Concerns\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * spatie Role'ünün proje sürümü: is_system bayrağı ve denetim kaydı ekler.
 *
 * config/permission.php içinde models.role olarak tanımlıdır.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Ada göre rolü döndürür.
     *
     * spatie'nin findByName() metodu Contracts\Role döndürür; bu yüzden
     * is_system gibi projeye özel alanlara erişilemez. Bu yardımcı proje
     * modelinin kendi tipini verir.
     */
    public static function byName(string $name, string $guard = 'web'): self
    {
        return static::query()
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->firstOrFail();
    }

    /** Sistem rolü silinemez ve adı değiştirilemez (CLAUDE.md §7.5). */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    public function label(): string
    {
        return RoleName::tryFrom($this->name)?->label() ?? $this->name;
    }

    public function description(): ?string
    {
        return RoleName::tryFrom($this->name)?->description();
    }
}
