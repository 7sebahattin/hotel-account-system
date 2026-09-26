<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Services;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Personnel\Models\PersonnelTerminal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TerminalService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data, int $actorId): array
    {
        $secret = bin2hex(random_bytes(32));
        $terminal = DB::transaction(function () use ($data, $actorId, $secret): PersonnelTerminal {
            $terminal = PersonnelTerminal::query()->create([
                'name' => $data['name'], 'platform' => $data['platform'], 'device_uuid' => $data['device_uuid'],
                'token_hash' => hash('sha256', $secret), 'token_prefix' => substr($secret, 0, 12),
                'created_by' => $actorId,
            ]);
            $this->audit->log(AuditEvent::Created, $terminal, newValues: ['name' => $terminal->name, 'platform' => $terminal->platform, 'device_uuid' => $terminal->device_uuid]);

            return $terminal;
        });

        return [$terminal, $terminal->id.'.'.$secret];
    }

    public function authenticate(?string $authorization): ?PersonnelTerminal
    {
        if (! is_string($authorization) || ! preg_match('/^Bearer ([1-9][0-9]*)\.([0-9a-f]{64})$/D', $authorization, $match)) {
            return null;
        }
        $terminal = PersonnelTerminal::query()->find((int) $match[1]);
        if ($terminal === null || $terminal->revoked_at !== null || ! hash_equals($terminal->token_hash, hash('sha256', $match[2]))) {
            return null;
        }

        return $terminal;
    }

    public function revoke(PersonnelTerminal $terminal, string $reason = ''): void
    {
        DB::transaction(function () use ($terminal, $reason): void {
            $terminal = PersonnelTerminal::query()->lockForUpdate()->findOrFail($terminal->id);
            if ($terminal->revoked_at !== null) {
                throw ValidationException::withMessages(['terminal' => 'Terminal zaten iptal edilmiş.']);
            }
            $terminal->update(['revoked_at' => now('UTC')]);
            $this->audit->log(AuditEvent::Cancelled, $terminal, newValues: ['revoked' => true, 'reason' => $reason]);
        });
    }
}
