<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Booking;
use App\Support\Bookings\PnrItinerarySyncSafetyPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PnrItinerarySyncSafetyPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_synced_sidecar_with_booleans_returns_expected_labels(): void
    {
        config([
            'suppliers.sabre.cancel_enabled' => false,
            'suppliers.sabre.cancel_live_call_enabled' => false,
        ]);

        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_sync' => [
                    'status' => 'synced',
                    'synced_at' => '2026-06-08T10:00:00+00:00',
                    'is_cancelable' => true,
                    'is_ticketed' => false,
                    'ticket_numbers_present' => false,
                    'booking_id_present' => true,
                ],
                'pnr_itinerary_snapshot' => [
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'KHI',
                            'airline_code' => 'PK',
                            'flight_number' => '303',
                            'segment_status' => 'HK',
                        ],
                    ],
                ],
            ],
        ]);

        $out = PnrItinerarySyncSafetyPresenter::forBooking($booking);

        $this->assertTrue($out['show_panel']);
        $this->assertSame('Success', $out['retrieve_result_label']);
        $this->assertSame('Synced', $out['sync_status_label']);
        $this->assertNull($out['reason_label']);
        $this->assertSame('Yes', $out['cancel_eligible_label']);
        $this->assertSame('No', $out['is_ticketed_label']);
        $this->assertSame('Not present', $out['ticket_numbers_label']);
        $this->assertSame('Present', $out['booking_id_label']);
        $this->assertSame('Disabled', $out['live_cancel_label']);
        $this->assertSame('Unresolved — manual required', $out['gds_cancel_posture_label']);
        $this->assertSame('Disabled — manual required', $out['gds_ticketing_posture_label']);
        $this->assertSame('Unknown/disabled — not production', $out['ndc_posture_label']);
        $this->assertCount(1, $out['segments']);
        $this->assertSame('LHE–KHI', $out['segments'][0]['route_label']);
        $this->assertSame('PK303', $out['segments'][0]['flight_label']);
        $this->assertSame('HK', $out['segments'][0]['segment_status']);
        $this->assertSame('Confirmed', $out['segments'][0]['status_label']);
    }

    public function test_retrieve_failed_reason_code_returns_safe_human_label(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'pnr' => 'IJYJMV',
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_sync' => [
                    'status' => 'retrieve_failed',
                    'reason_code' => 'sabre_auth_failed',
                    'attempted_at' => '2026-06-08T11:00:00+00:00',
                ],
            ],
        ]);

        $out = PnrItinerarySyncSafetyPresenter::forBooking($booking);

        $this->assertTrue($out['show_panel']);
        $this->assertSame('Failed', $out['retrieve_result_label']);
        $this->assertSame('Retrieve failed', $out['sync_status_label']);
        $this->assertSame('Sabre authentication failed', $out['reason_label']);
        $this->assertSame('Unknown', $out['cancel_eligible_label']);
        $this->assertSame([], $out['segments']);
    }

    public function test_booking_id_present_true_does_not_expose_booking_id_value(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_sync' => [
                    'status' => 'synced',
                    'booking_id_present' => true,
                    'bookingId' => 'SECRET-SABRE-BOOKING-ID-12345',
                ],
            ],
        ]);

        $out = PnrItinerarySyncSafetyPresenter::forBooking($booking);
        $encoded = json_encode($out);

        $this->assertSame('Present', $out['booking_id_label']);
        $this->assertStringNotContainsString('SECRET-SABRE-BOOKING-ID-12345', $encoded);
        $this->assertArrayNotHasKey('bookingId', $out);
        $this->assertArrayNotHasKey('booking_id', $out);
    }

    public function test_segments_show_route_flight_status_only(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_snapshot' => [
                    'segments' => [
                        [
                            'origin' => 'DXB',
                            'destination' => 'LHR',
                            'airline_code' => 'EK',
                            'flight_number' => '1',
                            'segment_status' => 'UC',
                            'passenger_name' => 'DOE/JOHN',
                            'bookingId' => 'leak-test',
                        ],
                    ],
                ],
            ],
        ]);

        $out = PnrItinerarySyncSafetyPresenter::forBooking($booking);
        $segment = $out['segments'][0];
        $encoded = json_encode($out);

        $this->assertSame('DXB–LHR', $segment['route_label']);
        $this->assertSame('EK1', $segment['flight_label']);
        $this->assertSame('UC', $segment['segment_status']);
        $this->assertSame('Unable to confirm', $segment['status_label']);
        $this->assertStringNotContainsString('DOE/JOHN', $encoded);
        $this->assertStringNotContainsString('leak-test', $encoded);
        $this->assertArrayNotHasKey('passenger_name', $segment);
        $this->assertArrayNotHasKey('bookingId', $segment);
    }

    public function test_non_sabre_booking_returns_show_panel_false(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'duffel',
            'pnr' => 'DUFFELPNR',
            'meta' => [
                'supplier_provider' => 'duffel',
                'pnr_itinerary_sync' => ['status' => 'synced'],
            ],
        ]);

        $out = PnrItinerarySyncSafetyPresenter::forBooking($booking);

        $this->assertFalse($out['show_panel']);
    }

    public function test_sabre_without_pnr_or_sync_data_returns_show_panel_false(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'pnr' => null,
            'meta' => ['supplier_provider' => 'sabre'],
        ]);

        $out = PnrItinerarySyncSafetyPresenter::forBooking($booking);

        $this->assertFalse($out['show_panel']);
    }

    public function test_live_cancel_label_enabled_when_both_config_flags_true(): void
    {
        config([
            'suppliers.sabre.cancel_enabled' => true,
            'suppliers.sabre.cancel_live_call_enabled' => true,
        ]);

        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'pnr' => 'UNGKWK',
            'meta' => ['supplier_provider' => 'sabre'],
        ]);

        $out = PnrItinerarySyncSafetyPresenter::forBooking($booking);

        $this->assertSame('Enabled', $out['live_cancel_label']);
    }

    public function test_architecture_posture_labels_are_independent_of_env_cancel_gate(): void
    {
        config([
            'suppliers.sabre.cancel_enabled' => true,
            'suppliers.sabre.cancel_live_call_enabled' => true,
        ]);

        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'pnr' => 'UNGKWK',
            'meta' => ['supplier_provider' => 'sabre'],
        ]);

        $out = PnrItinerarySyncSafetyPresenter::forBooking($booking);

        $this->assertSame('Enabled', $out['live_cancel_label']);
        $this->assertSame('Unresolved — manual required', $out['gds_cancel_posture_label']);
        $this->assertSame('Disabled — manual required', $out['gds_ticketing_posture_label']);
    }

    public function test_partial_resource_unavailable_sidecar_shows_locator_and_verification_note(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'pnr' => 'PPNYYM',
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_sync' => [
                    'status' => 'partial_resource_unavailable',
                    'reason_code' => 'partial_resource_unavailable',
                    'pnr' => 'PPNYYM',
                    'airline_locator_present' => true,
                    'airline_locator_value' => 'RQATZN',
                    'is_cancelable' => true,
                    'is_ticketed' => false,
                    'ticket_numbers_present' => false,
                ],
            ],
        ]);

        $out = PnrItinerarySyncSafetyPresenter::forBooking($booking);

        $this->assertSame('Partial / needs manual verification', $out['retrieve_result_label']);
        $this->assertSame('Partial verification (resource unavailable)', $out['sync_status_label']);
        $this->assertSame('PPNYYM', $out['sabre_pnr_label']);
        $this->assertSame('RQATZN', $out['airline_locator_label']);
        $this->assertSame('Pending / not ticketed', $out['ticketing_status_label']);
        $this->assertSame('RQATZN', $out['airline_locator_display']);
        $this->assertSame(
            'Carrier locator detected, but full itinerary was not synced. Verify with airline/carrier before ticketing.',
            $out['verification_note'],
        );
    }
}
