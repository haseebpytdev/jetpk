<?php

namespace Tests\Unit\Services\Suppliers\Sabre;

use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use Tests\TestCase;

class SabreStoredPricingContextDigestTest extends TestCase
{
    public function test_rebuild_restores_pricing_and_offer_refs_from_identifiers(): void
    {
        $snapshot = [
            'validating_carrier' => 'SV',
            'raw_payload' => [
                'sabre_shop_context' => [
                    'pricing_information_index' => 0,
                    'validating_carrier' => 'SV',
                    'fare_basis_codes' => ['QCLASS01', 'QCLASS02'],
                ],
                'sabre_shop_identifiers' => [
                    'pricing_0_ref' => 'pi-ref-99',
                    'pricing_0_offerRef' => 'offer-99',
                    'itinerary_id' => 'itin-99',
                ],
            ],
        ];

        $digest = app(SabreStoredPricingContextDigest::class);
        $rebuild = $digest->rebuildSnapshotPricingLinkage($snapshot);
        $after = $rebuild['readiness_after'];
        $ctx = $rebuild['snapshot']['raw_payload']['sabre_shop_context'] ?? [];
        $this->assertSame('pi-ref-99', $ctx['pricing_information_ref'] ?? null);
        $this->assertSame('offer-99', $ctx['offer_ref'] ?? null);
        $this->assertTrue($after['auto_pnr_pricing_context_ready']);
    }

    public function test_bfm_gds_two_segment_index_zero_is_pricing_ready_without_formal_refs(): void
    {
        $snapshot = $this->bfmGdsConnectingSnapshot();

        $readiness = app(SabreStoredPricingContextDigest::class)->assessReadiness($snapshot);

        $this->assertTrue($readiness['auto_pnr_pricing_context_ready']);
        $this->assertSame('bfm_gds_priced_itinerary', $readiness['pricing_context_policy']);
        $this->assertTrue($readiness['bfm_pricing_information_index_present']);
        $this->assertSame(0, $readiness['bfm_pricing_information_index']);
        $this->assertFalse($readiness['formal_offer_reference_required']);
        $this->assertFalse($readiness['formal_pricing_information_ref_required']);
        $this->assertFalse($readiness['has_pricing_information_ref']);
        $this->assertFalse($readiness['has_offer_reference']);
        $this->assertNotContains('pricing_information_ref', $readiness['missing_pricing_context_fields']);
        $this->assertNotContains('offer_reference', $readiness['missing_pricing_context_fields']);
    }

    public function test_pricing_information_index_zero_is_treated_as_present(): void
    {
        $snapshot = $this->bfmGdsConnectingSnapshot();
        unset($snapshot['raw_payload']['sabre_shop_context']['pricing_information_index']);
        $snapshot['raw_payload']['sabre_booking_context']['pricing_information_index'] = 0;

        $readiness = app(SabreStoredPricingContextDigest::class)->assessReadiness($snapshot);

        $this->assertTrue($readiness['bfm_pricing_information_index_present']);
        $this->assertSame(0, $readiness['bfm_pricing_information_index']);
    }

    public function test_missing_itinerary_ref_blocks_bfm_gds_readiness(): void
    {
        $snapshot = $this->bfmGdsConnectingSnapshot();
        unset($snapshot['raw_payload']['sabre_shop_context']['itinerary_ref']);
        unset($snapshot['raw_payload']['itinerary_reference']);
        unset($snapshot['raw_payload']['sabre_booking_context']['itinerary_reference']);

        $readiness = app(SabreStoredPricingContextDigest::class)->assessReadiness($snapshot);

        $this->assertFalse($readiness['auto_pnr_pricing_context_ready']);
        $this->assertContains('itinerary_reference', $readiness['missing_pricing_context_fields']);
    }

    public function test_missing_pricing_information_index_blocks_bfm_gds_readiness(): void
    {
        $snapshot = $this->bfmGdsConnectingSnapshot();
        unset($snapshot['raw_payload']['sabre_shop_context']['pricing_information_index']);
        unset($snapshot['raw_payload']['sabre_booking_context']['pricing_information_index']);

        $readiness = app(SabreStoredPricingContextDigest::class)->assessReadiness($snapshot);

        $this->assertFalse($readiness['auto_pnr_pricing_context_ready']);
        $this->assertContains('pricing_information_index', $readiness['missing_pricing_context_fields']);
    }

    public function test_mixed_carrier_snapshot_does_not_change_bfm_gds_digest_policy(): void
    {
        $snapshot = $this->bfmGdsConnectingSnapshot();
        $snapshot['segments'][1]['carrier'] = 'PK';

        $readiness = app(SabreStoredPricingContextDigest::class)->assessReadiness($snapshot);

        $this->assertTrue($readiness['auto_pnr_pricing_context_ready']);
        $this->assertSame('bfm_gds_priced_itinerary', $readiness['pricing_context_policy']);
    }

    public function test_missing_fare_basis_blocks_bfm_gds_readiness(): void
    {
        $snapshot = $this->bfmGdsConnectingSnapshot();
        unset($snapshot['raw_payload']['sabre_shop_context']['fare_basis_codes']);
        unset($snapshot['raw_payload']['sabre_booking_context']['fare_basis_codes_by_segment']);
        foreach ($snapshot['segments'] as $i => $seg) {
            unset($snapshot['segments'][$i]['fare_basis_code']);
        }

        $readiness = app(SabreStoredPricingContextDigest::class)->assessReadiness($snapshot);

        $this->assertFalse($readiness['auto_pnr_pricing_context_ready']);
        $this->assertContains('fare_basis_codes_by_segment', $readiness['missing_pricing_context_fields']);
    }

    public function test_formal_ref_policy_unchanged_when_explicit_refs_present(): void
    {
        $snapshot = [
            'validating_carrier' => 'SV',
            'segments' => [
                ['booking_class' => 'Q', 'fare_basis_code' => 'QCLASS01'],
            ],
            'raw_payload' => [
                'distribution_channel' => 'GDS',
                'sabre_shop_context' => [
                    'pricing_information_ref' => 'pi-1',
                    'offer_ref' => 'offer-1',
                    'itinerary_ref' => 'itin-1',
                    'validating_carrier' => 'SV',
                    'fare_basis_codes' => ['QCLASS01'],
                ],
            ],
        ];

        $readiness = app(SabreStoredPricingContextDigest::class)->assessReadiness($snapshot);

        $this->assertTrue($readiness['auto_pnr_pricing_context_ready']);
        $this->assertSame('formal_ref_linkage', $readiness['pricing_context_policy']);
        $this->assertTrue($readiness['formal_offer_reference_required']);
    }

    public function test_one_segment_direct_readiness_unchanged(): void
    {
        $snapshot = [
            'validating_carrier' => 'SV',
            'segments' => [
                ['booking_class' => 'Q', 'fare_basis_code' => 'QCLASS01'],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'pricing_information_ref' => 'pi-1',
                    'offer_ref' => 'offer-1',
                    'itinerary_ref' => 'itin-1',
                    'validating_carrier' => 'SV',
                    'fare_basis_codes' => ['QCLASS01'],
                ],
            ],
        ];

        $readiness = app(SabreStoredPricingContextDigest::class)->assessReadiness($snapshot);

        $this->assertTrue($readiness['auto_pnr_pricing_context_ready']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function bfmGdsConnectingSnapshot(): array
    {
        return [
            'validating_carrier' => 'SV',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'carrier' => 'SV',
                    'booking_class' => 'Q',
                    'fare_basis_code' => 'QCLASS01',
                ],
                [
                    'origin' => 'JED',
                    'destination' => 'DXB',
                    'carrier' => 'SV',
                    'booking_class' => 'Q',
                    'fare_basis_code' => 'QCLASS02',
                ],
            ],
            'raw_payload' => [
                'distribution_channel' => 'GDS',
                'itinerary_reference' => '2',
                'sabre_shop_context' => [
                    'distribution_channel' => 'GDS',
                    'shop_endpoint_path' => '/v4/offers/shop',
                    'itinerary_ref' => '2',
                    'pricing_information_index' => 0,
                    'validating_carrier' => 'SV',
                    'leg_refs' => [1],
                    'schedule_refs' => [1, 2],
                    'fare_basis_codes' => ['QCLASS01', 'QCLASS02'],
                ],
                'sabre_booking_context' => [
                    'distribution_channel' => 'GDS',
                    'itinerary_reference' => '2',
                    'pricing_information_index' => 0,
                    'booking_classes_by_segment' => ['Q', 'Q'],
                    'fare_basis_codes_by_segment' => ['QCLASS01', 'QCLASS02'],
                    'segment_slice_count' => 2,
                ],
            ],
        ];
    }
}
