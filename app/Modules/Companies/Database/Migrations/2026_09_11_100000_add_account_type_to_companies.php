<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('account_type', ['company', 'individual'])->default('company')->index();
        });
    }

    public function down(): void
    {
        // Bireysel hesaplar varken tür bilgisinin sessizce kaybolmasını önle.
        if (DB::table('companies')->where('account_type', 'individual')->exists()) {
            throw new RuntimeException('Bireysel hesaplar mevcut; veri koruyan ileri geçiş kullanın.');
        }

        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('account_type'));
    }
};
