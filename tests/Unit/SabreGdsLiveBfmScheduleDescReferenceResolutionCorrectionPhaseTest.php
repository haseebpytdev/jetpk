<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSegmentSignature;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationGirDescriptorResolver;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use Tests\TestCase;

class SabreGdsLiveBfmScheduleDescReferenceResolutionCorrectionPhaseTest extends TestCase
{
    public function test_descriptor_id_and_ref_both_indexed_for_lookup(): void
    {
        $resolver = app(SabreGdsRevalidationGirDescriptorResolver::class);
        $slice = $resolver->buildResolutionSlice([
            ['id' => 7, 'ref' => 3, 'departure' => ['airport' => 'LHE', 'time' => '11:35:00'], 'arrival' => ['airport' => 'DOH', 'time' => '15:05:00'], 'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629']],
        ]);
        $this->assertSame('15:05', app(SabreGdsRevalidationCanonicalSegmentSignature::class)->comparableWallClock(
            (string) ($resolver->resolveDescriptor($slice, 7)['arrival']['time'] ?? ''),
        ));
        $this->assertSame('15:05', app(SabreGdsRevalidationCanonicalSegmentSignature::class)->comparableWallClock(
            (string) ($resolver->resolveDescriptor($slice, 3)['arrival']['time'] ?? ''),
        ));
    }

    public function test_string_and_integer_schedule_refs_resolve_identically(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrDraft();
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $intRef = $this->duplicateQr629Response(99, '15:05:00', 1, '13:05:00');
        $stringRef = $intRef;
        $stringRef['groupedItineraryResponse']['legDescs'][0]['schedules'] = [['ref' => '99']];
        $this->assertSame(
            $linker->analyze($intRef, $selected)['exact_segment_signature_match_count'],
            $linker->analyze($stringRef, $selected)['exact_segment_signature_match_count'],
        );
    }

    public function test_duplicate_qr_629_selects_referenced_15_05_not_first_13_05(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $draft = $this->qrDraft();
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $response = $this->duplicateQr629Response(99, '15:05:00', 1, '13:05:00');
        $analysis = $linker->analyze($response, $selected);
        $this->assertSame(1, $analysis['exact_segment_signature_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);
        $segments = $linker->normalizedCandidateSegmentsForDiagnostics(
            $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0],
            $response,
        );
        $this->assertSame('15:05', $canonical->comparableWallClock((string) ($segments[0]['arrival_at'] ?? '')));
        $comparison = $canonical->safeLinkageDigestComparison($selected['segments'], $segments);
        $this->assertNull($comparison['tuple_mismatch_field_names'] ?? null);
    }

    public function test_schedule_id_at_array_index_zero_does_not_imply_ref_zero(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrDraft();
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $response = $this->duplicateQr629Response(99, '15:05:00', 1, '13:05:00');
        $response['groupedItineraryResponse']['scheduleDescs'][1]['id'] = 99;
        unset($response['groupedItineraryResponse']['scheduleDescs'][1]['ref']);
        $response['groupedItineraryResponse']['legDescs'][0]['schedules'] = [['id' => 99]];
        $analysis = $linker->analyze($response, $selected);
        $this->assertSame(1, $analysis['exact_segment_signature_match_count']);
    }

    public function test_unresolved_schedule_reference_fails_closed(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrDraft();
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $response = $this->duplicateQr629Response(99, '15:05:00', 1, '13:05:00');
        $response['groupedItineraryResponse']['legDescs'][0]['schedules'] = [['ref' => 404]];
        $analysis = $linker->analyze($response, $selected);
        $this->assertSame(0, $analysis['exact_segment_signature_match_count']);
        $this->assertFalse($analysis['usable_fare_linkage']);
    }

    public function test_ambiguous_descriptor_key_fails_closed(): void
    {
        $resolver = app(SabreGdsRevalidationGirDescriptorResolver::class);
        $slice = $resolver->buildResolutionSlice([
            ['id' => 5, 'ref' => 5, 'departure' => ['airport' => 'LHE', 'time' => '11:35:00'], 'arrival' => ['airport' => 'DOH', 'time' => '13:05:00'], 'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629']],
            ['id' => 5, 'ref' => 6, 'departure' => ['airport' => 'LHE', 'time' => '11:35:00'], 'arrival' => ['airport' => 'DOH', 'time' => '15:05:00'], 'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629']],
        ]);
        $this->assertContains(5, $slice['ambiguous_keys']);
        $this->assertNull($resolver->resolveDescriptor($slice, 5));
    }

    public function test_segment_order_preserved_from_leg_schedule_references(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $response = $this->duplicateQr629Response(99, '15:05:00', 1, '13:05:00');
        $segments = $linker->normalizedCandidateSegmentsForDiagnostics(
            $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0],
            $response,
        );
        $this->assertSame('LHE', $segments[0]['origin'] ?? null);
        $this->assertSame('DOH', $segments[0]['destination'] ?? null);
        $this->assertSame('DOH', $segments[1]['origin'] ?? null);
        $this->assertSame('JED', $segments[1]['destination'] ?? null);
    }

    public function test_descriptor_resolution_diagnostics_include_duplicate_count(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $response = $this->duplicateQr629Response(99, '15:05:00', 1, '13:05:00');
        $itinerary = $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0];
        $diag = $linker->buildCandidateScheduleDescriptorResolutionDiagnostics($itinerary, $response, 1);
        $this->assertCount(2, $diag['matching_qr_629_lhe_doh_schedule_summaries'] ?? []);
        $this->assertSame(2, $diag['segment_resolution'][0]['duplicate_matching_schedule_count'] ?? null);
        $this->assertSame('id_map', $diag['segment_resolution'][0]['lookup_mode'] ?? null);
    }

    public function test_thirty_one_candidate_fixture_ordinal_two_still_uniquely_usable(): void
    {
        $fixturePath = base_path('tests/Fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json');
        $decoded = json_decode((string) file_get_contents($fixturePath), true);
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $analysis = $linker->analyze(
            $decoded['response'],
            $linker->buildSelectedContextFromDraft($decoded['api_draft']),
            31,
        );
        $this->assertSame(2, $analysis['selected_response_candidate_ordinal']);
        $this->assertTrue($analysis['usable_fare_linkage']);
    }

    /**
     * @return array<string, mixed>
     */
    private function qrDraft(): array
    {
        $phase4 = new SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest('x');

        return (new \ReflectionMethod($phase4, 'qrLiveDraft'))->invoke($phase4);
    }

    /**
     * Two LHE-DOH QR 629 scheduleDesc rows; leg 1 references $selectedRef (15:05), decoy uses $decoyRef (13:05).
     *
     * @return array<string, mixed>
     */
    private function duplicateQr629Response(int $selectedRef, string $selectedArrival, int $decoyRef, string $decoyArrival): array
    {
        $phase4 = new SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest('x');
        $tables = (new \ReflectionMethod($phase4, 'qrLiveDescriptorTables'))->invoke($phase4);
        $tables['scheduleDescs'] = [
            [
                'ref' => $decoyRef,
                'departure' => ['airport' => 'LHE', 'time' => '11:35:00'],
                'arrival' => ['airport' => 'DOH', 'time' => $decoyArrival],
                'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629'],
            ],
            [
                'ref' => $selectedRef,
                'departure' => ['airport' => 'LHE', 'time' => '11:35:00'],
                'arrival' => ['airport' => 'DOH', 'time' => $selectedArrival],
                'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629'],
            ],
            $tables['scheduleDescs'][1],
        ];
        $tables['legDescs'][0]['schedules'] = [['ref' => $selectedRef]];

        return [
            'groupedItineraryResponse' => array_merge($tables, [
                'fareComponentDescs' => [
                    ['ref' => 10, 'fareBasisCode' => 'SLOW1'],
                    ['ref' => 11, 'fareBasisCode' => 'SLOW2'],
                ],
                'itineraryGroups' => [[
                    'itineraries' => [(new \ReflectionMethod($phase4, 'qrMatchingItinerary'))->invoke($phase4, 569.73)],
                ]],
            ]),
        ];
    }
}
