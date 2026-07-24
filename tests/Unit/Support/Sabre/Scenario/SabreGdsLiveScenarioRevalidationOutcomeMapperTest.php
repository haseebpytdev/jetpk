<?php

namespace Tests\Unit\Support\Sabre\Scenario;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationGate;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use Illuminate\Http\Client\ConnectionException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SabreGdsLiveScenarioRevalidationOutcomeMapperTest extends TestCase
{
    private SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new SabreGdsLiveScenarioRevalidationOutcomeMapper;
    }

    public function test_transport_exception_maps_to_transport_failure(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'reason_code' => 'sabre_revalidation_failed',
            'response_structure' => $this->emptyResponseStructure(),
        ], true, false, new ConnectionException('connection reset'));

        $code = $this->mapper->classifyScenarioReasonCode($outcome);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_TRANSPORT_FAILURE,
            $code,
        );
        $this->assertSame('connection', $outcome['transport_error_category']);
    }

    public function test_timeout_maps_to_timeout_reason(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'reason_code' => 'sabre_revalidation_failed',
            'response_structure' => $this->emptyResponseStructure(),
        ], true, false, new \RuntimeException('cURL error 28: Operation timed out'));

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_TIMEOUT,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
        $this->assertSame('timeout', $outcome['transport_error_category']);
    }

    #[DataProvider('httpRejectedProvider')]
    public function test_http_rejected_mapping(int $httpStatus): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => $httpStatus,
            'reason_code' => 'sabre_revalidation_failed',
            'revalidation_failure_class' => 'http_rejected',
            'response_structure' => $this->emptyResponseStructure(),
        ], true, true);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_HTTP_REJECTED,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function httpRejectedProvider(): array
    {
        return [
            'http_400' => [400],
            'http_500' => [500],
        ];
    }

    public function test_http_200_grouped_itinerary_error_mapping(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_application_warning_or_error',
            'revalidation_failure_class' => 'mip_5053',
            'error_digest' => ['response_error_codes' => ['MIP5053']],
            'response_structure' => $this->responseStructureWithCandidates(1),
        ], true, true);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_GROUPED_ITINERARY_ERROR,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
    }

    public function test_http_200_application_error_mapping(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_application_warning_or_error',
            'revalidation_failure_class' => 'application_warning',
            'blocking_application_warning_present' => true,
            'application_message_diagnostics' => [
                'blocking_application_warning_present' => true,
                'informational_warning_present' => false,
            ],
            'error_digest' => ['response_error_codes' => ['WARN'], 'response_error_messages' => ['warning']],
            'response_structure' => $this->responseStructureWithCandidates(1),
        ], true, true);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_SUPPLIER_APPLICATION_ERROR,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
    }

    public function test_http_200_fare_basis_incomplete_mapping(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_empty_or_unusable_response',
            'revalidation_failure_class' => 'fare_basis_incomplete',
            'linkage_digest' => ['per_segment_fare_basis_complete' => false],
            'canonical_linkage_normalization' => ['selected_fare_basis_complete' => false],
            'response_linkage_diagnostics' => [
                'response_candidate_count' => 1,
                'structurally_eligible_candidate_count' => 1,
                'exact_segment_signature_match_count' => 1,
                'exact_itinerary_match_count' => 1,
                'pricing_compatible_match_count' => 1,
                'fare_basis_compatible_match_count' => 1,
                'booking_class_compatible_match_count' => 1,
                'unique_usable_linkage_match_count' => 0,
                'ambiguous_linkage_match_count' => 0,
                'pricing_complete' => true,
                'usable_fare_linkage' => false,
            ],
            'response_structure' => $this->responseStructureWithCandidates(1),
        ], true, true);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_FARE_BASIS_INCOMPLETE,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
    }

    public function test_http_200_unusable_fare_linkage_mapping(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_empty_or_unusable_response',
            'revalidation_failure_class' => 'unusable_linkage',
            'linkage_digest' => [
                'per_segment_fare_basis_complete' => true,
                'has_revalidated_fare' => false,
                'has_revalidated_currency' => false,
            ],
            'response_linkage_diagnostics' => [
                'response_candidate_count' => 1,
                'structurally_eligible_candidate_count' => 1,
                'exact_segment_signature_match_count' => 1,
                'exact_itinerary_match_count' => 1,
                'pricing_compatible_match_count' => 1,
                'fare_basis_compatible_match_count' => 1,
                'booking_class_compatible_match_count' => 1,
                'unique_usable_linkage_match_count' => 0,
                'ambiguous_linkage_match_count' => 0,
                'pricing_complete' => true,
                'usable_fare_linkage' => false,
                'linkage_failure_reason_code' => 'no_exact_itinerary_match',
            ],
            'response_structure' => $this->responseStructureWithCandidates(1),
        ], true, true);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_FARE_LINKAGE_MISSING,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
        $this->assertFalse($outcome['usable_fare_linkage']);
    }

    public function test_http_200_valid_fare_linkage_success_mapping(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => true,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_ok',
            'linkage_digest' => [
                'per_segment_fare_basis_complete' => true,
                'has_revalidated_fare' => true,
                'has_revalidated_currency' => true,
            ],
            'response_structure' => $this->responseStructureWithCandidates(1),
        ], true, true);

        $evidence = $this->mapper->mapToScenarioEvidence($outcome, [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);

        $this->assertTrue($evidence['revalidation_success']);
        $this->assertTrue($evidence['freshness_satisfied']);
        $this->assertTrue($evidence['supplier_call_attempted']);
        $this->assertTrue($evidence['supplier_response_received']);
        $this->assertTrue($outcome['usable_fare_linkage']);
    }

    public function test_price_change_mapping(): void
    {
        $outcome = [
            'success' => true,
            'revalidation_attempted' => true,
            'supplier_call_attempted' => true,
            'supplier_response_received' => true,
            'reason_code' => 'sabre_revalidation_ok',
            'fare_comparison' => [
                'stored_total' => 520.83,
                'fresh_total' => 540.00,
                'stored_currency' => 'USD',
                'fresh_currency' => 'USD',
                'mismatches' => ['price_change'],
            ],
        ];

        $evidence = $this->mapper->mapToScenarioEvidence($outcome, [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_PRICE_CHANGED,
            $evidence['revalidation_reason_code'],
        );
        $this->assertTrue($evidence['fare_changed']);
        $this->assertFalse($evidence['freshness_satisfied']);
    }

    public function test_currency_change_mapping(): void
    {
        $outcome = [
            'success' => true,
            'fare_comparison' => [
                'mismatches' => ['currency_change'],
            ],
        ];

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_CURRENCY_CHANGED,
            $this->mapper->classifyScenarioReasonCode($outcome, [], ['currency_change']),
        );
    }

    public function test_selected_offer_route_continuity_correlation_when_segment_signature_matches(): void
    {
        $selected = [
            'selected_segment_signature_hash' => 'abc123',
            'selected_route' => 'LHE-DXB',
            'selected_segment_count' => 2,
            'scenario_search_correlation_id' => 'search-1',
        ];
        $log = [
            'reject_reason' => 'route_continuity_failed',
            'segment_signature_hash' => 'abc123',
            'offer_origin' => 'LHE',
            'offer_destination' => 'DXB',
            'segment_count' => 2,
            'scenario_search_correlation_id' => 'search-1',
        ];

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::CORRELATION_SELECTED_OFFER,
            $this->mapper->assessNormalizerRejectCorrelation($selected, $log),
        );
    }

    public function test_unrelated_route_continuity_warnings_do_not_classify_selected_offer(): void
    {
        $selected = [
            'selected_segment_signature_hash' => 'selected-sig',
            'selected_route' => 'LHE-DXB',
            'selected_segment_count' => 2,
            'scenario_search_correlation_id' => 'search-1',
        ];
        $log = [
            'reject_reason' => 'route_continuity_failed',
            'segment_signature_hash' => 'other-sig',
            'offer_origin' => 'LHE',
            'offer_destination' => 'JFK',
            'segment_count' => 3,
            'scenario_search_correlation_id' => 'search-1',
        ];

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::CORRELATION_UNRELATED_OFFER_SAME_RESPONSE,
            $this->mapper->assessNormalizerRejectCorrelation($selected, $log),
        );
    }

    public function test_scenario_evidence_excludes_raw_supplier_tokens(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'reason_code' => 'sabre_revalidation_failed',
            'message' => 'failed',
            'linkage' => ['offerReference' => 'SHOULD_NOT_PERSIST'],
            'response_structure' => $this->emptyResponseStructure(),
        ], true, true);

        $evidence = $this->mapper->mapToScenarioEvidence($outcome, [
            'selected_total' => 100.0,
            'selected_currency' => 'USD',
        ]);

        $json = json_encode($evidence, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('SHOULD_NOT_PERSIST', $json);
        $this->assertStringNotContainsString('access_token', strtolower($json));
        $this->assertArrayHasKey('revalidation_diagnostics', $evidence);
        $this->assertArrayHasKey('supplier_call_attempted', $evidence);
    }

    public function test_blocked_pre_call_evidence_maps_unsupported_context(): void
    {
        $evidence = $this->mapper->mapBlockedEvidence([
            'block_reason' => SabreGdsLiveScenarioRevalidationGate::REASON_DRAFT_INVALID,
            'attempted' => true,
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_UNSUPPORTED_CONTEXT,
            $evidence['revalidation_reason_code'],
        );
        $this->assertFalse($evidence['supplier_call_attempted']);
    }

    public function test_sparse_outcome_omits_unrecorded_supplier_booleans_and_response_summary(): void
    {
        $evidence = $this->mapper->mapToScenarioEvidence([
            'success' => false,
            'revalidation_attempted' => true,
            'reason_code' => 'scenario_revalidation_failed',
        ], [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
            'pre_block_reason' => 'scenario_revalidation_failed',
        ]);

        $this->assertArrayNotHasKey('supplier_call_attempted', $evidence);
        $this->assertArrayNotHasKey('supplier_response_received', $evidence);
        $this->assertArrayNotHasKey('response_structure_summary', $evidence);
        $this->assertArrayNotHasKey('revalidation_http_status', $evidence);
        $this->assertArrayNotHasKey('revalidation_endpoint_path', $evidence);
        $this->assertTrue($evidence['revalidation_attempted']);
        $this->assertFalse($evidence['revalidation_success']);
        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_FAILED,
            $evidence['revalidation_reason_code'],
        );
    }

    public function test_explicit_false_supplier_booleans_remain_false(): void
    {
        $evidence = $this->mapper->mapToScenarioEvidence([
            'success' => false,
            'revalidation_attempted' => true,
            'supplier_call_attempted' => false,
            'supplier_response_received' => false,
            'reason_code' => 'scenario_revalidation_failed',
        ], [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);

        $this->assertArrayHasKey('supplier_call_attempted', $evidence);
        $this->assertArrayHasKey('supplier_response_received', $evidence);
        $this->assertFalse($evidence['supplier_call_attempted']);
        $this->assertFalse($evidence['supplier_response_received']);
    }

    public function test_absent_response_structure_does_not_synthesize_candidate_count_zero(): void
    {
        $evidence = $this->mapper->mapToScenarioEvidence([
            'success' => false,
            'revalidation_attempted' => true,
            'supplier_call_attempted' => true,
            'supplier_response_received' => true,
            'reason_code' => 'sabre_revalidation_failed',
        ], [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);

        $this->assertArrayNotHasKey('response_structure_summary', $evidence);
    }

    public function test_rich_outcome_preserves_explicit_response_structure_summary(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 500,
            'reason_code' => 'sabre_revalidation_failed',
            'revalidation_failure_class' => 'http_rejected',
            'payload_style' => 'iati_like_bfm_revalidate_v1',
            'endpoint_path' => '/v4/shop/flights/revalidate',
            'response_structure' => $this->responseStructureWithCandidates(0),
        ], true, true);

        $evidence = $this->mapper->mapToScenarioEvidence($outcome, [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);

        $this->assertTrue($evidence['supplier_call_attempted']);
        $this->assertTrue($evidence['supplier_response_received']);
        $this->assertArrayHasKey('response_structure_summary', $evidence);
        $this->assertSame(0, $evidence['response_structure_summary']['candidate_count']);
        $this->assertSame(500, $evidence['revalidation_http_status']);
        $this->assertSame('/v4/shop/flights/revalidate', $evidence['revalidation_endpoint_path']);
        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_HTTP_REJECTED,
            $evidence['revalidation_reason_code'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function emptyResponseStructure(): array
    {
        return [
            'top_level_keys' => '',
            'key_paths' => '',
            'empty_body' => 'true',
            'json_valid' => 'false',
            'candidate_fields' => '',
            'candidate_count' => '0',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function responseStructureWithCandidates(int $count): array
    {
        return [
            'top_level_keys' => 'groupedItineraryResponse',
            'key_paths' => 'groupedItineraryResponse.itineraryGroups',
            'empty_body' => 'false',
            'json_valid' => 'true',
            'candidate_fields' => 'totalFare',
            'candidate_count' => (string) $count,
        ];
    }
}
