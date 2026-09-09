<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Exceptions;

class CannotReverseMovement extends CashBookException
{
    public static function alreadyReversed(int $movementId): self
    {
        return new self("#{$movementId} numaralı hareket zaten ters kaydedilmiş.");
    }

    public static function isReversal(int $movementId): self
    {
        return new self("#{$movementId} bir ters kayıttır; ters kaydın tersi alınamaz.");
    }

    public static function openingBalance(int $movementId): self
    {
        return new self(
            "#{$movementId} bir açılış bakiyesidir; ters kaydedilemez. "
            .'Hatalı açılış, kapanış sayımındaki fark olarak açıklanır.'
        );
    }

    public static function sessionClosed(int $movementId): self
    {
        return new self("#{$movementId} numaralı hareketin vardiyası kapanmıştır; ters kayıt yazılamaz.");
    }

    /**
     * `reference_type` taşıyan hareketler (ör. nakit kanallı bir tahsilata
     * bağlı `company_collection`) genel Kasa Hareketleri ekranından ters
     * kaydedilemez — düzeltme yalnızca kaynak kaydın kendi iptal akışından
     * yapılır, aksi hâlde kasa düzelir ama tahsilat/cari düzelmeden kalır.
     */
    public static function linkedToExternalSource(int $movementId): self
    {
        return new self(
            "#{$movementId} numaralı hareket bir kaynak kayda (ör. tahsilat) bağlıdır; "
            .'buradan ters kaydedilemez. Düzeltme için ilgili kaydı iptal edin.'
        );
    }
}
