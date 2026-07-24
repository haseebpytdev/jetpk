<?php

namespace App\Support\Sabre\Revalidation;

/**
 * Safe, non-payload linkage normalization diagnostics for revalidation candidate comparison.
 */
final class SabreGdsRevalidationLinkageNormalizationDiagnostics
{
    public function __construct(
        private readonly SabreGdsRevalidationCanonicalSegmentSignature $segmentSignature,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedContext
     * @param  array<string, mixed>  $linkageAnalysis
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>
     */
    public function buildForAnalysis(array $selectedContext, array $linkageAnalysis, ?array $response): array
    {
        $expected = is_array($selectedContext['segments'] ?? null) ? $selectedContext['segments'] : [];
        $ordinal = (int) ($linkageAnalysis['selected_response_candidate_ordinal'] ?? 0);
        $candidateSegments = $this->resolveBestCandidateSegments($selectedContext, $response, $linkageAnalysis, $ordinal);
        $comparison = $this->segmentSignature->safeLinkageDigestComparison($expected, $candidateSegments);

        return array_filter([
            'selected_segment_signature_digest' => $selectedContext['segment_signature'] ?? null,
            'selected_segment_count' => (int) ($selectedContext['segment_count'] ?? count($expected)),
            'response_candidate_ordinal_compared' => $ordinal > 0 ? $ordinal : null,
            'linkage_failure_reason_code' => $linkageAnalysis['linkage_failure_reason_code'] ?? null,
            'linkage_missing_components' => $linkageAnalysis['linkage_missing_components'] ?? null,
            'segment_normalization' => $comparison,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @param  array<string, mixed>  $linkageAnalysis
     * @return list<array<string, mixed>>
     */
    private function resolveBestCandidateSegments(array $selectedContext, ?array $response, array $linkageAnalysis, int $ordinal): array
    {
        if (! is_array($response) || $response === []) {
            return [];
        }

        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $candidates = $linker->enumerateCandidates($response);
        if ($ordinal > 0) {
            foreach ($candidates as $candidate) {
                if ((int) $candidate['ordinal'] === $ordinal) {
                    return $linker->normalizedCandidateSegmentsForDiagnostics($candidate['itinerary'], $response);
                }
            }
        }

        if (($linkageAnalysis['structurally_eligible_candidate_count'] ?? 0) === 1) {
            foreach ($candidates as $candidate) {
                $segments = $linker->normalizedCandidateSegmentsForDiagnostics($candidate['itinerary'], $response);
                if ($segments !== []) {
                    return $segments;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $segments = $linker->normalizedCandidateSegmentsForDiagnostics($candidate['itinerary'], $response);
            if ($segments === []) {
                continue;
            }
            $digest = $this->segmentSignature->safeLinkageDigestComparison(
                is_array($selectedContext['segments'] ?? null) ? $selectedContext['segments'] : [],
                $segments,
            );
            if (($digest['mismatch_categories'] ?? null) !== null) {
                return $segments;
            }
        }

        return [];
    }
}
