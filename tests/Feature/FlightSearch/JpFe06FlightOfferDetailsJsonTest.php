<?php

namespace Tests\Feature\FlightSearch;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * JP-FE-06 — authoritative offer details JSON contract for Next.js.
 */
class JpFe06FlightOfferDetailsJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_details_json_returns_authoritative_segments_and_breakdown(): void
    {
        [$searchId, $offer] = $this->storeSearchPayload();

        $response = $this->getJson('/flights/results/offer?search_id='.$searchId.'&offer_id='.$offer['offer_id'].'&format=json');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('offer_id', $offer['offer_id']);
        $response->assertJsonPath('flow', 'one_way');
        $response->assertJsonStructure([
            'offer' => [
                'segments',
                'fallback_details',
                'displayed_price',
                'price_display',
            ],
            'search_freshness',
            'revalidation_required',
        ]);
        $this->assertNotEmpty($response->json('offer.segments'));
        $this->assertArrayNotHasKey('raw_payload', $response->json('offer') ?? []);
    }

    public function test_offer_details_rejects_cross_search_offer_id(): void
    {
        [$searchA] = $this->storeSearchPayload('offer-a');
        [$searchB, $offerB] = $this->storeSearchPayload('offer-b');

        $this->getJson('/flights/results/offer?search_id='.$searchA.'&offer_id='.$offerB['offer_id'].'&format=json')
            ->assertNotFound()
            ->assertJsonPath('status', 'offer_not_found');
    }

    public function test_offer_details_rejects_invalid_fare_option_key(): void
    {
        [$searchId, $offer] = $this->storeSearchPayload();

        $this->getJson('/flights/results/offer?search_id='.$searchId.'&offer_id='.$offer['offer_id'].'&fare_option_key=invalid-brand-key&format=json')
            ->assertStatus(422)
            ->assertJsonPath('status', 'invalid_fare_option');
    }

    public function test_offer_details_expired_search_returns_410(): void
    {
        $searchId = (string) Str::uuid();

        $this->getJson('/flights/results/offer?search_id='.$searchId.'&offer_id=offer-1&format=json')
            ->assertStatus(410)
            ->assertJsonPath('status', 'expired_search');
    }

    public function test_offer_details_rejects_offer_id_used_as_fare_option_key(): void
    {
        [$searchId, $offer] = $this->storeSearchPayload();

        $this->getJson('/flights/results/offer?search_id='.$searchId.'&offer_id='.$offer['offer_id'].'&fare_option_key='.$offer['offer_id'].'&format=json')
            ->assertStatus(422)
            ->assertJsonPath('status', 'invalid_fare_option');
    }

    public function test_offer_details_without_fare_option_key_returns_base_offer(): void
    {
        [$searchId, $offer] = $this->storeSearchPayload();

        $this->getJson('/flights/results/offer?search_id='.$searchId.'&offer_id='.$offer['offer_id'].'&format=json')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_offer_details_does_not_mutate_search_store(): void
    {
        [$searchId, $offer] = $this->storeSearchPayload();
        $before = Cache::get('flight_search:'.$searchId);
        $this->assertIsArray($before);

        $this->getJson('/flights/results/offer?search_id='.$searchId.'&offer_id='.$offer['offer_id'].'&format=json')
            ->assertOk();

        $after = Cache::get('flight_search:'.$searchId);
        $this->assertSame($before, $after);
    }

    public function test_blade_offer_route_still_redirects_for_html(): void
    {
        [$searchId] = $this->storeSearchPayload();

        $this->get('/flights/results/offer?search_id='.$searchId)
            ->assertRedirect();
    }

    public function test_passengers_url_from_results_row_is_internal_only(): void
    {
        [$searchId] = $this->storeSearchPayload();
        $mapped = $this->getJson('/flights/results/data?search_id='.$searchId)->json('offers.0');
        $selectUrl = (string) ($mapped['select_url'] ?? '');
        $this->assertNotSame('', $selectUrl);
        $this->assertStringStartsWith('/booking/passengers', parse_url($selectUrl, PHP_URL_PATH) ?: $selectUrl);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function storeSearchPayload(?string $offerId = null): array
    {
        $searchId = (string) Str::uuid();
        $offerId = $offerId ?? 'offer-1';
        $offer = [
            'id' => $offerId,
            'offer_id' => $offerId,
            'supplier_provider' => 'duffel',
            'supplier_connection_id' => 1,
            'airline_code' => 'TA',
            'airline_name' => 'TestAir',
            'flight_number' => '123',
            'depart_at' => now()->addDays(14)->format('Y-m-d').'T08:00:00Z',
            'arrive_at' => now()->addDays(14)->format('Y-m-d').'T12:30:00Z',
            'duration_h' => 4,
            'duration_m' => 30,
            'stops' => 0,
            'baggage' => '20kg',
            'refundable' => false,
            'refund_rule' => 'Non-refundable',
            'change_rule' => 'Changes with penalty',
            'cabin' => 'economy',
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'base_fare' => 100000,
            'taxes' => 10000,
            'markup' => 2500,
            'service_fee' => 2499,
            'total' => 114999,
            'final_customer_price' => 114999,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => now()->addDays(14)->format('Y-m-d').'T08:00:00Z',
                    'arrival_at' => now()->addDays(14)->format('Y-m-d').'T12:30:00Z',
                    'airline_code' => 'TA',
                    'flight_number' => '123',
                ],
            ],
        ];

        Cache::put('flight_search:'.$searchId, [
            'search_id' => $searchId,
            'criteria' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => now()->addDays(14)->format('Y-m-d'),
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ],
            'offers' => [$offer],
            'warnings' => [],
            'created_at' => now()->toIso8601String(),
            'search_created_at' => now()->toIso8601String(),
        ], 1800);

        return [$searchId, $offer];
    }
}
