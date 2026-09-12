<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\Authorization\Enums\RoleName;
use App\Modules\Companies\Models\Company;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Stays\Models\Stay;
use App\Modules\Stays\Services\StayService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentAllocationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();
        parent::tearDown();
    }

    private function assertAccountLocked(int $id): void
    {
        $config = config('database.connections.mysql');
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
            $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT id FROM companies WHERE id = ? FOR UPDATE');
            $statement->execute([$id]);
            $this->fail('Competing financial operation must wait for account lock.');
        } catch (PDOException $exception) {
            $this->assertSame(1205, $exception->errorInfo[1]);
        } finally {
            $pdo->rollBack();
        }
    }

    #[Test]
    public function receipt_holds_account_lock_before_recording_payment(): void
    {
        $this->seedRoles();
        $actor = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        Payment::creating(fn () => $this->assertAccountLocked($company->id));
        app(PaymentService::class)->create(['company_id' => $company->id, 'amount' => '100',
            'currency' => 'TRY', 'payment_date' => '2026-09-12'], $actor);
    }

    #[Test]
    public function stay_cancellation_holds_same_account_lock(): void
    {
        $this->seedRoles();
        $actor = $this->userWithRole(RoleName::Admin);
        $company = Company::factory()->create();
        $stay = app(StayService::class)->create(['company_id' => $company->id, 'guest_name' => 'Misafir',
            'check_in_date' => '2026-09-11', 'check_out_date' => '2026-09-12', 'pricing_mode' => 'total',
            'amount' => '100', 'currency' => 'TRY'], $actor);
        Stay::updating(fn () => $this->assertAccountLocked($company->id));
        app(StayService::class)->cancel($stay, $actor);
    }
}
