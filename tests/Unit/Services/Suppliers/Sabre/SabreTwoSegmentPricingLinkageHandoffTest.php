<?php

namespace Tests\Unit\Services\Suppliers\Sabre;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreTwoSegmentPricingLinkageHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_segment_normalized_offer_preserves_pricing_and_offer_refs(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_two_segment_connecting_refs.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection);
        $this->assertCount(1, $offers);

        $raw = is_array($offers[0]->raw_payload) ? $offers[0]->raw_payload : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        $handoff = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];

        $this->assertSame('pi-sv-2seg-ref', $ctx['pricing_information_ref'] ?? null);
        $this->assertSame('offer-sv-2seg-ref', $ctx['offer_ref'] ?? null);
        $this->assertSame('pi-sv-2seg-ref', $raw['pricing_information_ref'] ?? null);
        $this->assertSame('offer-sv-2seg-ref', $raw['offer_reference'] ?? null);
        $this->assertSame('itin-sv-connect-2', $raw['itinerary_reference'] ?? null);
        $this->assertSame('pi-sv-2seg-ref', $ids['pricing_0_ref'] ?? null);
        $this->assertSame('offer-sv-2seg-ref', $ids['pricing_0_offer_ref'] ?? null);
        $this->assertSame('pi-sv-2seg-ref', $handoff['pricing_information_ref'] ?? null);
        $this->assertSame('offer-sv-2seg-ref', $handoff['offer_reference'] ?? null);
        $this->assertCount(2, $offers[0]->segments);
    }

    public function test_booking_cache_handoff_preserves_shop_identifiers(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_two_segment_connecting_refs.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $offer = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection)[0]->toArray();
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-10',
            'trip_type' => 'one_way',
        ];

        $searchId = app(FlightSearchResultStore::class)->store($criteria, [$offer], []);
        $cached = app(FlightSearchResultStore::class)->findOffer($searchId, (string) $offer['offer_id']);
        $this->assertIsArray($cached);

        $snapshot = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($cached, $criteria);
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];

        $this->assertSame('pi-sv-2seg-ref', $raw['pricing_information_ref'] ?? null);
        $this->assertSame('offer-sv-2seg-ref', $raw['offer_reference'] ?? null);
        $this->assertNotEmpty($raw['sabre_shop_identifiers'] ?? []);
    }

    public function test_prepare_pricing_context_succeeds_when_identifiers_exist(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->connectingBookingWithSnapshot([
            'sabre_shop_context' => [
                'pricing_information_index' => 0,
                'validating_carrier' => 'SV',
                'fare_basis_codes' => ['QCLASS01', 'QCLASS02'],
            ],
            'sabre_shop_identifiers' => [
                'pricing_0_ref' => 'pi-sv-2seg-ref',
                'pricing_0_offer_ref' => 'offer-sv-2seg-ref',
                'itinerary_id' => 'itin-sv-connect-2',
            ],
        ]);

        $result = app(SabrePnrCertificationSupport::class)->prepareSabrePricingContext($booking);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['pricing_context_ready']);
    }

    public function test_missing_identifiers_still_block_pricing_context(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->connectingBookingWithSnapshot([
            'sabre_shop_context' => [
                'distribution_channel' => 'GDS',
                'validating_carrier' => 'SV',
                'fare_basis_codes' => ['QCLASS01', 'QCLASS02'],
            ],
        ]);

        $result = app(SabrePnrCertificationSupport::class)->prepareSabrePricingContext($booking);
        $this->assertFalse($result['success']);
        $this->assertFalse($result['pricing_context_ready']);
        $this->assertContains('itinerary_reference', $result['missing_fields']);
        $this->assertContains('pricing_information_index', $result['missing_fields']);
    }

    public function test_index_only_bfm_connecting_normalization_preserves_indexes_not_formal_refs(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_two_segment_index_only.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection);
        $this->assertCount(1, $offers);

        $raw = is_array($offers[0]->raw_payload) ? $offers[0]->raw_payload : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];

        $this->assertSame('10', (string) ($ctx['itinerary_ref'] ?? ''));
        $this->assertSame(0, $ctx['pricing_information_index'] ?? null);
        $this->assertArrayHasKey('itinerary_index', $ctx);
        $this->assertNotEmpty($ids);
        $this->assertArrayNotHasKey('pricing_0_ref', $ids);
        $this->assertArrayNotHasKey('pricing_0_offer_ref', $ids);
        $this->assertSame('', trim((string) ($ctx['pricing_information_ref'] ?? '')));
        $this->assertSame('', trim((string) ($ctx['offer_ref'] ?? '')));
    }

    public function test_bfm_v4_index_policy_probe_sufficient_without_formal_refs(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_two_segment_index_only.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $offer = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection)[0]->toArray();
        $digest = app(SabreStoredPricingContextDigest::class);

        $strict = $digest->assessReadiness($offer);
        $this->assertTrue($strict['auto_pnr_pricing_context_ready']);
        $this->assertSame('bfm_gds_priced_itinerary', $strict['pricing_context_policy']);
        $this->assertNotContains('pricing_information_ref', $strict['missing_pricing_context_fields']);
        $this->assertNotContains('offer_reference', $strict['missing_pricing_context_fields']);

        $policy = $digest->assessBfmV4LinkagePolicy($offer);
        $this->assertTrue($policy['priced_itinerary_sequence_present']);
        $this->assertTrue($policy['air_pricing_info_index_present']);
        $this->assertTrue($policy['offer_reference_unavailable_in_bfm_v4']);
        $this->assertSame('bfm_gds_priced_itinerary', $policy['pricing_context_policy_used']);
        $this->assertTrue($policy['bfm_index_linkage_sufficient']);
        $this->assertFalse($policy['re_shop_required']);
    }

    public function test_one_segment_offer_readiness_unchanged(): void
    {
        $snapshot = [
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'SV',
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
     * @param  array<string, mixed>  $rawPayloadExtras
     */
    protected function connectingBookingWithSnapshot(array $rawPayloadExtras): Booking
    {
        $agency = Agency::factory()->create();
        $segments = [
            [
                'origin' => 'LHE',
                'destination' => 'JED',
                'departure_at' => '2026-09-10T08:00:00',
                'arrival_at' => '2026-09-10T12:00:00',
                'airline_code' => 'SV',
                'flight_number' => '739',
                'booking_class' => 'Q',
                'fare_basis_code' => 'QCLASS01',
            ],
            [
                'origin' => 'JED',
                'destination' => 'DXB',
                'departure_at' => '2026-09-10T14:00:00',
                'arrival_at' => '2026-09-10T18:00:00',
                'airline_code' => 'SV',
                'flight_number' => '568',
                'booking_class' => 'Q',
                'fare_basis_code' => 'QCLASS02',
            ],
        ];

        $snapshot = [
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'SV',
            'segments' => $segments,
            'raw_payload' => array_merge(['segments' => $segments], $rawPayloadExtras),
        ];

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => $snapshot,
                'validated_offer_snapshot' => $snapshot,
            ],
        ]);
    }
}
