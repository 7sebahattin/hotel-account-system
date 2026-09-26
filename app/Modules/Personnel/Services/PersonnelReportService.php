<?php

declare(strict_types=1);

namespace App\Modules\Personnel\Services;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Personnel\Models\PersonnelEmployee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PersonnelReportService
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function employees(array $filters): LengthAwarePaginator
    {
        $query = PersonnelEmployee::query();
        if (! empty($filters['q'])) {
            $q = '%'.addcslashes($filters['q'], '%_').'%';
            $query->where(function ($query) use ($q): void {
                $query->where('employee_code', 'like', $q)->orWhere('first_name', 'like', $q)->orWhere('last_name', 'like', $q);
            });
        }
        if (isset($filters['active']) && $filters['active'] !== 'all') {
            $query->where('is_active', $filters['active'] === '1');
        }
        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }
        $sort = in_array($filters['sort'] ?? '', ['employee_code', 'start_date'], true) ? $filters['sort'] : 'first_name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction)->orderBy('id')->paginate(in_array((int) ($filters['per_page'] ?? 25), [25, 50, 100], true) ? (int) ($filters['per_page'] ?? 25) : 25)->withQueryString();
    }

    public function daily(string $date, array $filters, bool $print = false): array
    {
        $employees = PersonnelEmployee::query()->whereDate('start_date', '<=', $date)->where(function ($query) use ($date): void {
            $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
        });
        if (! empty($filters['employee_id'])) {
            $employees->whereKey((int) $filters['employee_id']);
        }
        if (! empty($filters['department'])) {
            $employees->where('department', $filters['department']);
        }
        $rows = [];
        if ($print) {
            $page = collect();
            $employees->chunkById(100, function ($chunk) use (&$page, &$rows, $date): void {
                $page = $page->concat($chunk);
                $context = $this->attendance->preload($chunk->pluck('id')->all(), $date, $date);
                foreach ($chunk as $employee) {
                    $rows[] = $this->attendance->day($employee, $date, $context);
                }
            });
        } else {
            $page = $employees->orderBy('id')->paginate(100)->withQueryString();
            $context = $this->attendance->preload($page->pluck('id')->all(), $date, $date);
            $rows = $page->map(fn (PersonnelEmployee $employee): array => $this->attendance->day($employee, $date, $context))->all();
        }
        $totals = ['worked_minutes' => 0, 'net_minutes' => 0, 'missing_minutes' => 0, 'late_minutes' => 0, 'early_minutes' => 0, 'overtime_minutes' => 0, 'present_count' => 0, 'absent_count' => 0, 'missing_entries_count' => 0];
        foreach ($rows as $row) {
            foreach (['worked_minutes', 'net_minutes', 'missing_minutes', 'late_minutes', 'early_minutes', 'overtime_minutes'] as $key) {
                $totals[$key] += $row[$key];
            }
            if (in_array($row['status'], ['present', 'unscheduled'], true)) {
                $totals['present_count']++;
            } elseif ($row['status'] === 'missing_checkout') {
                $totals['missing_entries_count']++;
            } elseif ($row['status'] === 'absent') {
                $totals['absent_count']++;
            }
        }

        return ['date' => $date, 'filters' => $filters, 'rows' => $rows, 'employees' => $page, 'totals' => $totals];
    }

    public function monthly(string $month, array $filters, bool $withPayroll, bool $print = false): array
    {
        $first = Carbon::createFromFormat('Y-m-d', $month.'-01');
        $last = $first->copy()->endOfMonth()->toDateString();
        $query = PersonnelEmployee::query()->whereDate('start_date', '<=', $last)->where(function ($query) use ($first): void {
            $query->whereNull('end_date')->orWhereDate('end_date', '>=', $first->toDateString());
        });
        if (! empty($filters['employee_id'])) {
            $query->whereKey((int) $filters['employee_id']);
        }
        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }
        if (($filters['active'] ?? 'all') !== 'all') {
            $query->where('is_active', $filters['active'] === '1');
        }
        $employees = $print ? collect() : $query->orderBy('id')->paginate(100)->withQueryString();
        $days = [];
        for ($n = 1; $n <= $first->daysInMonth; $n++) {
            $days[] = $first->copy()->day($n)->toDateString();
        }
        $rows = [];
        $totals = [];
        $consume = function ($batch) use (&$rows, &$totals, $first, $last, $days): void {
            $context = $this->attendance->preload($batch->pluck('id')->all(), $first->toDateString(), $last);
            foreach ($batch as $employee) {
                foreach ($days as $date) {
                    if ($date < $employee->start_date->toDateString() || ($employee->end_date !== null && $date > $employee->end_date->toDateString())) {
                        continue;
                    }
                    $row = $this->attendance->day($employee, $date, $context);
                    $rows[$employee->id][$date] = $row;
                    foreach (['worked_minutes', 'break_minutes', 'net_minutes', 'missing_minutes', 'late_minutes', 'early_minutes', 'overtime_minutes'] as $key) {
                        $totals[$employee->id][$key] = ($totals[$employee->id][$key] ?? 0) + $row[$key];
                    }
                    if ($row['status'] === 'absent') {
                        $totals[$employee->id]['absent_days'] = ($totals[$employee->id]['absent_days'] ?? 0) + 1;
                    }
                }
            }
        };
        if ($print) {
            $query->chunkById(100, function ($batch) use (&$employees, $consume): void {
                $employees = $employees->concat($batch);
                $consume($batch);
            });
        } else {
            $consume($employees->getCollection());
        }
        $result = ['month' => $month, 'filters' => $filters, 'employees' => $employees, 'days' => $days, 'rows' => $rows, 'totalsByEmployee' => $totals];
        if ($withPayroll) {
            $ids = $employees->pluck('id')->all();
            $result['earningsByCurrency'] = $this->aggregatePayroll($ids, $month, 'earning');
            $result['paymentsByCurrency'] = $this->aggregatePayroll($ids, $month, 'payment');
            $result['bonusesByCurrency'] = $this->aggregatePayroll($ids, $month, 'bonus');
            $result['deductionsByCurrency'] = $this->aggregatePayroll($ids, $month, 'deduction');
        }

        return $result;
    }

    public function dashboardSnapshot(string $date, bool $withPayroll): array
    {
        $report = $this->daily($date, []);
        $rows = $report['rows'];
        $allEmployees = PersonnelEmployee::query()->where('is_active', true)->whereDate('start_date', '<=', $date)->where(function ($query) use ($date): void {
            $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
        })->orderBy('id')->get();
        $previous = Carbon::parse($date)->subDay()->toDateString();
        $context = $this->attendance->preload($allEmployees->pluck('id')->all(), $previous, $date);
        $allRows = $allEmployees->map(fn (PersonnelEmployee $employee): array => $this->attendance->day($employee, $date, $context))->all();
        $priorRows = $allEmployees->map(fn (PersonnelEmployee $employee): array => $this->attendance->day($employee, $previous, $context))->all();
        $inside = 0;
        foreach ($allRows as $index => $row) {
            if ($row['status'] === 'missing_checkout' || ($priorRows[$index]['status'] === 'missing_checkout' && $row['sessions'] === [])) {
                $inside++;
            }
        }
        $summary = [
            'active_employees' => $allEmployees->count(),
            'present_count' => count(array_filter($allRows, static fn (array $r): bool => in_array($r['status'], ['present', 'missing_checkout', 'unscheduled'], true))),
            'currently_inside_count' => $inside,
            'absent_count' => count(array_filter($allRows, static fn (array $r): bool => $r['status'] === 'absent')),
            'late_count' => count(array_filter($allRows, static fn (array $r): bool => $r['late_minutes'] > 0)),
            'early_count' => count(array_filter($allRows, static fn (array $r): bool => $r['early_minutes'] > 0)),
            'overtime_minutes' => array_sum(array_column($allRows, 'overtime_minutes')),
            'missing_entries_count' => $inside,
        ];
        $result = ['date' => $date, 'summary' => $summary, 'rows' => $rows, 'employees' => $report['employees']];
        if ($withPayroll) {
            $perEmployee = $this->aggregatePayroll($allEmployees->pluck('id')->all(), substr($date, 0, 7), 'payment');
            $byCurrency = [];
            foreach ($perEmployee as $currencies) {
                foreach ($currencies as $currency => $amount) {
                    $byCurrency[$currency] = ($byCurrency[$currency] ?? 0) + Money::fromDecimal($amount['decimal'])->minorUnits;
                }
            }
            $result['thisMonthPaymentsByCurrency'] = [];
            foreach ($byCurrency as $currency => $minor) {
                $result['thisMonthPaymentsByCurrency'][$currency] = $this->money(Money::fromMinorUnits($minor), $currency);
            }
        }

        return $result;
    }

    public function payroll(string $month, array $filters): array
    {
        $query = PersonnelEmployee::query();
        if (! empty($filters['employee_id'])) {
            $query->whereKey((int) $filters['employee_id']);
        }
        $employees = $query->orderBy('first_name')->paginate(25)->withQueryString();
        $ids = $employees->pluck('id')->all();
        $ledger = DB::table('personnel_payroll_entries')->whereIn('employee_id', $ids)->where('period', $month)->orderBy('id')->get();
        $originalTypes = DB::table('personnel_payroll_entries')->whereIn('id', $ledger->pluck('reverses_entry_id')->filter()->all())->pluck('type', 'id');
        $allBalances = DB::table('personnel_payroll_entries')->whereIn('employee_id', $ids)->select('employee_id', 'currency', DB::raw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) AS balance"))->groupBy('employee_id', 'currency')->get();
        $monthEnd = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
        $wageCurrencies = DB::table('personnel_wage_assignments')->whereIn('employee_id', $ids)->where('effective_from', '<=', $monthEnd)->orderBy('effective_from')->get(['employee_id', 'currency'])->keyBy('employee_id');
        $rows = [];
        foreach ($employees as $employee) {
            $currencies = $ledger->where('employee_id', $employee->id)->pluck('currency')->merge($allBalances->where('employee_id', $employee->id)->pluck('currency'))->unique()->all();
            if ($currencies === []) {
                $current = $wageCurrencies[$employee->id]->currency ?? null;
                $currencies = $current === null ? [] : [$current];
            }
            foreach ($currencies as $currency) {
                if (! empty($filters['currency']) && $filters['currency'] !== $currency) {
                    continue;
                }
                $entries = $ledger->where('employee_id', $employee->id)->where('currency', $currency);
                $totals = ['earned' => Money::zero(), 'bonus' => Money::zero(), 'deduction' => Money::zero(), 'paid' => Money::zero(), 'balance' => Money::zero()];
                foreach ($entries as $entry) {
                    $money = Money::fromDecimal($entry->amount);
                    $sourceType = $entry->type === 'reversal' ? ($originalTypes[$entry->reverses_entry_id] ?? null) : $entry->type;
                    $key = match ($sourceType) {
                        'earning' => 'earned', 'bonus' => 'bonus', 'deduction' => 'deduction', 'payment' => 'paid', default => null
                    };
                    if ($key !== null) {
                        $totals[$key] = $entry->type === 'reversal' ? $totals[$key]->subtract($money) : $totals[$key]->add($money);
                    }
                    $totals['balance'] = $entry->direction === 'credit' ? $totals['balance']->add($money) : $totals['balance']->subtract($money);
                }
                $periodBalance = $totals['balance'];
                $allTime = $allBalances->first(static fn ($item): bool => $item->employee_id === $employee->id && $item->currency === $currency);
                $totals['balance'] = $allTime === null ? Money::zero() : Money::fromDecimal($allTime->balance);
                $rows[] = ['employee_id' => $employee->id, 'full_name' => $employee->full_name, 'currency' => $currency,
                    'earned' => $this->money($totals['earned'], $currency), 'bonus' => $this->money($totals['bonus'], $currency),
                    'deduction' => $this->money($totals['deduction'], $currency), 'paid' => $this->money($totals['paid'], $currency),
                    'period_balance' => $this->money($periodBalance, $currency),
                    'balance' => $this->money($totals['balance'], $currency), 'calculation_status' => $totals['earned']->isZero() ? 'pending' : 'saved'];
            }
        }

        return ['month' => $month, 'filters' => $filters, 'rows' => $rows, 'employees' => $employees];
    }

    private function money(Money $amount, string $currency): array
    {
        return ['decimal' => $amount->toDecimal(), 'formatted' => $amount->format(Currency::from($currency)), 'currency' => $currency];
    }

    private function aggregatePayroll(array $employeeIds, string $month, string $type): array
    {
        if ($employeeIds === []) {
            return [];
        }
        $entries = DB::table('personnel_payroll_entries as e')->leftJoin('personnel_payroll_entries as original', 'e.reverses_entry_id', '=', 'original.id')
            ->whereIn('e.employee_id', $employeeIds)->where('e.period', $month)
            ->where(function ($query) use ($type): void {
                $query->where('e.type', $type)->orWhere(function ($query) use ($type): void {
                    $query->where('e.type', 'reversal')->where('original.type', $type);
                });
            })->select('e.employee_id', 'e.currency', 'e.type', 'e.amount')->get();
        $totals = [];
        foreach ($entries as $entry) {
            $key = $entry->employee_id.'|'.$entry->currency;
            $minor = Money::fromDecimal($entry->amount)->minorUnits;
            $totals[$key] = ($totals[$key] ?? 0) + ($entry->type === 'reversal' ? -$minor : $minor);
        }
        $formatted = [];
        foreach ($totals as $key => $minor) {
            [$employeeId, $currency] = explode('|', $key, 2);
            $formatted[$employeeId][$currency] = $this->money(Money::fromMinorUnits($minor), $currency);
        }

        return $formatted;
    }
}
