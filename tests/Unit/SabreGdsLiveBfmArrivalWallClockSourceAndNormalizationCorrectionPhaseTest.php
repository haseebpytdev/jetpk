<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSegmentSignature;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use Tests\TestCase;

class SabreGdsLiveBfmArrivalWallClockSourceAndNormalizationCorrectionPhaseTest extends TestCase
{
    public function test_iso_datetime_and_clock_only_normalize_to_same_wall_clock(): void
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
        $this->assertSame('11:35', $canonical->comparableWallClock($iso['departure_at']));
        $this->assertSame('15:05', $canonical->comparableWallClock($iso['arrival_at']));
        $this->assertSame(
            $canonical->hashFromSegments([$iso]),
            $canonical->hashFromSegments([$clock]),
        );
    }

    public function test_timezone_offset_does_not_shift_local_wall_clock_on_shop_iso(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $plain = ['origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '2026-09-01T11:35:00', 'arrival_at' => '2026-09-01T15:05:00', 'marketing_carrier' => 'QR', 'flight_number' => '629'];
        $offset = ['origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '2026-09-01T11:35:00+05:00', 'arrival_at' => '2026-09-01T15:05:00+03:00', 'marketing_carrier' => 'QR', 'flight_number' => '629'];
        $this->assertSame(
            $canonical->hashFromSegments([$plain]),
            $canonical->hashFromSegments([$offset]),
        );
    }

    public function test_schedule_desc_prefers_local_time_over_utc_shifted_date_time(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $row = $canonical->segmentRowFromScheduleDesc([
            'departure' => ['airport' => 'LHE', 'time' => '11:35:00'],
            'arrival' => [
                'airport' => 'DOH',
                'time' => '15:05:00',
                'dateTime' => '2026-09-01T12:05:00Z',
            ],
            'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629'],
        ], ['schedule_desc_ref' => '1']);
        $this->assertSame('15:05:00', $row['arrival_at']);
        $this->assertSame('bfm_endpoint_time_local', $row['arrival_clock_source_shape']);
        $shop = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '2026-09-01T11:35:00',
            'arrival_at' => '2026-09-01T15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
        ];
        $this->assertSame(
            $canonical->scheduleHashTupleSegmentDigests([$shop]),
            $canonical->scheduleHashTupleSegmentDigests([$row]),
        );
    }

    public function test_overnight_arrival_date_adjustment_preserves_wall_clock_in_tuple(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $row = $canonical->segmentRowFromScheduleDesc([
            'departure' => ['airport' => 'DOH', 'time' => '23:10:00'],
            'arrival' => ['airport' => 'JED', 'time' => '01:40:00', 'dateAdjustment' => 1],
            'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '1182'],
        ]);
        $this->assertSame(1, $row['arrival_date_adjustment_days']);
        $tuple = $canonical->scheduleHashTupleFromSegment($row);
        $this->assertSame('01:40', $tuple[6]);
        $shop = [
            'origin' => 'DOH',
            'destination' => 'JED',
            'departure_at' => '2026-09-01T23:10:00',
            'arrival_at' => '2026-09-02T01:40:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '1182',
        ];
        $this->assertSame(
            $canonical->scheduleHashTupleFromSegment($shop),
            $tuple,
        );
    }

    public function test_elapsed_time_is_not_used_as_arrival_clock(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $row = $canonical->segmentRowFromScheduleDesc([
            'elapsedTime' => 210,
            'departure' => ['airport' => 'LHE', 'time' => '11:35:00'],
            'arrival' => ['airport' => 'DOH'],
            'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629'],
        ]);
        $this->assertSame('', $canonical->comparableWallClock((string) ($row['arrival_at'] ?? '')));
    }

    public function test_departure_and_arrival_endpoints_are_not_confused(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $row = $canonical->segmentRowFromScheduleDesc([
            'departure' => ['airport' => 'LHE', 'time' => '11:35:00'],
            'arrival' => ['airport' => 'DOH', 'time' => '15:05:00'],
            'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629'],
        ]);
        $this->assertSame('11:35', $canonical->comparableWallClock($row['departure_at']));
        $this->assertSame('15:05', $canonical->comparableWallClock($row['arrival_at']));
    }

    public function test_genuinely_different_arrival_wall_clock_remains_mismatch(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $expected = [['origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '11:35:00', 'arrival_at' => '15:05:00', 'marketing_carrier' => 'QR', 'flight_number' => '629']];
        $actual = [['origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '11:35:00', 'arrival_at' => '16:00:00', 'marketing_carrier' => 'QR', 'flight_number' => '629']];
        $comparison = $canonical->safeLinkageDigestComparison($expected, $actual);
        $this->assertContains('arrival_wall_clock', $comparison['tuple_mismatch_field_names'] ?? []);
    }

    public function test_live_qr_shaped_response_matches_selected_with_conflicting_arrival_date_time(): void
    {
        $phase4 = new SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest('x');
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = (new \ReflectionMethod($phase4, 'qrLiveDraft'))->invoke($phase4);
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $response = $this->qrLiveResponseWithUtcShiftedArrivalDateTimeOnSegmentOne($phase4);
        $analysis = $linker->analyze($response, $selected);
        $this->assertSame(1, $analysis['exact_segment_signature_match_count']);
        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);
        $this->assertSame(1, $analysis['fare_basis_compatible_match_count']);

        $segments = $linker->normalizedCandidateSegmentsForDiagnostics(
            $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0],
            $response,
        );
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $comparison = $canonical->safeLinkageDigestComparison($selected['segments'], $segments);
        $this->assertNull($comparison['mismatch_categories'] ?? null);
        $this->assertSame(
            $canonical->scheduleHashTupleSegmentDigests($selected['segments'])[1],
            $canonical->scheduleHashTupleSegmentDigests($segments)[1],
        );
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
     * @param  SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest  $phase4
     * @return array<string, mixed>
     */
    private function qrLiveResponseWithUtcShiftedArrivalDateTimeOnSegmentOne(SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest $phase4): array
    {
        $tables = (new \ReflectionMethod($phase4, 'qrLiveDescriptorTables'))->invoke($phase4);
        $tables['scheduleDescs'][0]['arrival']['dateTime'] = '2026-09-01T12:05:00Z';

        return [
            'groupedItineraryResponse' => array_merge($tables, [
                'fareComponentDescs' => [
                    ['ref' => 10, 'fareBasisCode' => 'SLOW1'],
                    ['ref' => 11, 'fareBasisCode' => 'SLOW2'],
                ],
                'itineraryGroups' => [[
                    'itineraries' => [(new \ReflectionMethod($phase4, 'qrMatchingItinerary'))->invoke($phase4, 540.73)],
                ]],
            ]),
        ];
    }
}
