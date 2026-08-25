<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\PublicProgressiveSearchSnapshotPreparer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProgressiveSearchSnapshotPreparerTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_only_rejects_connecting_offer_before_partial_render(): void
    {
        $preparer = app(PublicProgressiveSearchSnapshotPreparer::class);
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(14)->toDateString(),
            'trip_type' => 'one_way',
            'direct_only' => true,
        ];

        $result = $preparer->prepare($criteria, [
            $this->pricedOffer('connecting', 1),
            $this->pricedOffer('direct', 0),
        ]);

        $ids = array_map(
            static fn (array $o): string => (string) ($o['offer_id'] ?? ''),
            $result['offers'],
        );

        $this->assertSame(['direct'], $ids);
        $this->assertSame(1, $result['diagnostics']['direct_only']['direct_filter_dropped_count'] ?? null);
    }

    public function test_departure_lead_policy_parity_hides_invalid_same_day_offer(): void
    {
        $preparer = app(PublicProgressiveSearchSnapshotPreparer::class);
        $today = now()->toDateString();
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $today,
            'trip_type' => 'one_way',
            'direct_only' => false,
        ];

        $tooSoon = $this->pricedOffer('too-soon', 0);
        $tooSoon['depart_at'] = now()->addHours(2)->toIso8601String();

        $valid = $this->pricedOffer('valid-future', 0);
        $valid['depart_at'] = now()->addHours(12)->toIso8601String();

        $result = $preparer->prepare($criteria, [$tooSoon, $valid]);
        $ids = array_map(
            static fn (array $o): string => (string) ($o['offer_id'] ?? ''),
            $result['offers'],
        );

        $this->assertSame(['valid-future'], $ids);
        $this->assertSame(1, $result['diagnostics']['departure_lead']['lead_filter_rejected_count'] ?? null);
    }

    public function test_mixed_carrier_policy_parity_hides_interline_offer(): void
    {
        config(['suppliers.sabre.hide_mixed_carrier_search_results' => true]);

        $preparer = app(PublicProgressiveSearchSnapshotPreparer::class);
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'LHR',
            'depart_date' => now()->addDays(21)->toDateString(),
            'trip_type' => 'one_way',
        ];

        $mixed = $this->pricedOffer('mixed', 1);
        $mixed['mixed_carrier'] = true;
        $mixed['segments'] = [
            ['marketing_carrier' => 'PK', 'departure_at' => now()->addDays(21)->toIso8601String()],
            ['marketing_carrier' => 'BA', 'departure_at' => now()->addDays(21)->addHours(5)->toIso8601String()],
        ];

        $same = $this->pricedOffer('same-carrier', 0);
        $same['segments'] = [
            ['marketing_carrier' => 'PK', 'departure_at' => now()->addDays(21)->toIso8601String()],
        ];

        $result = $preparer->prepare($criteria, [$mixed, $same]);
        $ids = array_map(
            static fn (array $o): string => (string) ($o['offer_id'] ?? ''),
            $result['offers'],
        );

        $this->assertSame(['same-carrier'], $ids);
        $this->assertGreaterThanOrEqual(
            1,
            (int) ($result['diagnostics']['mixed_carrier']['mixed_carrier_offers_filtered_count'] ?? 0),
        );
    }

    public function test_public_price_gate_rejects_unpriced_offer(): void
    {
        $preparer = app(PublicProgressiveSearchSnapshotPreparer::class);
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'trip_type' => 'one_way',
        ];

        $unpriced = $this->pricedOffer('unpriced', 0);
        $unpriced['final_customer_price'] = 0;
        $unpriced['total'] = 0;
        $unpriced['conversion_status'] = 'conversion_missing';

        $priced = $this->pricedOffer('priced', 0);

        $result = $preparer->prepare($criteria, [$unpriced, $priced]);
        $ids = array_map(
            static fn (array $o): string => (string) ($o['offer_id'] ?? ''),
            $result['offers'],
        );

        $this->assertSame(['priced'], $ids);
        $this->assertSame(1, $result['diagnostics']['public_price']['price_filter_rejected_count'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pricedOffer(string $id, int $stops): array
    {
        return [
            'offer_id' => $id,
            'supplier_provider' => 'sabre',
            'airline_code' => 'PK',
            'flight_number' => 'PK100',
            'stops' => $stops,
            'final_customer_price' => 55000,
            'total' => 55000,
            'pricing_currency' => 'PKR',
            'currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'depart_at' => now()->addDays(14)->setTime(10, 0)->toIso8601String(),
            'segments' => [
                [
                    'marketing_carrier' => 'PK',
                    'departure_at' => now()->addDays(14)->setTime(10, 0)->toIso8601String(),
                ],
            ],
        ];
    }
}
