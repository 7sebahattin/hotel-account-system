<?php

declare(strict_types=1);

namespace App\Modules\Offers\Services;

use App\Modules\Companies\Services\CompanyService;
use App\Modules\Offers\Models\Offer;
use Illuminate\Support\Facades\DB;

class OfferService
{
    public function __construct(private readonly CompanyService $companies) {}

    public function save(array $data, ?Offer $offer = null): Offer
    {
        return DB::transaction(function () use ($data, $offer): Offer {
            $companyId = isset($data['company_id']) ? (int) $data['company_id'] : null;
            if (blank($data['customer_name_snapshot'] ?? null) && $companyId !== null) {
                $data['customer_name_snapshot'] = $this->companies->namesByIds([$companyId])[$companyId];
            }

            $periods = array_values($data['periods']);
            $rooms = array_values($data['room_types']);
            $prices = $data['prices'] ?? [];
            unset($data['periods'], $data['room_types'], $data['prices']);

            if ($offer === null) {
                $offer = Offer::query()->create($data);
                // DB kimliği eşzamanlı isteklerde benzersizdir; unique index son güvencedir.
                $offer->offer_no = sprintf('TKF-%s-%06d', substr($data['offer_date'], 0, 4), $offer->id);
                $offer->save();
            } else {
                $offer->update($data);
                $offer->prices()->delete();
                $offer->periods()->delete();
                $offer->roomTypes()->delete();
            }

            $periodIds = [];
            foreach ($periods as $index => $period) {
                $periodIds[] = $offer->periods()->create([...$period, 'sort_order' => $index + 1])->id;
            }
            foreach ($rooms as $roomIndex => $room) {
                $roomId = $offer->roomTypes()->create(['name' => $room['name'], 'sort_order' => $roomIndex + 1])->id;
                foreach ($periodIds as $periodIndex => $periodId) {
                    $price = $prices[$roomIndex][$periodIndex] ?? null;
                    if ($price !== null && $price !== '') {
                        $offer->prices()->create([
                            'offer_period_id' => $periodId,
                            'offer_room_type_id' => $roomId,
                            'price' => $price,
                        ]);
                    }
                }
            }

            return $offer->load(['periods', 'roomTypes', 'prices', 'creator']);
        });
    }
}
