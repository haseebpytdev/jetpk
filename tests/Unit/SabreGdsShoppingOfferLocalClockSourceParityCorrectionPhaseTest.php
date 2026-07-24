<?php

namespace Tests\Unit;

use App\Data\FlightSegmentData;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Support\Sabre\Revalidation\SabreGdsBfmScheduleEndpointLocalClock;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSegmentSignature;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use Tests\TestCase;

class SabreGdsShoppingOfferLocalClockSourceParityCorrectionPhaseTest extends TestCase
{
    public function test_shared_helper_prefers_endpoint_time_over_datetime(): void
    {
        $clock = app(SabreGdsBfmScheduleEndpointLocalClock::class);
        $schedule = [
            'arrival' => [
                'time' => '13:05:00',
                'dateTime' => '2026-09-01T15:05:00Z',
            ],
        ];
        $this->assertSame('13:05:00', $clock->endpointClockRaw($schedule, 'arrival'));
        $this->assertSame('bfm_endpoint_time_local', $clock->endpointClockSourceShapeCategory($schedule, 'arrival'));
        $this->assertSame('13:05', $clock->normalizedWallClockFromRaw($clock->endpointClockRaw($schedule, 'arrival')));
    }

    public function test_revalidation_and_shared_helper_produce_identical_wall_clock(): void
    {
        $clock = app(SabreGdsBfmScheduleEndpointLocalClock::class);
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $schedule = [
            'departure' => ['airport' => 'LHE', 'time' => '11:35:00'],
            'arrival' => ['airport' => 'DOH', 'time' => '13:05:00', 'dateTime' => '2026-09-01T15:05:00'],
            'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629'],
        ];
        $row = $canonical->segmentRowFromScheduleDesc($schedule);
        $this->assertSame(
            $clock->normalizedWallClockFromRaw($clock->endpointClockRaw($schedule, 'arrival')),
            $canonical->comparableWallClock((string) $row['arrival_at']),
        );
    }

    public function test_shopping_segment_from_schedule_desc_uses_local_arrival_not_elapsed_block(): void
    {
        $normalizer = app(SabreFlightSearchNormalizer::class);
        $schedule = [
            'departure' => ['airport' => 'LHE', 'time' => '11:35:00'],
            'arrival' => ['airport' => 'DOH', 'time' => '13:05:00', 'dateTime' => '2026-09-01T15:05:00'],
            'elapsedTime' => 210,
            'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '629'],
        ];
        $state = ['prev_arrival' => null];
        $method = new \ReflectionMethod($normalizer, 'segmentFromScheduleDesc');
        /** @var FlightSegmentData $segment */
        $segment = $method->invokeArgs($normalizer, [$schedule, '2026-09-01', &$state]);
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $this->assertSame('13:05', $canonical->comparableWallClock($segment->arrival_at));
        $this->assertSame('11:35', $canonical->comparableWallClock($segment->departure_at));
    }

    public function test_iso_fallback_when_local_time_absent(): void
    {
        $clock = app(SabreGdsBfmScheduleEndpointLocalClock::class);
        $schedule = [
            'arrival' => ['dateTime' => '2026-09-01T15:05:00'],
        ];
        $this->assertSame('15:05', $clock->normalizedWallClockFromRaw($clock->endpointClockRaw($schedule, 'arrival')));
    }

    public function test_timezone_offset_on_iso_does_not_shift_literal_wall_clock(): void
    {
        $clock = app(SabreGdsBfmScheduleEndpointLocalClock::class);
        $this->assertSame('15:05', $clock->normalizedWallClockFromRaw('2026-09-01T15:05:00+03:00'));
    }

    public function test_live_shaped_qr_629_selected_and_candidate_share_arrival_wall_clock(): void
    {
        $phase4 = new SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest('x');
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $normalizer = app(SabreFlightSearchNormalizer::class);
        $draft = (new \ReflectionMethod($phase4, 'qrLiveDraft'))->invoke($phase4);
        $draft['segments'][0]['arrival_at'] = '2026-09-01T13:05:00';
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $response = (new \ReflectionMethod($phase4, 'qrLiveResponseWithFareComponentDescRefs'))->invoke($phase4);
        $response['groupedItineraryResponse']['scheduleDescs'][0]['arrival']['time'] = '13:05:00';
        $response['groupedItineraryResponse']['scheduleDescs'][0]['arrival']['dateTime'] = '2026-09-01T15:05:00';
        $response['groupedItineraryResponse']['scheduleDescs'][0]['elapsedTime'] = 210;

        $shopSchedule = $response['groupedItineraryResponse']['scheduleDescs'][0];
        $state = ['prev_arrival' => null];
        $shopSegment = (new \ReflectionMethod($normalizer, 'segmentFromScheduleDesc'))
            ->invokeArgs($normalizer, [$shopSchedule, '2026-09-01', &$state]);
        $this->assertSame('13:05', $canonical->comparableWallClock($shopSegment->arrival_at));

        $draft['segments'][0]['arrival_at'] = $shopSegment->arrival_at;
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $analysis = $linker->analyze($response, $selected);
        $this->assertSame(1, $analysis['exact_segment_signature_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);
    }

    public function test_genuinely_different_local_times_remain_mismatched(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $draft = [
            'segments' => [[
                'origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '2026-09-01T11:35:00',
                'arrival_at' => '2026-09-01T15:05:00', 'marketing_carrier' => 'QR', 'flight_number' => '629', 'booking_class' => 'S',
            ]],
        ];
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $candidate = [[
            'origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '11:35:00',
            'arrival_at' => '13:05:00', 'marketing_carrier' => 'QR', 'flight_number' => '629', 'booking_class' => 'S',
        ]];
        $comparison = $canonical->safeLinkageDigestComparison($selected['segments'], $candidate);
        $this->assertContains('arrival_wall_clock', $comparison['tuple_mismatch_field_names'] ?? []);
    }
}
