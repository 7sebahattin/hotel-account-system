<?php

declare(strict_types=1);

namespace App\Modules\Companies\Services;

use App\Modules\Companies\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Firma modülünün dışa açık tek kapısı (CLAUDE.md §4.3).
 *
 * KARAR — cari hareketi olan firmanın silinememesi (CLAUDE.md §5.2):
 * Bu kural Ledger modülü geldiğinde (Gün 4) burada uygulanacaktır: delete()
 * içinde LedgerService üzerinden hareket kontrolü yapılacak ve hareketi olan
 * firma için istisna fırlatılacaktır. Ledger tablosu henüz mevcut olmadığı için
 * bugün taklit bir kontrol yazılmamıştır — sahte bir kontrol, gerçek koruma
 * varmış izlenimi verirdi.
 */
class CompanyService
{
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

    /** Soft delete. Firma kaydı hiçbir zaman fiziksel olarak silinmez. */
    public function delete(Company $company): void
    {
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
