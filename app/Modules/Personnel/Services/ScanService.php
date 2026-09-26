<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Services;

use App\Modules\Personnel\Models\PersonnelCard;
use App\Modules\Personnel\Models\PersonnelCardAssignment;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelScanEvent;
use App\Modules\Personnel\Models\PersonnelShiftAssignment;
use App\Modules\Personnel\Models\PersonnelTerminal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ScanService
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function record(array $input, ?PersonnelTerminal $terminal, ?int $actorId): array
    {
        $uid = CardService::canonicalUid($input['card_uid']);
        $action = $input['action'] ?? 'auto';
        $source = $terminal === null ? 'kiosk' : 'android';
        $actorKey = $terminal === null ? 'user:'.$actorId : 'terminal:'.$terminal->id;
        $hash = hash('sha256', json_encode([$uid, $action, $input['technology'] ?? null, $actorKey], JSON_THROW_ON_ERROR));

        $lock = hash('sha256', 'personnel_scan_'.$input['request_id']);
        $obtained = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lock]);
        if ((int) $obtained->acquired !== 1) {
            throw new ServiceUnavailableHttpException(2, 'İstek işleniyor; aynı kimlikle yeniden deneyin.');
        }
        try {
            return DB::transaction(function () use ($input, $terminal, $actorId, $uid, $action, $source, $hash): array {
                if ($terminal !== null) {
                    $lockedTerminal = PersonnelTerminal::query()->whereKey($terminal->id)->lockForUpdate()->first();
                    if ($lockedTerminal === null || $lockedTerminal->revoked_at !== null) {
                        abort(401, 'Yetkisiz terminal.');
                    }
                    $lockedTerminal->update(['last_seen_at' => now('UTC')]);
                }
                $existing = PersonnelScanEvent::query()->where('request_id', $input['request_id'])->first();
                if ($existing !== null) {
                    return $this->replay($existing, $hash, $terminal, $actorId);
                }

                $card = PersonnelCard::query()->where('uid', $uid)->lockForUpdate()->first();
                $assignment = $card === null ? null : PersonnelCardAssignment::query()->where('card_id', $card->id)->whereNull('released_at')->lockForUpdate()->first();
                $employee = $assignment === null ? null : PersonnelEmployee::query()->lockForUpdate()->find($assignment->employee_id);
                $now = now('UTC');
                $result = $card === null ? 'unknown_card' : (! $card->is_active ? 'inactive_card' : ($employee === null ? 'unknown_card' : (! $this->isEmployed($employee, $now) ? 'inactive_employee' : 'accepted')));
                $resolved = null;
                $duplicateId = null;

                if ($result === 'accepted') {
                    $last = PersonnelScanEvent::query()->where('result', 'accepted')
                        ->where(function ($query) use ($card, $employee): void {
                            $query->where('card_id', $card->id)->orWhere('employee_id', $employee->id);
                        })->orderByDesc('occurred_at')->orderByDesc('id')->first();
                    $lastAt = $last === null ? null : Carbon::parse($last->getRawOriginal('occurred_at'), 'UTC');
                    if ($lastAt !== null && $now->getTimestamp() - $lastAt->getTimestamp() < (int) config('personnel.duplicate_window_seconds', 10) && $lastAt <= $now) {
                        $result = 'duplicate';
                        $duplicateId = $last->id;
                    } else {
                        $open = $this->openSession($employee, $now);
                        if ($open !== null && $now->getTimestamp() - Carbon::parse($open->getRawOriginal('occurred_at'), 'UTC')->getTimestamp() > 86400) {
                            $result = 'invalid_transition';
                        } else {
                            $resolved = $action === 'auto' ? ($open === null ? 'in' : 'out') : $action;
                            if (($resolved === 'in' && $open !== null) || ($resolved === 'out' && $open === null)) {
                                $result = 'invalid_transition';
                            }
                        }
                    }
                }

                $event = PersonnelScanEvent::query()->create([
                    'request_id' => $input['request_id'], 'payload_hash' => $hash,
                    'card_id' => $card?->id, 'card_assignment_id' => $assignment?->id,
                    'employee_id' => $employee?->id, 'terminal_id' => $terminal?->id,
                    'actor_user_id' => $actorId, 'source' => $source,
                    'platform' => $terminal === null ? 'kiosk' : $terminal->platform, 'device_uuid' => $terminal?->device_uuid,
                    'technology' => $input['technology'] ?? null, 'occurred_at' => $now,
                    'requested_action' => $action, 'action' => $result === 'accepted' ? $resolved : null,
                    'result' => $result, 'duplicate_of_id' => $duplicateId,
                ]);

                return $this->response($event, $employee?->full_name, false);
            }, 3);
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    }

    private function replay(PersonnelScanEvent $event, string $hash, ?PersonnelTerminal $terminal, ?int $actorId): array
    {
        if (! hash_equals($event->payload_hash, $hash) || $event->terminal_id !== $terminal?->id || $event->actor_user_id !== $actorId) {
            throw new ConflictHttpException('İstek kimliği başka bir işlemde kullanılmış.');
        }
        $name = $event->employee_id === null ? null : PersonnelEmployee::query()->find($event->employee_id)?->full_name;

        return $this->response($event, $name, true);
    }

    private function isEmployed(PersonnelEmployee $employee, Carbon $now): bool
    {
        $date = $now->copy()->timezone('Europe/Istanbul')->toDateString();

        return $employee->is_active && $employee->start_date->toDateString() <= $date
            && ($employee->end_date === null || $employee->end_date->toDateString() >= $date);
    }

    private function openSession(PersonnelEmployee $employee, Carbon $now): ?PersonnelScanEvent
    {
        $last = PersonnelScanEvent::query()->where('employee_id', $employee->id)->where('result', 'accepted')->orderByDesc('occurred_at')->orderByDesc('id')->first();
        if ($last === null || $last->action !== 'in') {
            return null;
        }
        $lastAt = Carbon::parse($last->getRawOriginal('occurred_at'), 'UTC');
        $workDate = $this->attendance->workDate($employee->id, $lastAt);
        $correctionLocal = $lastAt->copy()->timezone(config('app.timezone'))->toDateTimeString();
        $correction = DB::table('personnel_attendance_corrections')->where('employee_id', $employee->id)->where('work_date', $workDate)->where('created_at', '>=', $correctionLocal)->orderByDesc('created_at')->orderByDesc('id')->first();
        if ($correction !== null) {
            return null;
        }

        return $last;
    }

    private function response(PersonnelScanEvent $event, ?string $name, bool $replayed): array
    {
        $messages = [
            'accepted' => $event->action === 'in' ? 'Giriş kaydedildi.' : 'Çıkış kaydedildi.',
            'duplicate' => 'Hızlı tekrar okutma atlandı.',
            'unknown_card' => 'Kart tanınmıyor.',
            'inactive_card' => 'Kart etkin değil.',
            'inactive_employee' => 'Çalışan etkin değil.',
            'invalid_transition' => 'Giriş/çıkış sırası uygun değil; puantajı kontrol edin.',
        ];

        return [
            'event_id' => $event->id, 'request_id' => $event->request_id,
            'result' => $event->result, 'action' => $event->action,
            'employee_name' => $name, 'shift_label' => $this->shiftLabel($event),
            'occurred_at' => Carbon::parse($event->getRawOriginal('occurred_at'), 'UTC')->toIso8601ZuluString(),
            'message' => $messages[$event->result], 'replayed' => $replayed,
        ];
    }

    private function shiftLabel(PersonnelScanEvent $event): ?string
    {
        if ($event->employee_id === null) {
            return null;
        }
        $workDate = $this->attendance->workDate($event->employee_id, Carbon::parse($event->getRawOriginal('occurred_at'), 'UTC'));
        $assignment = PersonnelShiftAssignment::query()->where('employee_id', $event->employee_id)
            ->whereDate('effective_from', '<=', $workDate)->orderByDesc('effective_from')->with('version.shift')->first();
        if ($assignment === null) {
            return null;
        }
        $version = $assignment->version;

        return $version->shift->name.' (V'.$version->version.', '.substr($version->start_time, 0, 5).'–'.substr($version->end_time, 0, 5).')';
    }
}
