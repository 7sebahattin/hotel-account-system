<?php

declare(strict_types=1);

namespace App\Modules\Stays\Http\Requests;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\Stays\Enums\StayPricingMode;
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
     * Günlük ücretlendirmede `amount` da alınmaz: `exclude_if` ile doğrulanmış
     * veriden tamamen düşer, böylece sahte bir tutar servise hiç ulaşmaz.
     * Servis ayrıca tutarı her koşulda yeniden hesaplar (iki katmanlı savunma).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $stay = $this->route('stay');
        $currentRoomId = $stay instanceof Stay ? $stay->room_id : null;

        // Yeni kayıt: yalnızca AKTİF oda seçilebilir. Düzenlemede, kaydın
        // KENDİ odası pasifleşmiş olsa bile seçenekten kaybolmaz — aksi
        // hâlde form sessizce reddederdi (kullanıcı talimatı: Room Registry
        // §1 "inactive rooms ... cannot be selected for new records").
        $roomExists = Rule::exists('rooms', 'id')->where(function ($query) use ($currentRoomId): void {
            $query->where(function ($inner) use ($currentRoomId): void {
                $inner->where('is_active', true);

                if ($currentRoomId !== null) {
                    $inner->orWhere('id', $currentRoomId);
                }
            });
        });

        return [
            'company_id' => ['required', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'guest_name' => ['required', 'string', 'max:150'],
            'room_id' => ['nullable', 'integer', $roomExists],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'pricing_mode' => ['required', Rule::enum(StayPricingMode::class)],
            'amount' => ['exclude_if:pricing_mode,daily', 'required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'daily_rate' => ['exclude_if:pricing_mode,total', 'required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
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
            'room_id' => 'oda',
            'check_in_date' => 'giriş tarihi',
            'check_out_date' => 'çıkış tarihi',
            'pricing_mode' => 'ücret giriş şekli',
            'amount' => 'toplam tutar',
            'daily_rate' => 'günlük ücret',
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
            'check_out_date.after' => 'Çıkış tarihi, giriş tarihinden sonra olmalıdır (en az 1 gece).',
            'amount.gt' => 'Toplam tutar sıfırdan büyük olmalıdır.',
            'amount.decimal' => 'Toplam tutar en fazla iki ondalık basamak içerebilir.',
            'daily_rate.required' => 'Günlük ücretlendirmede gecelik tutar zorunludur.',
            'daily_rate.gt' => 'Günlük ücret sıfırdan büyük olmalıdır.',
            'daily_rate.decimal' => 'Günlük ücret en fazla iki ondalık basamak içerebilir.',
        ];
    }

    /** Türkçe klavyede ondalık ayırıcı virgüldür; noktaya çevrilir. */
    protected function prepareForValidation(): void
    {
        foreach (['amount', 'daily_rate'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => Money::normalizeInput($value)]);
            }
        }
    }
}
