<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Services;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Personnel\Models\PersonnelAttendanceCorrection;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelShiftAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AttendanceService
{
    private const TIMEZONE = 'Europe/Istanbul';

    public function __construct(private readonly AuditLogger $audit) {}

    public function preload(array $employeeIds, string $from, string $to): array
    {
        if ($employeeIds === []) {
            return ['assignments' => [], 'events' => [], 'corrections' => []];
        }
        $windowStart = Carbon::parse($from.' 00:00:00', self::TIMEZONE)->subDay()->utc();
        $windowEnd = Carbon::parse($to.' 00:00:00', self::TIMEZONE)->addDays(2)->utc();
        $assignments = PersonnelShiftAssignment::query()->whereIn('employee_id', $employeeIds)->whereDate('effective_from', '<=', $to)->with('version')->orderBy('effective_from')->get()->groupBy('employee_id');
        $events = DB::table('personnel_scan_events')->whereIn('employee_id', $employeeIds)->where('result', 'accepted')->whereBetween('occurred_at', [$windowStart, $windowEnd])->orderBy('occurred_at')->orderBy('id')->get()->groupBy('employee_id');
        $corrections = PersonnelAttendanceCorrection::query()->whereIn('employee_id', $employeeIds)->whereBetween('work_date', [$from, $to])->orderBy('revision')->get()->groupBy('employee_id');

        return ['assignments' => $assignments, 'events' => $events, 'corrections' => $corrections];
    }

    public function correct(PersonnelEmployee $employee, array $data, int $actorId): PersonnelAttendanceCorrection
    {
        return DB::transaction(function () use ($employee, $data, $actorId): PersonnelAttendanceCorrection {
            $employee = PersonnelEmployee::query()->lockForUpdate()->findOrFail($employee->id);
            $date = $data['work_date'];
            if ($date > today(self::TIMEZONE)->toDateString() || $date < $employee->start_date->toDateString() || ($employee->end_date !== null && $date > $employee->end_date->toDateString())) {
                throw ValidationException::withMessages(['work_date' => 'Tarih istihdam aralığı dışında veya gelecekte.']);
            }
            $prior = PersonnelAttendanceCorrection::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->orderByDesc('revision')->first();
            $revision = $prior === null ? 0 : $prior->revision;
            if ($revision !== (int) $data['expected_revision']) {
                throw new ConflictHttpException('Puantaj başka bir işlemle değişti.');
            }
            $sessions = [];
            foreach ($data['sessions'] as $session) {
                $in = Carbon::parse($session['in_at'])->utc();
                $out = Carbon::parse($session['out_at'])->utc();
                if ($out <= $in || $out > now('UTC') || $in->diffInSeconds($out) > 86400 || $this->workDate($employee->id, $in) !== $date) {
                    throw ValidationException::withMessages(['sessions' => 'Oturum tarihi veya süresi geçersiz.']);
                }
                $sessions[] = ['in_at' => $in->toIso8601ZuluString(), 'out_at' => $out->toIso8601ZuluString()];
            }
            usort($sessions, static fn (array $a, array $b): int => strcmp($a['in_at'], $b['in_at']));
            foreach ($sessions as $index => $session) {
                if ($index > 0 && $sessions[$index - 1]['out_at'] > $session['in_at']) {
                    throw ValidationException::withMessages(['sessions' => 'Oturumlar çakışamaz.']);
                }
            }
            foreach ([Carbon::parse($date)->subDay()->toDateString(), Carbon::parse($date)->addDay()->toDateString()] as $neighborDate) {
                $neighbor = $this->day($employee, $neighborDate);
                if ($neighbor['unresolved'] && $sessions !== [] && $neighbor['open_in_at'] < $sessions[count($sessions) - 1]['out_at']) {
                    throw ValidationException::withMessages(['sessions' => 'Önce komşu iş gününün açık oturumu düzeltilmelidir.']);
                }
                foreach ($neighbor['sessions'] as $other) {
                    foreach ($sessions as $session) {
                        if ($session['in_at'] < $other['out_at'] && $session['out_at'] > $other['in_at']) {
                            throw ValidationException::withMessages(['sessions' => 'Komşu iş gününün oturumu ile çakışma var.']);
                        }
                    }
                }
            }

            $correction = PersonnelAttendanceCorrection::query()->create([
                'employee_id' => $employee->id, 'work_date' => $date, 'revision' => $revision + 1,
                'supersedes_id' => $prior?->id, 'sessions' => $sessions, 'reason' => $data['reason'],
                'scan_watermark_id' => (int) (DB::table('personnel_scan_events')->where('employee_id', $employee->id)->where('result', 'accepted')->max('id') ?? 0),
                'created_by' => $actorId,
            ]);
            $this->audit->log(AuditEvent::Created, $correction, newValues: ['work_date' => $date, 'revision' => $correction->revision, 'reason' => $data['reason']]);

            return $correction;
        });
    }

    public function workDate(int $employeeId, Carbon $instant, ?array $context = null): string
    {
        $local = $instant->copy()->timezone(self::TIMEZONE);
        foreach ([$local->copy()->subDay(), $local] as $candidate) {
            $date = $candidate->toDateString();
            $assignment = $this->assignment($employeeId, $date, $context);
            if ($assignment === null) {
                continue;
            }
            $version = $assignment->version;
            if (! in_array($candidate->dayOfWeekIso, $version->workdays, true)) {
                continue;
            }
            $start = Carbon::parse($date.' '.$version->start_time, self::TIMEZONE);
            $end = Carbon::parse($date.' '.$version->end_time, self::TIMEZONE)->addDays($version->ends_next_day ? 1 : 0);
            if ($local >= $start && $local <= $end) {
                return $date;
            }
        }

        return $local->toDateString();
    }

    /** @return array<string, mixed> */
    public function day(PersonnelEmployee $employee, string $date, ?array $context = null): array
    {
        $assignment = $this->assignment($employee->id, $date, $context);
        $version = $assignment?->version;
        $startUtc = Carbon::parse($date.' 00:00:00', self::TIMEZONE)->subDay()->utc();
        $endUtc = Carbon::parse($date.' 00:00:00', self::TIMEZONE)->addDays(2)->utc();
        $events = $context === null
            ? DB::table('personnel_scan_events')->where('employee_id', $employee->id)->where('result', 'accepted')->whereBetween('occurred_at', [$startUtc, $endUtc])->orderBy('occurred_at')->orderBy('id')->get()
            : ($context['events'][$employee->id] ?? collect())->filter(static fn ($event): bool => $event->occurred_at >= $startUtc->toDateTimeString() && $event->occurred_at <= $endUtc->toDateTimeString());
        $sessions = [];
        $open = null;
        $scanIds = [];
        foreach ($events as $event) {
            $instant = Carbon::parse($event->occurred_at, 'UTC');
            if ($event->action === 'in') {
                if ($this->workDate($employee->id, $instant, $context) === $date) {
                    $open = $instant;
                    $scanIds[] = $event->id;
                }
            } elseif ($event->action === 'out' && $open !== null) {
                $sessions[] = ['in_at' => $open->toIso8601ZuluString(), 'out_at' => $instant->toIso8601ZuluString()];
                $scanIds[] = $event->id;
                $open = null;
            }
        }
        $correction = $context === null
            ? PersonnelAttendanceCorrection::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->orderByDesc('revision')->first()
            : ($context['corrections'][$employee->id] ?? collect())->last(static fn (PersonnelAttendanceCorrection $item): bool => $item->work_date->toDateString() === $date);
        if ($correction !== null) {
            $sessions = $correction->sessions;
            $open = null;
        }
        $seconds = 0;
        foreach ($sessions as $session) {
            $seconds += (int) Carbon::parse($session['in_at'])->diffInSeconds(Carbon::parse($session['out_at']));
        }
        $gross = intdiv($seconds, 60);
        $break = min($gross, $version === null ? 0 : $version->break_minutes);
        $net = $gross - $break;
        $scheduled = 0;
        $late = 0;
        $early = 0;
        $planned = $version !== null && in_array(Carbon::parse($date)->dayOfWeekIso, $version->workdays, true);
        if ($planned) {
            $shiftStart = Carbon::parse($date.' '.$version->start_time, self::TIMEZONE)->utc();
            $shiftEnd = Carbon::parse($date.' '.$version->end_time, self::TIMEZONE)->addDays($version->ends_next_day ? 1 : 0)->utc();
            $scheduled = max(0, (int) $shiftStart->diffInMinutes($shiftEnd) - $version->break_minutes);
            if ($sessions !== [] || $open !== null) {
                $first = $sessions !== [] ? Carbon::parse($sessions[0]['in_at']) : $open;
                $late = max(0, (int) $shiftStart->diffInMinutes($first, false) - $version->late_grace_minutes);
            }
            if ($sessions !== []) {
                $last = $sessions[count($sessions) - 1];
                $early = max(0, (int) Carbon::parse($last['out_at'])->diffInMinutes($shiftEnd, false) - $version->early_grace_minutes);
            }
        }
        $today = today(self::TIMEZONE)->toDateString();
        $periodFinalized = $date < $today || ($date === $today && ($planned
            ? now('UTC') >= $shiftEnd
            : now(self::TIMEZONE) >= Carbon::parse($date.' 23:59:59', self::TIMEZONE)));
        $status = $open !== null ? 'missing_checkout' : ($sessions === [] ? ($planned ? 'absent' : 'rest') : ($planned ? 'present' : 'unscheduled'));
        if ($status === 'absent' && ! $periodFinalized) {
            $status = 'pending';
        }
        $correctedAt = $correction === null ? null : Carbon::parse($correction->getRawOriginal('created_at'), config('app.timezone'))->utc()->toDateTimeString();
        $postCorrectionScan = $correction !== null && $events->contains(static fn ($event): bool => in_array($event->id, $scanIds, true) && (
            $correction->scan_watermark_id !== null
                ? $event->id > $correction->scan_watermark_id
                : $event->occurred_at > $correctedAt
        ));
        $unresolved = $open !== null || $postCorrectionScan;
        $missingMinutes = $planned && $periodFinalized && ! $unresolved ? max(0, $scheduled - min($net, $scheduled)) : 0;
        $labels = ['missing_checkout' => 'Eksik çıkış', 'absent' => 'Devamsız', 'rest' => 'Dinlenme', 'present' => 'Çalıştı', 'unscheduled' => 'Plan dışı', 'pending' => 'Vardiya sürüyor'];
        $display = array_map(static function (array $session): array {
            return $session + [
                'in_label' => Carbon::parse($session['in_at'])->timezone(self::TIMEZONE)->format('d.m.Y H:i'),
                'out_label' => Carbon::parse($session['out_at'])->timezone(self::TIMEZONE)->format('d.m.Y H:i'),
            ];
        }, $sessions);

        return [
            'employee_id' => $employee->id, 'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name, 'work_date' => $date,
            'shift_label' => $version === null ? 'Atanmamış' : $version->version.' · '.$version->start_time.'–'.$version->end_time,
            'shift_assignment_id' => $assignment?->id, 'shift_version_id' => $version?->id,
            'sessions' => $display, 'first_in_label' => $display[0]['in_label'] ?? $open?->timezone(self::TIMEZONE)->format('d.m.Y H:i'),
            'last_out_label' => $display[count($display) - 1]['out_label'] ?? null,
            'worked_minutes' => $gross, 'break_minutes' => $break, 'net_minutes' => $net,
            'scheduled_net_minutes' => $scheduled, 'missing_minutes' => $missingMinutes,
            'late_minutes' => $late, 'early_minutes' => $early,
            'overtime_minutes' => max(0, $net - $scheduled), 'status' => $status, 'status_label' => $labels[$status],
            'revision' => $correction === null ? 0 : $correction->revision, 'has_correction' => $correction !== null,
            'scan_ids' => $scanIds, 'unresolved' => $unresolved, 'period_finalized' => $periodFinalized,
            'open_in_at' => $open?->toIso8601ZuluString(),
            'post_correction_scan' => $postCorrectionScan,
        ];
    }

    private function assignment(int $employeeId, string $date, ?array $context): ?PersonnelShiftAssignment
    {
        if ($context === null) {
            return PersonnelShiftAssignment::query()->where('employee_id', $employeeId)->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->with('version')->first();
        }

        return ($context['assignments'][$employeeId] ?? collect())->last(static fn (PersonnelShiftAssignment $assignment): bool => $assignment->effective_from->toDateString() <= $date);
    }
}
