<?php

namespace Tests\Feature;

use App\Data\SupplierBookingResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Suppliers\BookingAdapters\DuffelSupplierBookingAdapter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class SupplierDiagnosticsRedactionTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_failed_supplier_booking_attempt_does_not_store_raw_payloads(): void
    {
        Http::fake();
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->once()->andReturn(new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Duffel->value,
                error_code: 'supplier_provider_error',
                error_message: 'Duffel failed Bearer super.secret.token for pax@example.com',
                request_payload: ['data' => ['passengers' => [['email' => 'pax@example.com']]]],
                response_payload: ['errors' => ['access_token' => 'tok_live', 'message' => 'fail']],
                safe_summary: [
                    'reason' => 'supplier_provider_error',
                    'request_payload' => ['raw' => true],
                    'http_status' => 422,
                ],
            ));
        });

        $admin = $this->platformAdmin();
        $booking = $this->eligibleDuffelBooking();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Duffel->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'transport_timeout',
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        app(BookingProviderRouter::class)->createSupplierBooking(
            $booking->fresh(),
            $admin,
            allowControlledStaffPnr: true,
            explicitRetry: true,
        );

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->latest('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('failed', $attempt->status);
        $this->assertNull($attempt->request_payload);
        $this->assertNull($attempt->response_payload);
        $this->assertArrayNotHasKey('request_payload', (array) $attempt->safe_summary);
        $this->assertStringContainsString('Bearer [REDACTED]', (string) $attempt->error_message);
        $this->assertStringContainsString('[REDACTED_EMAIL]', (string) $attempt->error_message);
    }

    public function test_sabre_cert_token_probe_command_output_excludes_credentials(): void
    {
        config([
            'suppliers.sabre.cert_stl.auth_url' => 'https://api.cert.sabre.com/v2/auth/token',
            'suppliers.sabre.cert_stl.profiles' => [
                'cert_test' => [
                    'user' => 'V1:cid:AA:ABC1',
                    'secret' => 'SUPER_SECRET_VALUE',
                    'pcc' => 'ABC1',
                    'domain' => 'AA',
                ],
            ],
        ]);

        Http::fake([
            'https://api.cert.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'eyJhbGciOiJIUzI1NiJ9.leaked',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        Artisan::call('sabre:cert-token-probe', ['--profile' => 'cert_test']);
        $output = Artisan::output();

        $this->assertStringContainsString('token_present=true', $output);
        $this->assertStringContainsString('http_status=200', $output);
        $this->assertStringNotContainsString('SUPER_SECRET_VALUE', $output);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9', $output);
        $this->assertStringNotContainsString('access_token=', $output);
        $this->assertStringNotContainsString('Basic ', $output);
    }

    public function test_admin_booking_show_does_not_render_raw_payload_keys(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'validated_offer_snapshot' => ['offer_id' => 'diag-redact'],
                'offer_validation_status' => 'valid',
            ],
        ]);

        $admin = $this->platformAdmin();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_booking_application_error',
            'error_message' => 'Application error Bearer leaked.token sig for pax@example.com',
            'safe_summary' => [
                'http_status' => 400,
                'endpoint_path' => '/v2.5/passenger/records',
                'payload_schema' => 'traditional_pnr_v1',
                'request_payload' => ['CreatePassengerNameRecordRQ' => ['secret' => true]],
                'response_payload' => ['access_token' => 'tok'],
                'response_error_messages' => ['NO FARES FOR CLASS'],
                'source' => 'admin',
                'reason' => 'sabre_booking_application_error',
            ],
            'attempted_by' => $admin->id,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('CreatePassengerNameRecordRQ', (string) $content);
        $this->assertStringNotContainsString('request_payload', (string) $content);
        $this->assertStringNotContainsString('response_payload', (string) $content);
        $this->assertStringNotContainsString('leaked.token', (string) $content);
        $this->assertStringNotContainsString('pax@example.com', (string) $content);
        $this->assertStringContainsString('http status', strtolower((string) $content));
    }

    protected function eligibleDuffelBooking(): Booking
    {
        $admin = $this->platformAdmin();
        $agencyId = (int) $admin->current_agency_id;
        $connection = SupplierConnection::query()
            ->where('agency_id', $agencyId)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        return Booking::factory()->create([
            'agency_id' => $agencyId,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => ['offer_id' => 'duffel-diag-redact'],
            ],
        ]);
    }
}
