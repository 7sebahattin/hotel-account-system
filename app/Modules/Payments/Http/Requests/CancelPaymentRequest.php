<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class CancelPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('payment');

        return $payment instanceof Payment && $this->user()->can('cancel', $payment);
    }

    /**
     * İptal gerekçesi zorunludur: cariyi geri çeviren bir işlemin denetim
     * satırı nedenini de taşımak zorundadır.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['cancellation_reason' => 'iptal gerekçesi'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['cancellation_reason.required' => 'İptal gerekçesi zorunludur.'];
    }
}
