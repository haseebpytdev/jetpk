<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\FlightSearch\SabreOfferFreshness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 18E: authoritative revalidation linkage through active controller path.
 */
class SabreGdsAuthoritativeRevalidationLinkagePhase18ETest extends TestCase
{
    use RefreshDatabase;

    private const LINKAGE_FIXTURE = 'tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'ota.offer_freshness.refresh_due_seconds' => 300,
            'ota.offer_freshness.stale_after_seconds' => 600,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.revalidate_payload_style' => 'bfm_revalidate_v1',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);
    }

    public function test_revalidate_selected_offer_live_path_preserves_itinerary_with_fake_http(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $connection = $this->activateSabreConnection();
        $fixture = $this->linkageFixture();
        $this->fakeRevalidateOnly(Http::response($fixture['response'], 200));

        $offer = $this->offerFromLinkageFixture($connection);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('offer_freshness.revalidation_status', 'success');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/revalidate'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'createBooking'));
        $this->assertDatabaseCount('bookings', 0);

        $payload = Cache::get('flight_search:'.$searchId);
        $this->assertIsArray($payload);
        $stored = collect($payload['offers'] ?? [])->first();
        $this->assertSame('success', $stored['selected_offer_revalidation_status'] ?? null);
        $this->assertSame('QR', $stored['validating_carrier'] ?? null);
        $this->assertCount(2, $stored['segments'] ?? []);
        $this->assertSame('LHE', $stored['segments'][0]['origin'] ?? null);
        $this->assertSame('JED', $stored['segments'][1]['destination'] ?? null);
        $this->assertSame('SLOW1', $stored['segments'][0]['fare_basis_code'] ?? $stored['segments'][0]['fare_basis'] ?? null);
    }

    public function test_stale_search_blocks_revalidate_endpoint(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $connection = $this->activateSabreConnection();
        $offer = $this->offerFromLinkageFixture($connection);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(20)->toIso8601String(), $offer);

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
        ]);

        $response->assertStatus(410);
        $response->assertJsonPath('status', 'offer_stale');
        Http::assertNothingSent();
    }

    public function test_revalidation_supplier_rejection_is_sanitized_and_skips_create_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $connection = $this->activateSabreConnection();
        $this->fakeRevalidateOnly(Http::response([
            'errors' => [['code' => 'ERR.NO.FARES', 'message' => 'NO FARES FOUND FOR REQUESTED ITINERARY']],
        ], 400));

        $offer = $this->offerFromLinkageFixture($connection);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $message = strtolower((string) $response->json('message'));
        $this->assertStringNotContainsString('no fares found', $message);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'createBooking'));
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_ambiguous_revalidation_response_does_not_confirm_offer(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $connection = $this->activateSabreConnection();
        $fixture = $this->linkageFixture();
        $response = $fixture['response'];
        $duplicate = $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][1];
        $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][] = $duplicate;
        $this->fakeRevalidateOnly(Http::response($response, 200));

        $offer = $this->offerFromLinkageFixture($connection);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function linkageFixture(): array
    {
        $fixture = json_decode((string) file_get_contents(base_path(self::LINKAGE_FIXTURE)), true);
        $this->assertIsArray($fixture);

        return $fixture;
    }

    protected function activateSabreConnection(): SupplierConnection
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return tap(
            SupplierConnection::query()
                ->where('agency_id', $agency->id)
                ->where('provider', SupplierProvider::Sabre)
                ->firstOrFail(),
            fn (SupplierConnection $conn) => $conn->update([
                'status' => SupplierConnectionStatus::Active,
                'is_active' => true,
                'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec', 'pcc' => 'TEST'],
            ]),
        );
    }

    protected function fakeRevalidateOnly(mixed $revalidateResponse): void
    {
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $revalidatePath = '/v4/shop/flights/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok-18e', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => $revalidateResponse,
            $sabreBase.'/*' => Http::response(['error' => 'create_pnr_forbidden_in_18e'], 500),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function offerFromLinkageFixture(SupplierConnection $connection): array
    {
        $draft = $this->linkageFixture()['api_draft'];
        $segments = [];
        foreach ($draft['segments'] as $segment) {
            $segments[] = [
                'origin' => $segment['origin'],
                'destination' => $segment['destination'],
                'departure_at' => $segment['departure_at'],
                'arrival_at' => $segment['arrival_at'],
                'carrier' => $segment['carrier'],
                'airline_code' => $segment['carrier'],
                'marketing_carrier' => $segment['carrier'],
                'operating_carrier' => $segment['carrier'],
                'flight_number' => $segment['flight_number'],
                'booking_class' => $segment['booking_class'],
                'fare_basis' => $segment['fare_basis_code'],
                'fare_basis_code' => $segment['fare_basis_code'],
                'cabin' => 'economy',
            ];
        }

        return [
            'id' => '18e-linkage-offer',
            'offer_id' => '18e-linkage-offer',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $connection->id,
            'validating_carrier' => $draft['validating_carrier'],
            'airline_code' => $draft['validating_carrier'],
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_at' => '2026-09-01T02:15:00',
            'arrive_at' => '2026-09-01T08:15:00',
            'final_customer_price' => 520.83,
            'currency' => 'USD',
            'conversion_status' => 'same_currency',
            'fare_breakdown' => [
                'base_fare' => 450.0,
                'taxes' => 70.83,
                'supplier_total' => 520.83,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'segments' => $segments,
            'raw_payload' => [
                'sabre_shop_context' => [
                    'leg_refs' => ['1', '2'],
                    'schedule_refs' => ['1', '2'],
                    'fare_basis_codes' => ['SLOW1', 'SLOW2'],
                    'validating_carrier' => $draft['validating_carrier'],
                ],
                'sabre_booking_context' => [
                    'has_revalidation_linkage' => true,
                    'leg_refs' => ['1', '2'],
                    'schedule_refs' => ['1', '2'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function storeSabreSearchPayload(string $createdAt, array $offer): string
    {
        $searchId = (string) Str::uuid();
        Cache::put('flight_search:'.$searchId, [
            'schema_version' => FlightSearchResultStore::PAYLOAD_SCHEMA_VERSION,
            'search_id' => $searchId,
            'criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'JED',
                'depart_date' => '2026-09-01',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'cabin' => 'economy',
                'source_channel' => 'public_guest',
            ],
            'offers' => [$offer],
            'warnings' => [],
            'created_at' => $createdAt,
            'search_created_at' => $createdAt,
        ], 1800);

        return $searchId;
    }
}
