<?php

declare(strict_types=1);

namespace App\Modules\Offers\Models;

use App\Modules\Companies\Models\Company;
use App\Modules\Core\Concerns\Auditable;
use App\Modules\Core\Concerns\HasCreator;
use App\Modules\Offers\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    use Auditable, HasCreator;

    protected $fillable = [
        'company_id', 'customer_name_snapshot', 'title', 'offer_date', 'valid_until',
        'currency', 'status', 'document_heading', 'document_year', 'intro_text',
        'general_notes', 'cancellation_terms', 'footer_notes', 'hotel_name_snapshot',
        'legal_name_snapshot', 'tax_office_snapshot', 'tax_number_snapshot',
        'address_snapshot', 'phone_snapshot', 'email_snapshot', 'bank_account_holder',
        'bank_iban_try', 'bank_iban_eur',
    ];

    protected function casts(): array
    {
        return [
            'offer_date' => 'date',
            'valid_until' => 'date',
            'status' => OfferStatus::class,
            'document_year' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(OfferPeriod::class)->orderBy('sort_order');
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(OfferRoomType::class)->orderBy('sort_order');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(OfferPrice::class);
    }
}
