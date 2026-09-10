<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            $table->string('name', 200);
            $table->string('tax_office', 100)->nullable();
            $table->string('tax_number', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Sıralama ve doğrudan filtre kolonları. Serbest metin araması
            // LIKE '%...%' kullandığı için indeksten yararlanmaz; V1 veri
            // hacminde (yılda binlerce kayıt) kabul edilebilir.
            $table->index('name');
            $table->index('tax_number');
            $table->index('city');
            $table->index('is_active');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
