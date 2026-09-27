<?php

declare(strict_types=1);

namespace App\Modules\ReceiptBook\Http\Requests;

use App\Modules\ReceiptBook\Models\ReceiptBookEntry;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Makbuz düzenleme — oluşturma ile AYNI alan kuralları. Yalnızca yetki ve
 * doğrulama hatasında dönülecek sayfa farklıdır.
 */
class UpdateReceiptBookEntryRequest extends StoreReceiptBookEntryRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('entry');

        return $entry instanceof ReceiptBookEntry
            && $this->user()->can('update', $entry);
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('receipt-book.edit', $this->route('entry'))
                ->withInput($this->except('_token', '_method'))
                ->withErrors($validator)
        );
    }
}
