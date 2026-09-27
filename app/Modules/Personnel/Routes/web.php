<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Personnel\Http\Controllers\AttendanceController;
use App\Modules\Personnel\Http\Controllers\EmployeeController;
use App\Modules\Personnel\Http\Controllers\PayrollController;
use App\Modules\Personnel\Http\Controllers\PersonnelController;
use App\Modules\Personnel\Http\Controllers\ScanController;
use App\Modules\Personnel\Http\Controllers\ShiftController;
use App\Modules\Personnel\Http\Controllers\TerminalController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('personnel')->name('personnel.')->group(function (): void {
    Route::get('/', [PersonnelController::class, 'index'])->middleware('can:'.Permission::PersonnelView->value)->name('index');
    Route::get('employees', [EmployeeController::class, 'index'])->middleware('can:'.Permission::PersonnelView->value)->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->middleware('can:'.Permission::PersonnelManage->value)->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->middleware('can:'.Permission::PersonnelManage->value)->name('employees.store');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('can:'.Permission::PersonnelView->value)->name('employees.show');
    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->middleware('can:'.Permission::PersonnelManage->value)->name('employees.edit');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->middleware('can:'.Permission::PersonnelManage->value)->name('employees.update');
    Route::patch('employees/{employee}/active', [EmployeeController::class, 'active'])->middleware('can:'.Permission::PersonnelManage->value)->name('employees.active');
    Route::get('shifts', [ShiftController::class, 'index'])->middleware('can:'.Permission::PersonnelView->value)->name('shifts.index');
    Route::post('shifts', [ShiftController::class, 'store'])->middleware('can:'.Permission::PersonnelManage->value)->name('shifts.store');
    Route::post('shifts/{shift}/versions', [ShiftController::class, 'version'])->middleware('can:'.Permission::PersonnelManage->value)->name('shifts.versions.store');
    Route::patch('shifts/{shift}/active', [ShiftController::class, 'active'])->middleware('can:'.Permission::PersonnelManage->value)->name('shifts.active');
    Route::post('employees/{employee}/shift-assignments', [ShiftController::class, 'assign'])->middleware('can:'.Permission::PersonnelManage->value)->name('shift-assignments.store');
    Route::post('employees/{employee}/wage-assignments', [PayrollController::class, 'wage'])->middleware('can:'.Permission::PersonnelPayroll->value)->name('wage-assignments.store');
    Route::post('employees/{employee}/cards', [EmployeeController::class, 'card'])->middleware('can:'.Permission::PersonnelManage->value)->name('cards.store');
    Route::post('cards/{card}/release', [EmployeeController::class, 'releaseCard'])->middleware('can:'.Permission::PersonnelManage->value)->name('cards.release');
    Route::patch('cards/{card}/active', [EmployeeController::class, 'cardActive'])->middleware('can:'.Permission::PersonnelManage->value)->name('cards.active');
    Route::get('kiosk', [PersonnelController::class, 'kiosk'])->middleware('can:'.Permission::PersonnelScan->value)->name('kiosk');
    Route::post('kiosk/scans', [ScanController::class, 'kiosk'])->middleware('can:'.Permission::PersonnelScan->value)->name('kiosk.scan');
    Route::get('attendance', [AttendanceController::class, 'index'])->middleware('can:'.Permission::PersonnelView->value)->name('attendance.index');
    Route::post('employees/{employee}/attendance-corrections', [AttendanceController::class, 'correct'])->middleware('can:'.Permission::PersonnelAttendance->value)->name('attendance-corrections.store');
    Route::get('reports/daily', [AttendanceController::class, 'daily'])->middleware('can:'.Permission::PersonnelView->value)->name('reports.daily');
    Route::get('reports/daily/print', [AttendanceController::class, 'dailyPrint'])->middleware('can:'.Permission::PersonnelView->value)->name('reports.daily.print');
    Route::get('reports/monthly', [AttendanceController::class, 'monthly'])->middleware('can:'.Permission::PersonnelView->value)->name('reports.monthly');
    Route::get('reports/monthly/print', [AttendanceController::class, 'monthlyPrint'])->middleware('can:'.Permission::PersonnelView->value)->name('reports.monthly.print');
    Route::get('payroll', [PayrollController::class, 'index'])->middleware('can:'.Permission::PersonnelPayroll->value)->name('payroll.index');
    Route::post('employees/{employee}/payroll/calculate', [PayrollController::class, 'calculate'])->middleware('can:'.Permission::PersonnelPayroll->value)->name('payroll.calculate');
    Route::post('employees/{employee}/payroll/entries', [PayrollController::class, 'entry'])->middleware('can:'.Permission::PersonnelPayroll->value)->name('payroll.entries.store');
    Route::post('payroll/entries/{entry}/reverse', [PayrollController::class, 'reverse'])->middleware('can:'.Permission::PersonnelPayroll->value)->name('payroll.entries.reverse');
    Route::get('terminals', [TerminalController::class, 'index'])->middleware('can:'.Permission::PersonnelTerminals->value)->name('terminals.index');
    Route::post('terminals', [TerminalController::class, 'store'])->middleware('can:'.Permission::PersonnelTerminals->value)->name('terminals.store');
    Route::post('terminals/{terminal}/revoke', [TerminalController::class, 'revoke'])->middleware('can:'.Permission::PersonnelTerminals->value)->name('terminals.revoke');
});
