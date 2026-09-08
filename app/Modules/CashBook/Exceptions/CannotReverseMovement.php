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
}
