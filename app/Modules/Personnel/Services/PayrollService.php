<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Services;

use App\Modules\AuditLog\Enums\AuditEvent;
use App\Modules\AuditLog\Services\AuditLogger;
use App\Modules\Core\Support\Money;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelPayrollEntry;
use App\Modules\Personnel\Models\PersonnelWageAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class PayrollService
{
    public function __construct(private readonly AttendanceService $attendance, private readonly AuditLogger $audit) {}

    /** @return array<string, mixed> */
    public function preview(PersonnelEmployee $employee, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01');
        $days = $start->daysInMonth;
        $earnings = [];
        $issues = [];
        $snapshotDays = [];
        $monthlySegments = [];
        $context = $this->attendance->preload([$employee->id], $start->toDateString(), $start->copy()->endOfMonth()->toDateString());
        $wages = PersonnelWageAssignment::query()->where('employee_id', $employee->id)->whereDate('effective_from', '<=', $start->copy()->endOfMonth()->toDateString())->orderBy('effective_from')->get();
        for ($day = 1; $day <= $days; $day++) {
            $date = $start->copy()->day($day)->toDateString();
            if ($date < $employee->start_date->toDateString() || ($employee->end_date !== null && $date > $employee->end_date->toDateString())) {
                continue;
            }
            if ($date > today('Europe/Istanbul')->toDateString()) {
                $issues[] = $date.' gelecek gün';

                continue;
            }
            $wage = $wages->last(static fn (PersonnelWageAssignment $item): bool => $item->effective_from->toDateString() <= $date);
            $attendance = $this->attendance->day($employee, $date, $context);
            if ($wage === null || $attendance['shift_assignment_id'] === null || $attendance['unresolved'] || ! $attendance['period_finalized']) {
                $issues[] = $date.' eksik ücret/vardiya veya açık okutma';

                continue;
            }
            $amount = Money::fromDecimal($wage->amount)->minorUnits;
            $normal = 0;
            $deduction = 0;
            $overtime = 0;
            $net = $attendance['net_minutes'];
            $scheduled = $attendance['scheduled_net_minutes'];
            $standard = $wage->standard_daily_minutes;
            $otMinutes = $wage->overtime_enabled ? max(0, $net - ($wage->wage_type === 'monthly' ? $scheduled : $standard)) : 0;
            if ($wage->wage_type === 'monthly') {
                $monthlySegments[$wage->id]['days'] = ($monthlySegments[$wage->id]['days'] ?? 0) + 1;
                $monthlySegments[$wage->id]['amount'] = $amount;
                $monthlySegments[$wage->id]['currency'] = $wage->currency;
                if ($scheduled > 0 && $attendance['status'] !== 'missing_checkout') {
                    $shortfall = max(0, $scheduled - min($net, $scheduled));
                    $deduction = min($this->roundRatio($amount, 1, $days), $this->roundRatio($amount, $shortfall, $days * $scheduled));
                }
                $monthlySegments[$wage->id]['deduction_minor'] = ($monthlySegments[$wage->id]['deduction_minor'] ?? 0) + $deduction;
                $denominator = $days * max(1, $scheduled > 0 ? $scheduled : $standard);
            } elseif ($wage->wage_type === 'daily') {
                $normal = $this->roundRatio($amount, min($net, $standard), $standard);
                $denominator = $standard;
            } else {
                $normal = $this->roundRatio($amount, $wage->overtime_enabled ? min($net, $standard) : $net, 60);
                $denominator = 60;
            }
            if ($otMinutes > 0) {
                $overtime = $this->roundRatio($amount, $otMinutes * $wage->overtime_multiplier_bp, $denominator * 10000);
            }
            $total = $normal - $deduction + $overtime;
            $earnings[$wage->currency] = ($earnings[$wage->currency] ?? 0) + $total;
            $snapshotDays[] = [
                'date' => $date, 'wage_assignment_id' => $wage->id, 'shift_assignment_id' => $attendance['shift_assignment_id'],
                'shift_version_id' => $attendance['shift_version_id'], 'correction_revision' => $attendance['revision'],
                'scan_ids' => $attendance['scan_ids'], 'net_minutes' => $net,
                'scheduled_net_minutes' => $scheduled, 'wage_type' => $wage->wage_type, 'rate' => $wage->amount,
                'currency' => $wage->currency, 'base_minor' => $normal, 'deduction_minor' => $deduction,
                'overtime_minor' => $overtime, 'total_minor' => $total,
            ];
        }
        foreach ($monthlySegments as $assignmentId => $segment) {
            $base = $this->roundRatio($segment['amount'], $segment['days'], $days);
            $monthlySegments[$assignmentId]['base_minor'] = $base;
            $earnings[$segment['currency']] = ($earnings[$segment['currency']] ?? 0) + $base;
            if ($segment['deduction_minor'] > $base) {
                $monthlySegments[$assignmentId]['rounding_cap_minor'] = $segment['deduction_minor'] - $base;
                $earnings[$segment['currency']] += $monthlySegments[$assignmentId]['rounding_cap_minor'];
            }
        }
        foreach ($earnings as $currency => $minor) {
            $earnings[$currency] = Money::fromMinorUnits($minor)->toDecimal();
        }
        $digest = hash('sha256', json_encode([$employee->id, $month, $snapshotDays, $monthlySegments, $issues], JSON_THROW_ON_ERROR));

        return ['month' => $month, 'earnings' => $earnings, 'issues' => $issues, 'days' => $snapshotDays, 'monthly_segments' => $monthlySegments, 'digest' => $digest, 'formula_version' => 1];
    }

    public function calculate(PersonnelEmployee $employee, string $month, string $digest, int $actorId, ?string $requestId = null): array
    {
        return $this->withRequestLock($requestId ?? $digest, function () use ($employee, $month, $digest, $actorId, $requestId): array {
            return DB::transaction(function () use ($employee, $month, $digest, $actorId, $requestId): array {
                $employee = PersonnelEmployee::query()->lockForUpdate()->findOrFail($employee->id);
                $preview = $this->preview($employee, $month);
                if (! hash_equals($preview['digest'], $digest)) {
                    throw new ConflictHttpException('Hesap girdileri değişti; önizlemeyi yenileyin.');
                }
                if ($preview['issues'] !== []) {
                    throw ValidationException::withMessages(['month' => 'Eksik günler düzeltilmeden hakediş kaydedilemez.']);
                }
                if ($requestId !== null) {
                    $previousRequest = PersonnelPayrollEntry::query()->where('source_key', 'like', 'earning:'.$requestId.':%')->first();
                    if ($previousRequest !== null && ($previousRequest->employee_id !== $employee->id || $previousRequest->created_by !== $actorId || ($previousRequest->calculation_snapshot['digest'] ?? null) !== $digest)) {
                        throw new ConflictHttpException('İstek kimliği başka bir hesap için kullanıldı.');
                    }
                }
                $entries = [];
                foreach ($preview['earnings'] as $currency => $decimal) {
                    $sourceKey = 'earning:'.($requestId ?? hash('sha256', $digest.$actorId)).':'.$currency;
                    $sameRequest = PersonnelPayrollEntry::query()->where('source_key', $sourceKey)->first();
                    if ($sameRequest !== null) {
                        if ($sameRequest->employee_id !== $employee->id || $sameRequest->calculation_snapshot['digest'] !== $digest || $sameRequest->created_by !== $actorId) {
                            throw new ConflictHttpException('İstek kimliği başka bir hesap için kullanıldı.');
                        }
                        $entries[] = $sameRequest;

                        continue;
                    }
                    $active = PersonnelPayrollEntry::query()->where('employee_id', $employee->id)->where('period', $month)->where('currency', $currency)->where('type', 'earning')
                        ->whereNotIn('id', DB::table('personnel_payroll_entries')->whereNotNull('reverses_entry_id')->select('reverses_entry_id'))->exists();
                    if ($active) {
                        throw new ConflictHttpException('Bu ay için etkin hakediş zaten var; önce ters kayıt gerekir.');
                    }
                    if (Money::fromDecimal($decimal)->isZero()) {
                        continue;
                    }
                    $entries[] = PersonnelPayrollEntry::query()->create([
                        'employee_id' => $employee->id, 'entry_date' => $month.'-01', 'period' => $month,
                        'type' => 'earning', 'direction' => 'credit', 'amount' => $decimal, 'currency' => $currency,
                        'description' => $month.' hakedişi', 'source_key' => $sourceKey,
                        'calculation_snapshot' => $preview, 'created_by' => $actorId,
                    ]);
                    $this->audit->log(AuditEvent::Created, $entries[count($entries) - 1], newValues: ['type' => 'earning', 'period' => $month]);
                }

                return $entries;
            }, 3);
        });
    }

    public function addEntry(PersonnelEmployee $employee, array $data, int $actorId): PersonnelPayrollEntry
    {
        return $this->withRequestLock($data['request_id'], function () use ($employee, $data, $actorId): PersonnelPayrollEntry {
            return DB::transaction(function () use ($employee, $data, $actorId): PersonnelPayrollEntry {
                $employee = PersonnelEmployee::query()->lockForUpdate()->findOrFail($employee->id);
                $normalizedAmount = Money::fromDecimal($data['amount'])->toDecimal();
                $hash = hash('sha256', json_encode([$employee->id, $data['type'], $data['entry_date'], $normalizedAmount, $data['currency'], $data['payment_method'] ?? null, $data['description'], $actorId], JSON_THROW_ON_ERROR));
                $keyPrefix = 'manual:'.$data['request_id'].':';
                $key = $keyPrefix.$hash;
                $existing = PersonnelPayrollEntry::query()->where('source_key', 'like', $keyPrefix.'%')->first();
                if ($existing !== null) {
                    if ($existing->source_key !== $key) {
                        throw new ConflictHttpException('İstek kimliği başka bir ödeme için kullanıldı.');
                    }

                    return $existing;
                }
                if ($data['type'] === 'payment' && empty($data['payment_method'])) {
                    throw ValidationException::withMessages(['payment_method' => 'Ödeme yöntemi zorunludur.']);
                }
                $amount = Money::fromDecimal($normalizedAmount);
                if (! $amount->isPositive()) {
                    throw ValidationException::withMessages(['amount' => 'Tutar pozitif olmalıdır.']);
                }

                $entry = PersonnelPayrollEntry::query()->create([
                    'employee_id' => $employee->id, 'entry_date' => $data['entry_date'], 'period' => substr($data['entry_date'], 0, 7),
                    'type' => $data['type'], 'direction' => $data['type'] === 'bonus' ? 'credit' : 'debit',
                    'amount' => $amount->toDecimal(), 'currency' => $data['currency'],
                    'payment_method' => $data['type'] === 'payment' ? $data['payment_method'] : null,
                    'description' => $data['description'], 'source_key' => $key, 'created_by' => $actorId,
                ]);
                $this->audit->log(AuditEvent::Created, $entry, newValues: ['type' => $entry->type]);

                return $entry;
            }, 3);
        });
    }

    public function reverse(PersonnelPayrollEntry $entry, string $reason, string $requestId, int $actorId): PersonnelPayrollEntry
    {
        return $this->withRequestLock($requestId, function () use ($entry, $reason, $requestId, $actorId): PersonnelPayrollEntry {
            return DB::transaction(function () use ($entry, $reason, $requestId, $actorId): PersonnelPayrollEntry {
                PersonnelEmployee::query()->whereKey($entry->employee_id)->lockForUpdate()->firstOrFail();
                $entry = PersonnelPayrollEntry::query()->findOrFail($entry->id);
                $existing = PersonnelPayrollEntry::query()->where('source_key', 'reverse:'.$requestId)->first();
                if ($existing !== null) {
                    if ($existing->reverses_entry_id !== $entry->id || $existing->description !== $reason || $existing->created_by !== $actorId) {
                        throw new ConflictHttpException('İstek kimliği başka bir ters kayıt için kullanıldı.');
                    }

                    return $existing;
                }
                if ($entry->type === 'reversal' || PersonnelPayrollEntry::query()->where('reverses_entry_id', $entry->id)->exists()) {
                    throw new ConflictHttpException('Bu hareket yeniden ters kaydedilemez.');
                }

                $reversal = PersonnelPayrollEntry::query()->create([
                    'employee_id' => $entry->employee_id, 'entry_date' => $entry->entry_date,
                    'period' => $entry->period, 'type' => 'reversal',
                    'direction' => $entry->direction === 'credit' ? 'debit' : 'credit',
                    'amount' => $entry->amount, 'currency' => $entry->currency,
                    'payment_method' => $entry->payment_method, 'description' => $reason,
                    'source_key' => 'reverse:'.$requestId, 'reverses_entry_id' => $entry->id, 'created_by' => $actorId,
                ]);
                $this->audit->log(AuditEvent::Reversed, $reversal, newValues: ['reverses_entry_id' => $entry->id, 'reason' => $reason]);

                return $reversal;
            }, 3);
        });
    }

    private function roundRatio(int $amount, int $numerator, int $denominator): int
    {
        if ($denominator <= 0 || $numerator < 0 || $amount < 0 || ($numerator !== 0 && $amount > intdiv(PHP_INT_MAX - intdiv($denominator, 2), $numerator))) {
            throw ValidationException::withMessages(['amount' => 'Hesap tutarı taşma sınırını aşıyor.']);
        }

        return Money::fromMinorUnits(intdiv($amount * $numerator + intdiv($denominator, 2), $denominator))->minorUnits;
    }

    private function withRequestLock(string $requestId, callable $callback): mixed
    {
        $lock = hash('sha256', 'personnel_payroll_'.$requestId);
        $obtained = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lock]);
        if ((int) $obtained->acquired !== 1) {
            throw new ServiceUnavailableHttpException(2, 'İstek işleniyor; aynı kimlikle yeniden deneyin.');
        }
        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    }
}
