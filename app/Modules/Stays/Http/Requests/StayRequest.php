<?php

declare(strict_types=1);

namespace App\Modules\Stays\Http\Requests;

use App\Modules\Core\Enums\Currency;
use App\Modules\Stays\Models\Stay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $stay = $this->route('stay');

        return $stay instanceof Stay
            ? $this->user()->can('update', $stay)
            : $this->user()->can('create', Stay::class);
    }

    /**
     * `nights` ve `status` kullanıcıdan ALINMAZ; sunucu hesaplar/belirler.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'guest_name' => ['required', 'string', 'max:150'],
            'room_no' => ['nullable', 'string', 'max:20'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after_or_equal:check_in_date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_id' => 'firma',
            'guest_name' => 'misafir adı',
            'room_no' => 'oda',
            'check_in_date' => 'giriş tarihi',
            'check_out_date' => 'çıkış tarihi',
            'amount' => 'tutar',
            'currency' => 'para birimi',
            'notes' => 'notlar',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'check_out_date.after_or_equal' => 'Çıkış tarihi, giriş tarihinden önce olamaz.',
            'amount.gt' => 'Tutar sıfırdan büyük olmalıdır.',
            'amount.decimal' => 'Tutar en fazla iki ondalık basamak içerebilir.',
        ];
    }

    /** Türkçe klavyede ondalık ayırıcı virgüldür; noktaya çevrilir. */
    protected function prepareForValidation(): void
    {
        if ($this->has('amount') && is_string($this->input('amount'))) {
            $this->merge([
                'amount' => str_replace([' ', ','], ['', '.'], trim($this->input('amount'))),
            ]);
        }
    }
}
