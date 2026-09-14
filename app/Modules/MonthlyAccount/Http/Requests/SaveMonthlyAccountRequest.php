<?php

declare(strict_types=1);

namespace App\Modules\MonthlyAccount\Http\Requests;

use App\Modules\Core\Enums\Currency;
use App\Modules\Core\Support\Money;
use App\Modules\MonthlyAccount\Enums\ConversionMode;
use App\Modules\MonthlyAccount\Enums\MonthlyPaymentMethod;
use App\Modules\MonthlyAccount\Models\MonthlyAccountDay;
use App\Modules\MonthlyAccount\Models\MonthlyPaymentProvider;
use App\Modules\MonthlyAccount\Services\MonthlyAccountConversion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class SaveMonthlyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $day = $this->route('day');

        return $day instanceof MonthlyAccountDay
            ? $this->user()->can('update', $day)
            : $this->user()->can('create', MonthlyAccountDay::class);
    }

    public function rules(): array
    {
        $money = ['string', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'];

        return [
            'owner_id' => ['required', 'integer', 'min:1'],
            'account_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'version' => [$this->route('day') ? 'required' : 'nullable', 'integer', 'min:1', 'max:4294967294'],
            'daily_total' => ['bail', 'required', ...$money],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['present', 'array', 'list', 'max:100'],
            'items.*' => ['array:method,currency,bank_name,amount,conversion_mode,exchange_rate_to_try,try_equivalent,note'],
            'items.*.method' => ['required', Rule::enum(MonthlyPaymentMethod::class)],
            'items.*.currency' => ['required', Rule::enum(Currency::class)],
            'items.*.bank_name' => ['nullable', 'string', 'max:100'],
            'items.*.amount' => ['bail', 'required', ...$money],
            'items.*.conversion_mode' => ['nullable', Rule::enum(ConversionMode::class)],
            'items.*.exchange_rate_to_try' => ['nullable', 'string', 'regex:/^\d{1,6}(?:\.\d{1,4})?$/'],
            'items.*.try_equivalent' => ['nullable', ...$money],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        $dailyTotal = $this->input('daily_total');
        if (is_string($dailyTotal) || is_int($dailyTotal)) {
            $data['daily_total'] = $this->normalizeNumericInput((string) $dailyTotal);
        }

        $items = $this->input('items', []);
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['amount', 'exchange_rate_to_try', 'try_equivalent'] as $field) {
                    if (isset($item[$field]) && (is_string($item[$field]) || is_int($item[$field]))) {
                        $item[$field] = $this->normalizeNumericInput((string) $item[$field]);
                    }
                }
                // TL'de ve seçilmeyen dönüşüm yönteminde istemci hesabına güvenilmez.
                if (($item['currency'] ?? null) === Currency::TRY->value) {
                    $item['conversion_mode'] = null;
                    $item['exchange_rate_to_try'] = null;
                    $item['try_equivalent'] = null;
                } elseif (($item['conversion_mode'] ?? null) === ConversionMode::Rate->value) {
                    $item['try_equivalent'] = null;
                } elseif (($item['conversion_mode'] ?? null) === ConversionMode::Total->value) {
                    $item['exchange_rate_to_try'] = null;
                }
            }
            unset($item);
            $data['items'] = $items;
        }

        $this->merge($data);
    }

    private function normalizeNumericInput(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, ',') && ! preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+),\d{1,4}$/', $value)) {
            return $value;
        }

        return Money::normalizeInput($value);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $active = array_column(Currency::active(), 'value');
            $day = $this->route('day');
            $previous = $day instanceof MonthlyAccountDay
                ? $day->loadMissing('currentRevision.items')->currentRevision?->items
                : null;
            $activeProviderNames = MonthlyPaymentProvider::query()
                ->where('is_active', true)
                ->pluck('name')
                ->all();

            foreach ($this->input('items', []) as $index => $item) {
                $prefix = 'items.'.$index;
                $method = MonthlyPaymentMethod::from($item['method']);
                $bankName = trim((string) ($item['bank_name'] ?? ''));
                $previousItem = $previous?->get($index);
                $keepsHistoricalProvider = $previousItem?->bank_name === $bankName
                    && $previousItem->method->value === $item['method'];
                if ($method->requiresBank()) {
                    if ($bankName === '') {
                        $validator->errors()->add($prefix.'.bank_name', 'Kredi kartı, link ve havale için tanımlı bir banka / ödeme kuruluşu seçin.');
                    } elseif (! in_array($bankName, $activeProviderNames, true) && ! $keepsHistoricalProvider) {
                        $validator->errors()->add($prefix.'.bank_name', 'Bu banka / ödeme kuruluşu yeni işlemler için etkin değil.');
                    }
                }
                if (! in_array($item['currency'], $active, true)
                    && $previous?->get($index)?->currency->value !== $item['currency']) {
                    $validator->errors()->add($prefix.'.currency', 'Bu para birimi yeni işlemler için etkin değil.');
                }

                if ($item['currency'] !== Currency::TRY->value) {
                    if (empty($item['conversion_mode'])) {
                        $validator->errors()->add($prefix.'.conversion_mode', 'Kur gir veya TL toplamı gir yöntemini seçin.');

                        continue;
                    }
                    $field = $item['conversion_mode'] === ConversionMode::Rate->value ? 'exchange_rate_to_try' : 'try_equivalent';
                    if (! isset($item[$field]) || $item[$field] === '') {
                        $validator->errors()->add($prefix.'.'.$field, 'Döviz için seçtiğiniz kur veya TL toplamını girin.');

                        continue;
                    }
                }

                try {
                    MonthlyAccountConversion::snapshot($item);
                } catch (InvalidArgumentException $exception) {
                    $field = ($item['conversion_mode'] ?? null) === ConversionMode::Total->value
                        ? 'try_equivalent' : 'exchange_rate_to_try';
                    if (MonthlyAccountConversion::money($item['amount'])->isZero()) {
                        $field = 'amount';
                    }
                    $validator->errors()->add($prefix.'.'.$field, $exception->getMessage());
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'owner_id' => 'hesap sahibi', 'account_date' => 'tarih', 'version' => 'kayıt sürümü',
            'daily_total' => 'günlük liste toplamı', 'note' => 'gün notu', 'items' => 'işlemler',
            'items.*.method' => 'ödeme yöntemi', 'items.*.currency' => 'para birimi',
            'items.*.bank_name' => 'banka / ödeme kuruluşu', 'items.*.amount' => 'tutar',
            'items.*.conversion_mode' => 'döviz hesaplama yöntemi',
            'items.*.exchange_rate_to_try' => 'kur', 'items.*.try_equivalent' => 'TL karşılığı',
            'items.*.note' => 'işlem açıklaması',
        ];
    }

    public function messages(): array
    {
        return [
            '*.regex' => 'Tutarı örneğin 3.000,00 biçiminde, kuru en fazla dört ondalık basamakla girin.',
            'items.max' => 'Bir güne en fazla 100 işlem eklenebilir.',
        ];
    }
}
