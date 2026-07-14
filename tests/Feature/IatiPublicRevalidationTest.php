<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Iati\IatiFareRevalidationService;
use App\Services\Suppliers\Iati\IatiSelectedOfferRevalidationGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class IatiPublicRevalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);
        $this->ensureIatiConnection();
    }

    #[Test]
    public function test_public_revalidate_endpoint_accepts_iati_offer_and_returns_safe_json(): void
    {
        $offer = $this->iatiCachedOffer();
        $searchId = $this->storeSearch($offer);

        $report = [
            'revalidation_status' => 'valid',
            'provider' => 'iati',
            'original_offer_id' => $offer['offer_id'],
            'original_total' => 89716.0,
            'confirmed_total' => 89716.0,
            'price_changed' => false,
            'baggage_confirmed' => true,
            'booking_class_confirmed' => true,
            'fare_rules_confirmed' => true,
            'revalidation_endpoint' => '/fare',
            'revalidation_http_status' => 200,
            'supplier_mutation_attempted' => false,
            'booking_created' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
            'emails_sent' => false,
            'safe_customer_message' => 'Fare confirmed with the airline.',
            'has_fare_key' => true,
            'fare_key_present' => true,
        ];

        $this->mock(IatiSelectedOfferRevalidationGate::class, function ($mock) use ($report, $offer): void {
            $mock->shouldReceive('refreshSelectedOffer')
                ->once()
                ->andReturn([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Fare confirmed with the airline.',
                    'block_code' => null,
                    'diagnostic' => null,
                    'revalidation' => $report,
                    'meta_patch' => [
                        'selected_offer_revalidation_status' => 'success',
                        'revalidation_status' => 'success',
                        'iati_revalidation_status' => 'valid',
                        'fare_breakdown' => $offer['fare_breakdown'],
                        'raw_payload' => $offer['raw_payload'],
                    ],
                ]);
        });

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['offer_id'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('revalidation.provider', 'iati');
        $response->assertJsonPath('revalidation.revalidation_status', 'valid');
        $response->assertJsonPath('revalidation.booking_created', false);
        $response->assertJsonPath('revalidation.supplier_mutation_attempted', false);
        $this->assertArrayNotHasKey('fare_detail_key', $response->json('revalidation') ?? []);
        $this->assertArrayNotHasKey('raw_payload', $response->json('revalidation') ?? []);
        $this->assertNotEmpty($response->json('passengers_url'));
    }

    #[Test]
    public function test_public_revalidate_endpoint_does_not_invoke_booking_services(): void
    {
        $offer = $this->iatiCachedOffer();
        $searchId = $this->storeSearch($offer);

        $this->mock(IatiFareRevalidationService::class, function ($mock): void {
            $mock->shouldReceive('revalidate')->never();
            $mock->shouldReceive('buildPublicRevalidationReport')->never();
        });

        $this->mock(IatiSelectedOfferRevalidationGate::class, function ($mock): void {
            $mock->shouldReceive('refreshSelectedOffer')->once()->andReturn([
                'success' => false,
                'status' => 'failed',
                'message' => 'We could not confirm this fare with the airline.',
                'block_code' => 'selected_offer_revalidation_failed',
                'diagnostic' => 'iati_fare_confirmation_failed',
                'revalidation' => [
                    'revalidation_status' => 'failed',
                    'provider' => 'iati',
                    'booking_created' => false,
                    'supplier_mutation_attempted' => false,
                ],
                'meta_patch' => ['selected_offer_revalidation_status' => 'failed'],
            ]);
        });

        $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['offer_id'],
        ])->assertStatus(422);
    }

    #[Test]
    public function test_public_revalidate_endpoint_returns_changed_status_with_price_fields(): void
    {
        $offer = $this->iatiCachedOffer();
        $searchId = $this->storeSearch($offer);

        $report = [
            'revalidation_status' => 'changed',
            'provider' => 'iati',
            'original_total' => 80294.0,
            'confirmed_total' => 82500.0,
            'price_changed' => true,
            'supplier_mutation_attempted' => false,
            'booking_created' => false,
            'safe_customer_message' => 'Fare price has changed. Please review before continuing.',
        ];

        $this->mock(IatiSelectedOfferRevalidationGate::class, function ($mock) use ($report, $offer): void {
            $mock->shouldReceive('refreshSelectedOffer')
                ->once()
                ->andReturn([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Fare price has changed. Please review before continuing.',
                    'revalidation' => $report,
                    'meta_patch' => [
                        'selected_offer_revalidation_status' => 'success',
                        'iati_revalidation_status' => 'changed',
                        'fare_breakdown' => $offer['fare_breakdown'],
                    ],
                ]);
        });

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['offer_id'],
            'provider' => 'iati',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('revalidation.revalidation_status', 'changed');
        $response->assertJsonPath('revalidation.original_total', 80294);
        $response->assertJsonPath('revalidation.confirmed_total', 82500);
        $response->assertJsonPath('revalidation.booking_created', false);
        $this->assertNotEmpty($response->json('passengers_url'));
    }

    #[Test]
    public function test_public_revalidate_endpoint_blocks_expired_iati_offer(): void
    {
        $offer = $this->iatiCachedOffer();
        $searchId = $this->storeSearch($offer);

        $this->mock(IatiSelectedOfferRevalidationGate::class, function ($mock): void {
            $mock->shouldReceive('refreshSelectedOffer')->once()->andReturn([
                'success' => false,
                'status' => 'expired',
                'message' => 'This fare is no longer available. Please search again or choose another fare.',
                'revalidation' => [
                    'revalidation_status' => 'expired',
                    'provider' => 'iati',
                    'booking_created' => false,
                    'supplier_mutation_attempted' => false,
                ],
                'meta_patch' => ['selected_offer_revalidation_status' => 'failed'],
            ]);
        });

        $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['offer_id'],
            'provider' => 'iati',
        ])
            ->assertStatus(410)
            ->assertJsonPath('success', false)
            ->assertJsonPath('revalidation.revalidation_status', 'expired');
    }

    #[Test]
    public function test_public_revalidate_endpoint_forwards_selected_fare_option_id_to_gate(): void
    {
        $offer = $this->iatiCachedOffer();
        $searchId = $this->storeSearch($offer);
        $fareOptionId = 'branded-fare-key-1';

        $this->mock(IatiSelectedOfferRevalidationGate::class, function ($mock) use ($fareOptionId, $offer): void {
            $mock->shouldReceive('refreshSelectedOffer')
                ->once()
                ->withArgs(function ($agency, $cachedOffer, $criteria, $payload, $selectedFareOptionId, $searchId) use ($fareOptionId, $offer): bool {
                    return $selectedFareOptionId === $fareOptionId
                        && ($cachedOffer['offer_id'] ?? null) === $offer['offer_id'];
                })
                ->andReturn([
                    'success' => true,
                    'status' => 'success',
                    'revalidation' => [
                        'revalidation_status' => 'valid',
                        'provider' => 'iati',
                        'selected_fare_option_id' => $fareOptionId,
                        'booking_created' => false,
                    ],
                    'meta_patch' => [],
                ]);
        });

        $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['offer_id'],
            'provider' => 'iati',
            'selected_fare_option_id' => $fareOptionId,
        ])->assertOk();
    }

    #[Test]
    public function test_desktop_results_page_wires_iati_select_revalidation_before_passenger_handoff(): void
    {
        $this->mockFlightSearchForResultsPage();

        $this->get('/flights/results?from=LHE&to=DXB&depart=2026-07-18&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertSee('beginIatiSelectRevalidation', false)
            ->assertSee('isIatiProviderOffer', false)
            ->assertSee('data-iati-price-change-prompt', false)
            ->assertSee('selected_fare_option_id', false);
    }

    #[Test]
    public function test_sabre_offer_still_uses_sabre_revalidation_path(): void
    {
        $offer = PublicCheckoutTestDoubles::searchOfferPayload('2026-07-18');
        $offer['supplier_provider'] = 'sabre';
        $offer['id'] = 'sabre_gate_offer_1';
        $offer['offer_id'] = 'sabre_gate_offer_1';
        $searchId = $this->storeSearch($offer);

        $this->mock(IatiSelectedOfferRevalidationGate::class, function ($mock): void {
            $mock->shouldReceive('refreshSelectedOffer')->never();
        });

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['offer_id'],
        ]);

        $response->assertJsonMissing(['status' => 'unsupported_supplier']);
        $this->assertNotSame(
            'This fare needs to be refreshed because airline prices and availability can change quickly.',
            $response->json('message'),
        );
    }

    protected function mockFlightSearchForResultsPage(): void
    {
        $offer = PublicCheckoutTestDoubles::searchOfferPayload('2026-07-18');
        $offer['supplier_provider'] = 'iati';
        $offer['provider'] = 'iati';

        $mock = \Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn([
            'offers' => [$offer],
            'warnings' => [],
        ]);
        $mock->shouldReceive('search')->andReturn([$offer]);
        $this->instance(FlightSearchService::class, $mock);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function storeSearch(array $offer): string
    {
        $searchId = (string) Str::uuid();
        Cache::put('flight_search:'.$searchId, [
            'search_id' => $searchId,
            'criteria' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-07-18',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'trip_type' => 'one_way',
                'cabin' => 'economy',
            ],
            'offers' => [$offer],
            'warnings' => [],
            'created_at' => now()->toIso8601String(),
        ], 1800);

        return $searchId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function iatiCachedOffer(): array
    {
        $offer = PublicCheckoutTestDoubles::searchOfferPayload('2026-07-18');
        $offer['supplier_provider'] = 'iati';
        $offer['provider'] = 'iati';
        $offer['offer_id'] = 'iati_reval_offer_1';
        $offer['id'] = 'iati_reval_offer_1';
        $offer['supplier_connection_id'] = SupplierConnection::query()
            ->where('provider', SupplierProvider::Iati)
            ->value('id');
        $offer['raw_payload'] = [
            'provider_context' => [
                'departure_fare_key' => 'dep-live-key',
                'pax_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
        $offer['fare_breakdown'] = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [
            'base_fare' => 80000.0,
            'taxes' => 9716.0,
            'supplier_total' => 89716.0,
            'currency' => 'PKR',
            'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
        ];
        $offer['baggage'] = ['checked' => '30kg', 'cabin' => '7kg'];

        return $offer;
    }

    protected function ensureIatiConnection(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        if (SupplierConnection::query()->where('provider', SupplierProvider::Iati)->exists()) {
            return;
        }

        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'auth_code' => 'code',
                'organization_id' => 'org',
                'secret' => 'secret',
            ],
        ]);
    }
}
