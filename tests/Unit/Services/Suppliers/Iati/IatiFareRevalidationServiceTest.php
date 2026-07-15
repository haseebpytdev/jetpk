<?php

namespace Tests\Unit\Services\Suppliers\Iati;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiFareRevalidationService;
use App\Services\Suppliers\Iati\IatiPayloadBuilder;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiFareRevalidationServiceTest extends TestCase
{
    #[Test]
    public function test_cached_offer_with_fare_key_builds_revalidation_payload(): void
    {
        $offer = $this->sampleOffer('dep-live-key');
        $context = $offer->raw_payload['provider_context'] ?? [];

        $payload = app(IatiPayloadBuilder::class)->buildFarePayload($context);

        $this->assertSame('dep-live-key', $payload['departure_fare_key']);
        $this->assertSame([['type' => 'ADULT', 'count' => 1]], $payload['pax_list']);
    }

    #[Test]
    public function test_valid_fare_response_normalizes_to_valid_status(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response.json')), true),
                200,
            ),
        ]);

        $offer = $this->sampleOffer('dep-fare-key-abc', 355.0);
        $connection = $this->connection();
        $result = app(IatiFareRevalidationService::class)->revalidate($offer, $connection);

        $this->assertTrue($result->is_valid);
        $this->assertSame('valid', app(IatiFareRevalidationService::class)->buildPublicRevalidationReport($result, $offer->toArray())['revalidation_status']);
        $this->assertSame('fare-detail-key-xyz', $result->meta['fare_detail_key'] ?? null);
    }

    #[Test]
    public function test_price_change_response_normalizes_to_changed_with_totals(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response.json')), true),
                200,
            ),
        ]);

        $offer = $this->sampleOffer('dep-fare-key-abc', 300.0);
        $connection = $this->connection();
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $connection);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertTrue($result->is_valid);
        $this->assertTrue($result->price_changed);
        $this->assertSame('changed', $report['revalidation_status']);
        $this->assertSame(300.0, $report['original_total']);
        $this->assertSame(355.0, $report['confirmed_total']);
        $this->assertTrue($report['price_changed']);
    }

    #[Test]
    public function test_missing_fare_key_returns_failed_safely(): void
    {
        $offer = $this->sampleOffer('');
        $connection = $this->connection();
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $connection);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertFalse($result->is_valid);
        $this->assertSame('failed', $report['revalidation_status']);
        $this->assertFalse($report['fare_key_present']);
        Http::assertNothingSent();
    }

    #[Test]
    public function test_unavailable_offer_maps_to_expired(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response([
                'message' => 'This fare is no longer available.',
                'code' => 'VA009',
            ], 409),
        ]);

        $offer = $this->sampleOffer('expired-key');
        $connection = $this->connection();
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $connection);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertFalse($result->is_valid);
        $this->assertSame('expired', $report['revalidation_status']);
        $this->assertSame('offer_unavailable', $result->meta['error_code'] ?? null);
    }

    #[Test]
    public function test_nested_fare_response_extracts_confirmed_total_from_departure_fare_path(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_nested_total_fare.json')), true),
                200,
            ),
        ]);

        $offer = $this->sampleOffer('dep-live-key', 89692.0);
        $connection = $this->connection();
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $connection);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertTrue($result->is_valid);
        $this->assertSame('valid', $report['revalidation_status']);
        $this->assertSame(89692.0, $report['confirmed_total']);
        $this->assertSame(
            'departure_fare.fare_info.fare_detail.price_info.total_fare',
            $report['confirmed_total_source_path'],
        );
        $this->assertFalse($report['price_changed']);
        $this->assertFalse($report['supplier_mutation_attempted'] ?? true);
    }

    #[Test]
    public function test_root_total_fare_response_normalizes_confirmed_total(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response([
                'result' => [
                    'fare_detail_key' => 'fare-detail-root-total',
                    'offers' => [['offer_key' => 'offer-1']],
                    'total_fare' => 91234.5,
                    'currency_code' => 'PKR',
                ],
            ], 200),
        ]);

        $offer = $this->sampleOffer('dep-root-total', 91234.5);
        $connection = $this->connection();
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $connection);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertTrue($result->is_valid);
        $this->assertSame(91234.5, $report['confirmed_total']);
        $this->assertSame('total_fare', $report['confirmed_total_source_path']);
    }

    #[Test]
    public function test_missing_confirmed_total_does_not_default_to_zero_or_changed(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_missing_total.json')), true),
                200,
            ),
        ]);

        $offer = $this->sampleOffer('dep-incomplete', 89692.0);
        $connection = $this->connection();
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $connection);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertFalse($result->is_valid);
        $this->assertSame('failed', $report['revalidation_status']);
        $this->assertNull($report['confirmed_total']);
        $this->assertFalse($report['price_changed']);
        $this->assertStringContainsString('incomplete pricing', strtolower((string) $report['safe_customer_message']));
        $this->assertFalse($report['booking_created'] ?? true);
        $this->assertFalse($report['ticketing_attempted'] ?? true);
    }

    #[Test]
    public function test_zero_confirmed_total_is_treated_as_failed_not_changed(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response([
                'result' => [
                    'fare_detail_key' => 'fare-detail-zero',
                    'offers' => [['offer_key' => 'offer-1', 'total_price' => 0]],
                    'total_fare' => 0,
                ],
            ], 200),
        ]);

        $offer = $this->sampleOffer('dep-zero-total', 89692.0);
        $connection = $this->connection();
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $connection);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertFalse($result->is_valid);
        $this->assertSame('failed', $report['revalidation_status']);
        $this->assertNull($report['confirmed_total']);
        $this->assertFalse($report['price_changed']);
    }

    #[Test]
    public function test_single_offer_response_uses_that_offer_total(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response.json')), true),
                200,
            ),
        ]);

        $offer = $this->sampleOffer('dep-fare-key-abc', 355.0);
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection());
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertTrue($result->is_valid);
        $this->assertSame(355.0, $report['confirmed_total']);
        $this->assertSame('offers.0.total_price', $report['confirmed_total_source_path']);
        $this->assertSame(1, $report['returned_offer_count']);
        $this->assertSame(0, $report['matched_offer_index']);
    }

    #[Test]
    public function test_multi_offer_response_uses_only_matching_fare_key_total(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_matched.json')), true),
                200,
            ),
        ]);

        $offer = $this->sampleOffer('dep-match-key', 80294.0);
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection());
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertTrue($result->is_valid);
        $this->assertSame('valid', $report['revalidation_status']);
        $this->assertSame(80294.0, $report['confirmed_total']);
        $this->assertSame('offers.1.total_price', $report['confirmed_total_source_path']);
        $this->assertSame(1, $report['matched_offer_index']);
        $this->assertSame(3, $report['returned_offer_count']);
        $this->assertSame(1, $report['returned_offer_key_match_count']);
        $this->assertSame('fare_key_match', $report['matched_reason']);
        $this->assertFalse($report['price_changed']);
    }

    #[Test]
    public function test_multi_offer_without_key_match_uses_exact_original_total_when_unique(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_total_match.json')), true),
                200,
            ),
        ]);

        $offer = $this->sampleOffer('unknown-submitted-key', 80294.0);
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection());
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertTrue($result->is_valid);
        $this->assertSame('valid', $report['revalidation_status']);
        $this->assertSame(80294.0, $report['confirmed_total']);
        $this->assertSame(0, $report['matched_offer_index']);
        $this->assertSame('original_total_exact_match', $report['matched_reason']);
        $this->assertSame(1, $report['original_total_match_count']);
        $this->assertSame(0, $report['returned_offer_key_match_count']);
        $this->assertFalse($report['price_changed']);
    }

    #[Test]
    public function test_multi_offer_with_duplicate_original_total_matches_fails(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_duplicate_total.json')), true),
                200,
            ),
        ]);

        $offer = $this->sampleOffer('unknown-key', 80294.0);
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection());
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertFalse($result->is_valid);
        $this->assertSame('failed', $report['revalidation_status']);
        $this->assertNull($report['confirmed_total']);
        $this->assertSame(2, $report['original_total_match_count']);
    }

    #[Test]
    public function test_fare_key_match_wins_over_original_total_match(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response([
                'result' => [
                    'fare_detail_key' => 'fare-detail-key-wins',
                    'offers' => [
                        ['fare_key' => 'other', 'total_price' => 80294, 'currency' => 'PKR'],
                        ['fare_key' => 'dep-key-wins', 'total_price' => 85158, 'currency' => 'PKR'],
                    ],
                ],
            ], 200),
        ]);

        $offer = $this->sampleOffer('dep-key-wins', 80294.0);
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection());
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertTrue($result->is_valid);
        $this->assertSame(85158.0, $report['confirmed_total']);
        $this->assertSame(1, $report['matched_offer_index']);
        $this->assertSame('fare_key_match', $report['matched_reason']);
        $this->assertTrue($report['price_changed']);
    }

    #[Test]
    public function test_multi_offer_response_without_fare_key_match_fails_without_summing(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_no_match.json')), true),
                200,
            ),
        ]);

        $offer = $this->sampleOffer('submitted-unknown-key', 80294.0);
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection());
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertFalse($result->is_valid);
        $this->assertSame('failed', $report['revalidation_status']);
        $this->assertNull($report['confirmed_total']);
        $this->assertFalse($report['price_changed']);
        $this->assertSame(0, $report['returned_offer_key_match_count']);
        $this->assertSame(0, $report['original_total_match_count']);
        $this->assertStringContainsString('multiple fare options', strtolower((string) $report['safe_customer_message']));
        $this->assertNotSame(295000.0, $report['confirmed_total']);
    }

    #[Test]
    public function test_branded_fare_option_swaps_departure_fare_key_before_revalidation(): void
    {
        $offer = $this->sampleOffer('base-key');
        $snapshot = $offer->toArray();
        $snapshot['branded_fares'] = [[
            'id' => 'iati_brand_1',
            'name' => 'Flex',
            'departure_fare_key' => 'brand-dep-key',
        ]];
        $normalized = NormalizedFlightOfferData::fromArray($snapshot);
        $connection = $this->connection();

        $linkage = app(IatiFareRevalidationService::class)->auditLinkageFromOffer($normalized, 'iati_brand_1');
        $this->assertTrue($linkage['fare_key_present']);

        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => function ($request) {
                $body = $request->data();
                if (($body['departure_fare_key'] ?? '') !== 'brand-dep-key') {
                    return Http::response(['message' => 'wrong key'], 422);
                }

                return Http::response([
                    'result' => [
                        'fare_detail_key' => 'fare-detail-brand',
                        'offers' => [
                            ['offer_key' => 'o1', 'fare_key' => 'other-brand', 'total_price' => 99000],
                            ['offer_key' => 'o2', 'fare_key' => 'brand-dep-key', 'total_price' => 355.0, 'currency' => 'USD'],
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = app(IatiFareRevalidationService::class)->revalidate($normalized, $connection, 'iati_brand_1');
        $report = app(IatiFareRevalidationService::class)->buildPublicRevalidationReport($result, $normalized->toArray(), 'iati_brand_1');

        $this->assertTrue($result->is_valid);
        $this->assertSame(355.0, $report['confirmed_total']);
        $this->assertSame('offers.1.total_price', $report['confirmed_total_source_path']);
        $this->assertSame(1, $report['matched_offer_index']);
        $this->assertSame('fare_key_match', $report['matched_reason']);
    }

    #[Test]
    public function test_option_key_selects_fare_two_total_and_departure_key(): void
    {
        $offer = $this->brandedMultiFareOffer();
        $optionKey = 'fare-2-85158-1';

        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => function ($request) {
                $body = $request->data();
                if (($body['departure_fare_key'] ?? '') !== 'dep-fare-2-key') {
                    return Http::response(['message' => 'wrong key'], 422);
                }

                return Http::response([
                    'result' => [
                        'fare_detail_key' => 'fare-detail-2',
                        'offers' => [
                            ['offer_key' => 'o2', 'fare_key' => 'dep-fare-2-key', 'total_price' => 85158, 'currency' => 'PKR'],
                        ],
                    ],
                ], 200);
            },
        ]);

        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection(), $optionKey);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray(), $optionKey);

        $this->assertTrue($result->is_valid);
        $this->assertSame(85158.0, $result->old_total);
        $this->assertSame(85158.0, $report['original_total']);
        $this->assertSame(85158.0, $report['confirmed_total']);
        $this->assertFalse($report['price_changed']);
        $this->assertTrue($report['selected_fare_option_matched']);
        $this->assertSame('option_key', $report['selected_fare_option_key_field']);
        $this->assertEquals(85158.0, $report['selected_fare_option_original_total']);
        $this->assertNotSame(80294.0, $report['confirmed_total']);
    }

    #[Test]
    public function test_selected_option_original_total_exact_match_picks_fare_two_from_multi_offer_response(): void
    {
        $offer = $this->brandedMultiFareOffer();
        $optionKey = 'fare-2-85158-1';

        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response([
                'result' => [
                    'fare_detail_key' => 'fare-detail-multi',
                    'offers' => [
                        ['offer_key' => 'o1', 'fare_key' => 'other-1', 'total_price' => 80294, 'currency' => 'PKR'],
                        ['offer_key' => 'o2', 'fare_key' => 'other-2', 'total_price' => 85158, 'currency' => 'PKR'],
                        ['offer_key' => 'o3', 'fare_key' => 'other-3', 'total_price' => 90098, 'currency' => 'PKR'],
                    ],
                ],
            ], 200),
        ]);

        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection(), $optionKey);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray(), $optionKey);

        $this->assertTrue($result->is_valid);
        $this->assertSame(85158.0, $report['original_total']);
        $this->assertSame(85158.0, $report['confirmed_total']);
        $this->assertSame('original_total_exact_match', $report['matched_reason']);
        $this->assertNotSame(80294.0, $report['confirmed_total']);
    }

    #[Test]
    public function test_unknown_selected_option_returns_failed_without_supplier_call(): void
    {
        Http::fake();
        $offer = $this->brandedMultiFareOffer();
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection(), 'missing-option-key');
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray(), 'missing-option-key');

        $this->assertFalse($result->is_valid);
        $this->assertSame('failed', $report['revalidation_status']);
        $this->assertFalse($report['selected_fare_option_matched']);
        $this->assertStringContainsString('choose the fare again', strtolower((string) $report['safe_customer_message']));
        Http::assertNothingSent();
    }

    #[Test]
    public function test_selected_option_missing_fare_key_returns_failed_without_supplier_call(): void
    {
        Http::fake();
        $offer = $this->brandedMultiFareOffer();
        $snapshot = $offer->toArray();
        $snapshot['branded_fares'][1]['departure_fare_key'] = '';
        $offer = NormalizedFlightOfferData::fromArray($snapshot);
        $optionKey = 'fare-2-85158-1';

        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection(), $optionKey);
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray(), $optionKey);

        $this->assertFalse($result->is_valid);
        $this->assertContains($report['revalidation_status'], ['failed', 'expired']);
        $this->assertFalse($report['selected_fare_option_fare_key_present']);
        Http::assertNothingSent();
    }

    #[Test]
    public function test_default_offer_without_selected_option_still_uses_base_fare(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response.json')), true),
                200,
            ),
        ]);

        $offer = $this->brandedMultiFareOffer();
        $service = app(IatiFareRevalidationService::class);
        $result = $service->revalidate($offer, $this->connection());
        $report = $service->buildPublicRevalidationReport($result, $offer->toArray());

        $this->assertTrue($result->is_valid);
        $this->assertSame(80294.0, $report['original_total']);
        $this->assertNull($report['selected_fare_option_matched']);
    }

    protected function brandedMultiFareOffer(): NormalizedFlightOfferData
    {
        $offer = $this->sampleOffer('dep-fare-1-key', 80294.0);
        $snapshot = $offer->toArray();
        $snapshot['fare_breakdown']['currency'] = 'PKR';
        $snapshot['branded_fares'] = [
            [
                'id' => 'iati_brand_0',
                'name' => 'Fare 1',
                'price_total' => 80294,
                'currency' => 'PKR',
                'departure_fare_key' => 'dep-fare-1-key',
            ],
            [
                'id' => 'iati_brand_1',
                'name' => 'Fare 2',
                'price_total' => 85158,
                'currency' => 'PKR',
                'departure_fare_key' => 'dep-fare-2-key',
            ],
            [
                'id' => 'iati_brand_2',
                'name' => 'Fare 3',
                'price_total' => 90098,
                'currency' => 'PKR',
                'departure_fare_key' => 'dep-fare-3-key',
            ],
        ];

        $resolved = FlightOfferDisplayPresenter::resolveSelectedFareFamilyOption($snapshot, 'fare-2-85158-1');
        $this->assertNotNull($resolved);
        $this->assertSame('option_key', $resolved['match_field']);

        return NormalizedFlightOfferData::fromArray($snapshot);
    }

    protected function connection(): SupplierConnection
    {
        return new SupplierConnection([
            'id' => 12,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'credentials' => ['auth_code' => 'direct-bearer-code', 'organization_id' => '187570'],
        ]);
    }

    protected function sampleOffer(string $departureFareKey, float $total = 355.0): NormalizedFlightOfferData
    {
        return new NormalizedFlightOfferData(
            offer_id: 'iati_test_offer_1',
            supplier_provider: SupplierProvider::Iati->value,
            supplier_connection_id: 12,
            airline_code: 'EK',
            airline_name: 'Emirates',
            flight_number: '601',
            origin: 'LHE',
            destination: 'DXB',
            departure_at: '2026-07-18T08:00:00Z',
            arrival_at: '2026-07-18T12:30:00Z',
            duration_minutes: 270,
            stops: 0,
            cabin: 'economy',
            fare_family: 'economy',
            refundable: true,
            seats_left: 9,
            segments: [
                ['origin' => 'LHE', 'destination' => 'DXB', 'booking_class' => 'Y'],
            ],
            baggage: new BaggageAllowanceData(checked: '30kg', cabin: '7kg', summary: '30kg checked'),
            fare_breakdown: new FareBreakdownData(
                base_fare: $total - 70,
                taxes: 70.0,
                supplier_fees: 0.0,
                supplier_total: $total,
                currency: 'USD',
                passenger_counts: ['adults' => 1, 'children' => 0, 'infants' => 0],
            ),
            raw_payload: [
                'provider_context' => [
                    'departure_fare_key' => $departureFareKey,
                    'pax_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                ],
            ],
        );
    }
}
