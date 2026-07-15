<?php

namespace App\Support;

class DemoFlightOffers
{
    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    public static function withDemoMeta(array $offers): array
    {
        $meta = config('ota-flights.offers', []);

        return array_map(function (array $offer) use ($meta): array {
            $id = $offer['id'] ?? '';
            $overlay = $meta[$id] ?? [
                'airline_name' => 'Partner airline',
                'airline_code' => $offer['carrier_code'] ?? 'XX',
                'baggage' => 'As per fare rule',
                'refundable' => false,
                'fare_family' => 'Economy',
                'seats_left' => 8,
            ];
            $mins = (int) ($offer['duration_minutes'] ?? 0);

            return array_merge($offer, $overlay, [
                'duration_h' => intdiv($mins, 60),
                'duration_m' => $mins % 60,
            ]);
        }, $offers);
    }
}
