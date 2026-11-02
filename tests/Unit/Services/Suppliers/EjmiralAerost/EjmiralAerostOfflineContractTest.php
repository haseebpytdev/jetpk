<?php

namespace Tests\Unit\Services\Suppliers\EjmiralAerost;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostAuthService;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostBookingService;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostClient;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostConfigResolver;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostFareInfoService;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostOfferTokenSigner;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostProfileService;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostPnrService;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostResponseNormalizer;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostSearchService;
use App\Services\Suppliers\EjmiralAerost\EjmiralAerostTicketingService;
use App\Services\Suppliers\SupplierAdapterResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EjmiralAerostOfflineContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_resolution_requires_sub_agent_credentials(): void
    {
        $connection = $this->connection([
            'username' => '',
            'password' => '',
            'api_base_url' => 'https://aerost.example.test',
        ]);

        $this->expectException(\App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostValidationException::class);
        app(EjmiralAerostConfigResolver::class)->resolve($connection);
    }

    public function test_login_token_capture_and_reuse(): void
    {
        Cache::flush();
        Http::fake([
            'https://aerost.example.test/api/ota/v1/login' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/EjmiralAerost/login_success.json')), true)
            ),
        ]);

        $connection = $this->connection();
        $auth = app(EjmiralAerostAuthService::class);
        $first = $auth->getBearerToken($connection);
        $second = $auth->getBearerToken($connection);

        $this->assertSame('AEROST_FIXTURE_TOKEN_MASKED', $first);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_401_refresh_flow(): void
    {
        Cache::flush();
        Http::fake([
            'https://aerost.example.test/api/ota/v1/login' => Http::sequence()
                ->push(['token' => 'TOKEN_ONE'])
                ->push(['token' => 'TOKEN_TWO']),
            'https://aerost.example.test/api/ota/v1/my-profile' => Http::sequence()
                ->push([], 401)
                ->push(['office' => 'UAT', 'currency' => 'PKR', 'account_type' => 'sub_agent', 'available_limit' => 1000], 200),
        ]);

        $connection = $this->connection();
        $profile = app(EjmiralAerostProfileService::class)->myProfile($connection);
        $this->assertSame('UAT', $profile['office']);
        $this->assertTrue($profile['balance_ready']);
    }

    public function test_search_request_and_opaque_normalization(): void
    {
        Cache::flush();
        Http::fake([
            'https://aerost.example.test/api/ota/v1/login' => Http::response(['token' => 'TOKEN']),
            'https://aerost.example.test/api/ota/v1/res/flights' => Http::response(
                json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/EjmiralAerost/search_oneway.json')), true)
            ),
        ]);

        config(['suppliers.ejmiral_aerost.live_search_enabled' => true]);
        $connection = $this->connection();
        $result = app(EjmiralAerostSearchService::class)->search(
            new FlightSearchRequestData(
                origin: 'LHE',
                destination: 'DXB',
                departure_date: now()->addMonths(2)->toDateString(),
                return_date: null,
                adults: 1,
                children: 0,
                infants: 0,
                cabin: 'economy',
                trip_type: 'one_way',
            ),
            $connection,
        );

        $this->assertCount(1, $result->offers);
        $this->assertTrue((bool) ($result->meta['has_nld'] ?? false));
        $token = (string) data_get($result->offers[0]->raw_payload, 'provider_context.ejmiral_aerost.offer_token');
        $payload = app(EjmiralAerostOfferTokenSigner::class)->verify($token);
        $this->assertSame('NLD_FIXTURE_OPAQUE', data_get($payload, 'opaque.nld'));
        $this->assertSame(['ACCEPT_HASH_OUTBOUND'], data_get($payload, 'opaque.acceptance'));
        $this->assertStringNotContainsString('NLD_FIXTURE_OPAQUE', $result->offers[0]->offer_id);
    }

    public function test_pspt_nati_passenger_mapping_and_booking_request(): void
    {
        $booking = new Booking;
        $booking->meta = [];
        $booking->passengers = [
            [
                'first_name' => 'TEST',
                'last_name' => 'ADULT',
                'id_type' => 'NATI',
                'document' => '12345-1234567-1',
            ],
            [
                'first_name' => 'TEST',
                'last_name' => 'CHILD',
                'document_type' => 'passport',
                'document_number' => 'AB1234567',
            ],
        ];
        $token = app(EjmiralAerostOfferTokenSigner::class)->sign([
            'supplier' => SupplierProvider::EjmiralAerost->value,
            'opaque' => ['nld' => 'NLD1', 'acceptance' => ['A1', 'A2']],
            'expires_at' => time() + 600,
        ]);
        $booking->meta = ['ejmiral_aerost' => ['offer_token' => $token]];

        $payload = app(EjmiralAerostBookingService::class)->buildBookingPayload($booking);
        $this->assertSame('NLD1', $payload['NLD']);
        $this->assertSame(['A1', 'A2'], $payload['acceptance']);
        $this->assertSame('NATI', $payload['passengers'][0]['id_type']);
        $this->assertSame('PSPT', $payload['passengers'][1]['id_type']);
    }

    public function test_fare_info_profile_pnr_ticketing_request_shapes(): void
    {
        Cache::flush();
        Http::fake([
            'https://aerost.example.test/api/ota/v1/login' => Http::response(['token' => 'TOKEN']),
            'https://aerost.example.test/api/ota/v1/res/fare_info' => Http::response([
                'base_fare' => 40000,
                'YQ' => 2000,
                'YR' => 1000,
                'tax' => 3000,
                'total' => 46000,
                'currency' => 'PKR',
            ]),
            'https://aerost.example.test/api/ota/v1/res/pnr_info/*' => Http::response([
                'status' => 'reserved',
                'pax_count' => 1,
                'time_limit' => '2026-12-01T10:00:00',
                'route' => 'LHE-DXB',
                'ticket_ref' => 'TREF_MASKED',
            ]),
            'https://aerost.example.test/api/ota/v1/res/ticketing' => Http::response([
                'booking_status' => 'ticketed',
                'pnr' => 'PNR_MASKED',
                'invoice_number' => 'INV_MASKED',
            ]),
        ]);

        config(['suppliers.ejmiral_aerost.live_ticketing_enabled' => true]);
        $connection = $this->connection();

        $fare = app(EjmiralAerostFareInfoService::class)->fareInfo($connection, [
            'nld' => 'NLD1',
            'acceptance' => 'HASH1',
        ]);
        $this->assertSame(46000.0, $fare['total']);
        $this->assertSame(40000.0, $fare['base_fare']);

        $pnr = app(EjmiralAerostPnrService::class)->pnrInfo($connection, 'ABCDEF');
        $this->assertTrue($pnr['ticket_ref_present']);
        $this->assertSame(1, $pnr['pax_count']);

        $ticket = app(EjmiralAerostTicketingService::class)->ticket($connection, 'TREF_MASKED', 46000);
        $this->assertTrue($ticket['success']);
        $this->assertTrue($ticket['invoice_present']);
    }

    public function test_adapter_resolver_isolation_from_one_api(): void
    {
        $resolver = app(SupplierAdapterResolver::class);
        $this->assertSame(
            SupplierProvider::EjmiralAerost,
            $resolver->resolve(SupplierProvider::EjmiralAerost)->provider()
        );
        $this->assertSame(
            SupplierProvider::OneApi,
            $resolver->resolve(SupplierProvider::OneApi)->provider()
        );
    }

    public function test_no_credential_leak_in_offer_token_payload_keys(): void
    {
        $signer = app(EjmiralAerostOfferTokenSigner::class);
        $token = $signer->sign([
            'opaque' => ['nld' => 'N', 'acceptance' => ['A']],
            'expires_at' => time() + 100,
        ]);
        $this->assertStringNotContainsString('password', strtolower($token));
        $this->assertStringNotContainsString('bearer', strtolower($token));
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function connection(array $overrides = []): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::EjmiralAerost,
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => array_merge([
                'username' => 'uat_sub_agent',
                'password' => 'uat_password_fixture',
                'api_base_url' => 'https://aerost.example.test',
            ], $overrides),
        ]);
    }
}
