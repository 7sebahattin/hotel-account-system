<?php

declare(strict_types=1);

namespace App\Modules\CashBook\Http\Requests;

use App\Modules\CashBook\Http\Requests\Concerns\ParsesCurrencyAmounts;
use App\Modules\CashBook\Models\CashSession;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Kasa açılışı: para birimi başına açılış bakiyesi.
 *
 * Bakiye girilmemesi geçerlidir — boş kasadan başlanabilir.
 */
class OpenCashSessionRequest extends FormRequest
{
    use ParsesCurrencyAmounts;

    public function authorize(): bool
    {
        return $this->user()->can('create', CashSession::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->currencyAmountRules() + [
            'opening_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amounts' => 'açılış bakiyesi', 'opening_note' => 'not'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAmounts();
    }
}
