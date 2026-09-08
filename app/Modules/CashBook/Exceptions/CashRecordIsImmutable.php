<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Exceptions;

/**
 * Devir tutarı (`cash_transfer_amounts`) ve sayım (`cash_counts`) satırları da
 * `cash_movements` ile AYNI ilkeye tabidir: bir kez yazıldıktan sonra
 * değiştirilemez/silinemez.
 *
 * Gerekçe: bir devir kabul edildiğinde, teslim alınan tutar YENİ vardiyanın
 * açılış bakiyesi olarak zaten kopyalanmıştır (kullanıcı talimatı §1 — "devir
 * tutarları sonradan değiştirilemiyor"). Sayım satırı da kapanmış bir
 * vardiyanın imzalanmış tanığıdır. İkisi de değiştirilebilseydi, geçmiş bir
 * devrin/kapanışın kaydı ile o anda oluşan gerçek hareket geçmişi sessizce
 * birbirinden kopabilirdi — tıpkı `ledger_entries`/`cash_movements`'ta olduğu
 * gibi tek ortak sınıf kullanılır, ayrı bir varlık başına ayrı sınıf açılmaz.
 */
class CashRecordIsImmutable extends CashBookException
{
    public static function cannotUpdate(string $label): self
    {
        return new self("{$label} değiştirilemez.");
    }

    public static function cannotDelete(string $label): self
    {
        return new self("{$label} silinemez.");
    }
}
