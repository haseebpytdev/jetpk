<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\ComplexItineraryPolicy;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePnrFailureClassifier;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\Bookings\SabreVerifiedAutoPnrReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingSupplierActionsTest extends TestCase
{
    use RefreshDatabase;

    protected AdminBookingSupplierActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = app(AdminBookingSupplierActions::class);
    }

    public function test_sabre_pnr_can_sync_pnr_itinerary_when_not_ticketed(): void
    {
        $booking = $this->sabreBookingBase(['pnr' => 'UNGKWK']);

        $state = $this->actions->build($booking, false, false);

        $this->assertTrue($state['can_sync_pnr_itinerary']);
        $this->assertSame('Sync PNR itinerary', $state['sync_pnr_itinerary_label']);
    }

    public function test_sabre_booking_includes_sabre_capability_posture_without_changing_action_booleans(): void
    {
        config([
            'suppliers.sabre.cancel_enabled' => false,
            'suppliers.sabre.cancel_live_call_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->sabreBookingBase([
            'pnr' => 'UNGKWK',
            'payment_status' => 'paid',
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, true);

        $this->assertArrayHasKey('sabre_capability_posture', $state);
        $posture = $state['sabre_capability_posture'];
        $this->assertIsArray($posture);
        $this->assertTrue($posture['show']);
        $this->assertSame('Unresolved — manual required', $posture['gds_cancel_label']);
        $this->assertSame('Disabled — manual required', $posture['gds_ticketing_label']);
        $this->assertSame('Unknown/disabled — not production', $posture['ndc_label']);
        $this->assertStringContainsString('not customer-facing', strtolower($posture['diagnostics_label']));

        $this->assertFalse($state['can_issue_ticket_live']);
        $this->assertFalse($state['can_retry_ticketing']);
        $this->assertSame('Disabled', $state['pnr_retrieve_safety']['live_cancel_label']);
    }

    public function test_non_sabre_booking_has_null_sabre_capability_posture(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'duffel',
            'meta' => ['supplier_provider' => 'duffel'],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertNull($state['sabre_capability_posture']);
    }

    public function test_sabre_booking_includes_pnr_retrieve_safety_key(): void
    {
        $booking = $this->sabreBookingBase([
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'normalized_offer_snapshot' => ['offer_id' => 'test-offer'],
                'pnr_itinerary_sync' => [
                    'status' => 'synced',
                    'is_cancelable' => true,
                    'booking_id_present' => true,
                ],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertArrayHasKey('pnr_retrieve_safety', $state);
        $this->assertTrue($state['pnr_retrieve_safety']['show_panel']);
        $this->assertSame('Success', $state['pnr_retrieve_safety']['retrieve_result_label']);
        $this->assertSame('Present', $state['pnr_retrieve_safety']['booking_id_label']);
    }

    public function test_existing_snapshot_shows_resync_label(): void
    {
        $booking = $this->sabreBookingBase([
            'pnr' => 'UNGKWK',
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_snapshot' => [
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'KHI'],
                    ],
                ],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertTrue($state['has_pnr_itinerary_snapshot']);
        $this->assertSame('Re-sync PNR itinerary', $state['sync_pnr_itinerary_label']);
    }

    public function test_booking_with_pnr_hides_create_and_retry_pnr(): void
    {
        $booking = $this->sabreBookingBase(['pnr' => 'IJYJMV', 'payment_status' => 'unpaid']);

        $state = $this->actions->build($booking, false, false);

        $this->assertTrue($state['has_pnr_or_reference']);
        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertStringContainsString('already created', strtolower((string) $state['supplier_status_message']));
        $this->assertStringContainsString('PNR: IJYJMV', (string) $state['supplier_status_message']);
        $this->assertStringContainsString('manual ticketing', strtolower($state['supplier_status_message']));
        $this->assertFalse($state['can_issue_ticket_live']);
    }

    public function test_booking_with_synced_pnr_itinerary_shows_sync_aware_supplier_message(): void
    {
        $booking = $this->sabreBookingBase([
            'pnr' => 'TQMNEV',
            'payment_status' => 'unpaid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'normalized_offer_snapshot' => ['offer_id' => 'test-offer'],
                'pnr_itinerary_sync' => [
                    'status' => 'synced',
                    'endpoint_path' => '/v1/trip/orders/getBooking',
                ],
                'pnr_itinerary_snapshot' => [
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DXB', 'segment_status' => 'HK'],
                    ],
                ],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertStringContainsString('itinerary synced from sabre', strtolower((string) $state['supplier_status_message']));
        $this->assertStringContainsString('PNR: TQMNEV', (string) $state['supplier_status_message']);
        $this->assertStringNotContainsString('continue with sync', strtolower((string) $state['supplier_status_message']));
    }

    public function test_stale_segment_blocks_retry_and_sets_search_again_primary(): void
    {
        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_passenger_records_stale_shop_segment',
            'safe_summary' => [
                'stale_segment_route' => 'LHE-DXB',
                'stale_segment_flight' => 'PK201',
                'probable_issue' => 'class_mismatch',
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertTrue($state['stale_segment']);
        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertSame('Search again required', $state['primary_cta_label']);
        $this->assertSame('LHE-DXB', $state['stale_context']['stale_segment_route'] ?? null);
    }

    public function test_uc_application_error_shows_host_rejection_message_and_disables_retry(): void
    {
        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => [
                    'Segment SV739 returned status code UC',
                    'HALT_ON_STATUS_RECEIVED',
                ],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_REJECTED_UC, $state['pnr_failure_classification']);
        $this->assertStringContainsString('sv739', strtolower($state['pnr_failure_admin_message']));
        $this->assertContains('halt_on_status_received', $state['pnr_failure_retry_blocker_reasons'] ?? []);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertStringContainsString('retry not allowed', strtolower($state['retry_pnr_reason']));
        $this->assertSame('Search again required', $state['primary_cta_label']);
        $this->assertSame('UC', $state['uc_segment_context']['segment_status_returned'] ?? null);
    }

    public function test_no_fares_application_error_classifies_and_disables_retry(): void
    {
        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_PNR_REQUIRES_MANUAL_SABRE_PRICING,
            $state['pnr_failure_classification'],
        );
        $this->assertStringContainsString('manual sabre pricing', strtolower($state['pnr_failure_admin_message']));
        $this->assertFalse($state['can_retry_pnr']);
    }

    public function test_booking_class_mismatch_diagnostic_disables_retry_and_search_again_cta(): void
    {
        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'fresh_same_rbd_found' => false,
                'probable_issue' => 'booking_class_mismatch',
                'response_error_messages' => ['*NO FARES/RBD/CARRIER'],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_BOOKING_CLASS_MISMATCH, $state['pnr_failure_classification']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertSame('Search again required', $state['primary_cta_label']);
        $this->assertStringContainsString('booking class', strtolower($state['pnr_failure_admin_message']));
    }

    public function test_missing_inventory_diagnostic_disables_retry(): void
    {
        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => ['probable_issue' => 'no_normalized_offers'],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY, $state['pnr_failure_classification']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertSame('Search again required', $state['primary_cta_label']);
    }

    public function test_sabre_offer_validation_failed_booking_42_like_safe_summary_unlocks_retry(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->sabreBookingBase([
            'payment_status' => 'paid',
            'status' => BookingStatus::Paid,
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'GF',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'DXB',
                            'carrier' => 'GF',
                            'flight_number' => '765',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-06-20T08:00:00Z',
                            'arrival_at' => '2026-06-20T12:00:00Z',
                        ],
                        [
                            'origin' => 'DXB',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '510',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-06-20T14:00:00Z',
                            'arrival_at' => '2026-06-20T18:00:00Z',
                        ],
                    ],
                    'raw_payload' => [
                        'sabre_shop_context' => [
                            'pricing_information_ref' => 'pi-1',
                            'offer_ref' => 'offer-1',
                            'itinerary_ref' => 'itin-1',
                            'validating_carrier' => 'GF',
                            'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                        ],
                    ],
                    'fare_breakdown' => [
                        'supplier_total' => 100.0,
                        'currency' => 'PKR',
                        'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                    ],
                ],
            ],
        ]);

        $this->attempt($booking, [
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_offer_validation_failed',
            'error_message' => 'This fare needs to be refreshed because airline prices and availability can change quickly.',
            'completed_at' => now()->subMinutes(10),
            'safe_summary' => [
                'source' => 'sabre_booking_service',
                'error_code' => 'sabre_offer_validation_failed',
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_OFFER_FRESHNESS_RETRYABLE,
            $state['pnr_failure_classification'],
        );
        $this->assertTrue($state['pnr_failure_retry_allowed']);
        $this->assertFalse($state['staff_review']);
        $this->assertTrue($state['can_retry_pnr'], (string) ($state['retry_pnr_reason'] ?? ''));
        $this->assertSame(
            'Retry will refresh the Sabre offer before PNR creation.',
            $state['retry_pnr_refresh_helper'],
        );
        $this->assertSame('', $state['retry_pnr_reason']);
    }

    public function test_sabre_offer_validation_failed_blocks_retry_when_live_action_not_allowed(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'failed',
            'error_code' => 'sabre_offer_validation_failed',
            'completed_at' => now()->subMinutes(10),
            'safe_summary' => [
                'source' => 'sabre_booking_service',
                'error_code' => 'sabre_offer_validation_failed',
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertFalse($state['admin_pnr_live_action_allowed']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertNull($state['retry_pnr_refresh_helper']);
    }

    public function test_sabre_offer_validation_failed_allows_controlled_admin_retry_when_live_action_ready(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->sabreBookingBase([
            'payment_status' => 'paid',
            'status' => BookingStatus::Paid,
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'GF',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'DXB',
                            'carrier' => 'GF',
                            'flight_number' => '765',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-06-20T08:00:00Z',
                            'arrival_at' => '2026-06-20T12:00:00Z',
                        ],
                        [
                            'origin' => 'DXB',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '510',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-06-20T14:00:00Z',
                            'arrival_at' => '2026-06-20T18:00:00Z',
                        ],
                    ],
                    'raw_payload' => [
                        'sabre_shop_context' => [
                            'pricing_information_ref' => 'pi-1',
                            'offer_ref' => 'offer-1',
                            'itinerary_ref' => 'itin-1',
                            'validating_carrier' => 'GF',
                            'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                        ],
                    ],
                    'fare_breakdown' => [
                        'supplier_total' => 100.0,
                        'currency' => 'PKR',
                        'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                    ],
                ],
            ],
        ]);

        $this->attempt($booking, [
            'status' => 'failed',
            'error_code' => 'sabre_offer_validation_failed',
            'completed_at' => now()->subMinutes(10),
            'safe_summary' => [
                'source' => 'sabre_booking_service',
                'create_status' => 'validation_failed',
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertTrue($state['admin_pnr_live_action_allowed'], (string) ($state['connecting_certification_status_message'] ?? ''));
        $this->assertTrue($state['can_retry_pnr'], (string) ($state['retry_pnr_reason'] ?? ''));
        $this->assertSame(
            'Retry will refresh the Sabre offer before PNR creation.',
            $state['retry_pnr_refresh_helper'],
        );
    }

    public function test_http_429_within_cooldown_blocks_retry(): void
    {
        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'failed',
            'error_code' => 'sabre_booking_http_failed',
            'completed_at' => now()->subMinutes(2),
            'safe_summary' => ['http_status' => 429],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertTrue($state['rate_limit']['in_cooldown']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertStringContainsString('retry later', strtolower($state['primary_cta_label']));
    }

    public function test_http_429_after_cooldown_allows_admin_retry_when_eligible(): void
    {
        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'failed',
            'error_code' => 'sabre_booking_http_failed',
            'completed_at' => now()->subMinutes(10),
            'safe_summary' => ['http_status' => 429],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertFalse($state['rate_limit']['in_cooldown']);
        $this->assertTrue($state['can_retry_pnr']);
        $this->assertSame('Retry PNR', $state['primary_cta_label']);
    }

    public function test_certified_route_pending_checkout_defer_allows_controlled_admin_retry_when_live_action_ready(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
        ]);

        $booking = $this->sabreBookingBase([
            'payment_status' => 'paid',
            'status' => BookingStatus::Paid,
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'GF',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'DXB',
                            'carrier' => 'GF',
                            'flight_number' => '765',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-06-20T08:00:00Z',
                            'arrival_at' => '2026-06-20T12:00:00Z',
                        ],
                        [
                            'origin' => 'DXB',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '510',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-06-20T14:00:00Z',
                            'arrival_at' => '2026-06-20T18:00:00Z',
                        ],
                    ],
                    'raw_payload' => [
                        'sabre_shop_context' => [
                            'pricing_information_ref' => 'pi-1',
                            'offer_ref' => 'offer-1',
                            'itinerary_ref' => 'itin-1',
                            'validating_carrier' => 'GF',
                            'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => SabreCertifiedRouteSelector::ERROR_CODE_PENDING,
            'safe_summary' => [
                'source' => 'sabre_public_checkout',
                'live_call_attempted' => false,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertTrue($state['admin_pnr_live_action_allowed'], (string) ($state['connecting_certification_status_message'] ?? ''));
        $this->assertFalse($state['staff_review']);
        $this->assertTrue($state['can_retry_pnr'], (string) ($state['retry_pnr_reason'] ?? $state['create_pnr_reason'] ?? ''));
        $this->assertFalse($state['can_create_pnr']);
        $this->assertNull($this->actions->assertSupplierBookingPostAllowed($this->reloadBooking($booking), true));
    }

    public function test_deferred_controlled_connecting_booking_without_attempts_allows_initial_create(): void
    {
        $this->configureControlledConnecting();

        $booking = $this->booking44LikeControlledInitialCreate();

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertTrue($state['admin_pnr_live_action_allowed'], (string) ($state['connecting_certification_status_message'] ?? ''));
        $this->assertFalse($state['staff_review']);
        $this->assertSame('', $state['pnr_failure_classification']);
        $this->assertTrue($state['pnr_failure_retry_allowed']);
        $this->assertSame(SabreCertifiedRouteSelector::CONTROLLED_PNR_VERIFIED, $state['controlled_pnr_certification_status']);
        $this->assertSame('Verified controlled PNR-capable', $state['controlled_pnr_certification_label']);
        $this->assertSame(44, $state['controlled_pnr_verified_booking_id']);
        $this->assertTrue($state['controlled_pnr_verified_pnr_present']);
        $this->assertTrue($state['controlled_pnr_airline_locator_present']);
        $this->assertFalse($state['controlled_pnr_ticketing_enabled']);
        $this->assertTrue($state['can_create_pnr'], (string) ($state['create_pnr_reason'] ?? ''));
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertSame('Create supplier booking / PNR', $state['primary_cta_label']);
        $this->assertSame('', $state['create_pnr_reason']);
        $this->assertNotSame('No supplier attempt to retry.', $state['retry_pnr_reason']);
        $this->assertSame(
            'Controlled create will refresh the Sabre offer and attempt Passenger Records create once.',
            $state['retry_pnr_refresh_helper'],
        );
        $this->assertNull($this->actions->assertSupplierBookingPostAllowed($this->reloadBooking($booking), true));
    }

    public function test_booking_46_like_route_still_allows_admin_controlled_create(): void
    {
        $this->configureControlledConnecting();

        $booking = $this->booking44LikeControlledInitialCreate();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'BAH',
                'carrier' => 'GF',
                'flight_number' => '765',
                'booking_class' => 'W',
                'fare_basis_code' => 'WDLIT3PK',
                'departure_at' => '2026-07-31T15:10:00',
                'arrival_at' => '2026-07-31T18:40:00',
            ],
            [
                'origin' => 'BAH',
                'destination' => 'JED',
                'carrier' => 'GF',
                'flight_number' => '173',
                'booking_class' => 'W',
                'fare_basis_code' => 'WDLIT3PK',
                'departure_at' => '2026-08-01T18:05:00',
                'arrival_at' => '2026-08-01T20:30:00',
            ],
        ];
        $meta['normalized_offer_snapshot'] = $snapshot;
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-31',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'booking-46-like-search',
            'checkout_offer_id' => 'booking-46-like-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertSame(SabreCertifiedRouteSelector::CONTROLLED_PNR_VERIFIED, $state['controlled_pnr_certification_status']);
        $this->assertTrue($state['can_create_pnr'], (string) ($state['create_pnr_reason'] ?? ''));
    }

    public function test_deferred_controlled_initial_create_blocks_when_pnr_exists(): void
    {
        $this->configureControlledConnecting();

        $booking = $this->booking44LikeControlledInitialCreate(['pnr' => 'ABC123']);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertStringContainsString('already exists', strtolower((string) $state['create_pnr_reason']));
    }

    public function test_booking_46_like_fare_rbd_failure_blocks_create_and_retry(): void
    {
        $this->configureControlledConnecting();

        $booking = $this->connectingCertSabreBookingBase([
            'supplier_booking_status' => 'manual_review',
        ]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['verified_multiseg_auto_pnr_result'] = 'failed';
        $meta['verified_multiseg_auto_pnr_reason_code'] = SabreVerifiedAutoPnrReadiness::VERIFIED_AUTO_PNR_TERMINAL_FAILURE_REASON;
        $booking->forceFill(['meta' => $meta, 'supplier_booking_status' => 'manual_review'])->save();

        $this->attempt($booking, [
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'completed_at' => now()->subMinutes(5),
            'safe_summary' => [
                'http_status' => 200,
                'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
                'payload_schema' => 'iati_like_cpnr_v2_4_gds',
                'response_error_codes' => ['ERR.SP.PROVIDER_ERROR', 'WARN.SWS.HOST.ERROR_IN_RESPONSE'],
                'response_error_messages' => [
                    'Unable to perform air booking step',
                    'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER',
                ],
                'create_segment_count' => 2,
                'create_air_price_present' => true,
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_FARE_RBD_CARRIER_NOT_SELLABLE,
            $state['pnr_failure_classification'],
        );
        $this->assertTrue($state['staff_review']);
        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertStringContainsString('fresh search', strtolower($state['pnr_failure_admin_message']));
    }

    public function test_booking_44_like_post_pnr_with_prior_deferred_attempt_shows_booked_state_not_staff_review(): void
    {
        $this->configureControlledConnecting();

        $booking = $this->booking44LikeControlledInitialCreate([
            'pnr' => 'SZFXWM',
            'supplier_reference' => 'SZFXWM',
            'ticketing_status' => 'pending',
        ]);

        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => 'sabre',
            'status' => 'created',
            'pnr' => 'SZFXWM',
            'supplier_reference' => 'SZFXWM',
        ]);

        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => SabreCertifiedRouteSelector::ERROR_CODE_PENDING,
            'safe_summary' => [
                'source' => 'sabre_public_checkout',
                'live_call_attempted' => false,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertFalse($state['staff_review']);
        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_SUPPLIER_PNR_BOOKED, $state['pnr_failure_classification']);
        $this->assertSame('', $state['pnr_failure_admin_message']);
        $this->assertNotSame('Staff review required', $state['primary_cta_label']);
        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertStringContainsString('already created', strtolower((string) $state['supplier_status_message']));
        $this->assertSame(SabreCertifiedRouteSelector::CONTROLLED_PNR_VERIFIED, $state['controlled_pnr_certification_status']);
        $readiness = is_array($state['verified_auto_pnr_readiness'] ?? null) ? $state['verified_auto_pnr_readiness'] : [];
        $this->assertFalse($readiness['eligible'] ?? true);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_PNR_ALREADY_EXISTS, $readiness['reason_code'] ?? null);
    }

    public function test_deferred_controlled_initial_create_blocks_when_supplier_booking_exists(): void
    {
        $this->configureControlledConnecting();

        $booking = $this->booking44LikeControlledInitialCreate();
        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => 'sabre',
            'status' => 'created',
            'pnr' => 'SUP123',
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertTrue($state['has_pnr_or_reference']);
        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
    }

    public function test_deferred_controlled_initial_create_blocks_when_safe_refresh_context_missing(): void
    {
        $this->configureControlledConnecting();

        $booking = $this->booking44LikeControlledInitialCreate([], false);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertTrue($state['admin_pnr_live_action_allowed'], (string) ($state['connecting_certification_status_message'] ?? ''));
        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertSame('Staff review required', $state['primary_cta_label']);
        $this->assertNull($state['retry_pnr_refresh_helper']);
    }

    public function test_deferred_controlled_initial_create_blocks_when_ticketing_enabled(): void
    {
        $this->configureControlledConnecting();
        config(['suppliers.sabre.ticketing_enabled' => true]);

        $booking = $this->booking44LikeControlledInitialCreate();

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertTrue($state['ticketing_env_enabled']);
    }

    public function test_pk_host_noop_certification_blocks_controlled_initial_create(): void
    {
        $this->configureControlledConnecting();

        $booking = $this->booking43LikeHostNoopBlockedControlledInitialCreate();

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertSame(SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED, $state['controlled_pnr_certification_status']);
        $this->assertSame('Host rejected / do not retry same itinerary', $state['controlled_pnr_certification_label']);
        $this->assertFalse($state['admin_pnr_live_action_allowed']);
        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertStringContainsString('host rejected', strtolower($state['create_pnr_reason']));
    }

    public function test_connection_timeout_within_cooldown_blocks_retry(): void
    {
        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'failed',
            'error_code' => 'sabre_booking_connection_error',
            'completed_at' => now()->subMinute(),
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertTrue($state['rate_limit']['in_cooldown']);
        $this->assertFalse($state['can_retry_pnr']);
    }

    public function test_host_noop_application_error_unlocks_controlled_diagnostic_retry(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->connectingCertSabreBookingBase();
        $this->attempt($booking, [
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'completed_at' => now()->subMinutes(10),
            'safe_summary' => [
                'http_status' => 200,
                'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
                'payload_schema' => 'iati_like_cpnr_v2_4_gds',
                'response_error_codes' => [
                    'ERR.SP.PROVIDER_ERROR',
                    'WARN.SWS.HOST.ERROR_IN_RESPONSE',
                    '0118',
                ],
                'response_error_messages' => [
                    'EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE',
                    'SYSTEM UNABLE TO PROCESS',
                ],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_AIR_BOOKING_NOOP,
            $state['pnr_failure_classification'],
        );
        $this->assertTrue($state['admin_pnr_live_action_allowed'], (string) ($state['connecting_certification_status_message'] ?? ''));
        $this->assertFalse($state['staff_review']);
        $this->assertTrue($state['pnr_failure_retry_allowed']);
        $this->assertTrue($state['can_retry_pnr'], (string) ($state['retry_pnr_reason'] ?? ''));
        $this->assertSame('Retry PNR', $state['primary_cta_label']);
        $this->assertStringContainsString(
            'regenerate safe Passenger Records create diagnostics',
            (string) $state['retry_pnr_refresh_helper'],
        );
        $this->assertNull($this->actions->assertSupplierBookingPostAllowed($this->reloadBooking($booking), true));
    }

    public function test_host_noop_with_safe_create_summary_terminalizes_retry(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->connectingCertSabreBookingBase();
        $this->attempt($booking, [
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'completed_at' => now()->subMinutes(10),
            'safe_summary' => [
                'response_error_messages' => ['EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE'],
                'create_segment_count' => 1,
                'create_segment_source' => 'refreshed_offer',
                'create_segments_summary' => [
                    ['carrier' => 'PK', 'flight_number' => '301', 'booking_class' => 'V'],
                ],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_INVENTORY_OR_CERT_LIMITATION,
            $state['pnr_failure_classification'],
        );
        $this->assertTrue($state['staff_review']);
        $this->assertFalse($state['pnr_failure_retry_allowed']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertNull($state['retry_pnr_refresh_helper']);
        $this->assertSame('Staff review required', $state['primary_cta_label']);
        $this->assertSame(SabrePnrFailureClassifier::RETRY_REASON_HOST_NOOP_TERMINAL, $state['retry_pnr_reason']);
        $this->assertStringContainsString('do not retry this same flight/date', strtolower($state['pnr_failure_admin_message']));
    }

    public function test_unrelated_application_error_remains_staff_review_blocked(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->connectingCertSabreBookingBase();
        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => ['Unexpected supplier application fault'],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_PROVIDER_APPLICATION_ERROR,
            $state['pnr_failure_classification'],
        );
        $this->assertTrue($state['staff_review']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertNull($state['retry_pnr_refresh_helper']);
    }

    public function test_host_noop_blocks_retry_when_pnr_exists(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->connectingCertSabreBookingBase(['pnr' => 'ABC123']);
        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => ['EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE'],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertFalse($state['can_retry_pnr']);
        $this->assertStringContainsString('pnr already created', strtolower($state['retry_pnr_reason']));
    }

    public function test_host_noop_blocks_retry_when_live_action_not_allowed(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->sabreBookingBase();
        $this->attempt($booking, [
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'response_error_messages' => ['EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE'],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertFalse($state['admin_pnr_live_action_allowed']);
        $this->assertTrue($state['staff_review']);
        $this->assertFalse($state['can_retry_pnr']);
    }

    public function test_host_noop_retry_unlocked_when_latest_attempt_is_blocked_wrapper(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->connectingCertSabreBookingBase();
        $this->attempt($booking, [
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'completed_at' => now()->subMinutes(10),
            'safe_summary' => [
                'http_status' => 200,
                'response_error_messages' => [
                    'EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE',
                    'SYSTEM UNABLE TO PROCESS',
                ],
            ],
        ]);
        $this->attempt($booking, [
            'action' => 'create_pnr',
            'status' => 'blocked',
            'error_code' => 'supplier_booking_retry_not_allowed',
            'completed_at' => now(),
            'safe_summary' => [
                'source' => 'admin',
                'reason' => 'supplier_booking_retry_not_allowed',
                'prior_error_code' => 'sabre_booking_application_error',
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_AIR_BOOKING_NOOP,
            $state['pnr_failure_classification'],
        );
        $this->assertTrue($state['can_retry_pnr'], (string) ($state['retry_pnr_reason'] ?? ''));
        $this->assertNull($this->actions->assertSupplierBookingPostAllowed($this->reloadBooking($booking), true));
    }

    public function test_host_noop_with_safe_create_summary_terminalizes_when_latest_attempt_is_blocked_wrapper(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->connectingCertSabreBookingBase();
        $this->attempt($booking, [
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'completed_at' => now()->subMinutes(10),
            'safe_summary' => [
                'http_status' => 200,
                'response_error_codes' => ['ERR.SP.PROVIDER_ERROR', 'WARN.SWS.HOST.ERROR_IN_RESPONSE', '0118'],
                'response_error_messages' => [
                    'Unable to perform air booking step',
                    'EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE',
                    'SYSTEM UNABLE TO PROCESS',
                ],
                'create_segment_count' => 2,
                'create_segment_source' => 'refreshed_offer',
                'create_segments_summary' => [
                    ['carrier' => 'PK', 'flight_number' => '301', 'booking_class' => 'V'],
                    ['carrier' => 'PK', 'flight_number' => '741', 'booking_class' => 'V'],
                ],
            ],
        ]);
        $this->attempt($booking, [
            'action' => 'create_pnr',
            'status' => 'blocked',
            'error_code' => 'supplier_booking_retry_not_allowed',
            'completed_at' => now(),
            'safe_summary' => [
                'source' => 'admin',
                'reason' => 'supplier_booking_retry_not_allowed',
                'prior_error_code' => 'sabre_booking_application_error',
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_INVENTORY_OR_CERT_LIMITATION,
            $state['pnr_failure_classification'],
        );
        $this->assertTrue($state['staff_review']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertSame(SabrePnrFailureClassifier::RETRY_REASON_HOST_NOOP_TERMINAL, $state['retry_pnr_reason']);
        $this->assertStringContainsString(
            'do not retry this same flight/date',
            strtolower($this->actions->assertSupplierBookingPostAllowed($this->reloadBooking($booking), true) ?? ''),
        );
    }

    public function test_ticketing_live_is_never_enabled_for_sabre(): void
    {
        $booking = $this->sabreBookingBase([
            'pnr' => 'UNGKWK',
            'payment_status' => 'paid',
            'status' => BookingStatus::Paid,
        ]);

        $state = $this->actions->build($booking, false, true);

        $this->assertFalse($state['can_issue_ticket_live']);
        $this->assertStringContainsString('sabre ticketing is disabled in environment settings', strtolower($state['ticketing_status_message']));
    }

    public function test_sabre_env_disabled_shows_environment_settings_message(): void
    {
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $booking = $this->sabreBookingBase([
            'pnr' => 'UNGKWK',
            'payment_status' => 'paid',
            'status' => BookingStatus::Paid,
        ]);

        $state = $this->actions->build($booking, false, true);

        $this->assertFalse($state['can_issue_ticket_live']);
        $this->assertStringContainsString(
            'sabre ticketing is disabled in environment settings',
            strtolower((string) $state['issue_ticket_disabled_reason']),
        );
    }

    public function test_missing_pnr_shows_create_or_attach_message(): void
    {
        $booking = $this->sabreBookingBase(['payment_status' => 'paid', 'status' => BookingStatus::Paid]);

        $state = $this->actions->build($booking, false, true);

        $this->assertFalse($state['can_issue_ticket_action']);
        $this->assertStringContainsString('create or attach pnr before ticketing', strtolower($state['issue_ticket_disabled_reason']));
    }

    public function test_post_guard_blocks_when_pnr_exists(): void
    {
        $booking = $this->sabreBookingBase(['pnr' => 'UNWWPS']);

        $message = $this->actions->assertSupplierBookingPostAllowed($booking, true);

        $this->assertNotNull($message);
        $this->assertStringContainsString('already exists', strtolower($message));
    }

    public function test_round_trip_sabre_booking_defers_create_and_retry_pnr(): void
    {
        config(['suppliers.sabre.complex_itinerary_pnr_enabled' => false]);
        $booking = $this->sabreBookingBase([
            'payment_status' => 'paid',
            'status' => BookingStatus::Paid,
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'normalized_offer_snapshot' => ['offer_id' => 'rt-offer'],
                'search_criteria' => ['trip_type' => 'round_trip'],
                'complex_itinerary_requires_staff_confirmation' => true,
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertTrue($state['complex_itinerary_deferred']);
        $this->assertFalse($state['can_create_pnr']);
        $this->assertFalse($state['can_retry_pnr']);
        $this->assertStringContainsString('return/multi-city', strtolower($state['create_pnr_reason']));
        $this->assertSame(ComplexItineraryPolicy::adminDeferMessage(), $state['complex_itinerary_message']);
    }

    public function test_multi_city_sabre_booking_defers_create_pnr(): void
    {
        config(['suppliers.sabre.complex_itinerary_pnr_enabled' => false]);
        $booking = $this->sabreBookingBase([
            'payment_status' => 'paid',
            'status' => BookingStatus::Paid,
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'normalized_offer_snapshot' => ['offer_id' => 'mc-offer'],
                'search_criteria' => [
                    'trip_type' => 'multi_city',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DXB'],
                    ],
                ],
            ],
        ]);

        $state = $this->actions->build($this->reloadBooking($booking), true, false);

        $this->assertTrue($state['complex_itinerary_deferred']);
        $this->assertFalse($state['can_create_pnr']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function connectingCertSabreBookingBase(array $overrides = []): Booking
    {
        return $this->sabreBookingBase(array_merge([
            'payment_status' => 'paid',
            'status' => BookingStatus::Paid,
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'GF',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'BAH',
                            'carrier' => 'GF',
                            'flight_number' => '765',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3GF',
                            'departure_at' => '2026-07-23T08:00:00Z',
                            'arrival_at' => '2026-07-23T10:00:00Z',
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '181',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3GF',
                            'departure_at' => '2026-07-24T02:30:00Z',
                            'arrival_at' => '2026-07-24T05:30:00Z',
                        ],
                    ],
                    'raw_payload' => [
                        'sabre_shop_context' => [
                            'pricing_information_ref' => 'pi-1',
                            'offer_ref' => 'offer-1',
                            'itinerary_ref' => 'itin-1',
                            'validating_carrier' => 'GF',
                            'fare_basis_codes' => ['VDLIT3GF', 'VDLIT3GF'],
                        ],
                    ],
                    'fare_breakdown' => [
                        'supplier_total' => 100.0,
                        'currency' => 'PKR',
                        'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                    ],
                ],
            ],
        ], $overrides));
    }

    protected function configureControlledConnecting(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function booking44LikeControlledInitialCreate(array $overrides = [], bool $withSafeRefreshContext = true): Booking
    {
        $booking = $this->connectingCertSabreBookingBase($overrides);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta['defer_supplier_booking_to_manual_review'] = true;
        $meta['supplier_pnr_deferred_reason'] = SabreCertifiedRouteSelector::DEFER_REASON;
        $meta['offer_refresh_status'] = 'refreshed';
        $meta['selected_offer_last_revalidated_at'] = now()->toIso8601String();
        if ($withSafeRefreshContext) {
            $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'JED',
                'depart_date' => '2026-07-23',
                'adults' => 1,
            ], [
                'checkout_search_id' => 'booking-44-like-search',
                'checkout_offer_id' => 'booking-44-like-offer',
                'supplier_total' => 100.0,
                'supplier_currency' => 'PKR',
            ]);
        } else {
            unset($meta[SabreSafeRefreshContext::META_KEY]);
        }
        $booking->forceFill(['meta' => $meta])->save();

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']);
    }

    protected function booking43LikeHostNoopBlockedControlledInitialCreate(): Booking
    {
        $booking = $this->sabreBookingBase([
            'payment_status' => 'paid',
            'status' => BookingStatus::Paid,
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'PK',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'KHI',
                            'carrier' => 'PK',
                            'flight_number' => '301',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3PK',
                            'departure_at' => '2026-07-23T08:00:00Z',
                            'arrival_at' => '2026-07-23T10:00:00Z',
                        ],
                        [
                            'origin' => 'KHI',
                            'destination' => 'JED',
                            'carrier' => 'PK',
                            'flight_number' => '741',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3PK',
                            'departure_at' => '2026-07-24T02:30:00Z',
                            'arrival_at' => '2026-07-24T05:30:00Z',
                        ],
                    ],
                    'raw_payload' => [
                        'sabre_shop_context' => [
                            'pricing_information_ref' => 'pi-1',
                            'offer_ref' => 'offer-1',
                            'itinerary_ref' => 'itin-1',
                            'validating_carrier' => 'PK',
                            'fare_basis_codes' => ['VDLIT3PK', 'VDLIT3PK'],
                        ],
                    ],
                    'fare_breakdown' => [
                        'supplier_total' => 100.0,
                        'currency' => 'PKR',
                        'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                    ],
                ],
            ],
        ]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta['defer_supplier_booking_to_manual_review'] = true;
        $meta['supplier_pnr_deferred_reason'] = SabreCertifiedRouteSelector::DEFER_REASON;
        $meta['offer_refresh_status'] = 'refreshed';
        $meta['selected_offer_last_revalidated_at'] = now()->toIso8601String();
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-23',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'booking-43-like-search',
            'checkout_offer_id' => 'booking-43-like-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function sabreBookingBase(array $overrides = []): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'status' => BookingStatus::Draft,
            'payment_status' => 'unpaid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_validation_status' => 'valid',
                'normalized_offer_snapshot' => ['offer_id' => 'test-offer'],
            ],
        ], $overrides));

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']);
    }

    public function test_sabre_booking_includes_controlled_pnr_readiness_key(): void
    {
        $booking = $this->sabreBookingBase(['pnr' => 'UNGKWK']);

        $state = $this->actions->build($this->reloadBooking($booking), false, false);

        $this->assertArrayHasKey('controlled_pnr_readiness', $state);
        $controlled = $state['controlled_pnr_readiness'];
        $this->assertIsArray($controlled);
        $this->assertTrue($controlled['has_existing_pnr']);
        $this->assertContains('existing_pnr_present', $controlled['blockers']);
        $this->assertTrue($controlled['ticketing_disabled']);
        $this->assertTrue($controlled['cancellation_disabled']);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function reloadBooking(Booking $booking): Booking
    {
        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function attempt(Booking $booking, array $attrs): SupplierBookingAttempt
    {
        return SupplierBookingAttempt::query()->create(array_merge([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => 'sabre',
            'action' => 'create_supplier_booking',
            'status' => 'failed',
            'attempted_at' => now(),
            'completed_at' => now(),
        ], $attrs));
    }
}
