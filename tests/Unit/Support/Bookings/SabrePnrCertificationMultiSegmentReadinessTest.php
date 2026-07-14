<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabrePnrCertificationMultiSegmentReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);
    }

    public function test_same_rbd_on_two_segments_counts_as_rbd_complete(): void
    {
        $booking = $this->sabreConnectingBooking([
            'segments' => [
                $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q'),
                $this->segmentRow('JED', 'DXB', 'SV', '568', 'Q'),
            ],
            'validating_carrier' => 'SV',
            'raw_payload' => [
                'sabre_shop_context' => [
                    'pricing_information_ref' => 'pi-1',
                    'offer_ref' => 'offer-1',
                    'itinerary_ref' => 'itin-1',
                    'validating_carrier' => 'SV',
                    'fare_basis_codes' => ['ABC123'],
                ],
            ],
        ]);

        $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking);

        $this->assertTrue($diag['rbd_complete']);
        $this->assertSame(2, $diag['rbd_present_count']);
        $this->assertStringContainsString('segment_snapshot', (string) ($diag['rbd_source'] ?? ''));
    }

    public function test_missing_fare_basis_keeps_iati_like_connecting_not_ready(): void
    {
        $booking = $this->sabreConnectingBooking([
            'segments' => [
                $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q'),
                $this->segmentRow('JED', 'DXB', 'SV', '568', 'Q'),
            ],
            'validating_carrier' => 'SV',
        ]);

        $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking);

        $this->assertFalse($diag['fare_basis_complete']);
        $this->assertFalse($diag['iati_like_connecting_ready']);
        $this->assertContains('fare_basis_incomplete', $diag['blocker_reasons']);
    }

    public function test_meta_sabre_booking_context_enables_bfm_gds_pricing_readiness(): void
    {
        $booking = $this->sabreConnectingBooking([
            'segments' => [
                $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q', 'QCLASS01'),
                $this->segmentRow('JED', 'DXB', 'SV', '568', 'Q', 'QCLASS02'),
            ],
            'validating_carrier' => 'SV',
            'raw_payload' => [
                'sabre_shop_context' => [
                    'itinerary_ref' => '2',
                    'pricing_information_index' => 0,
                    'validating_carrier' => 'SV',
                    'leg_refs' => [1],
                    'schedule_refs' => [1, 2],
                ],
            ],
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['sabre_booking_context'] = [
            'distribution_channel' => 'GDS',
            'itinerary_reference' => '2',
            'pricing_information_index' => 0,
            'booking_classes_by_segment' => ['Q', 'Q'],
            'fare_basis_codes_by_segment' => ['QCLASS01', 'QCLASS02'],
            'segment_slice_count' => 2,
        ];
        $booking->update(['meta' => $meta]);

        $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking->fresh());

        $this->assertTrue($diag['pricing_context_ready']);
        $this->assertSame('bfm_gds_priced_itinerary', $diag['pricing_context_policy']);
        $this->assertSame('no', $diag['formal_offer_reference_required']);
    }

    public function test_bfm_gds_index_only_pricing_context_enables_admin_readiness(): void
    {
        $booking = $this->sabreConnectingBooking([
            'segments' => [
                $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q', 'QCLASS01'),
                $this->segmentRow('JED', 'DXB', 'SV', '568', 'Q', 'QCLASS02'),
            ],
            'validating_carrier' => 'SV',
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
                    'itinerary_reference' => '2',
                    'pricing_information_index' => 0,
                    'booking_classes_by_segment' => ['Q', 'Q'],
                    'fare_basis_codes_by_segment' => ['QCLASS01', 'QCLASS02'],
                    'segment_slice_count' => 2,
                ],
            ],
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'test@example.com',
            'phone' => '+923001234567',
        ]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['offer_validation_status'] = 'valid';
        $booking->update(['meta' => $meta]);
        $booking = $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']);

        $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking);

        $this->assertTrue($diag['pricing_context_ready']);
        $this->assertSame('bfm_gds_priced_itinerary', $diag['pricing_context_policy']);
        $this->assertSame('no', $diag['formal_offer_reference_required']);
        $this->assertTrue($diag['admin_staff_pnr_readiness_passed']);
        $this->assertFalse($diag['context_refresh_available']);
    }

    public function test_missing_pricing_context_blocks_admin_staff_pnr_retry(): void
    {
        $booking = $this->sabreConnectingBooking([
            'segments' => [
                $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q', 'QCLASS01'),
                $this->segmentRow('JED', 'DXB', 'SV', '568', 'Q', 'QCLASS02'),
            ],
            'validating_carrier' => 'SV',
        ]);

        $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking);

        $this->assertTrue($diag['rbd_complete']);
        $this->assertTrue($diag['fare_basis_complete']);
        $this->assertFalse($diag['pricing_context_ready']);
        $this->assertTrue($diag['admin_staff_pnr_retry_route_allowed']);
        $this->assertFalse($diag['admin_staff_pnr_readiness_passed']);
        $this->assertFalse($diag['admin_staff_pnr_retry_allowed']);
        $this->assertFalse($diag['admin_pnr_live_action_allowed']);
        $this->assertTrue($diag['context_refresh_available']);
        $this->assertContains('pricing_context_incomplete', $diag['blocker_reasons']);

        $actions = app(AdminBookingSupplierActions::class)->build($booking, true, false);
        $this->assertFalse($actions['can_create_pnr']);
        $this->assertFalse($actions['can_retry_pnr']);
        $this->assertFalse($actions['admin_pnr_live_action_allowed']);
        $this->assertTrue($actions['can_prepare_supplier_context']);
    }

    public function test_complete_pricing_context_enables_controlled_create_pnr(): void
    {
        $booking = $this->sabreConnectingBooking([
            'segments' => [
                $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q', 'QCLASS01'),
                $this->segmentRow('JED', 'DXB', 'SV', '568', 'Q', 'QCLASS02'),
            ],
            'validating_carrier' => 'SV',
            'raw_payload' => [
                'sabre_shop_context' => [
                    'pricing_information_ref' => 'pi-1',
                    'offer_ref' => 'offer-1',
                    'itinerary_ref' => 'itin-1',
                    'validating_carrier' => 'SV',
                    'fare_basis_codes' => ['QCLASS01', 'QCLASS02'],
                ],
            ],
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'test@example.com',
            'phone' => '+923001234567',
        ]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['offer_validation_status'] = 'valid';
        $booking->update(['meta' => $meta]);

        $booking = $booking->fresh(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts']);

        $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking);
        $this->assertTrue($diag['pricing_context_ready']);
        $this->assertTrue($diag['admin_staff_pnr_readiness_passed']);
        $this->assertFalse($diag['context_refresh_available']);

        $actions = app(AdminBookingSupplierActions::class)->build($booking, true, false);
        $this->assertTrue($actions['can_create_pnr'], (string) ($actions['create_pnr_reason'] ?? ''));
        $this->assertFalse($actions['admin_pnr_live_action_allowed']);
        $this->assertFalse($actions['can_prepare_supplier_context']);
    }

    public function test_prepare_rebuilds_pricing_context_from_stored_identifiers(): void
    {
        $booking = $this->sabreConnectingBooking([
            'segments' => [
                $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q', 'QCLASS01'),
                $this->segmentRow('JED', 'DXB', 'SV', '568', 'Q', 'QCLASS02'),
            ],
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
        ]);

        $result = app(SabrePnrCertificationSupport::class)->prepareSabrePricingContext($booking);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['pricing_context_ready']);
        $booking->refresh();
        $this->assertSame('complete', $booking->meta['sabre_pricing_context_refresh']['status'] ?? null);
    }

    public function test_mixed_carrier_two_segment_blocks_controlled_route(): void
    {
        $booking = $this->sabreConnectingBooking([
            'segments' => [
                $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q', 'QCLASS01'),
                $this->segmentRow('JED', 'DXB', 'PK', '568', 'Q', 'QCLASS02'),
            ],
            'validating_carrier' => 'SV',
        ]);

        $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking);

        $this->assertTrue($diag['mixed_carrier']);
        $this->assertTrue($diag['mixed_carrier_candidate']);
        $this->assertSame('SV→PK', $diag['marketing_carriers_by_segment']);
        $this->assertFalse($diag['mixed_carrier_public_checkout_enabled']);
        $this->assertFalse($diag['mixed_carrier_admin_enabled']);
        $this->assertSame('inspection_only', $diag['mixed_carrier_next_step']);
        $this->assertSame('one_way_connecting_mixed_carrier_gds', $diag['proposed_mixed_carrier_category']);
        $this->assertContains('certified_route_mixed_interline', $diag['mixed_carrier_readiness_blockers']);
        $this->assertFalse($diag['connecting_same_carrier_candidate']);
        $this->assertFalse($diag['admin_staff_pnr_retry_route_allowed']);
        $this->assertFalse($diag['admin_pnr_live_action_allowed']);
    }

    public function test_one_way_direct_readiness_unchanged_with_single_segment(): void
    {
        $booking = $this->sabreConnectingBooking([
            'search_criteria' => ['trip_type' => 'one_way'],
            'segments' => [
                $this->segmentRow('LHE', 'DXB', 'SV', '739', 'Q', 'QCLASS01'),
            ],
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
        ]);

        $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking);

        $this->assertFalse($diag['multi_segment_candidate']);
        $this->assertFalse($diag['connecting_same_carrier_candidate']);

        $readiness = app(SabrePnrCertificationSupport::class)->buildReadiness($booking);
        $this->assertSame(1, $readiness['segment_count']);
        $this->assertSame(['Q'], $readiness['rbd_list']);
    }

    /**
     * @param  array<string, mixed>  $snapshotExtras
     */
    protected function sabreConnectingBooking(array $snapshotExtras): Booking
    {
        $agency = Agency::factory()->create();
        $segments = $snapshotExtras['segments'] ?? [];
        unset($snapshotExtras['segments']);

        $snapshot = array_merge([
            'supplier_provider' => 'sabre',
            'segments' => $segments,
        ], $snapshotExtras);

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

    /**
     * @return array<string, mixed>
     */
    protected function segmentRow(
        string $origin,
        string $destination,
        string $carrier,
        string $flight,
        string $bookingClass,
        string $fareBasis = '',
    ): array {
        return array_filter([
            'origin' => $origin,
            'destination' => $destination,
            'carrier' => $carrier,
            'flight_number' => $flight,
            'booking_class' => $bookingClass,
            'departure_at' => '2026-06-20T10:00:00Z',
            'arrival_at' => '2026-06-20T14:00:00Z',
            'fare_basis_code' => $fareBasis !== '' ? $fareBasis : null,
        ], static fn ($v) => $v !== null);
    }
}
