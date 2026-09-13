<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Models;

use App\Models\User;
use App\Modules\AuditLog\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Denetim kaydı. CLAUDE.md §5.6: değiştirilemez ve silinemez.
 *
 * updated_at kolonu yoktur; update() ve delete() çağrıları exception fırlatır.
 * Yazma yalnızca AuditLogger servisi üzerinden yapılır.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property AuditEvent $event
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $url
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Denetim kaydı güncellenemez. */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('Denetim kaydı değiştirilemez.');
    }

    /** Denetim kaydı silinemez. */
    public function delete(): bool
    {
        throw new RuntimeException('Denetim kaydı silinemez.');
    }

    /** Görüntülenen kaydın kısa adı. */
    public function subjectLabel(): string
    {
        if ($this->auditable_type === null) {
            return '—';
        }

        return $this->auditable_type.' #'.$this->auditable_id;
    }

    /**
     * Kayıt tipinin Türkçe adı — morph map anahtarından (CLAUDE.md §5.7)
     * türetilir. Tanınmayan bir anahtar ham hâliyle gösterilir: enum veri
     * yolunu kısıtlamaz, yalnızca ETİKETLER (LedgerEntryType ile AYNI karar).
     */
    public function subjectTypeLabel(): string
    {
        if ($this->auditable_type === null) {
            return '—';
        }

        return [
            'stay' => 'Konaklama',
            'payment' => 'Tahsilat',
            'company' => 'Cari hesap',
            'user' => 'Kullanıcı',
            'role' => 'Rol',
            'ledger_entry' => 'Cari hareket',
            'cash_session' => 'Vardiya',
            'cash_movement' => 'Kasa hareketi',
            'cash_transfer' => 'Kasa devri',
            'cash_movement_category' => 'Kasa hareket türü',
            'room' => 'Oda',
            'receipt_book_entry' => 'Makbuz defteri kaydı',
        ][$this->auditable_type] ?? $this->auditable_type;
    }

    /**
     * Eski/yeni değerlerin alan alan karşılaştırması — detay ekranı içindir.
     *
     * `old_values`/`new_values` yalnızca DEĞİŞEN alanları taşır (CLAUDE.md §8);
     * bu yüzden iki tarafın anahtar birleşimi gezilir. Sıra `new_values`
     * önceliklidir: servis alanları anlamlı bir sırayla yazar.
     *
     * @return array<int, array{field: string, old: ?string, new: ?string}>
     */
    public function changes(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $fields = array_keys($new + $old);
        $changes = [];

        foreach ($fields as $field) {
            $changes[] = [
                'field' => (string) $field,
                'old' => self::displayValue($old[$field] ?? null),
                'new' => self::displayValue($new[$field] ?? null),
            ];
        }

        return $changes;
    }

    /** Denetim değeri okunabilir metne çevrilir; yokluk null kalır (ekranda "—"). */
    private static function displayValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'evet' : 'hayır';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
