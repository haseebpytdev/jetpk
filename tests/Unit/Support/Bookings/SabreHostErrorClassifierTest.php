<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\SabreHostErrorClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SabreHostErrorClassifierTest extends TestCase
{
    #[DataProvider('hostSegmentStatusUnconfirmedProvider')]
    public function test_it_classifies_nn_halt_on_status_as_host_segment_status_unconfirmed(array $input): void
    {
        $result = SabreHostErrorClassifier::classify($input);

        $this->assertSame(
            SabreHostErrorClassifier::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED,
            $result['safe_reason_code'],
        );
        $this->assertSame(
            SabreHostErrorClassifier::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            SabreHostErrorClassifier::hostErrorFamilyForReason($result['safe_reason_code']),
        );
        $this->assertSame(SabreHostErrorClassifier::LAYER_AIRBOOK_SELL, $result['source_layer']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER, $result['retry_policy']);
        $this->assertTrue($result['manual_review_required']);
        $this->assertNotEmpty($result['matched_signals']);
        $this->assertSame(
            'We could not confirm this fare with the airline. Please choose another option or refresh your search.',
            $result['safe_summary'],
        );
        $this->assertSame(
            'Sabre returned an unconfirmed/pending segment status during booking. Re-shop/revalidate and choose a fresh confirmable itinerary before retrying.',
            $result['recommended_admin_action'],
        );
        $this->assertStringNotContainsString('EK623', $result['safe_summary']);
        $this->assertStringNotContainsString('Flight', $result['safe_summary']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function hostSegmentStatusUnconfirmedProvider(): array
    {
        return [
            'halt_on_status_received_message' => [[
                'error_code' => 'sabre_booking_application_error',
                'halt_on_status_received' => true,
                'response_error_messages' => ['HALT_ON_STATUS_RECEIVED'],
            ]],
            'flight_returned_status_code_nn' => [[
                'error_code' => 'sabre_booking_application_error',
                'halt_on_status_received' => true,
                'airline_segment_status' => 'NN',
                'response_error_messages' => ['Flight EK623 returned status code NN'],
            ]],
            'segment_status_nn_message' => [[
                'error_code' => 'sabre_booking_application_error',
                'response_error_messages' => ['segment status NN'],
            ]],
            'specified_halt_on_status_received' => [[
                'error_code' => 'sabre_booking_application_error',
                'response_error_messages' => ['EnhancedAirBookRQ: Specified HaltOnStatus Received'],
            ]],
            'airline_segment_status_nn' => [[
                'error_code' => 'sabre_booking_application_error',
                'airline_segment_status' => 'NN',
            ]],
        ];
    }

    public function test_build_persisted_slice_for_host_segment_status_is_safe(): void
    {
        $slice = SabreHostErrorClassifier::buildPersistedSlice(
            [
                'error_code' => 'sabre_booking_application_error',
                'halt_on_status_received' => true,
                'airline_segment_status' => 'NN',
                'response_error_messages' => ['Flight EK623 returned status code NN'],
            ],
            [
                'live_call_attempted' => true,
                'segment_count' => 1,
                'passenger_count' => 1,
            ],
        );

        $this->assertSame(
            SabreHostErrorClassifier::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED,
            $slice['safe_reason_code'] ?? null,
        );
        $this->assertSame(
            SabreHostErrorClassifier::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            $slice['host_error_family'] ?? null,
        );
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER, $slice['retry_policy'] ?? null);
        $this->assertNotEmpty($slice['admin_summary'] ?? null);
        $this->assertNotEmpty($slice['safe_summary'] ?? null);

        $encoded = json_encode($slice, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('response_error_messages', $encoded);
        $this->assertStringNotContainsString('EK623', $encoded);
        $this->assertStringNotContainsString('Flight', $encoded);
    }

    public function test_it_classifies_uc_segment_status_as_host_sell_rejected_uc(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'airline_segment_status' => 'UC',
            'halt_on_status_received' => true,
            'response_error_messages' => ['Flight PK123 returned status code UC'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_HOST_SELL_REJECTED_UC, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::LAYER_AIRBOOK_SELL, $result['source_layer']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER, $result['retry_policy']);
        $this->assertTrue($result['manual_review_required']);
        $this->assertNotEmpty($result['matched_signals']);
        $this->assertTrue(
            collect($result['matched_signals'])->contains(fn (string $signal): bool => str_contains($signal, 'uc'))
        );
        $this->assertStringNotContainsString('PK123', $result['safe_summary']);
        $this->assertStringNotContainsString('Flight', $result['safe_summary']);
    }

    #[DataProvider('inventoryUnavailableProvider')]
    public function test_it_classifies_no_hx_un_as_inventory_unavailable(array $input): void
    {
        $result = SabreHostErrorClassifier::classify($input);

        $this->assertSame(
            SabreHostErrorClassifier::REASON_INVENTORY_UNAVAILABLE,
            $result['safe_reason_code'],
        );
        $this->assertSame(SabreHostErrorClassifier::LAYER_AIRBOOK_SELL, $result['source_layer']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER, $result['retry_policy']);
        $this->assertTrue($result['manual_review_required']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function inventoryUnavailableProvider(): array
    {
        return [
            'segment_status_no' => [[
                'error_code' => 'sabre_booking_application_error',
                'airline_segment_status' => 'NO',
                'response_error_messages' => ['Segment returned status code NO'],
            ]],
            'segment_status_hx' => [[
                'error_code' => 'sabre_booking_application_error',
                'airline_segment_status' => 'HX',
                'response_error_messages' => ['Segment returned status code HX'],
            ]],
            'unable_to_confirm_message' => [[
                'error_code' => 'sabre_booking_application_error',
                'response_error_messages' => ['Unable to confirm requested segment'],
            ]],
        ];
    }

    public function test_it_classifies_no_fares_rbd_carrier(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::LAYER_AIRPRICE, $result['source_layer']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER, $result['retry_policy']);
        $this->assertTrue($result['manual_review_required']);
    }

    public function test_it_classifies_generic_no_fares_as_airprice_failed(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES FOR ITINERARY'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_AIRPRICE_FAILED, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::LAYER_AIRPRICE, $result['source_layer']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER, $result['retry_policy']);
        $this->assertTrue($result['manual_review_required']);
    }

    public function test_it_classifies_entitlement_security_error(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_forbidden',
            'http_status' => 403,
            'response_error_messages' => ['ERR.2SG.SEC.NOT_AUTHORIZED for this PCC'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_ENTITLEMENT_OR_SECURITY, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_RETRY_UNTIL_CREDENTIALS_OR_PCC, $result['retry_policy']);
        $this->assertSame(SabreHostErrorClassifier::LAYER_ENTITLEMENT, $result['source_layer']);
        $this->assertTrue($result['manual_review_required']);
    }

    #[DataProvider('transportTimeoutProvider')]
    public function test_it_classifies_transport_timeout(array $input): void
    {
        $result = SabreHostErrorClassifier::classify($input);

        $this->assertSame(
            SabreHostErrorClassifier::REASON_SUPPLIER_TIMEOUT_OR_TRANSPORT,
            $result['safe_reason_code'],
        );
        $this->assertSame(
            SabreHostErrorClassifier::RETRY_ONLY_AFTER_OPERATOR_REVIEW,
            $result['retry_policy'],
        );
        $this->assertSame(SabreHostErrorClassifier::LAYER_HTTP_TRANSPORT, $result['source_layer']);
        $this->assertTrue($result['manual_review_required']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function transportTimeoutProvider(): array
    {
        return [
            'connection_error_code' => [[
                'error_code' => 'sabre_booking_connection_error',
            ]],
            'http_503' => [[
                'error_code' => 'sabre_booking_http_failed',
                'http_status' => 503,
            ]],
        ];
    }

    public function test_it_falls_back_to_unknown_sabre_host_error(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'response_error_messages' => ['Unhandled supplier response code'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_UNKNOWN, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_AUTO_RETRY, $result['retry_policy']);
        $this->assertSame(SabreHostErrorClassifier::LAYER_UNKNOWN, $result['source_layer']);
        $this->assertTrue($result['manual_review_required']);
    }

    public function test_it_classifies_incomplete_no_locator_without_detail(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'reason_code' => 'sabre_passenger_records_incomplete_no_pnr',
            'application_status' => 'Incomplete',
            'http_status' => 200,
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_APPLICATION_INCOMPLETE_NO_LOCATOR, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::HOST_ERROR_FAMILY_APPLICATION_INCOMPLETE, SabreHostErrorClassifier::hostErrorFamilyForReason($result['safe_reason_code']));
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_AUTO_RETRY, $result['retry_policy']);
        $this->assertTrue($result['manual_review_required']);
    }

    public function test_it_classifies_mixed_interline_not_bookable(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'response_error_messages' => ['Invalid carrier combination for interline booking'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_MIXED_INTERLINE_NOT_BOOKABLE, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_AUTO_RETRY, $result['retry_policy']);
    }

    public function test_it_classifies_fare_pricing_qualifier_rejected(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'response_error_messages' => ['CommandPricing fare basis qualifier rejected'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_FARE_PRICING_QUALIFIER_REJECTED, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_AUTO_RETRY, $result['retry_policy']);
    }

    public function test_it_classifies_segment_sell_unavailable(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'response_error_messages' => ['Unable to sell segment for requested itinerary'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_SEGMENT_SELL_UNAVAILABLE, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER, $result['retry_policy']);
    }

    public function test_it_classifies_commandpricing_segmentselect_pairing_required(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'response_error_messages' => ['EnhancedAirBookRQ: CommandPricing@RPH must be combined with SegmentSelect@RPH'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_COMMANDPRICING_SEGMENTSELECT_PAIRING_REQUIRED, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::HOST_ERROR_FAMILY_FARE_PRICING_QUALIFIER, SabreHostErrorClassifier::hostErrorFamilyForReason($result['safe_reason_code']));
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_AUTO_RETRY, $result['retry_policy']);
        $this->assertTrue($result['manual_review_required']);
        $this->assertSame(
            'Fix v2.4 CommandPricing/SegmentSelect RPH pairing before retry.',
            $result['recommended_admin_action'],
        );
    }

    public function test_it_classifies_brand_rph_schema_invalid(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_validation_failed',
            'http_status' => 400,
            'response_error_messages' => [
                'pointer: /CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/Brand/0/RPH instance type (string) does not match schema type (integer)',
            ],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_BRAND_RPH_SCHEMA_INVALID, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::HOST_ERROR_FAMILY_FARE_PRICING_QUALIFIER, SabreHostErrorClassifier::hostErrorFamilyForReason($result['safe_reason_code']));
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_AUTO_RETRY, $result['retry_policy']);
        $this->assertTrue($result['manual_review_required']);
        $this->assertSame(
            'Fix v2.4 Brand RPH schema/type before retry.',
            $result['recommended_admin_action'],
        );
    }

    public function test_it_classifies_brand_segmentselect_pairing_required(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'response_error_messages' => ['EnhancedAirBookRQ: Brand without RPH cannot combine with SegmentSelect'],
        ]);

        $this->assertSame(SabreHostErrorClassifier::REASON_BRAND_SEGMENTSELECT_PAIRING_REQUIRED, $result['safe_reason_code']);
        $this->assertSame(SabreHostErrorClassifier::HOST_ERROR_FAMILY_FARE_PRICING_QUALIFIER, SabreHostErrorClassifier::hostErrorFamilyForReason($result['safe_reason_code']));
        $this->assertSame(SabreHostErrorClassifier::RETRY_NO_AUTO_RETRY, $result['retry_policy']);
        $this->assertTrue($result['manual_review_required']);
        $this->assertSame(
            'Fix v2.4 Brand/SegmentSelect RPH pairing or omit Brand safely for mixed v2.4 create before retry.',
            $result['recommended_admin_action'],
        );
    }

    public function test_build_persisted_slice_includes_safe_meta_fields_without_raw_messages(): void
    {
        $slice = SabreHostErrorClassifier::buildPersistedSlice(
            [
                'error_code' => 'sabre_booking_application_error',
                'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            ],
            [
                'live_call_attempted' => true,
                'booking_schema' => 'create_passenger_name_record',
                'payload_schema' => 'traditional_pnr_create_passenger_name_record_v1',
                'segment_count' => 1,
                'passenger_count' => 2,
            ],
        );

        $this->assertSame(SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER, $slice['safe_reason_code'] ?? null);
        $this->assertSame(SabreHostErrorClassifier::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER, $slice['host_error_family'] ?? null);
        $this->assertSame(SabreHostErrorClassifier::CLASSIFIER_VERSION, $slice['classifier_version'] ?? null);
        $this->assertNotEmpty($slice['recorded_at'] ?? null);
        $this->assertNotEmpty($slice['admin_summary'] ?? null);
        $this->assertTrue($slice['live_call_attempted'] ?? false);
        $this->assertSame(1, $slice['segment_count'] ?? null);
        $this->assertSame(2, $slice['passenger_count'] ?? null);

        $encoded = json_encode($slice, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('response_error_messages', $encoded);
        $this->assertStringNotContainsString('NO FARES/RBD/CARRIER', $encoded);
        $this->assertStringNotContainsString('EnhancedAirBookRQ', $encoded);
    }

    public function test_it_redacts_and_does_not_echo_pii_or_raw_payload(): void
    {
        $result = SabreHostErrorClassifier::classify([
            'error_code' => 'sabre_booking_application_error',
            'passenger_name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '+1-555-010-9999',
            'pcc' => 'AB12',
            'targetCity' => 'DFW',
            'token' => 'secret-oauth-token-value',
            'FormOfPayment' => ['CardNumber' => '4111111111111111'],
            'CreatePassengerNameRecordRQ' => ['PassengerName' => ['Name' => 'Jane Doe']],
            'response_error_messages' => ['Unhandled supplier response code'],
        ]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR);

        foreach ([
            'Jane Doe',
            'jane.doe@example.com',
            '+1-555-010-9999',
            'AB12',
            'DFW',
            'secret-oauth-token-value',
            '4111111111111111',
            'CreatePassengerNameRecordRQ',
            'PassengerName',
            'FormOfPayment',
            'targetCity',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded, 'Output leaked: '.$forbidden);
        }
    }
}
