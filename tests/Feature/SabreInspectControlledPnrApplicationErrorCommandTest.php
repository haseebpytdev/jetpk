<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreInspectControlledPnrApplicationErrorCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
    }

    public function test_inspect_command_is_read_only_and_outputs_digest_from_meta(): void
    {
        $booking = $this->bookingWithDigestMeta();

        Artisan::call('sabre:inspect-controlled-pnr-application-error', [
            '--booking' => (string) $booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('application_error_digest_available=true', $output);
        $this->assertStringContainsString('digest_status=incomplete_no_locator', $output);
        $this->assertStringContainsString('sabre_application_first_error_code=ERR.SP.PROVIDER_ERROR', $output);
        $this->assertStringNotContainsString('request_payload', $output);
        $this->assertStringNotContainsString('response_payload', $output);
        Http::assertNothingSent();
    }

    public function test_production_requires_exact_readonly_confirm(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->bookingWithDigestMeta();

        Artisan::call('sabre:inspect-controlled-pnr-application-error', [
            '--booking' => (string) $booking->id,
        ]);
        $this->assertStringContainsString('Production requires --confirm=', Artisan::output());

        Artisan::call('sabre:inspect-controlled-pnr-application-error', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'READONLY-CONTROLLED-PNR-APPLICATION-ERROR',
        ]);
        $this->assertStringContainsString('application_error_digest_available=true', Artisan::output());
    }

    public function test_inspect_falls_back_to_attempt_safe_summary_without_raw_payload(): void
    {
        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'PAR-F9G-FALLBACK',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 2,
            ],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => 2,
            'provider' => 'sabre',
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'error_message' => 'Application error',
            'safe_summary' => [
                'application_results_status' => 'Incomplete',
                'response_error_codes' => ['ERR.SP.PROVIDER_ERROR'],
                'response_error_messages' => ['Unable to perform air booking step'],
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        Artisan::call('sabre:inspect-controlled-pnr-application-error', [
            '--booking' => (string) $booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('digest_source=attempt_safe_summary_fallback', $output);
        $this->assertStringContainsString('application_error_digest_available=true', $output);
        $this->assertStringNotContainsString('response_payload', $output);
        $this->assertStringNotContainsString('request_body', $output);
    }

    protected function bookingWithDigestMeta(): Booking
    {
        $agency = Agency::query()->firstOrFail();
        $digest = [
            'status' => 'incomplete_no_locator',
            'application_status' => 'Incomplete',
            'has_record_locator' => false,
            'record_locator_present' => false,
            'error_count' => 1,
            'warning_count' => 0,
            'message_count' => 0,
            'errors' => [
                ['type' => 'error', 'code' => 'ERR.SP.PROVIDER_ERROR', 'message' => 'Unable to perform air booking step'],
            ],
            'warnings' => [],
            'messages' => [],
            'source' => 'passenger_records_create',
            'recorded_at' => now()->toIso8601String(),
        ];

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'PAR-F9G-DIGEST',
            'meta' => array_merge([
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 2,
            ], app(SabrePassengerRecordsApplicationResultDigest::class)->convenienceMetaFromDigest($digest), [
                SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => $digest,
            ]),
        ]);
    }
}
