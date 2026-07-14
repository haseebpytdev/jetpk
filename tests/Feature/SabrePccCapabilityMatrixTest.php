<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabrePccCapabilityMatrix;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SabrePccCapabilityMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:pcc-capability-matrix', Artisan::output());
    }

    public function test_blocked_outside_local_and_testing(): void
    {
        Config::set('app.env', 'production');
        $this->assertFalse(SabreInspectGate::allowed());

        $exit = Artisan::call('sabre:pcc-capability-matrix', ['--connection' => '1']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('only runs when APP_ENV is local or testing', Artisan::output());
    }

    public function test_inspect_only_does_not_send_booking_create_post(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:pcc-capability-matrix', [
            '--connection' => (string) $conn->id,
            '--json' => true,
        ]);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/v2/auth/token'));

        $payload = $this->decodeMatrixOutput(Artisan::output());
        $this->assertTrue($payload['inspect_only']);
        $this->assertFalse($payload['live_call_attempted']);

        foreach ((array) ($payload['rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['section'] ?? '') === 'passenger_records_cpnr') {
                $this->assertSame('inspect_only', $row['access_result']);
                $this->assertFalse($row['live_call_attempted'] ?? true);
            }
        }
    }

    public function test_destructive_endpoints_excluded(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:pcc-capability-matrix', [
            '--connection' => (string) $conn->id,
            '--json' => true,
        ]);

        $payload = $this->decodeMatrixOutput(Artisan::output());
        $destructive = array_values(array_filter(
            (array) ($payload['rows'] ?? []),
            static fn ($r): bool => is_array($r) && ($r['section'] ?? '') === 'destructive_excluded',
        ));
        $this->assertNotEmpty($destructive);
        foreach ($destructive as $row) {
            $this->assertSame('destructive_excluded', $row['access_result']);
            $this->assertFalse($row['live_call_attempted'] ?? true);
        }
        $paths = array_map(static fn (array $r): string => (string) ($r['endpoint_path'] ?? ''), $destructive);
        $this->assertTrue(
            collect($paths)->contains(static fn (string $p): bool => str_contains(strtolower($p), 'cancelbooking')
                || str_contains(strtolower($p), 'void')
                || str_contains(strtolower($p), 'refund')),
        );
    }

    public function test_send_respects_max_calls(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*' => Http::response(['errors' => [['code' => 'ERR.VALIDATION']]], 400),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:pcc-capability-matrix', [
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--max-calls' => 2,
            '--json' => true,
        ]);

        $payload = $this->decodeMatrixOutput(Artisan::output());
        $this->assertLessThanOrEqual(2, (int) ($payload['calls_made'] ?? 99));
    }

    public function test_certification_output_safety_allows_not_authorized_diagnostics(): void
    {
        $support = app(SabrePnrCertificationSupport::class);
        $support->assertOutputSafe([
            'rows' => [[
                'access_result' => 'not_authorized',
                'safe_error_code' => 'ERR.2SG.SEC.NOT_AUTHORIZED',
                'safe_error_message_truncated' => 'authorization failure',
                'entitlement_hint' => 'http_401_not_authorized',
            ]],
        ]);
    }

    public function test_certification_output_safety_rejects_authorization_bearer(): void
    {
        $support = app(SabrePnrCertificationSupport::class);
        try {
            $support->assertOutputSafe([
                'debug' => 'Authorization: Bearer abc123secret',
            ]);
            $this->fail('Expected safety check to reject Authorization: Bearer leak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Certification output failed safety check:', $e->getMessage());
            $this->assertTrue(
                str_contains($e->getMessage(), 'authorization')
                || str_contains($e->getMessage(), 'bearer '),
                'Expected authorization or bearer leak detection: '.$e->getMessage(),
            );
        }
    }

    public function test_certification_output_safety_rejects_access_token_leak(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('access_token');
        app(SabrePnrCertificationSupport::class)->assertOutputSafe([
            'oauth' => ['access_token' => 'live-secret-token-value'],
        ]);
    }

    public function test_v2_passengers_create_403_maps_to_not_authorized(): void
    {
        $result = SabrePccCapabilityMatrix::classifyMatrixAccessResult(
            403,
            null,
            '/v2/passengers/create',
            '',
            '',
        );
        $this->assertSame('not_authorized', $result);
    }

    public function test_trip_orders_agency_phone_missing_maps_to_profile_configuration_error(): void
    {
        $result = SabrePccCapabilityMatrix::classifyMatrixAccessResult(
            400,
            null,
            '/v1/trip/orders/createBooking',
            'AGENCY_PHONE_MISSING',
            'Agency phone is required',
        );
        $this->assertSame('profile_configuration_error', $result);
    }

    public function test_passenger_records_no_fares_maps_to_host_application_error(): void
    {
        $result = SabrePccCapabilityMatrix::classifyMatrixAccessResult(
            200,
            null,
            '/v2.5.0/passenger/records?mode=create',
            '',
            'NO FARES FOR CLASS USED',
        );
        $this->assertSame('host_application_error', $result);
    }

    public function test_json_output_redacts_secrets_and_pii(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'MATRIX_SECRET_TOKEN', 'expires_in' => 1800], 200),
        ]);
        $conn = $this->seedSabreConnection();
        $booking = $this->sabreBooking([
            'pnr' => 'SEC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
                'supplier_pnr' => 'SEC123',
                'contact_email' => 'secret@example.test',
            ],
        ]);

        Artisan::call('sabre:pcc-capability-matrix', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $out = Artisan::output();
        $payload = $this->decodeMatrixOutput($out);
        $encoded = json_encode($payload);
        $this->assertStringNotContainsString('MATRIX_SECRET_TOKEN', $encoded);
        $this->assertStringNotContainsString('SEC123', $encoded);
        $this->assertStringNotContainsString('secret@example.test', $encoded);
        $this->assertStringNotContainsString('client_secret', strtolower($encoded));
    }

    public function test_booking_inspect_uses_compare_without_live_booking_post(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
        ]);
        $conn = $this->seedSabreConnection();
        $booking = $this->sabreBooking([
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
                'normalized_offer_snapshot' => [
                    'segments' => [
                        ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'PK', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW'],
                    ],
                    'validating_carrier' => 'PK',
                    'total' => 100,
                    'currency' => 'PKR',
                ],
            ],
        ]);

        $mock = Mockery::mock(SabreBookingService::class)->makePartial();
        $mock->shouldReceive('compareBookingEndpointsForCommand')
            ->once()
            ->with(Mockery::type(Booking::class), false, false, null, null)
            ->andReturn([[
                'endpoint_path' => '/v2.5.0/passenger/records?mode=create',
                'payload_style' => 'traditional_pnr_create_passenger_name_record_v1',
                'access_result' => 'inspect_only',
                'wire_traditional_pnr_contract_valid' => true,
            ]]);
        $mock->shouldReceive('bookingCapabilityReportForCommand')->andReturn([
            'recommended_next_action' => 'use_passenger_records',
        ]);
        $mock->shouldNotReceive('compareBookingEndpointsForCommand')
            ->with(Mockery::any(), true, Mockery::any(), Mockery::any(), Mockery::any());
        $this->app->instance(SabreBookingService::class, $mock);

        Artisan::call('sabre:pcc-capability-matrix', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        Http::assertSentCount(1);
    }

    protected function seedSabreConnection(): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://example.sabre.test';
        $conn->credentials = ['client_id' => 'matrix_ci', 'client_secret' => 'matrix_cs', 'pcc' => 'TEST'];
        $conn->save();
        Cache::flush();

        return $conn;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function sabreBooking(array $overrides = []): Booking
    {
        $meta = array_merge([
            'supplier_provider' => SupplierProvider::Sabre->value,
        ], (array) ($overrides['meta'] ?? []));
        unset($overrides['meta']);

        return Booking::factory()->create(array_merge([
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => $meta,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeMatrixOutput(string $output): array
    {
        if (! preg_match('/pcc_capability_matrix_json=(.+)/s', trim($output), $matches)) {
            $this->fail('Expected pcc_capability_matrix_json= line in output: '.$output);
        }
        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
