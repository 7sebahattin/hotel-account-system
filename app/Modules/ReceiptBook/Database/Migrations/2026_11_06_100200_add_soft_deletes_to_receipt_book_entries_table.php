<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ortak Çöp Kutusu (6 Kasım 2026): silinen makbuz soft delete ile çöpe girer, geri alınabilir. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_book_entries', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('receipt_book_entries', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
