<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use Illuminate\Database\Query\Builder;

/**
 * Çöp kutusundaki kaynağın defter satırlarını GÖRÜNÜR sorgulardan çıkarır
 * (7 Kasım 2026, kullanıcı talimatı: çöpteki veri ekstrede, toplamda, raporda yoktur).
 *
 * Defter append-only olduğundan satırlar silinmez/işaretlenmez; bunun yerine kaynak
 * modül (konaklama/tahsilat) "çöpte mi" bilgisini buraya KAYDEDER ve her görünür
 * sorgu bu yardımcıyla süzülür. Çöpe yalnızca iptal edilmiş (net sıfır) kayıt girdiği
 * için orijinal + ters kayıt birlikte çıkar ve bakiye DEĞİŞMEZ. Ledger başka modülü
 * tanımaz (§4.3/3): yalnızca (kaynak tipi, tablo, "çöpte" kolonu) üçlüsünü bilir.
 *
 * Kalıcı silme hazırlığı (kimlik toplama, net sıfır kontrolü) bu süzgeci KULLANMAZ.
 */
final class LedgerVisibility
{
    /** @var array<string, array{table: string, column: string}> */
    private static array $hiddenWhenSet = [];

    /** `$table.$column` dolu olan kaynağın defter satırları gizlenir. */
    public static function hideSourceWhenSet(string $sourceType, string $table, string $column): void
    {
        self::$hiddenWhenSet[$sourceType] = ['table' => $table, 'column' => $column];
    }

    public static function apply(Builder $query, string $entries = 'ledger_entries'): Builder
    {
        foreach (self::$hiddenWhenSet as $sourceType => $source) {
            $table = $source['table'];
            $column = $source['column'];

            $query->whereNot(function (Builder $hidden) use ($entries, $sourceType, $table, $column): void {
                $hidden->where("{$entries}.source_type", $sourceType)
                    ->whereExists(function (Builder $exists) use ($entries, $table, $column): void {
                        $exists->selectRaw('1')->from($table)
                            ->whereColumn("{$table}.id", "{$entries}.source_id")
                            ->whereNotNull("{$table}.{$column}");
                    });
            });
        }

        return $query;
    }
}
