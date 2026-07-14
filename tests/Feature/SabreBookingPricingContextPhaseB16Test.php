<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase B16 — stored Sabre pricing context digest command + revalidate payload styles.
 */
class SabreBookingPricingContextPhaseB16Test extends TestCase
{
    use RefreshDatabase;

    public function test_inspect_booking_pricing_context_prints_safe_digest_and_no_raw_payload(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBookingWithSnapshot();

        Artisan::call('sabre:inspect-booking-pricing-context', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();

        $this->assertStringContainsString('booking_id='.$booking->id, $out);
        $this->assertStringContainsString('supplier_offer_id_short_hash=', $out);
        $this->assertStringContainsString('itinerary_group_index=1', $out);
        $this->assertStringContainsString('pricing_information_index=2', $out);
        $this->assertStringContainsString('pricing_node_scalar_keys=', $out);
        $this->assertStringContainsString('fare_basis_codes=', $out);
        $this->assertStringContainsString('payload_style=', $out);
        $this->assertStringContainsString('auto_pnr_pricing_context_ready=false', $out);
        $this->assertStringContainsString('has_pricing_information_ref=true', $out);
        $this->assertStringContainsString('has_offer_reference=false', $out);
        $this->assertStringContainsString('missing_pricing_context_fields=offer_reference', $out);
        $this->assertStringNotContainsString('{', $out);
        $this->assertStringNotContainsString('passport', $out);
        $this->assertStringNotContainsString('phone', $out);
        $this->assertStringNotContainsString('email', $out);
    }

    public function test_pricing_context_readiness_reports_missing_fields_when_linkage_incomplete(): void
    {
        $digest = $this->app->make(SabreStoredPricingContextDigest::class);
        $readiness = $digest->assessReadiness([
            'validating_carrier' => 'EK',
            'fare_breakdown' => [
                'fare_basis_codes' => ['YLOW'],
                'passenger_counts' => ['adults' => 1],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'itinerary_ref' => '10',
                    'fare_component_refs' => [7],
                    'validating_carrier' => 'EK',
                    'fare_basis_codes' => ['YOWPK7', 'O', 'Y'],
                ],
                'sabre_shop_identifiers' => [],
            ],
        ]);

        $this->assertFalse($readiness['auto_pnr_pricing_context_ready']);
        $this->assertContains('pricing_information_ref', $readiness['missing_pricing_context_fields']);
        $this->assertContains('offer_reference', $readiness['missing_pricing_context_fields']);
        $this->assertTrue($readiness['has_itinerary_reference']);
        $this->assertTrue($readiness['has_selected_passenger_info']);
    }

    public function test_revalidate_payload_pricing_context_style_includes_indexes_and_fare_basis(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = [
            'provider' => SupplierProvider::Sabre->value,
            'selected_offer_id' => 'x',
            'supplier_offer_id' => 'x',
            'validating_carrier' => 'EK',
            'fare' => ['amount' => 100, 'currency' => 'USD'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-09-01T10:00:00',
                    'arrival_at' => '2026-09-01T14:00:00',
                    'carrier' => 'EK',
                    'flight_number' => '615',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLOW',
                ],
            ],
            'passengers' => [['type' => 'ADT']],
            '_sabre_shop_context' => [
                'itinerary_group_index' => 1,
                'itinerary_index' => 0,
                'itinerary_ref' => 'itin-abc',
                'itinerary_pricing_index' => 0,
                'pricing_information_index' => 2,
                'leg_refs' => [3],
                'schedule_refs' => [9],
                'fare_component_refs' => [1],
            ],
        ];

        $payload = $builder->buildPayload($draft, 'bfm_revalidate_with_pricing_context');
        $this->assertSame('bfm_revalidate_with_pricing_context', $payload['_ota_revalidate_payload_style'] ?? null);
        $pi0 = is_array($payload['pricingInformation'][0] ?? null) ? $payload['pricingInformation'][0] : [];
        $this->assertSame(1, $pi0['itineraryGroupIndex'] ?? null);
        $this->assertSame(2, $pi0['pricingInformationIndex'] ?? null);
        $this->assertSame(['KLOW'], $pi0['fareBasisCodes'] ?? null);
        $this->assertSame(['K'], $pi0['bookingClasses'] ?? null);
        $this->assertSame([1], $pi0['segmentNumbers'] ?? null);

        $summary = $builder->safePayloadSummary($payload);
        $this->assertTrue($summary['has_reconstructed_pricing_context'] ?? false);
        $this->assertSame('bfm_revalidate_with_pricing_context', $summary['payload_style'] ?? '');
    }

    public function test_inspect_booking_revalidate_accepts_style_option(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->makeSabreBookingWithSnapshot();

        Artisan::call('sabre:inspect-booking-revalidate', [
            '--booking' => (string) $booking->id,
            '--style' => 'bfm_revalidate_with_pricing_context',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('payload_summary.payload_style=bfm_revalidate_with_pricing_context', $out);
        $this->assertStringContainsString('payload_summary.has_reconstructed_pricing_context=true', $out);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotBody(): array
    {
        return [
            'offer_id' => 'b16-offer',
            'supplier_offer_id' => 'b16-offer',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'EK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-10-01T10:00:00',
                    'arrival_at' => '2026-10-01T14:00:00',
                    'carrier' => 'EK',
                    'flight_number' => '615',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLOW',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 500,
                'currency' => 'USD',
                'base_fare' => 400,
                'taxes' => 100,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                'fare_basis_codes' => ['KLOW'],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'itinerary_group_index' => 1,
                    'itinerary_index' => 0,
                    'itinerary_ref' => 'itin-xyz',
                    'itinerary_pricing_index' => 0,
                    'pricing_information_index' => 2,
                    'leg_refs' => [3],
                    'schedule_refs' => [9],
                    'fare_component_refs' => [1],
                    'validating_carrier' => 'EK',
                    'fare_basis_codes' => ['KLOW'],
                ],
                'sabre_shop_identifiers' => [
                    'pricing_0_ref' => 'PI-REF-1',
                ],
                'sabre_fare_excerpt' => [
                    'total_price' => 500,
                    'currency' => 'USD',
                ],
            ],
        ];
    }

    protected function makeSabreBookingWithSnapshot(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $snapshot = $this->snapshotBody();
        $snapshot['supplier_connection_id'] = $sabreConn->id;

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => '2026-10-01',
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'b16@example.com',
            'phone' => '+10000000000',
            'country' => 'US',
            'address_line' => null,
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 400,
            'taxes' => 100,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 500,
            'currency' => 'USD',
            'breakdown' => [],
        ]);

        return $booking;
    }
}
