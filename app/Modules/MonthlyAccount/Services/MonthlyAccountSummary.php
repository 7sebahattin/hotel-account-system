<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Services;

use App\Modules\Core\Support\Money;
use App\Modules\MonthlyAccount\Enums\MonthlyPaymentMethod;
use App\Modules\MonthlyAccount\Models\MonthlyAccountRevision;

final class MonthlyAccountSummary
{
    /**
     * Para birimleri kendi başlıklarında toplanır. receipt_total yalnızca
     * kullanıcının onayladığı TL anlık görüntülerinin bilgi amaçlı toplamıdır.
     *
     * @param  iterable<MonthlyAccountRevision>  $revisions
     */
    public static function fromRevisions(iterable $revisions): array
    {
        $summary = [
            'methods' => array_fill_keys(array_column(MonthlyPaymentMethod::cases(), 'value'), []),
            'banks' => [],
            'daily_total' => Money::zero(),
            'receipt_total' => Money::zero(),
        ];

        foreach ($revisions as $revision) {
            $summary['daily_total'] = $summary['daily_total']->add($revision->dailyTotalMoney());

            foreach ($revision->items as $item) {
                $method = $item->method->value;
                $currency = $item->currency->value;
                $group = $summary['methods'][$method][$currency] ?? self::emptyGroup();
                $group['amount'] = $group['amount']->add($item->amountMoney());
                $group['try_equivalent'] = $group['try_equivalent']->add($item->tryEquivalentMoney());
                $summary['methods'][$method][$currency] = $group;
                $summary['receipt_total'] = $summary['receipt_total']->add($item->tryEquivalentMoney());

                if ($item->bank_name !== null) {
                    $bank = $summary['banks'][$method][$item->bank_name][$currency] ?? self::emptyGroup();
                    $bank['amount'] = $bank['amount']->add($item->amountMoney());
                    $bank['try_equivalent'] = $bank['try_equivalent']->add($item->tryEquivalentMoney());
                    $summary['banks'][$method][$item->bank_name][$currency] = $bank;
                }
            }
        }

        $summary['difference'] = $summary['receipt_total']->subtract($summary['daily_total']);

        return $summary;
    }

    private static function emptyGroup(): array
    {
        return ['amount' => Money::zero(), 'try_equivalent' => Money::zero()];
    }
}
