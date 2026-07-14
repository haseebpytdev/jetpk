<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\ComplexItineraryPolicy;
use App\Support\Bookings\SabrePnrFailureClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SabrePnrFailureClassifierTest extends TestCase
{
    public function test_uc_messages_classify_as_host_sell_rejected(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', [
            'response_error_messages' => [
                'Segment SV739 returned status code UC',
                'HALT_ON_STATUS_RECEIVED',
            ],
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_REJECTED_UC, $result['classification']);
        $this->assertSame(SabrePnrFailureClassifier::NEXT_ACTION_CHOOSE_ALTERNATE_ITINERARY, $result['next_action']);
        $this->assertFalse($result['retry_allowed']);
        $this->assertContains('airline_segment_status_uc', $result['retry_blocker_reasons'] ?? []);
        $this->assertStringContainsString('sv739', strtolower($result['admin_message']));
        $this->assertStringContainsString('do not retry', strtolower($result['admin_message']));
    }

    public function test_no_fares_rbd_carrier_classifies_without_retry(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', [
            'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            'auto_pnr_pricing_context_ready' => true,
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_NO_FARES_RBD_CARRIER, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
        $this->assertStringContainsString('search again', strtolower($result['admin_message']));
    }

    public function test_booking_46_like_fare_rbd_create_rejection_classifies_terminal(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', [
            'response_error_codes' => ['ERR.SP.PROVIDER_ERROR', 'WARN.SWS.HOST.ERROR_IN_RESPONSE'],
            'response_error_messages' => [
                'Unable to perform air booking step',
                'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER',
            ],
            'create_segment_count' => 2,
            'create_air_price_present' => true,
            'auto_pnr_pricing_context_ready' => true,
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_FARE_RBD_CARRIER_NOT_SELLABLE, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
        $this->assertStringContainsString('fresh search', strtolower($result['admin_message']));
        $this->assertFalse(SabrePnrFailureClassifier::isControlledStaffHostNoopDiagnosticRetryable(
            'sabre_booking_application_error',
            [
                'response_error_messages' => ['Unable to perform air booking step', 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
                'create_segment_count' => 2,
            ],
        ));
    }

    public function test_no_fares_with_incomplete_pricing_linkage_classifies_manual_sabre_pricing(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', [
            'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            'auto_pnr_pricing_context_ready' => false,
            'missing_pricing_context_fields' => ['pricing_information_ref', 'offer_reference'],
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_PNR_REQUIRES_MANUAL_SABRE_PRICING, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
        $this->assertStringContainsString('manual sabre pricing', strtolower($result['admin_message']));
    }

    public function test_revalidate_27131_with_incomplete_linkage_classifies_revalidation_linkage_incomplete(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_revalidation_failed', [
            'includes_sabre_error_27131' => true,
            'auto_pnr_pricing_context_ready' => false,
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_REVALIDATION_LINKAGE_INCOMPLETE, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
    }

    public function test_booking_class_mismatch_from_diagnostics_disables_retry(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', [
            'fresh_same_rbd_found' => false,
            'probable_issue' => 'booking_class_mismatch',
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_BOOKING_CLASS_MISMATCH, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
        $this->assertStringContainsString('booking class', strtolower($result['admin_message']));
    }

    public function test_missing_inventory_probable_issue_classifies_stale(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', [
            'probable_issue' => 'flight_not_in_shop_inventory',
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
    }

    public function test_stale_shop_segment_with_rbd_diagnostics_classifies_booking_class_mismatch(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_passenger_records_stale_shop_segment', [
            'segments' => [
                ['probable_issue' => 'booking_class_mismatch', 'fresh_same_rbd_found' => false],
                ['probable_issue' => 'ok', 'fresh_same_rbd_found' => true],
            ],
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_BOOKING_CLASS_MISMATCH, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
        $this->assertStringContainsString('booking class', strtolower($result['admin_message']));
    }

    public function test_stale_shop_segment_without_rbd_diagnostics_classifies_stale_inventory(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_passenger_records_stale_shop_segment', [
            'probable_issue' => 'flight_not_in_shop_inventory',
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY, $result['classification']);
        $this->assertStringContainsString('shop inventory', strtolower($result['admin_message']));
    }

    public function test_complex_deferred_error_code(): void
    {
        $result = SabrePnrFailureClassifier::classify(ComplexItineraryPolicy::ERROR_CODE, []);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_COMPLEX_DEFERRED, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
        $this->assertStringContainsString('deferred', strtolower($result['admin_message']));
    }

    public function test_temporary_provider_error_allows_retry(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_http_failed', [
            'http_status' => 429,
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_TEMPORARY_PROVIDER_ERROR, $result['classification']);
        $this->assertTrue($result['retry_allowed']);
    }

    public function test_sabre_offer_validation_failed_is_retryable_for_controlled_staff(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_offer_validation_failed', [
            'create_status' => 'validation_failed',
            'source' => 'sabre_booking_service',
        ]);

        $this->assertTrue($result['retry_allowed']);
        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_OFFER_FRESHNESS_RETRYABLE,
            $result['classification'],
        );
        $this->assertStringContainsString('refresh the Sabre offer', $result['admin_message']);
    }

    public function test_sabre_offer_validation_failed_top_level_error_code_is_retryable_without_create_status(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_offer_validation_failed', [
            'source' => 'sabre_booking_service',
            'error_code' => 'sabre_offer_validation_failed',
        ]);

        $this->assertTrue(SabrePnrFailureClassifier::isControlledStaffOfferValidationRetryable(
            'sabre_offer_validation_failed',
            ['source' => 'sabre_booking_service', 'error_code' => 'sabre_offer_validation_failed'],
        ));
        $this->assertTrue($result['retry_allowed']);
        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_OFFER_FRESHNESS_RETRYABLE,
            $result['classification'],
        );
    }

    public function test_sabre_offer_validation_failed_safe_summary_error_code_is_retryable_without_top_level_code(): void
    {
        $safeSummary = [
            'source' => 'sabre_booking_service',
            'error_code' => 'sabre_offer_validation_failed',
        ];

        $this->assertTrue(SabrePnrFailureClassifier::isControlledStaffOfferValidationRetryable(null, $safeSummary));

        $result = SabrePnrFailureClassifier::classify(null, $safeSummary);

        $this->assertTrue($result['retry_allowed']);
        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_OFFER_FRESHNESS_RETRYABLE,
            $result['classification'],
        );
    }

    public function test_unknown_staff_review_error_remains_non_retryable(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_unknown_outcome', [
            'source' => 'sabre_booking_service',
        ]);

        $this->assertFalse(SabrePnrFailureClassifier::isControlledStaffOfferValidationRetryable(
            'sabre_booking_unknown_outcome',
            ['source' => 'sabre_booking_service'],
        ));
        $this->assertFalse($result['retry_allowed']);
        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_UNKNOWN_STAFF_REVIEW, $result['classification']);
    }

    public function test_host_noop_application_error_classifies_as_host_air_booking_noop(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', [
            'http_status' => 200,
            'response_error_codes' => [
                'ERR.SP.PROVIDER_ERROR',
                'WARN.SWS.HOST.ERROR_IN_RESPONSE',
                '0118',
            ],
            'response_error_messages' => [
                'EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE',
                'SYSTEM UNABLE TO PROCESS',
            ],
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_HOST_AIR_BOOKING_NOOP, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
        $this->assertTrue(SabrePnrFailureClassifier::isControlledStaffHostNoopDiagnosticRetryable(
            'sabre_booking_application_error',
            [
                'response_error_codes' => ['0118'],
                'response_error_messages' => ['EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE'],
            ],
        ));
        $this->assertStringContainsString('regenerate safe', strtolower($result['admin_message']));
    }

    public function test_unrelated_application_error_stays_provider_application_error(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', [
            'response_error_messages' => ['Unexpected supplier application fault'],
        ]);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_PROVIDER_APPLICATION_ERROR, $result['classification']);
        $this->assertFalse($result['retry_allowed']);
        $this->assertFalse(SabrePnrFailureClassifier::isControlledStaffHostNoopDiagnosticRetryable(
            'sabre_booking_application_error',
            ['response_error_messages' => ['Unexpected supplier application fault']],
        ));
    }

    public function test_host_noop_with_safe_create_summary_terminalizes_retry(): void
    {
        $safeSummary = [
            'response_error_messages' => ['EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE'],
            'create_segment_count' => 1,
            'create_segment_source' => 'refreshed_offer',
            'create_segments_summary' => [
                ['carrier' => 'PK', 'flight_number' => '301'],
            ],
        ];

        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', $safeSummary);

        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_INVENTORY_OR_CERT_LIMITATION,
            $result['classification'],
        );
        $this->assertFalse($result['retry_allowed']);
        $this->assertFalse(SabrePnrFailureClassifier::isControlledStaffHostNoopDiagnosticRetryable(
            'sabre_booking_application_error',
            $safeSummary,
        ));
        $this->assertStringContainsString('do not retry this same flight/date', strtolower($result['admin_message']));
    }

    #[DataProvider('bookingClassMismatchDiagnosticProvider')]
    public function test_booking_class_mismatch_beats_no_fares_message(array $summary): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_booking_application_error', $summary);

        $this->assertSame(SabrePnrFailureClassifier::CLASSIFICATION_BOOKING_CLASS_MISMATCH, $result['classification']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function bookingClassMismatchDiagnosticProvider(): array
    {
        return [
            'top_level_flag' => [['fresh_same_rbd_found' => false, 'response_error_messages' => ['*NO FARES/RBD/CARRIER']]],
            'segment_row' => [['segments' => [['probable_issue' => 'booking_class_mismatch', 'fresh_same_rbd_found' => false]]]],
        ];
    }
}
