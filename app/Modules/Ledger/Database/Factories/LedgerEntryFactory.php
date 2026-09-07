<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Database\Factories;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Core\Enums\Currency;
use App\Modules\Ledger\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Yalnızca test amaçlıdır. Üretim kodunda hareketler LedgerService üzerinden yazılır.
 *
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'source_type' => null,
            'source_id' => null,
            'entry_date' => now()->toDateString(),
            'description' => 'Test hareketi',
            'debit' => '100.00',
            'credit' => '0.00',
            'currency' => Currency::TRY->value,
            'is_reversal' => false,
            'reverses_entry_id' => null,
            'created_by' => User::factory(),
        ];
    }

    public function credit(string $amount = '100.00'): static
    {
        return $this->state(fn () => ['debit' => '0.00', 'credit' => $amount]);
    }

    public function debit(string $amount = '100.00'): static
    {
        return $this->state(fn () => ['debit' => $amount, 'credit' => '0.00']);
    }
}
