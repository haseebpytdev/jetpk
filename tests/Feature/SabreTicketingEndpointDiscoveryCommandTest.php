<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\BookingTicket;
use App\Models\SupplierConnection;
use App\Models\TicketingAttempt;
use App\Services\Suppliers\Sabre\Diagnostics\SabreTicketingEndpointDiscovery;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreTicketingEndpointDiscoveryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:discover-ticketing-endpoints', Artisan::output());
    }

    public function test_blocked_outside_local_and_testing(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:discover-ticketing-endpoints', ['--connection' => '1']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('only runs when APP_ENV is local or testing', Artisan::output());
        $this->assertFalse(SabreInspectGate::allowed('production'));
    }

    public function test_inspect_only_prints_matrix_without_http_calls(): void
    {
        Config::set('app.env', 'testing');
        Http::fake();
        $conn = $this->seedSabreConnection();

        $booking = $this->sabreBooking([
            'pnr' => 'ABC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        Artisan::call('sabre:discover-ticketing-endpoints', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        Http::assertNothingSent();

        $payload = $this->decodeJsonOutput(Artisan::output());
        $this->assertFalse($payload['live_call_attempted']);
        $this->assertTrue($payload['pnr_present']);

        $getBooking = $this->findCandidate($payload, '/v1/trip/orders/getBooking');
        $this->assertSame('confirmation_id_probe', $getBooking['body_style']);
        $this->assertFalse($getBooking['live_call_attempted']);
        $this->assertSame('inspect_only', $getBooking['access_result']);

        $this->assertExcludedDestructiveDocumentsCancelBooking($payload);
        $this->assertNull($this->findCandidateOrNull($payload, '/v1/trip/orders/cancelBooking'));
    }

    public function test_destructive_custom_path_is_rejected(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:discover-ticketing-endpoints', [
            '--connection' => (string) $conn->id,
            '--path' => '/v1/trip/orders/cancelBooking',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('destructive endpoint', Artisan::output());
    }

    public function test_send_probes_get_booking_and_classifies_ready_on_200(): void
    {
        Config::set('app.env', 'testing');
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://example.sabre.test';
        $conn->credentials = ['client_id' => 't2b_ci', 'client_secret' => 't2b_cs'];
        $conn->save();
        Cache::flush();

        $secretToken = 'T2B_SECRET_TOKEN_MUST_NOT_LEAK';
        $booking = $this->sabreBooking([
            'pnr' => 'PNR200',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $calledUrls = [];
        Http::fake(function (Request $request) use ($secretToken, &$calledUrls) {
            $u = $request->url();
            $calledUrls[] = $u;
            if (str_contains($u, '/v2/auth/token')) {
                return Http::response(['access_token' => $secretToken, 'expires_in' => 600], 200);
            }
            if (str_contains($u, 'getBooking')) {
                $body = json_decode((string) $request->body(), true);
                $this->assertIsArray($body);
                $this->assertArrayHasKey('confirmationId', $body);
                $this->assertArrayNotHasKey('passengers', $body);
                $this->assertArrayNotHasKey('payment', $body);
                $this->assertArrayNotHasKey('fop', $body);

                return Http::response(['order' => ['isTicketed' => false]], 200);
            }
            if (str_contains($u, 'cancelBooking')) {
                $this->fail('cancelBooking must never be probed');
            }

            return Http::response(['errors' => [['code' => 'ERR.FORBIDDEN']]], 403);
        });

        $beforeAttempts = TicketingAttempt::query()->count();
        $beforeTickets = BookingTicket::query()->count();

        Artisan::call('sabre:discover-ticketing-endpoints', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--json' => true,
            '--max-calls' => 3,
        ]);

        $payload = $this->decodeJsonOutput(Artisan::output());
        $encoded = json_encode($payload);

        $this->assertTrue($payload['live_call_attempted']);
        $getBooking = $this->findCandidate($payload, '/v1/trip/orders/getBooking');
        $this->assertTrue($getBooking['live_call_attempted']);
        $this->assertSame(200, $getBooking['http_status']);
        $this->assertSame('ready', $getBooking['access_result']);

        $this->assertStringNotContainsString('cancelBooking', implode("\n", $calledUrls));
        $this->assertStringNotContainsString($secretToken, $encoded);
        $this->assertStringNotContainsString('PNR200', $encoded);

        $this->assertSame($beforeAttempts, TicketingAttempt::query()->count());
        $this->assertSame($beforeTickets, BookingTicket::query()->count());
    }

    public function test_403_classifies_forbidden_and_404_not_found(): void
    {
        $this->assertSame('forbidden', SabreTicketingEndpointDiscovery::ticketingDiscoveryAccessResult(403, null));
        $this->assertSame('not_found', SabreTicketingEndpointDiscovery::ticketingDiscoveryAccessResult(404, null));
        $this->assertSame('not_authorized', SabreTicketingEndpointDiscovery::ticketingDiscoveryAccessResult(401, null));
        $this->assertSame('transport_error', SabreTicketingEndpointDiscovery::ticketingDiscoveryAccessResult(0, 'timeout'));
    }

    public function test_send_with_http_fake_classifies_forbidden_on_ticketing_candidate(): void
    {
        Config::set('app.env', 'testing');
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://example.sabre.test';
        $conn->credentials = ['client_id' => 't2b_ci2', 'client_secret' => 't2b_cs2'];
        $conn->save();
        Cache::flush();

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 600], 200);
            }
            if (str_contains($request->url(), '/v1/air/ticket')) {
                return Http::response(['errors' => [['code' => 'ERR.FORBIDDEN']]], 403);
            }

            return Http::response([], 404);
        });

        Artisan::call('sabre:discover-ticketing-endpoints', [
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--json' => true,
            '--max-calls' => 5,
        ]);

        $payload = $this->decodeJsonOutput(Artisan::output());
        $air = $this->findCandidate($payload, '/v1/air/ticket');
        $this->assertSame('forbidden', $air['access_result']);
        $this->assertSame(403, $air['http_status']);
    }

    public function test_json_output_excludes_pii_and_raw_body(): void
    {
        Config::set('app.env', 'testing');
        Http::fake();
        $conn = $this->seedSabreConnection();

        $booking = $this->sabreBooking([
            'pnr' => 'SEC999',
            'booking_reference' => 'OTA-SEC',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
                'contact_email' => 'secret@example.test',
            ],
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'passenger_index' => 0,
        ]);

        Artisan::call('sabre:discover-ticketing-endpoints', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $out = Artisan::output();
        $payload = $this->decodeJsonOutput($out);
        $encoded = json_encode($payload);

        $this->assertStringContainsString('ticketing_endpoint_discovery_json=', $out);
        $this->assertStringNotContainsString('SEC999', $encoded);
        $this->assertStringNotContainsString('secret@example.test', $encoded);
        $this->assertStringNotContainsString('Jane', $encoded);
        $this->assertStringNotContainsString('Authorization', $encoded);
        $this->assertArrayNotHasKey('raw', $payload);
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
        $conn->credentials = ['client_id' => 't2b_ci', 'client_secret' => 't2b_cs'];
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

        $booking = Booking::factory()->create(array_merge([
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => $meta,
        ], $overrides));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonOutput(string $output): array
    {
        if (! preg_match('/ticketing_endpoint_discovery_json=(.+)/s', trim($output), $matches)) {
            $this->fail('Expected ticketing_endpoint_discovery_json= line in output: '.$output);
        }

        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertExcludedDestructiveDocumentsCancelBooking(array $payload): void
    {
        $excluded = (array) ($payload['excluded_destructive_endpoints'] ?? []);
        $paths = array_map(
            static fn (array $row): string => (string) ($row['endpoint_path'] ?? ''),
            array_filter($excluded, is_array(...)),
        );
        $this->assertContains('/v1/trip/orders/cancelBooking', $paths);
        foreach ($excluded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $this->assertSame('excluded_destructive', $row['status'] ?? '');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function findCandidateOrNull(array $payload, string $pathFragment): ?array
    {
        foreach ((array) ($payload['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            if (str_contains((string) ($candidate['endpoint_path'] ?? ''), $pathFragment)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function findCandidate(array $payload, string $pathFragment): array
    {
        foreach ((array) ($payload['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            if (str_contains((string) ($candidate['endpoint_path'] ?? ''), $pathFragment)) {
                return $candidate;
            }
        }

        $this->fail('Candidate not found for path fragment: '.$pathFragment);
    }
}
