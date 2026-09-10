<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Tahsilatta yalnızca metadata düzeltilebilir.
 *
 * Finansal alanlar burada HİÇ tanımlı değildir; `validated()` yalnızca
 * `reference_no` ve `description` döndürür, dolayısıyla istekte gönderilseler
 * bile servise ulaşmazlar. Servis ve model katmanları da ayrıca korur.
 */
class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('payment');

        return $payment instanceof Payment && $this->user()->can('update', $payment);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference_no' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reference_no' => 'referans no',
            'description' => 'açıklama',
        ];
    }
}
