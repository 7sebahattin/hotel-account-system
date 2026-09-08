<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Requests\Concerns;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;

/**
 * Çok para birimli tutar girişini doğrular ve `Money` nesnelerine çevirir.
 *
 * Kasa formlarının çoğu (açılış, devir, sayım) para birimi başına bir alan
 * taşır: `amounts[TRY]`, `amounts[EUR]`… Üç formda da aynı doğrulama ve aynı
 * dönüşüm gerektiği için kural Core'a değil bu modülün kendi trait'ine kondu:
 * tek kullanıcısı kasa defteridir (CLAUDE.md §4.3/4 kopyalamayı yasaklar,
 * gereksiz genelleştirmeyi değil).
 */
trait ParsesCurrencyAmounts
{
    /**
     * Alan adı `amounts` olan çok para birimli tutar kuralları.
     *
     * `array:TRY,EUR,…` anahtar kümesini de kısıtlar: bilinmeyen bir para
     * birimi kodu doğrulamada düşer, servise hiç ulaşmaz.
     *
     * @return array<string, mixed>
     */
    protected function currencyAmountRules(): array
    {
        return [
            'amounts' => ['array:'.implode(',', Currency::values())],
            'amounts.*' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2', 'max:9999999999999.99'],
        ];
    }

    /**
     * Doğrulanmış tutarlar — yalnızca SIFIRDAN BÜYÜK olanlar.
     *
     * Boş bırakılan ya da sıfır girilen para birimi hiç yazılmaz; kasa
     * defterinde "0,00 TRY" satırı gürültüden başka bir şey değildir.
     *
     * @return array<string, Money>
     */
    public function currencyAmounts(): array
    {
        /** @var array<string, string|null> $raw */
        $raw = $this->validated()['amounts'] ?? [];

        $amounts = [];

        foreach ($raw as $code => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $money = Money::fromDecimal((string) $value);

            if ($money->isPositive()) {
                $amounts[$code] = $money;
            }
        }

        return $amounts;
    }

    /** Türkçe klavyede ondalık ayırıcı virgüldür; her alan ayrı normalleştirilir. */
    protected function normalizeAmounts(): void
    {
        $amounts = $this->input('amounts');

        if (! is_array($amounts)) {
            return;
        }

        $this->merge([
            'amounts' => array_map(
                static fn (mixed $value): mixed => is_string($value) && trim($value) !== ''
                    ? Money::normalizeInput($value)
                    : $value,
                $amounts,
            ),
        ]);
    }
}
