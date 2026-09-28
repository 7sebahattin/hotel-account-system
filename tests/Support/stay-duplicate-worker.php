<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Stays\Services\StayService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
echo "ready\n";
flush();

try {
    $barrier = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$argv[2]]);
    if ((int) ($barrier->acquired ?? 0) !== 1) {
        throw new RuntimeException('Test barrier lock could not be acquired.');
    }
    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$argv[2]]);

    app(StayService::class)->create([
        'guest_name' => 'Ahmet Kurt',
        'check_in_date' => '2026-09-28',
        'check_out_date' => '2026-09-29',
        'pricing_mode' => 'total',
        'amount' => '1000.00',
        'currency' => 'TRY',
    ], User::query()->findOrFail((int) $argv[1]));
    echo "created\n";
} catch (ValidationException $exception) {
    if (! array_key_exists('guest_name', $exception->errors())) {
        fwrite(STDERR, 'Unexpected validation error: '.json_encode($exception->errors())."\n");
        exit(1);
    }
    echo "duplicate\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}
