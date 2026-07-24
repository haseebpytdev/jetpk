<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSegmentSignature;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use Tests\TestCase;

class SabreGdsLiveBfmOperatingCarrierCanonicalSignatureActivePathCorrectionPhaseTest extends TestCase
{
    public function test_nested_carrier_operating_same_as_marketing_hashes_like_absent_selected(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $selected = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '2026-09-01T11:35:00',
            'arrival_at' => '2026-09-01T15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
            'booking_class' => 'S',
        ];
        $response = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
            'booking_class' => 'S',
            'carrier' => ['marketing' => 'QR', 'operating' => 'QR'],
        ];
        $this->assertSame(
            $canonical->hashFromSegments($canonical->canonicalScheduleIdentityRows([$selected])),
            $canonical->hashFromSegments($canonical->canonicalScheduleIdentityRows([$response])),
        );
    }

    public function test_mismatch_diagnostics_use_same_identity_rows_as_hash(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $expectedIdentity = $canonical->canonicalScheduleIdentityRow([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
            'booking_class' => 'S',
        ]);
        $actualIdentity = $canonical->canonicalScheduleIdentityRow([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'operating_carrier' => 'BA',
            'flight_number' => '629',
            'booking_class' => 'S',
        ]);
        $this->assertSame('', $expectedIdentity['canonical_operating_carrier_slot']);
        $this->assertSame('BA', $actualIdentity['canonical_operating_carrier_slot']);
        $this->assertNotSame(
            $canonical->hashFromSegments($canonical->canonicalScheduleIdentityRows([[
                'origin' => 'LHE',
                'destination' => 'DOH',
                'departure_at' => '11:35:00',
                'arrival_at' => '15:05:00',
                'marketing_carrier' => 'QR',
                'flight_number' => '629',
                'booking_class' => 'S',
            ]])),
            $canonical->hashFromSegments($canonical->canonicalScheduleIdentityRows([[
                'origin' => 'LHE',
                'destination' => 'DOH',
                'departure_at' => '11:35:00',
                'arrival_at' => '15:05:00',
                'marketing_carrier' => 'QR',
                'operating_carrier' => 'BA',
                'flight_number' => '629',
                'booking_class' => 'S',
            ]])),
        );
    }

    public function test_response_candidate_signature_rebuilt_after_fare_overlay(): void
    {
        $phase4 = new SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest('x');
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = (new \ReflectionMethod($phase4, 'qrLiveDraft'))->invoke($phase4);
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $response = (new \ReflectionMethod($phase4, 'qrLiveResponseWithFareComponentDescRefs'))->invoke($phase4);
        $analysis = $linker->analyze($response, $selected);
        $this->assertSame(1, $analysis['exact_segment_signature_match_count']);
        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);
        $this->assertSame(1, $analysis['fare_basis_compatible_match_count']);
    }

    public function test_schedule_desc_explicit_operating_records_same_as_marketing_category_with_empty_slot(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $row = $canonical->segmentRowFromScheduleDesc([
            'departure' => ['airport' => 'LHE', 'time' => '11:35:00'],
            'arrival' => ['airport' => 'DOH', 'time' => '15:05:00'],
            'carrier' => ['marketing' => 'QR', 'operating' => 'QR', 'marketingFlightNumber' => '629'],
        ]);
        $this->assertSame('same_as_marketing', $row['operating_carrier_shape_category'] ?? null);
        $this->assertSame('', $row['canonical_operating_carrier_slot'] ?? 'missing');
        $this->assertArrayNotHasKey('operating_carrier', $row);
    }

    public function test_whitespace_operating_same_as_marketing_is_equivalent(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $absent = ['marketing_carrier' => 'QR', 'origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '11:35:00', 'arrival_at' => '15:05:00', 'flight_number' => '629', 'booking_class' => 'S'];
        $explicit = array_merge($absent, ['operating_carrier' => ' QR ']);
        $this->assertSame(
            $canonical->hashFromSegments($canonical->canonicalScheduleIdentityRows([$absent])),
            $canonical->hashFromSegments($canonical->canonicalScheduleIdentityRows([$explicit])),
        );
    }
}
