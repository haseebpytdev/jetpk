<?php

namespace Tests\Unit\Support\Sabre\Scenario;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioExactOfferEvidence;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreGdsLiveScenarioExactOfferEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_identifier_present_produces_non_empty_source_identifier_hash(): void
    {
        $conn = $this->seedSabreConnection();
        $evidence = app(SabreGdsLiveScenarioExactOfferEvidence::class);

        $context = $evidence->buildLinkageContext(
            $conn,
            $this->twoSegmentBfmSnap(),
            $this->twoSegmentRow(),
            null,
            '2026-08-15T10:00:00+00:00',
        );

        $this->assertTrue($context['offer_identifier_present']);
        $this->assertTrue($context['source_identifier_hash_present']);
        $this->assertSame(64, $context['source_identifier_hash_length']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $context['source_identifier_hash']);
    }

    public function test_two_segment_bfm_offer_produces_deterministic_segment_signature(): void
    {
        $conn = $this->seedSabreConnection();
        $evidence = app(SabreGdsLiveScenarioExactOfferEvidence::class);
        $snap = $this->twoSegmentBfmSnap();
        $row = $this->twoSegmentRow();

        $first = $evidence->buildLinkageContext($conn, $snap, $row);
        $second = $evidence->buildLinkageContext($conn, $snap, $row);

        $this->assertTrue($first['segment_signature_present']);
        $this->assertSame(64, $first['segment_signature_length']);
        $this->assertSame($first['segment_signature'], $second['segment_signature']);
    }

    public function test_reordered_segment_data_changes_segment_signature(): void
    {
        $conn = $this->seedSabreConnection();
        $evidence = app(SabreGdsLiveScenarioExactOfferEvidence::class);
        $base = $this->twoSegmentBfmSnap();
        $reordered = $base;
        $segments = $base['segments'];
        $reordered['segments'] = [$segments[1], $segments[0]];

        $baseSignature = $evidence->buildLinkageContext($conn, $base, $this->twoSegmentRow())['segment_signature'];
        $reorderedSignature = $evidence->buildLinkageContext($conn, $reordered, $this->twoSegmentRow())['segment_signature'];

        $this->assertNotSame($baseSignature, $reorderedSignature);
    }

    public function test_fare_basis_absent_does_not_block_when_minimum_linkage_complete(): void
    {
        $conn = $this->seedSabreConnection();
        $context = app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext(
            $conn,
            $this->twoSegmentBfmSnap(),
            $this->twoSegmentRow(),
        );

        $this->assertNull($context['fare_basis_codes_by_segment'] ?? null);
        $this->assertTrue($context['revalidation_linkage_ready']);
        $this->assertSame([], $context['revalidation_linkage_missing_components']);
    }

    public function test_fare_basis_included_when_available(): void
    {
        $conn = $this->seedSabreConnection();
        $row = $this->twoSegmentRow();
        $row['fare_basis_codes_by_segment'] = ['SLOW1', 'SLOW2'];
        $context = app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext(
            $conn,
            $this->twoSegmentBfmSnap(),
            $row,
        );

        $this->assertSame(['SLOW1', 'SLOW2'], $context['fare_basis_codes_by_segment']);
        $this->assertTrue($context['revalidation_linkage_ready']);
    }

    public function test_missing_source_identifier_blocks_linkage_readiness(): void
    {
        $conn = $this->seedSabreConnection();
        $snap = $this->twoSegmentBfmSnap();
        unset($snap['raw_payload']['sabre_shop_context']['itinerary_ref']);
        unset($snap['raw_payload']['sabre_booking_context']['itinerary_reference']);
        unset($snap['raw_payload']['sabre_booking_context']['pricing_information_index']);
        unset($snap['sabre_booking_context']['itinerary_reference']);
        unset($snap['sabre_booking_context']['pricing_information_index']);
        unset($snap['raw_payload']['sabre_shop_context']['pricing_information_index']);

        $context = app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext(
            $conn,
            $snap,
            $this->twoSegmentRow(),
        );

        $this->assertFalse($context['revalidation_linkage_ready']);
        $this->assertContains(
            SabreGdsLiveScenarioExactOfferEvidence::MISSING_SOURCE_IDENTIFIER_HASH,
            $context['revalidation_linkage_missing_components'],
        );
    }

    public function test_missing_segments_blocks_segment_signature(): void
    {
        $conn = $this->seedSabreConnection();
        $snap = $this->twoSegmentBfmSnap();
        $snap['segments'] = [];

        $context = app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext(
            $conn,
            $snap,
            $this->twoSegmentRow(),
        );

        $this->assertFalse($context['segment_signature_present']);
        $this->assertContains(
            SabreGdsLiveScenarioExactOfferEvidence::MISSING_SEGMENT_SIGNATURE,
            $context['revalidation_linkage_missing_components'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function twoSegmentBfmSnap(): array
    {
        return [
            'validating_carrier' => 'QR',
            'distribution_channel' => 'gds',
            'fare_breakdown' => [
                'supplier_total' => 520.83,
                'currency' => 'USD',
            ],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'carrier' => 'QR',
                    'operating_carrier' => 'QR',
                    'flight_number' => '615',
                    'departure_at' => '2026-09-01T02:15:00',
                    'arrival_at' => '2026-09-01T04:30:00',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'carrier' => 'QR',
                    'operating_carrier' => 'QR',
                    'flight_number' => '1184',
                    'departure_at' => '2026-09-01T06:00:00',
                    'arrival_at' => '2026-09-01T08:15:00',
                ],
            ],
            'sabre_booking_context' => [
                'itinerary_reference' => '2',
                'pricing_information_index' => 0,
                'booking_classes_by_segment' => ['S', 'S'],
                'segment_slice_count' => 2,
            ],
            'raw_payload' => [
                'distribution_channel' => 'GDS',
                'sabre_shop_context' => [
                    'distribution_channel' => 'GDS',
                    'shop_endpoint_path' => '/v4/offers/shop',
                    'itinerary_ref' => '2',
                    'pricing_information_index' => 0,
                    'validating_carrier' => 'QR',
                ],
                'sabre_booking_context' => [
                    'itinerary_reference' => '2',
                    'pricing_information_index' => 0,
                    'booking_classes_by_segment' => ['S', 'S'],
                    'segment_slice_count' => 2,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function twoSegmentRow(): array
    {
        return [
            'validating_carrier' => 'QR',
            'segment_count' => 2,
            'total_fare' => 520.83,
            'currency' => 'USD',
            'booking_classes_by_segment' => ['S', 'S'],
            'route' => 'LHE-DOH-JED',
        ];
    }

    protected function seedSabreConnection(): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://api.cert.platform.sabre.com';
        $conn->is_active = true;
        $conn->status = SupplierConnectionStatus::Active;
        $conn->save();

        return $conn;
    }
}
