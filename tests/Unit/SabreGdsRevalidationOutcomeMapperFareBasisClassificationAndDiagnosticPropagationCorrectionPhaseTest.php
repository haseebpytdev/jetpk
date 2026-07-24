<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use Tests\TestCase;

final class SabreGdsRevalidationOutcomeMapperFareBasisClassificationAndDiagnosticPropagationCorrectionPhaseTest extends TestCase
{
    private SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = app(SabreGdsLiveScenarioRevalidationOutcomeMapper::class);
    }

    public function test_fare_basis_complete_true_cannot_map_to_fare_basis_incomplete(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_empty_or_unusable_response',
            'revalidation_failure_class' => 'fare_basis_incomplete',
            'linkage_digest' => ['per_segment_fare_basis_complete' => true],
            'response_linkage_diagnostics' => [
                'response_candidate_count' => 2,
                'structurally_eligible_candidate_count' => 1,
                'exact_segment_signature_match_count' => 1,
                'exact_itinerary_match_count' => 1,
                'pricing_compatible_match_count' => 1,
                'fare_basis_compatible_match_count' => 1,
                'booking_class_compatible_match_count' => 1,
                'unique_usable_linkage_match_count' => 1,
                'ambiguous_linkage_match_count' => 0,
                'pricing_complete' => true,
                'usable_fare_linkage' => true,
                'selected_response_candidate_ordinal' => 1,
            ],
            'canonical_linkage_normalization' => [
                'selected_fare_basis_complete' => true,
                'draft_fare_basis_complete' => true,
                'candidate_fare_basis_complete' => true,
            ],
            'response_structure' => $this->responseStructureWithCandidates(2),
        ], true, true);

        $this->assertTrue($outcome['fare_basis_complete']);
        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_SUCCESS,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
    }

    public function test_missing_linkage_diagnostics_map_to_diagnostics_incomplete_not_fare_basis_incomplete(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_empty_or_unusable_response',
            'revalidation_failure_class' => 'fare_basis_incomplete',
            'linkage_digest' => ['per_segment_fare_basis_complete' => false],
            'response_structure' => $this->responseStructureWithCandidates(1),
        ], true, true);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_DIAGNOSTICS_INCOMPLETE,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
    }

    public function test_genuinely_incomplete_selected_fare_basis_maps_correctly(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'revalidation_failure_class' => 'fare_basis_incomplete',
            'linkage_digest' => ['per_segment_fare_basis_complete' => false],
            'canonical_linkage_normalization' => ['selected_fare_basis_complete' => false],
            'response_linkage_diagnostics' => $this->partialLinkageDiagnostics(),
            'response_structure' => $this->responseStructureWithCandidates(1),
        ], true, true);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_FARE_BASIS_INCOMPLETE,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
    }

    public function test_fare_basis_incompatibility_is_distinct_from_incompleteness(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'revalidation_failure_class' => 'unusable_linkage',
            'linkage_digest' => ['per_segment_fare_basis_complete' => true],
            'response_linkage_diagnostics' => array_merge($this->partialLinkageDiagnostics(), [
                'fare_basis_compatible_match_count' => 0,
                'linkage_failure_reason_code' => SabreGdsRevalidationResponseCandidateLinker::REASON_FARE_BASIS_INCOMPATIBLE,
            ]),
            'response_structure' => $this->responseStructureWithCandidates(2),
        ], true, true);

        $code = $this->mapper->classifyScenarioReasonCode($outcome);
        $this->assertNotSame(SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_FARE_BASIS_INCOMPLETE, $code);
        $this->assertSame(SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_FARE_LINKAGE_MISSING, $code);
    }

    public function test_no_exact_signature_maps_to_linkage_failure(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'revalidation_failure_class' => 'unusable_linkage',
            'response_linkage_diagnostics' => array_merge($this->partialLinkageDiagnostics(), [
                'exact_segment_signature_match_count' => 0,
                'linkage_failure_reason_code' => SabreGdsRevalidationResponseCandidateLinker::REASON_NO_EXACT_SEGMENT_SIGNATURE_MATCH,
            ]),
            'response_structure' => $this->responseStructureWithCandidates(3),
        ], true, true);

        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_FARE_LINKAGE_MISSING,
            $this->mapper->classifyScenarioReasonCode($outcome),
        );
    }

    public function test_synthetic_full_linkage_success_despite_stale_failure_class(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'revalidation_failure_class' => 'fare_basis_incomplete',
            'linkage_digest' => ['per_segment_fare_basis_complete' => true],
            'response_linkage_diagnostics' => [
                'response_candidate_count' => 5,
                'structurally_eligible_candidate_count' => 2,
                'exact_segment_signature_match_count' => 1,
                'exact_itinerary_match_count' => 1,
                'pricing_compatible_match_count' => 1,
                'fare_basis_compatible_match_count' => 1,
                'booking_class_compatible_match_count' => 1,
                'unique_usable_linkage_match_count' => 1,
                'ambiguous_linkage_match_count' => 0,
                'pricing_complete' => true,
                'usable_fare_linkage' => true,
                'selected_response_candidate_ordinal' => 2,
            ],
            'canonical_linkage_normalization' => [
                'selected_fare_basis_complete' => true,
                'draft_fare_basis_complete' => true,
                'candidate_fare_basis_complete' => true,
            ],
            'response_structure' => $this->responseStructureWithCandidates(5),
        ], true, true);

        $evidence = $this->mapper->mapToScenarioEvidence($outcome, [
            'selected_total' => 569.73,
            'selected_currency' => 'USD',
        ]);

        $this->assertSame(SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_SUCCESS, $evidence['revalidation_reason_code']);
        $this->assertTrue($evidence['revalidation_success']);
        $this->assertSame(1, $evidence['revalidation_diagnostics']['unique_usable_linkage_match_count'] ?? null);
        $this->assertNotEmpty($evidence['revalidation_diagnostics']['outcome_mapper_input_snapshot'] ?? null);
    }

    public function test_failed_http_200_path_retains_complete_safe_diagnostics(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'revalidation_failure_class' => 'unusable_linkage',
            'response_linkage_diagnostics' => array_merge($this->partialLinkageDiagnostics(), [
                'unique_usable_linkage_match_count' => 0,
                'ambiguous_linkage_match_count' => 0,
                'linkage_failure_reason_code' => SabreGdsRevalidationResponseCandidateLinker::REASON_NO_EXACT_ITINERARY_MATCH,
            ]),
            'response_structure' => $this->responseStructureWithCandidates(2),
        ], true, true);

        $evidence = $this->mapper->mapToScenarioEvidence($outcome, [
            'selected_total' => 100,
            'selected_currency' => 'USD',
        ]);

        $diag = $evidence['revalidation_diagnostics'] ?? [];
        foreach ([
            'response_candidate_count',
            'structurally_eligible_candidate_count',
            'exact_segment_signature_match_count',
            'exact_itinerary_match_count',
            'pricing_compatible_match_count',
            'fare_basis_compatible_match_count',
            'booking_class_compatible_match_count',
            'unique_usable_linkage_match_count',
            'ambiguous_linkage_match_count',
            'outcome_mapper_input_snapshot',
            'scenario_reason_code_selected',
            'scenario_reason_predicate',
        ] as $key) {
            $this->assertArrayHasKey($key, $diag, 'missing diagnostic key: '.$key);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function partialLinkageDiagnostics(): array
    {
        return [
            'response_candidate_count' => 2,
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
        ];
    }

    /**
     * @return array<string, string>
     */
    private function responseStructureWithCandidates(int $count): array
    {
        return [
            'top_level_keys' => 'groupedItineraryResponse',
            'key_paths' => '',
            'empty_body' => 'false',
            'json_valid' => 'true',
            'candidate_fields' => 'itinerary',
            'candidate_count' => (string) $count,
        ];
    }
}
