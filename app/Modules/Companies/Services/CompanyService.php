<?php

declare(strict_types=1);

namespace App\Modules\Companies\Services;

use App\Modules\Companies\Exceptions\CompanyHasLedgerEntries;
use App\Modules\Companies\Models\Company;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Firma modülünün dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * Cari hareketi olan firma silinemez (CLAUDE.md §5.2). Bu kontrol Policy'de
 * DEĞİL burada zorunludur: Gate::before admin için Policy'leri kısa devre
 * yapabilir ve servis, HTTP dışındaki çağrı yollarını da (konsol komutu,
 * gelecekteki import) kapsar. Policy aynı kuralı yalnızca arayüzde butonu
 * gizlemek için tekrarlar.
 */
class CompanyService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Company
    {
        return DB::transaction(fn (): Company => Company::query()->create($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): void
    {
        DB::transaction(function () use ($company, $data): void {
            $company->update($data);
        });
    }

    /**
     * Soft delete. Firma kaydı hiçbir zaman fiziksel olarak silinmez.
     *
     * @throws CompanyHasLedgerEntries cari hareketi olan firma için
     */
    public function delete(Company $company): void
    {
        if ($this->ledger->hasEntriesForCompany($company->getKey())) {
            throw CompanyHasLedgerEntries::make($company->name);
        }

        DB::transaction(function () use ($company): void {
            $company->delete();
        });
    }

    public function restore(Company $company): void
    {
        DB::transaction(function () use ($company): void {
            $company->restore();
        });
    }
}
