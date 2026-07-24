<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationLinkageAggregateContract;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use Tests\TestCase;

final class SabreGdsRevalidationAggregateFareBasisCompletenessAndUsableLinkageDerivationCorrectionPhaseTest extends TestCase
{
    private SabreGdsRevalidationLinkageAggregateContract $aggregates;

    private SabreGdsLiveScenarioRevalidationOutcomeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregates = app(SabreGdsRevalidationLinkageAggregateContract::class);
        $this->mapper = app(SabreGdsLiveScenarioRevalidationOutcomeMapper::class);
    }

    public function test_all_scoped_complete_produces_overall_true(): void
    {
        $normalized = $this->aggregates->normalize($this->productionLinkageCounts(), [
            'selected_fare_basis_complete' => true,
            'draft_fare_basis_complete' => true,
            'candidate_fare_basis_complete' => true,
        ]);

        $this->assertTrue($normalized['overall_fare_basis_complete']);
        $this->assertTrue($normalized['fare_basis_complete']);
        $this->assertTrue($normalized['usable_fare_linkage']);
    }

    public function test_candidate_per_segment_present_all_true_produces_candidate_true(): void
    {
        $normalized = $this->aggregates->normalize([
            'selected_response_candidate_ordinal' => 1,
            'unique_usable_linkage_match_count' => 1,
        ], [
            'fare_basis_presence_by_candidate' => [
                '1' => ['complete' => true, 'per_segment_present' => [true, true]],
            ],
        ]);

        $this->assertTrue($normalized['candidate_fare_basis_complete']);
    }

    public function test_fare_basis_compatible_count_alone_is_not_enough_when_segment_missing(): void
    {
        $normalized = $this->aggregates->normalize([
            'fare_basis_compatible_match_count' => 1,
            'unique_usable_linkage_match_count' => 1,
            'ambiguous_linkage_match_count' => 0,
            'exact_segment_signature_match_count' => 1,
            'exact_itinerary_match_count' => 1,
            'pricing_compatible_match_count' => 1,
            'booking_class_compatible_match_count' => 1,
            'pricing_complete' => true,
            'selected_response_candidate_ordinal' => 1,
        ], [
            'selected_fare_basis_complete' => false,
            'draft_fare_basis_complete' => true,
            'fare_basis_presence_by_candidate' => [
                '1' => ['complete' => true, 'per_segment_present' => [true, true]],
            ],
        ]);

        $this->assertFalse($normalized['overall_fare_basis_complete']);
        $this->assertFalse($normalized['usable_fare_linkage']);
    }

    public function test_stale_booking_false_cannot_override_authoritative_linker_true(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'revalidation_failure_class' => 'unusable_linkage',
            'linkage_digest' => ['per_segment_fare_basis_complete' => false],
            'response_linkage_diagnostics' => array_merge($this->productionLinkageCounts(), [
                'usable_fare_linkage' => false,
            ]),
            'canonical_linkage_normalization' => [
                'selected_fare_basis_complete' => true,
                'draft_fare_basis_complete' => true,
                'candidate_fare_basis_complete' => true,
            ],
            'response_structure' => $this->responseStructureWithCandidates(31),
        ], true, true);

        $this->assertTrue($outcome['fare_basis_complete']);
        $this->assertTrue($outcome['usable_fare_linkage']);
    }

    public function test_production_shaped_snapshot_maps_to_success(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => false,
            'http_status' => 200,
            'revalidation_failure_class' => 'unusable_linkage',
            'linkage_digest' => ['per_segment_fare_basis_complete' => false],
            'response_linkage_diagnostics' => $this->productionLinkageCounts(),
            'canonical_linkage_normalization' => [
                'selected_fare_basis_complete' => true,
                'draft_fare_basis_complete' => true,
                'candidate_fare_basis_complete' => true,
                'fare_basis_presence_by_candidate' => [
                    '1' => ['complete' => true, 'per_segment_present' => [true, true]],
                ],
            ],
            'response_structure' => $this->responseStructureWithCandidates(31),
        ], true, true);

        $evidence = $this->mapper->mapToScenarioEvidence($outcome, [
            'selected_total' => 540.73,
            'selected_currency' => 'USD',
        ]);

        $snapshot = $evidence['revalidation_diagnostics']['outcome_mapper_input_snapshot'] ?? [];
        $this->assertTrue($snapshot['fare_basis_complete'] ?? false);
        $this->assertTrue($snapshot['usable_fare_linkage'] ?? false);
        $this->assertSame(SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_SUCCESS, $evidence['revalidation_reason_code']);
        $this->assertTrue($evidence['revalidation_success']);
        $this->assertSame('scenario_revalidation_success', $evidence['revalidation_diagnostics']['scenario_reason_code_selected'] ?? null);
    }

    public function test_ambiguity_produces_usable_false(): void
    {
        $normalized = $this->aggregates->normalize(array_merge($this->productionLinkageCounts(), [
            'unique_usable_linkage_match_count' => 2,
            'ambiguous_linkage_match_count' => 2,
        ]), [
            'selected_fare_basis_complete' => true,
            'draft_fare_basis_complete' => true,
            'candidate_fare_basis_complete' => true,
        ]);

        $this->assertFalse($normalized['usable_fare_linkage']);
    }

    /**
     * @return array<string, mixed>
     */
    private function productionLinkageCounts(): array
    {
        return [
            'response_candidate_count' => 31,
            'structurally_eligible_candidate_count' => 1,
            'exact_segment_signature_match_count' => 1,
            'exact_itinerary_match_count' => 1,
            'pricing_compatible_match_count' => 1,
            'fare_basis_compatible_match_count' => 1,
            'booking_class_compatible_match_count' => 1,
            'unique_usable_linkage_match_count' => 1,
            'ambiguous_linkage_match_count' => 0,
            'pricing_complete' => true,
            'usable_fare_linkage' => false,
            'selected_response_candidate_ordinal' => 1,
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
