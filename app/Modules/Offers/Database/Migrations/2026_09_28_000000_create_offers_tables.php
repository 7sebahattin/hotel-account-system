<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->string('offer_no', 32)->nullable()->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('customer_name_snapshot', 200);
            $table->string('title', 200);
            $table->date('offer_date');
            $table->date('valid_until')->nullable();
            $table->char('currency', 3);
            $table->string('status', 20)->default('draft');
            $table->string('document_heading', 200);
            $table->unsignedSmallInteger('document_year');
            $table->text('intro_text')->nullable();
            $table->text('general_notes')->nullable();
            $table->text('cancellation_terms')->nullable();
            $table->text('footer_notes')->nullable();
            $table->string('hotel_name_snapshot', 200)->nullable();
            $table->string('legal_name_snapshot', 255)->nullable();
            $table->string('tax_office_snapshot', 100)->nullable();
            $table->string('tax_number_snapshot', 30)->nullable();
            $table->text('address_snapshot')->nullable();
            $table->string('phone_snapshot', 60)->nullable();
            $table->string('email_snapshot', 200)->nullable();
            $table->string('bank_account_holder', 200)->nullable();
            $table->string('bank_iban_try', 50)->nullable();
            $table->string('bank_iban_eur', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'offer_date']);
        });

        Schema::create('offer_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->string('label', 160);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->unique(['offer_id', 'sort_order']);
        });

        Schema::create('offer_room_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order');
            $table->unique(['offer_id', 'sort_order']);
        });

        Schema::create('offer_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('offer_period_id')->constrained('offer_periods')->cascadeOnDelete();
            $table->foreignId('offer_room_type_id')->constrained('offer_room_types')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->unique(['offer_period_id', 'offer_room_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_prices');
        Schema::dropIfExists('offer_room_types');
        Schema::dropIfExists('offer_periods');
        Schema::dropIfExists('offers');
    }
};
