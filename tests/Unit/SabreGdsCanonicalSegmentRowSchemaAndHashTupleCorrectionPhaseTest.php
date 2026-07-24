<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSegmentSignature;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use Tests\TestCase;

class SabreGdsCanonicalSegmentRowSchemaAndHashTupleCorrectionPhaseTest extends TestCase
{
    public function test_absent_key_and_empty_string_field_hash_identically(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $withKey = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
            'operating_carrier' => '',
        ];
        $withoutKey = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
        ];
        $this->assertSame(
            $canonical->hashFromSegments([$withKey]),
            $canonical->hashFromSegments([$withoutKey]),
        );
    }

    public function test_null_and_empty_string_hash_identically(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $nullish = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => null,
            'arrival_at' => null,
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
        ];
        $empty = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '',
            'arrival_at' => '',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
        ];
        $this->assertSame(
            $canonical->hashFromSegments([$nullish]),
            $canonical->hashFromSegments([$empty]),
        );
    }

    public function test_associative_key_insertion_order_cannot_affect_digest(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $a = [
            'flight_number' => '629',
            'origin' => 'LHE',
            'marketing_carrier' => 'QR',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'diagnostic_only' => 'ignored',
        ];
        $b = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
        ];
        $this->assertSame($canonical->hashFromSegments([$a]), $canonical->hashFromSegments([$b]));
    }

    public function test_extra_diagnostic_fields_cannot_affect_digest(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $base = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '2026-09-01T11:35:00',
            'arrival_at' => '2026-09-01T15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
            'booking_class' => 'S',
        ];
        $withDiagnostics = array_merge($base, [
            'operating_carrier_shape_category' => 'same_as_marketing',
            'canonical_operating_carrier_slot' => '',
            'carrier' => ['marketing' => 'QR', 'operating' => 'QR'],
            'equipment' => '788',
        ]);
        $this->assertSame(
            $canonical->hashFromSegments($canonical->canonicalScheduleIdentityRows([$base])),
            $canonical->hashFromSegments($canonical->canonicalScheduleIdentityRows([$withDiagnostics])),
        );
    }

    public function test_fixed_hash_tuple_schema_has_seven_fields_in_order(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $tuple = $canonical->scheduleHashTupleFromSegment([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '0629',
        ]);
        $this->assertSame(SabreGdsRevalidationCanonicalSegmentSignature::HASH_TUPLE_FIELD_COUNT, count($tuple));
        $this->assertSame(
            ['LHE', 'DOH', 'QR', '', '629', '11:35', '15:05'],
            $tuple,
        );
        $this->assertSame($canonical->hashTupleFieldLabels(), [
            'route_origin',
            'route_destination',
            'marketing_carrier',
            'canonical_operating_carrier_slot',
            'normalized_flight_number',
            'departure_wall_clock',
            'arrival_wall_clock',
        ]);
    }

    public function test_same_as_marketing_operating_hashes_like_absent(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $absent = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
        ];
        $sameAsMarketing = array_merge($absent, [
            'operating_carrier' => 'QR',
            'carrier' => ['marketing' => 'QR', 'operating' => 'QR'],
        ]);
        $this->assertSame(
            $canonical->scheduleHashTuplesFromSegments([$absent]),
            $canonical->scheduleHashTuplesFromSegments([$sameAsMarketing]),
        );
    }

    public function test_genuine_codeshare_still_differs_in_tuple(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $marketingOnly = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
        ];
        $codeshare = array_merge($marketingOnly, ['operating_carrier' => 'BA']);
        $this->assertNotSame(
            $canonical->hashFromSegments([$marketingOnly]),
            $canonical->hashFromSegments([$codeshare]),
        );
        $comparison = $canonical->safeLinkageDigestComparison([$marketingOnly], [$codeshare]);
        $this->assertContains('operating_carrier', $comparison['mismatch_categories'] ?? []);
        $this->assertContains('canonical_operating_carrier_slot', $comparison['tuple_mismatch_field_names'] ?? []);
    }

    public function test_mismatch_classifier_uses_tuple_not_raw_operating_carrier(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $selected = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
            'booking_class' => 'S',
        ];
        $candidate = array_merge($selected, [
            'operating_carrier' => 'QR',
            'carrier' => ['marketing' => 'QR', 'operating' => 'QR'],
            'operating_carrier_shape_category' => 'same_as_marketing',
            'canonical_operating_carrier_slot' => '',
        ]);
        $comparison = $canonical->safeLinkageDigestComparison([$selected], [$candidate]);
        $this->assertNull($comparison['mismatch_categories'] ?? null);
        $this->assertNull($comparison['tuple_mismatch_field_names'] ?? null);
        $this->assertSame(
            $canonical->scheduleHashTupleSegmentDigests([$selected]),
            $canonical->scheduleHashTupleSegmentDigests([$candidate]),
        );
    }

    public function test_iso_departure_and_wall_clock_only_segments_align_in_tuple(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $iso = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '2026-09-01T11:35:00',
            'arrival_at' => '2026-09-01T15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
        ];
        $clock = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
        ];
        $this->assertSame(
            $canonical->hashFromSegments([$iso]),
            $canonical->hashFromSegments([$clock]),
        );
    }

    public function test_live_qr_shaped_candidate_matches_with_empty_mismatch_categories(): void
    {
        $phase4 = new SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest('x');
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $draft = (new \ReflectionMethod($phase4, 'qrLiveDraft'))->invoke($phase4);
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $response = (new \ReflectionMethod($phase4, 'qrLiveResponseWithFareComponentDescRefs'))->invoke($phase4);
        $analysis = $linker->analyze($response, $selected);
        $this->assertSame(1, $analysis['exact_segment_signature_match_count']);
        $this->assertSame(1, $analysis['exact_itinerary_match_count']);
        $this->assertSame(1, $analysis['pricing_compatible_match_count']);
        $this->assertSame(1, $analysis['fare_basis_compatible_match_count']);
        $this->assertSame(1, $analysis['booking_class_compatible_match_count']);
        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertSame(0, $analysis['ambiguous_linkage_match_count']);
        $this->assertTrue($analysis['pricing_complete']);
        $this->assertTrue($analysis['usable_fare_linkage']);

        $expectedSegments = is_array($selected['segments'] ?? null) ? $selected['segments'] : [];
        $candidateSegments = $linker->normalizedCandidateSegmentsForDiagnostics(
            $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0],
            $response,
        );
        $comparison = $canonical->safeLinkageDigestComparison($expectedSegments, $candidateSegments);
        $this->assertNull($comparison['mismatch_categories'] ?? null);
        $this->assertCount(2, $canonical->scheduleHashTupleSegmentDigests($expectedSegments));
        $this->assertSame(
            $canonical->scheduleHashTupleSegmentDigests($expectedSegments),
            $canonical->scheduleHashTupleSegmentDigests($candidateSegments),
        );
    }

    public function test_thirty_one_candidate_fixture_ordinal_two_still_links(): void
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
        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);
    }
}
