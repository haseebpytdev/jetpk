<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\ComplexItineraryPolicy;
use App\Support\Bookings\SabrePnrFailureClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SabreClassifyPnrFailureCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.env', 'testing');
    }

    public function test_booking_15_style_uc_classifies_as_host_sell_rejected(): void
    {
        $booking = $this->sabreBooking();
        $attempt = $this->createPnrAttempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => [
                    'Segment SV739 returned status code UC',
                    'HALT_ON_STATUS_RECEIVED',
                ],
            ],
        ]);

        $exit = Artisan::call('sabre:classify-pnr-failure', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame($booking->id, $payload['booking_id']);
        $this->assertSame($attempt->id, $payload['latest_attempt_id']);
        $this->assertSame('latest_attempt', $payload['source']);
        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_REJECTED_UC, $payload['classification']);
        $this->assertFalse($payload['retry_allowed']);
        $this->assertStringContainsString('host refused', strtolower((string) $payload['staff_message']));
    }

    public function test_booking_16_style_no_fares_classifies_as_no_fares_rbd_carrier(): void
    {
        $booking = $this->sabreBooking();
        $this->createPnrAttempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            ],
        ]);

        $exit = Artisan::call('sabre:classify-pnr-failure', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_NO_FARES_RBD_CARRIER, $payload['classification']);
        $this->assertFalse($payload['retry_allowed']);
        $this->assertStringContainsString('search again', strtolower((string) $payload['staff_message']));
    }

    public function test_complex_deferred_classifies_from_policy_or_attempt(): void
    {
        config(['suppliers.sabre.complex_itinerary_pnr_enabled' => false]);

        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'round_trip'],
            ],
        ]);

        $this->createPnrAttempt($booking, [
            'status' => 'needs_review',
            'error_code' => ComplexItineraryPolicy::ERROR_CODE,
            'safe_summary' => ['supplier_pnr_deferred_reason' => ComplexItineraryPolicy::DEFER_REASON],
        ]);

        $exit = Artisan::call('sabre:classify-pnr-failure', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_COMPLEX_DEFERRED, $payload['classification']);
        $this->assertFalse($payload['retry_allowed']);
        $this->assertContains($payload['source'], ['complex_policy', 'latest_attempt']);
        $this->assertStringContainsString('deferred', strtolower((string) $payload['staff_message']));
    }

    public function test_missing_booking_returns_clean_error(): void
    {
        $exit = Artisan::call('sabre:classify-pnr-failure', ['--booking' => 999999]);
        $payload = $this->decodeOutput();

        $this->assertSame(1, $exit);
        $this->assertSame('booking_not_found', $payload['error']);
        $this->assertSame(999999, $payload['booking_id']);
    }

    public function test_picks_latest_create_pnr_when_no_certification_attempt(): void
    {
        $booking = $this->sabreBooking();
        $older = $this->createAttempt($booking, 'create_pnr', [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => ['response_error_messages' => ['old']],
        ]);
        $latest = $this->createAttempt($booking, 'create_pnr', [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            ],
        ]);

        $exit = Artisan::call('sabre:classify-pnr-failure', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame($latest->id, $payload['latest_attempt_id']);
        $this->assertSame('create_pnr', $payload['latest_attempt_action']);
        $this->assertNotSame($older->id, $payload['latest_attempt_id']);
    }

    public function test_picks_newer_certification_attempt_by_default(): void
    {
        $booking = $this->sabreBooking();
        $createPnr = $this->createAttempt($booking, 'create_pnr', [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => ['response_error_messages' => ['older create_pnr']],
        ]);
        $certification = $this->createAttempt($booking, 'create_pnr_certification', [
            'status' => 'needs_review',
            'error_code' => 'sabre_passenger_records_stale_shop_segment',
            'safe_summary' => [
                'probable_issue' => 'booking_class_mismatch',
                'segments' => [
                    ['probable_issue' => 'booking_class_mismatch', 'fresh_same_rbd_found' => false],
                ],
            ],
        ]);

        $exit = Artisan::call('sabre:classify-pnr-failure', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame($certification->id, $payload['latest_attempt_id']);
        $this->assertSame('create_pnr_certification', $payload['latest_attempt_action']);
        $this->assertNotSame($createPnr->id, $payload['latest_attempt_id']);
        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_BOOKING_CLASS_MISMATCH, $payload['classification']);
        $this->assertFalse($payload['retry_allowed']);
    }

    public function test_action_create_pnr_returns_latest_normal_attempt_only(): void
    {
        $booking = $this->sabreBooking();
        $createPnr = $this->createAttempt($booking, 'create_pnr', [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => ['response_error_messages' => ['normal pnr']],
        ]);
        $this->createAttempt($booking, 'create_pnr_certification', [
            'status' => 'needs_review',
            'error_code' => 'sabre_passenger_records_stale_shop_segment',
            'safe_summary' => ['probable_issue' => 'booking_class_mismatch'],
        ]);

        $exit = Artisan::call('sabre:classify-pnr-failure', [
            '--booking' => $booking->id,
            '--action' => 'create_pnr',
        ]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame($createPnr->id, $payload['latest_attempt_id']);
        $this->assertSame('create_pnr', $payload['latest_attempt_action']);
    }

    public function test_action_create_pnr_certification_returns_latest_certification_attempt(): void
    {
        $booking = $this->sabreBooking();
        $this->createAttempt($booking, 'create_pnr', [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => ['response_error_messages' => ['normal pnr']],
        ]);
        $certification = $this->createAttempt($booking, 'create_pnr_certification', [
            'status' => 'needs_review',
            'error_code' => 'sabre_passenger_records_stale_shop_segment',
            'safe_summary' => ['probable_issue' => 'flight_not_in_shop_inventory'],
        ]);

        $exit = Artisan::call('sabre:classify-pnr-failure', [
            '--booking' => $booking->id,
            '--action' => 'create_pnr_certification',
        ]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame($certification->id, $payload['latest_attempt_id']);
        $this->assertSame('create_pnr_certification', $payload['latest_attempt_action']);
        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY, $payload['classification']);
        $this->assertFalse($payload['retry_allowed']);
    }

    public function test_stale_shop_without_rbd_diagnostics_classifies_stale_inventory(): void
    {
        $booking = $this->sabreBooking();
        $this->createAttempt($booking, 'create_pnr_certification', [
            'status' => 'needs_review',
            'error_code' => 'sabre_passenger_records_stale_shop_segment',
            'safe_summary' => ['probable_issue' => 'flight_not_in_shop_inventory'],
        ]);

        $exit = Artisan::call('sabre:classify-pnr-failure', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY, $payload['classification']);
        $this->assertStringContainsString('shop inventory', strtolower((string) $payload['staff_message']));
    }

    public function test_output_contains_no_sensitive_payload_fields(): void
    {
        $booking = $this->sabreBooking();
        $this->createAttempt($booking, 'create_pnr', [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => ['Segment SV739 returned status code UC'],
                'passport_number' => 'should-not-appear',
                'pcc' => 'ABC1',
            ],
        ]);

        Artisan::call('sabre:classify-pnr-failure', ['--booking' => $booking->id]);
        $line = trim(Artisan::output());
        $json = substr($line, strlen('pnr_failure_classification_json='));

        $this->assertStringNotContainsString('passport', strtolower($json));
        $this->assertStringNotContainsString('should-not-appear', $json);
        $this->assertStringNotContainsString('ABC1', $json);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeOutput(): array
    {
        $line = trim(Artisan::output());
        $this->assertStringStartsWith('pnr_failure_classification_json=', $line);
        $json = substr($line, strlen('pnr_failure_classification_json='));
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    protected function sabreBooking(): Booking
    {
        return Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => ['supplier_provider' => 'sabre'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function createPnrAttempt(Booking $booking, array $attrs): SupplierBookingAttempt
    {
        return $this->createAttempt($booking, 'create_pnr', $attrs);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function createAttempt(Booking $booking, string $action, array $attrs): SupplierBookingAttempt
    {
        return SupplierBookingAttempt::query()->create(array_merge([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => 'sabre',
            'action' => $action,
            'attempted_at' => now(),
            'completed_at' => now(),
        ], $attrs));
    }
}
