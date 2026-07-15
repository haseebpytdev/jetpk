<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use App\Support\Bookings\BookingSupplierConfirmationNoticeResolver;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreGdsAutoPnrLifecycleService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * SABRE-GDS-S3A: CPNR readiness must not depend on Sabre ticketing env flags.
 */
class SabreGdsCpnrTicketingGateSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_refreshed_revalidated_booking_allows_cpnr_when_ticketing_flags_off(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.ticketing_live_call_enabled', false);

        $booking = $this->createRefreshedSabreGdsBooking();
        $decision = $this->freshnessDecisionForBooking($booking);

        $this->assertFalse($decision['blocks_booking'] ?? true);
        $this->assertTrue($decision['context_ready_for_booking_payload'] ?? false);
        $this->assertFalse($decision['freshness_blocks_booking'] ?? true);
        $this->assertNotContains('ticketing_auto_issue_not_allowed_for_iati_style', $decision['reasons'] ?? []);
    }

    public function test_enabling_ticketing_flags_does_not_block_cpnr_style_or_freshness(): void
    {
        Config::set([
            'suppliers.sabre.ticketing_enabled' => true,
            'suppliers.sabre.ticketing_live_call_enabled' => true,
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
        ]);

        $booking = $this->createRefreshedSabreGdsBooking();
        $style = $this->styleDecisionForBooking();
        $decision = $this->freshnessDecisionForBooking($booking, $style);

        $this->assertNotContains('ticketing_auto_issue_not_allowed_for_iati_style', $style['reasons'] ?? []);
        $this->assertTrue($style['iati_like_eligible'] ?? false);
        $this->assertTrue($style['iati_like_selected'] ?? false);
        $this->assertFalse($decision['blocks_booking'] ?? true);
        $this->assertTrue($decision['context_ready_for_booking_payload'] ?? false);
        $this->assertFalse($decision['freshness_blocks_booking'] ?? true);
        $this->assertNotSame('iati_cpnr_context_not_ready', $decision['reason_code'] ?? null);
    }

    public function test_cpnr_style_never_emits_ticketing_auto_issue_reason(): void
    {
        foreach ([false, true] as $ticketingEnabled) {
            Config::set('suppliers.sabre.ticketing_enabled', $ticketingEnabled);
            Config::set('suppliers.sabre.ticketing_live_call_enabled', $ticketingEnabled);
            Config::set('suppliers.sabre.booking_mode', $ticketingEnabled ? 'certified' : 'pnr_only');
            Config::set('suppliers.sabre.booking_schema', 'create_passenger_name_record');
            Config::set('suppliers.sabre.cpnr_iati_style_certified_gds_enabled', true);

            $style = $this->styleDecisionForBooking();
            $this->assertNotContains(
                'ticketing_auto_issue_not_allowed_for_iati_style',
                $style['reasons'] ?? [],
                'ticketing_enabled='.($ticketingEnabled ? 'true' : 'false'),
            );
        }
    }

    public function test_ticketing_readiness_still_blocks_issue_ticket_without_pnr(): void
    {
        Config::set([
            'suppliers.sabre.ticketing_enabled' => true,
            'suppliers.sabre.ticketing_live_call_enabled' => true,
            'suppliers.sabre.ticketing_printer_lniata' => 'TESTLN',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => null,
            'supplier_reference' => null,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'pnr_itinerary_sync' => ['status' => 'synced'],
                'pnr_itinerary_snapshot' => ['segments' => [['origin' => 'LHE', 'destination' => 'KHI']]],
            ],
        ]);

        $result = app(SabreGdsTicketingReadiness::class)->evaluate($booking->fresh(), ['dry_run' => true]);

        $this->assertContains('missing_pnr_or_locator', $result['blockers']);
        $this->assertNotSame(SabreGdsTicketingReadiness::ACTION_ISSUE_TICKET, $result['action_state']);
    }

    public function test_incomplete_branded_fare_context_still_blocks_cpnr_freshness(): void
    {
        Config::set([
            'suppliers.sabre.ticketing_enabled' => true,
            'suppliers.sabre.ticketing_live_call_enabled' => true,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
        ]);

        $booking = $this->createRefreshedSabreGdsBooking([
            'selected_fare_family_option' => null,
            'fare_option_key' => null,
            'sabre_booking_context' => [],
        ]);
        $style = $this->styleDecisionForBooking(incompleteBrand: true);
        $decision = $this->freshnessDecisionForBooking($booking, $style);

        $this->assertFalse($style['iati_like_eligible'] ?? true);
        $this->assertContains('branded_fare_context_incomplete', $style['reasons'] ?? []);
        $this->assertTrue($decision['blocks_booking'] ?? false);
        $this->assertFalse($decision['context_ready_for_booking_payload'] ?? true);
        $this->assertNotContains('ticketing_auto_issue_not_allowed_for_iati_style', $style['reasons'] ?? []);
    }

    public function test_revalidation_success_with_offer_refresh_reconciles_waiver_flags(): void
    {
        $booking = $this->createRefreshedSabreGdsBooking([
            'revalidation_status' => 'success',
            'selected_offer_revalidation_status' => 'success',
        ]);
        $decision = $this->freshnessDecisionForBooking($booking);
        $reconciled = app(SabreGdsAutoPnrLifecycleService::class)->reconcileObsoleteIatiWaiverFlags($decision, $booking);

        $this->assertSame('iati_cpnr_refresh_satisfied', $reconciled['revalidation_skip_reason'] ?? null);
        $this->assertFalse($reconciled['blocks_booking'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $metaExtra
     */
    protected function createRefreshedSabreGdsBooking(array $metaExtra = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $meta = array_merge([
            'supplier_provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => 'gds',
            'supplier_connection_id' => 1,
            'offer_refresh_status' => 'refreshed',
            'revalidation_status' => 'success',
            'selected_offer_revalidation_status' => 'success',
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
            ],
            'sabre_booking_context' => [
                'brand_code' => 'SM',
                'selected_brand_code' => 'SM',
                'ready_for_booking_payload' => true,
            ],
        ], $metaExtra);

        $meta = BookingSupplierConfirmationNoticeResolver::reconcileSabreBrandedFareMeta($meta);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'fare_revalidated_at' => now(),
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
            'supplier_total' => 26590,
            'currency' => 'PKR',
        ]);

        return $booking->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $styleOverride
     * @return array<string, mixed>
     */
    protected function freshnessDecisionForBooking(Booking $booking, ?array $styleOverride = null): array
    {
        $svc = app(SabreBookingService::class);
        $style = $styleOverride ?? $this->styleDecisionForBooking();
        $offer = $this->offerFromBooking($booking);
        $draft = $this->draftFromBooking($booking, $offer);

        $decision = $svc->decideSabreBookingFreshnessStrategy($offer, $draft, null, $style, $booking);
        $decision = app(SabreGdsAutoPnrLifecycleService::class)->reconcileObsoleteIatiWaiverFlags($decision, $booking);
        $decision['freshness_blocks_booking'] = $decision['blocks_booking'] ?? false;

        return $decision;
    }

    protected function styleDecisionForBooking(bool $incompleteBrand = false): array
    {
        Config::set([
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $connection = new SupplierConnection;
        $connection->id = 1;
        $connection->provider = SupplierProvider::Sabre;

        $offer = [
            'id' => 'offer-cpnr-gate',
            'supplier_connection_id' => 1,
            'validating_carrier' => 'PK',
            'raw_payload' => [
                'distribution_model' => 'gds',
                'sabre_booking_context' => $incompleteBrand
                    ? []
                    : ['brand_code' => 'SM', 'ready_for_booking_payload' => true],
            ],
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
        ];

        $draft = [
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            '_sabre_booking_context' => $offer['raw_payload']['sabre_booking_context'],
            'segments' => $offer['segments'],
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => 'Test',
                'last_name' => 'Traveler',
                'gender' => 'MALE',
                'date_of_birth' => '1990-01-15',
            ]],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
        ];

        $route = [
            'category' => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER,
            'route_status' => SabreCertifiedRouteSelector::STATUS_CERTIFIED,
            'live_booking_allowed' => true,
            'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
            'payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
        ];

        return app(SabreBookingService::class)->decidePassengerRecordsPayloadStyle(
            $offer,
            $draft,
            $connection,
            $route,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function offerFromBooking(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return [
            'id' => 'offer-'.$booking->id,
            'supplier_connection_id' => (int) ($meta['supplier_connection_id'] ?? 1),
            'validating_carrier' => 'PK',
            'raw_payload' => [
                'distribution_model' => 'gds',
                'sabre_booking_context' => $meta['sabre_booking_context'] ?? [],
            ],
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
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected function draftFromBooking(Booking $booking, array $offer): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return [
            'supplier_connection_id' => (int) ($meta['supplier_connection_id'] ?? 1),
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            '_sabre_booking_context' => $meta['sabre_booking_context'] ?? [],
            'segments' => $offer['segments'],
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => 'Test',
                'last_name' => 'Traveler',
                'gender' => 'MALE',
                'date_of_birth' => '1990-01-15',
            ]],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
        ];
    }
}
