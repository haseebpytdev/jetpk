<?php

namespace Tests\Feature;

use App\Data\OfferValidationResultData;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\OfferValidationService;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use App\Support\FlightSearch\SelectedFareContextAuditor;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class SelectedFareContextFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.iati.branded_fares_display_enabled', true);
        Config::set('suppliers.iati.branded_fares_selection_enabled', true);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function iatiBrandedOffer(string $departDate): array
    {
        $base = PublicCheckoutTestDoubles::searchOfferPayload($departDate);
        $base['supplier_provider'] = 'iati';
        $base['provider'] = 'iati';
        $base['offer_id'] = 'iati-branded-offer';
        $base['id'] = 'iati-branded-offer';
        $base['supplier_total_source'] = 80000;
        $base['final_customer_price'] = 84309;
        $base['raw_payload'] = [
            'provider_context' => [
                'departure_fare_key' => 'dep-fare-1-key',
                'return_fare_key' => null,
            ],
        ];
        $base['branded_fares'] = [
            [
                'name' => 'Fare 1',
                'price_total' => 80000,
                'currency' => 'PKR',
                'departure_fare_key' => 'dep-fare-1-key',
                'baggage_summary' => '20 kg',
                'cabin' => 'Economy',
            ],
            [
                'name' => 'Fare 2',
                'price_total' => 85158,
                'currency' => 'PKR',
                'departure_fare_key' => 'dep-fare-2-key',
                'baggage_summary' => '30 kg',
                'cabin' => 'Economy',
            ],
        ];

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    protected function iatiSingleOffer(string $departDate): array
    {
        $base = PublicCheckoutTestDoubles::searchOfferPayload($departDate);
        $base['supplier_provider'] = 'iati';
        $base['provider'] = 'iati';
        $base['offer_id'] = 'iati-single-offer';
        $base['id'] = 'iati-single-offer';
        $base['supplier_total_source'] = 89716;
        $base['final_customer_price'] = 89716;
        $base['raw_payload'] = [
            'provider_context' => [
                'departure_fare_key' => 'dep-single-key',
            ],
        ];

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseIatiGroupedMember(string $id, int $price, string $checkedKg, string $fareKey): array
    {
        return [
            'offer_id' => $id,
            'id' => $id,
            'supplier_provider' => 'iati',
            'supplier_connection_id' => 7,
            'validating_carrier' => 'PF',
            'primary_display_carrier' => 'PF',
            'airline_code' => 'PF',
            'cabin' => 'economy',
            'stops' => 0,
            'departure_at' => '2026-07-16T13:20:00',
            'arrive_at' => '2026-07-16T15:40:00',
            'depart_at' => '2026-07-16T13:20:00',
            'final_customer_price' => $price,
            'supplier_total_source' => $price - 1000,
            'refundable' => false,
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'baggage' => ['checked' => $checkedKg, 'cabin' => '7 kg'],
            'fare_breakdown' => [
                'supplier_total' => $price - 1000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'segments' => [[
                'airline_code' => 'PF',
                'operating_airline_code' => 'PF',
                'flight_number' => 'PF-752',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-07-16T13:20:00',
                'arrival_at' => '2026-07-16T15:40:00',
            ]],
            'raw_payload' => [
                'provider_context' => [
                    'departure_fare_key' => $fareKey,
                    'return_fare_key' => null,
                ],
            ],
        ];
    }

    protected function bindCheckout(array $offer, string $departDate): string
    {
        $validated = PublicCheckoutTestDoubles::validatedNormalizedOffer($departDate);
        $result = new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: (string) ($offer['offer_id'] ?? PublicCheckoutTestDoubles::OFFER_ID),
            validated_offer: $validated,
            currency: 'PKR',
            meta: ['pricing_snapshot' => PublicCheckoutTestDoubles::pricingSnapshot()],
        );

        $ovs = \Mockery::mock(OfferValidationService::class);
        $ovs->shouldReceive('validateSelectedOffer')->andReturn($result);
        $ovs->shouldReceive('pricingSnapshotForCachedOffer')->andReturn(PublicCheckoutTestDoubles::pricingSnapshot());
        $this->app->instance(OfferValidationService::class, $ovs);

        $flightSearch = \Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('search')->andReturn([$offer]);
        $flightSearch->shouldReceive('searchWithMeta')->andReturn(['offers' => [$offer], 'warnings' => []]);
        $this->app->instance(FlightSearchService::class, $flightSearch);

        return app(FlightSearchResultStore::class)->store(
            [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => $departDate,
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ],
            [$offer],
            [],
        );
    }

    public function test_iati_branded_fare_2_passengers_and_review_show_same_context(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->iatiBrandedOffer($depart);
        $options = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($offer);
        $fare2Key = (string) ($options[1]['option_key'] ?? '');
        $this->assertNotSame('', $fare2Key);

        $searchId = $this->bindCheckout($offer, $depart);

        $this->get('/booking/passengers?flight_id=iati-branded-offer'
            .'&offer_id=iati-branded-offer'
            .'&search_id='.$searchId
            .'&fare_option_key='.$fare2Key
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertSee('Fare 2', false)
            ->assertSee('30 kg', false)
            ->assertDontSee('20 kg', false);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => 'iati-branded-offer',
                'offer_id' => 'iati-branded-offer',
                'search_id' => $searchId,
                'fare_option_key' => $fare2Key,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $this->get(route('booking.review'))
            ->assertOk()
            ->assertSee('Fare 2', false)
            ->assertSee('30 kg', false)
            ->assertDontSee('20 kg', false);
    }

    public function test_grouped_iati_40kg_option_keeps_source_offer_not_cheapest_parent(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $offers = [
            $this->baseIatiGroupedMember('iati_offer_30', 85435, '30 kg', 'fare-key-30'),
            $this->baseIatiGroupedMember('iati_offer_40', 87735, '40 kg', 'fare-key-40'),
        ];
        $parent = ItineraryFareConsolidator::consolidate($offers)[0];
        $options = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($parent);
        $fortyKey = (string) ($options[1]['option_key'] ?? '');
        $this->assertNotSame('', $fortyKey);

        $selection = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($parent, $fortyKey);
        $this->assertNull($selection['error_code']);
        $this->assertSame('fare-key-40', data_get($selection['offer'], 'raw_payload.provider_context.departure_fare_key'));
        $this->assertSame('iati_offer_30', $selection['offer']['offer_id']);

        $searchId = $this->bindCheckout($parent, $depart);

        $this->get('/booking/passengers?flight_id=iati_offer_30'
            .'&offer_id=iati_offer_30'
            .'&search_id='.$searchId
            .'&fare_option_key='.$fortyKey
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertSee('40 kg', false)
            ->assertDontSee('30 kg', false);
    }

    public function test_iati_single_default_passengers_without_fare_option_key(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->iatiSingleOffer($depart);
        $searchId = $this->bindCheckout($offer, $depart);

        $this->get('/booking/passengers?flight_id=iati-single-offer'
            .'&offer_id=iati-single-offer'
            .'&search_id='.$searchId
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertSee('Passenger', false)
            ->assertDontSee('Selected fare family', false);
    }

    public function test_stale_fare_option_key_redirects_to_results_with_safe_message(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->iatiBrandedOffer($depart);
        $searchId = $this->bindCheckout($offer, $depart);

        $this->get('/booking/passengers?flight_id=iati-branded-offer'
            .'&offer_id=iati-branded-offer'
            .'&search_id='.$searchId
            .'&fare_option_key=unknown-stale-key'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertRedirect()
            ->assertSessionHasErrors('fare_option_key');
    }

    public function test_selected_fare_context_audit_reports_safe_fields(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->iatiBrandedOffer($depart);
        $options = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($offer);
        $fare2Key = (string) ($options[1]['option_key'] ?? '');

        $searchId = app(FlightSearchResultStore::class)->store(
            [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => $depart,
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ],
            [$offer],
            [],
        );

        $report = SelectedFareContextAuditor::buildReport($offer, $searchId, 'iati-branded-offer', $fare2Key, [
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);

        $this->assertSame('iati', $report['provider']);
        $this->assertSame('iati-branded-offer', $report['offer_id']);
        $this->assertSame($searchId, $report['search_id']);
        $this->assertTrue($report['selection_resolved']);
        $this->assertSame('Fare 2', $report['fare_option_name']);
        $this->assertSame('30 kg', $report['checked_baggage']);
        $this->assertFalse($report['supplier_mutation_attempted']);
        $this->assertArrayNotHasKey('departure_fare_key', $report);
    }

    public function test_audit_command_runs_for_cached_offer(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->iatiBrandedOffer($depart);
        $options = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($offer);
        $fare2Key = (string) ($options[1]['option_key'] ?? '');

        $searchId = app(FlightSearchResultStore::class)->store(
            [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => $depart,
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ],
            [$offer],
            [],
        );

        $this->artisan('ota:selected-fare-context-audit', [
            '--search-id' => $searchId,
            '--offer-id' => 'iati-branded-offer',
            '--fare-option-key' => $fare2Key,
        ])->assertSuccessful()
            ->expectsOutputToContain('provider=iati')
            ->expectsOutputToContain('fare_option_name=Fare 2');
    }
}
