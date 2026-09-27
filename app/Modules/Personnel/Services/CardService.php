<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Services;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Personnel\Models\PersonnelCard;
use App\Modules\Personnel\Models\PersonnelCardAssignment;
use App\Modules\Personnel\Models\PersonnelEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CardService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function canonicalUid(string $uid): string
    {
        $uid = strtoupper(trim($uid));
        if (! preg_match('/^(?:[0-9A-F]{2}){4,20}$/D', $uid)) {
            throw ValidationException::withMessages(['card_uid' => 'Kart UID değeri 4–20 bayt hexadecimal olmalıdır.']);
        }

        return $uid;
    }

    public function assign(PersonnelEmployee $employee, array $data, int $actorId): PersonnelCardAssignment
    {
        $uid = self::canonicalUid($data['uid']);

        return DB::transaction(function () use ($employee, $data, $actorId, $uid): PersonnelCardAssignment {
            $card = PersonnelCard::query()->firstOrCreate(['uid' => $uid], [
                'label' => $data['label'] ?? null, 'technology' => $data['technology'] ?? null,
                'is_active' => true, 'created_by' => $actorId, 'updated_by' => $actorId,
            ]);
            $card = PersonnelCard::query()->lockForUpdate()->findOrFail($card->id);
            $employee = PersonnelEmployee::query()->lockForUpdate()->findOrFail($employee->id);
            if (! $card->is_active || ! $employee->is_active || PersonnelCardAssignment::query()->where('card_id', $card->id)->whereNull('released_at')->exists()) {
                throw ValidationException::withMessages(['uid' => 'Kart veya çalışan etkin değil ya da kart zaten atanmış.']);
            }

            $assignment = PersonnelCardAssignment::query()->create([
                'card_id' => $card->id, 'employee_id' => $employee->id,
                'assigned_at' => now('UTC'), 'assigned_by' => $actorId,
            ]);
            $this->audit->log(AuditEvent::Created, $card, newValues: ['employee_id' => $employee->id, 'masked_uid' => $card->masked_uid]);

            return $assignment;
        });
    }

    public function release(PersonnelCard $card, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($card, $reason, $actorId): void {
            $card = PersonnelCard::query()->lockForUpdate()->findOrFail($card->id);
            $assignment = PersonnelCardAssignment::query()->where('card_id', $card->id)->whereNull('released_at')->lockForUpdate()->first();
            if ($assignment === null) {
                throw ValidationException::withMessages(['card' => 'Kartta etkin atama bulunamadı.']);
            }
            PersonnelEmployee::query()->whereKey($assignment->employee_id)->lockForUpdate()->firstOrFail();
            $assignment->update(['released_at' => now('UTC'), 'released_by' => $actorId]);
            $this->audit->log(AuditEvent::Updated, $card, newValues: ['employee_id' => $assignment->employee_id, 'masked_uid' => $card->masked_uid, 'released' => true, 'reason' => $reason]);
        });
    }

    public function setActive(PersonnelCard $card, bool $active, int $actorId): void
    {
        DB::transaction(function () use ($card, $active, $actorId): void {
            $card = PersonnelCard::query()->lockForUpdate()->findOrFail($card->id);
            $card->update(['is_active' => $active, 'updated_by' => $actorId]);
            $this->audit->log(AuditEvent::Updated, $card, newValues: ['is_active' => $active, 'masked_uid' => $card->masked_uid]);
        });
    }
}
