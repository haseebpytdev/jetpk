<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\BookingSupplierConfirmationNoticeResolver;
use App\Support\Bookings\SabreGdsAutoPnrLifecycleService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * SABRE-GDS-S1B: one-way branded fare PNR flow regression — stored metadata only, no live supplier calls.
 */
class SabreGdsAutoPnrBrandedFareFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_way_branded_fare_snapshot_merges_selected_brand_into_cpnr_wire(): void
    {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);

        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->createOneWayBrandedPkBooking($agency);

        $svc = app(SabreBookingService::class);
        $snapshot = $this->mergeSnapshotForBooking($svc, $booking);
        $this->assertSame('SM', data_get($snapshot, 'sabre_booking_context.brand_code'));
        $this->assertSame('SMART', data_get($snapshot, 'sabre_booking_context.selected_fare_family_option.brand_name'));

        $draft = [
            '_valid' => true,
            'supplier_connection_id' => (int) ($snapshot['supplier_connection_id'] ?? 1),
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            '_sabre_booking_context' => $snapshot['sabre_booking_context'] ?? [],
            'segments' => $snapshot['segments'] ?? [],
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => 'Test',
                'last_name' => 'Traveler',
                'gender' => 'MALE',
                'date_of_birth' => '1990-01-15',
            ]],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
        ];

        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, []),
        );

        $brand = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand.0.content'
        );
        $this->assertSame('SM', $brand);
    }

    public function test_freshness_waiver_flags_reconciled_after_successful_offer_refresh_meta(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->createOneWayBrandedPkBooking($agency, [
            'offer_refresh_status' => 'refreshed',
        ]);

        $svc = app(SabreBookingService::class);
        $snapshot = $this->mergeSnapshotForBooking($svc, $booking);
        $draft = [
            'supplier_connection_id' => (int) ($snapshot['supplier_connection_id'] ?? 1),
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            '_sabre_booking_context' => $snapshot['sabre_booking_context'] ?? [],
            'segments' => $snapshot['segments'] ?? [],
            'passengers' => [['type' => 'ADT', 'first_name' => 'Test', 'last_name' => 'Traveler']],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
        ];

        $style = [
            'selected_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'iati_like_selected' => true,
            'eligible' => true,
            'segment_context_complete' => true,
            'rbd_complete' => true,
            'supplier_connection_present' => true,
            'certified_route_result' => ['route_status' => 'certified'],
            'gds_compatible' => true,
            'pcc_present' => true,
            'target_city_present' => true,
            'cpnr_required_blocks_missing' => [],
        ];

        $decision = $svc->decideSabreBookingFreshnessStrategy(
            array_merge($snapshot, ['raw_payload' => ['sabre_booking_context' => $snapshot['sabre_booking_context'] ?? []]]),
            $draft,
            null,
            $style,
            $booking,
        );

        $reconciled = app(SabreGdsAutoPnrLifecycleService::class)->reconcileObsoleteIatiWaiverFlags($decision, $booking);

        $this->assertArrayNotHasKey('iati_like_expects_revalidation_waiver_or_refresh', $reconciled);
        $this->assertTrue($reconciled['refresh_satisfied_revalidation_waiver'] ?? false);
        $this->assertFalse($reconciled['blocks_booking'] ?? true);
    }

    public function test_lifecycle_records_offer_refreshed_from_stored_meta(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->createOneWayBrandedPkBooking($agency, [
            'offer_refresh_status' => 'refreshed',
        ]);

        app(SabreGdsAutoPnrLifecycleService::class)->recordOfferRefreshed($booking);
        $booking->refresh();

        $this->assertTrue(data_get($booking->meta, SabreGdsAutoPnrLifecycleService::META_KEY.'.offer_refreshed'));
    }

    /**
     * @param  array<string, mixed>  $metaExtra
     */
    protected function createOneWayBrandedPkBooking(Agency $agency, array $metaExtra = []): Booking
    {
        $snapshot = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 1,
            'validating_carrier' => 'PK',
            'distribution_channel' => 'gds',
            'raw_payload' => ['distribution_model' => 'gds'],
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'KHI',
                'carrier' => 'PK',
                'flight_number' => '303',
                'departure_at' => '2026-08-15T08:00:00',
                'arrival_at' => '2026-08-15T09:45:00',
                'booking_class' => 'V',
                'fare_basis_code' => 'VOWSM',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 26590,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'sabre_booking_context' => [
                'brand_code' => 'LT',
                'selected_brand_code' => 'LT',
            ],
        ];

        $meta = array_merge([
            'supplier_provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => 'gds',
            'supplier_connection_id' => 1,
            'fare_option_key' => 'smart-key',
            'search_criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'KHI',
                'depart_date' => '2026-08-15',
            ],
            'selected_fare_family_option' => [
                'option_key' => 'smart-key',
                'name' => 'SMART',
                'brand_code' => 'SM',
                'brand_name' => 'SMART',
                'booking_class' => 'V',
                'fare_basis' => 'VOWSM',
                'price_display' => 'PKR 26,590',
            ],
            'normalized_offer_snapshot' => $snapshot,
        ], $metaExtra);

        $meta = BookingSupplierConfirmationNoticeResolver::reconcileSabreBrandedFareMeta($meta);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => $meta,
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_type' => 'adult',
            'first_name' => 'Test',
            'last_name' => 'Traveler',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'booker@example.com',
            'phone' => '3001234567',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => 26590,
            'currency' => 'PKR',
        ]);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mergeSnapshotForBooking(SabreBookingService $svc, Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $base = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $method = new \ReflectionMethod($svc, 'mergePublicReviewSabreSnapshotFromBooking');
        $method->setAccessible(true);

        return $method->invoke($svc, $booking, $base);
    }
}
