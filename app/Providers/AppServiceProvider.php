<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Authorization\Models\Role;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\MonthlyAccount\Models\MonthlyAccountDay;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use App\Modules\Offers\Models\Offer;
use App\Modules\Payments\Models\Payment;
use App\Modules\Personnel\Models\PersonnelAttendanceCorrection;
use App\Modules\Personnel\Models\PersonnelCard;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelPayrollEntry;
use App\Modules\Personnel\Models\PersonnelScanEvent;
use App\Modules\Personnel\Models\PersonnelShift;
use App\Modules\Personnel\Models\PersonnelTerminal;
use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use App\Modules\Rooms\Models\Room;
use App\Modules\Stays\Models\Stay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Eski kurulumların migration geçmişi ve tahsilat FK’leri korunur.
        $this->loadMigrationsFrom(app_path('Modules/CashBook/Database/Migrations'));

        // CLAUDE.md §13: N+1, sessizce yutulan mass assignment ve eksik
        // attribute erişimi geliştirme/testte hata olarak patlar.
        Model::shouldBeStrict(! $this->app->isProduction());

        // CLAUDE.md §5.7: polimorfik ilişkiler sınıf adı yerine takma ad tutar,
        // böylece sınıf taşınsa/yeniden adlandırılsa da veri bozulmaz.
        // Yeni modüller geldikçe buraya kendi girdilerini ekler.
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'company' => Company::class,
            'ledger_entry' => LedgerEntry::class,
            'stay' => Stay::class,
            'payment' => Payment::class,
            'personnel_employee' => PersonnelEmployee::class,
            'personnel_shift' => PersonnelShift::class,
            'personnel_card' => PersonnelCard::class,
            'personnel_terminal' => PersonnelTerminal::class,
            'personnel_scan_event' => PersonnelScanEvent::class,
            'personnel_attendance_correction' => PersonnelAttendanceCorrection::class,
            'personnel_payroll_entry' => PersonnelPayrollEntry::class,
            'room' => Room::class,
            'receipt_book_entry' => ReceiptBookEntry::class,
            'monthly_account_day' => MonthlyAccountDay::class,
            'monthly_payment_provider' => MonthlyPaymentProvider::class,
            'offer' => Offer::class,
        ]);

        // CLAUDE.md §7.1: admin rolü yetki kontrollerini geçer.
        //
        // ÖNEMLİ: kısayol yalnızca ARGÜMANSIZ yetki kontrolleri için geçerlidir
        // (ör. can:'users.manage' middleware'i). Model/kayıt argümanı taşıyan
        // Policy yetenekleri kısa devre YAPILMAZ; aksi halde "kendi hesabını
        // silememe" ve "sistem rolünü koruma" gibi kayıt bazlı kurallar admin
        // için hiç çalışmazdı.
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            // Kaldırılan yetkiler admin kısayoluyla dahi tekrar açılamaz.
            if (str_starts_with($ability, 'cashbook.')) {
                return false;
            }

            $subject = $arguments[0] ?? null;
            $isAuditLog = $subject instanceof AuditLog || $subject === AuditLog::class;

            // Mutlak kural: denetim kaydı hiç kimse tarafından değiştirilemez/silinemez.
            if ($isAuditLog && in_array($ability, ['update', 'delete', 'forceDelete', 'restore'], true)) {
                return false;
            }

            if ($arguments !== []) {
                return null;
            }

            return $user->hasRole(RoleName::Admin->value) ? true : null;
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
