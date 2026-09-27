<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Models\User;
use App\Modules\Personnel\Models\PersonnelEmployee;
use App\Modules\Personnel\Models\PersonnelScanEvent;
use App\Modules\Personnel\Models\PersonnelShiftVersion;
use App\Modules\Personnel\Services\CardService;
use App\Modules\Personnel\Services\ScheduleService;
use App\Modules\Personnel\Services\TerminalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonnelCoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function terminal_auto_scan_replay_duplicate_and_revoke(): void
    {
        $user = User::factory()->create();
        $employee = PersonnelEmployee::query()->create([
            'employee_code' => 'P100', 'first_name' => 'Ada', 'last_name' => 'Yılmaz',
            'start_date' => today('Europe/Istanbul')->subDay()->toDateString(), 'is_active' => true,
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
        $shift = app(ScheduleService::class)->createShift([
            'name' => 'Gündüz', 'start_time' => '09:00', 'end_time' => '17:00',
            'ends_next_day' => false, 'break_minutes' => 0, 'workdays' => [1, 2, 3, 4, 5, 6, 7],
        ], $user->id);
        app(ScheduleService::class)->assignShift($employee, PersonnelShiftVersion::query()->where('shift_id', $shift->id)->firstOrFail()->id, today('Europe/Istanbul')->toDateString(), $user->id);
        app(CardService::class)->assign($employee, ['uid' => '04AABBCCDD0011'], $user->id);
        [$terminal, $token] = app(TerminalService::class)->create(['name' => 'T1', 'platform' => 'android', 'device_uuid' => (string) Str::uuid()], $user->id);
        $input = ['request_id' => (string) Str::uuid(), 'card_uid' => '04AABBCCDD0011'];
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $this->postJson('/api/personnel/scans', $input, $headers)->assertOk()->assertJsonPath('result', 'accepted')->assertJsonPath('action', 'in')->assertJsonPath('shift_label', 'Gündüz (V1, 09:00–17:00)');
        $this->postJson('/api/personnel/scans', $input, $headers)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('shift_label', 'Gündüz (V1, 09:00–17:00)');
        $this->postJson('/api/personnel/scans', ['request_id' => (string) Str::uuid(), 'card_uid' => '04AABBCCDD0011'], $headers)->assertOk()->assertJsonPath('result', 'duplicate');
        $this->assertSame(2, PersonnelScanEvent::query()->count());
        app(TerminalService::class)->revoke($terminal);
        $this->postJson('/api/personnel/scans', $input, $headers)->assertUnauthorized();
    }

    #[Test]
    public function unauthenticated_invalid_body_is_unauthorized(): void
    {
        $this->postJson('/api/personnel/scans', ['card_uid' => 'wrong'])->assertUnauthorized();
    }
}
