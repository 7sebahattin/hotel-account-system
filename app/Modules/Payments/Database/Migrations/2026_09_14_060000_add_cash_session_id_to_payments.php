<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.1.1-A.1 — tahsilatın oluşturulduğu vardiyaya EXPLICIT, DEĞİŞMEZ referans.
 *
 * KARAR: zaman penceresi çıkarımı (`created_by` + `opened_at`/`closed_at`
 * aralığı) yeterince belirleyici değildi — aynı kullanıcı, vardiya sınırına
 * yakın bir anda işlem yaparsa yanlış vardiyaya atfedilebilirdi. Bunun yerine
 * oluşturma anında AÇIKÇA yazılan, bir daha ASLA değişmeyen bir FK eklendi.
 *
 * `cash_sessions`e nullable FK — kart/banka/çek `CashBook` hareketi ÜRETMEZ
 * (kasa yalnızca nakit için kullanılır) ama "hangi vardiyada alındığı" bilgisi
 * hâlâ operasyonel olarak değerlidir; bu yüzden referans `cash_movements`
 * DEĞİL doğrudan `payments` üzerindedir. Nakit kanalda da AYNI kolon
 * kullanılır — kasaya yazılan hareketin kendi `cash_session_id`'siyle
 * birebir aynı vardiyayı gösterir (iki farklı kaynak, tek doğruluk).
 *
 * NULL anlamı: oluşturma anında aktör açık BİR VARDİYAYI YÖNETMİYORDU
 * (örn. Admin/Patron açık vardiya yokken kart tahsilatı girdi). Geçmiş
 * (mevcut) satırlar hep NULL kalır — zaman damgasından TAHMİN EDİLMEZ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('payment_method')
                ->constrained('cash_sessions')->restrictOnDelete();

            $table->index(['cash_session_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });
    }
};
