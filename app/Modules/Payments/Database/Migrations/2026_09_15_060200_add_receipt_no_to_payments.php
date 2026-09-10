<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.1.1-B — Tahsilat Makbuzu numarası.
 *
 * KARAR: yeni bir sıra/sequence tablosu AÇILMADI. `payments.id` zaten
 * MySQL'in kendi AUTO_INCREMENT'i sayesinde eşzamanlılığa karşı güvenli,
 * benzersiz ve `MAX()+1` riski taşımaz — `receipt_no` bu id'den TÜRETİLİR
 * (`TM-{yıl}-{id altı haneli}`), oluşturma anında BİR KEZ hesaplanıp
 * saklanır (`PaymentService::create()`). Bu "en küçük sağlam çözüm"dür
 * (kullanıcı talimatı §13).
 *
 * NULLABLE: bu migration'dan ÖNCEKİ satırlar asla geriye dönük numara
 * ALMAZ — geçmiş veri tahmin/icat edilmez (K-094/K-095'teki ilkeyle aynı).
 * Yalnızca BU özellikten SONRA oluşturulan tahsilatların makbuzu olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_no', 20)->nullable()->unique()->after('cash_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_no']);
            $table->dropColumn('receipt_no');
        });
    }
};
