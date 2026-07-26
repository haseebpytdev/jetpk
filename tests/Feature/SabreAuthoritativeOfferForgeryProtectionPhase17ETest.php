<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Support\PublicBooking;
use Tests\Support\Sabre\SabrePublicCreatePhase17ETestCase;
use Tests\Support\Sabre\SabrePublicCreateStructuralScenarioCatalog;

/**
 * Phase 17E: forged browser payloads cannot override authoritative revalidated offer.
 */
class SabreAuthoritativeOfferForgeryProtectionPhase17ETest extends SabrePublicCreatePhase17ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_guest_forged_fields_do_not_change_create_payload(): void
    {
        $this->stubSabreCreatePnrHttp('FORGEG1');
        $booking = $this->makeAuthoritativeBooking();

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
                'origin' => 'JFK',
                'destination' => 'LAX',
                'total' => 1,
                'currency' => 'USD',
                'segments' => [['origin' => 'JFK', 'destination' => 'LAX', 'carrier' => 'AA']],
                'offer_id' => 'forged-offer',
            ])
            ->assertRedirect(route('booking.confirmation'));

        $this->assertAuthoritativePayloadUsed();
    }

    public function test_customer_forged_fields_do_not_change_create_payload(): void
    {
        $this->stubSabreCreatePnrHttp('FORGEC1');
        $booking = $this->makeAuthoritativeBooking();

        $this->actingAs($this->customerUser())
            ->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
                'validating_carrier' => 'AA',
                'supplier_connection_id' => 99999,
                'fare_basis' => 'FORGED',
            ])
            ->assertRedirect(route('booking.confirmation'));

        $this->assertAuthoritativePayloadUsed();
    }

    public function test_agent_forged_fields_do_not_change_create_payload(): void
    {
        $this->stubSabreCreatePnrHttp('FORGEA1');
        $booking = $this->makeAuthoritativeBooking();

        $this->actingAs($this->agentUser())
            ->withSession(array_merge($this->agentSessionContext(), [
                PublicBooking::SESSION_BOOKING_ID => $booking->id,
            ]))
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
                'flight_number' => '9999',
                'carrier' => 'AA',
            ])
            ->assertRedirect(route('booking.confirmation'));

        $this->assertAuthoritativePayloadUsed();
    }

    public function test_authoritative_segment_order_cases_produce_valid_payload_chain(): void
    {
        foreach (SabrePublicCreateStructuralScenarioCatalog::authoritativeSegmentOrderCases() as $label => $segments) {
            $draft = [
                '_valid' => true,
                'supplier_connection_id' => 1,
                '_sabre_pseudo_city_code' => 'AB12',
                'validating_carrier' => (string) ($segments[0]['carrier'] ?? 'PK'),
                'segments' => $segments,
                'passengers' => [['type' => 'ADT', 'first_name' => 'T', 'last_name' => 'U', 'gender' => 'MALE', 'date_of_birth' => '1990-01-15']],
                'contact' => ['email' => 't@example.com', 'phone' => '3001234567'],
                '_requires_passport_doc' => false,
                '_sabre_booking_context' => [],
            ];
            $wire = app(\App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder::class)
                ->buildIatiLikeCpnrV24GdsWire($draft, []);
            $wireSegs = $this->extractWireFlightSegments($wire);
            $this->assertCount(3, $wireSegs, $label);
            $this->assertSame('LHE', $wireSegs[0]['OriginLocation']['LocationCode'] ?? null, $label);
            $this->assertSame('JED', $wireSegs[2]['DestinationLocation']['LocationCode'] ?? null, $label);
        }
    }

    public function test_disconnected_descriptor_fixture_is_rejected_by_normalizer(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_explicit_ids_unresolved_schedule_refs.json')),
            true,
        );
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection, $searchRequest);
        $this->assertCount(0, $offers);
    }

    protected function makeAuthoritativeBooking(): \App\Models\Booking
    {
        $authoritativeSegments = [
            [
                'origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'EK',
                'marketing_carrier' => 'EK', 'operating_carrier' => 'EK',
                'flight_number' => '625', 'departure_at' => now()->addDays(14)->format('Y-m-d').'T08:00:00Z',
                'arrival_at' => now()->addDays(14)->format('Y-m-d').'T14:00:00Z',
                'booking_class' => 'Y', 'fare_basis_code' => 'YLOW',
            ],
        ];

        return $this->makeFreshSabreDraftBooking([], [], $authoritativeSegments);
    }

    protected function assertAuthoritativePayloadUsed(): void
    {
        $this->assertExactlyOneCreatePnrDispatch();
        $body = $this->lastCreatePnrRequestBody();
        $this->assertIsArray($body);
        $segments = $this->extractWireFlightSegments($body);
        $this->assertNotEmpty($segments);
        $this->assertSame('LHE', $segments[0]['OriginLocation']['LocationCode'] ?? null);
        $this->assertSame('DXB', $segments[0]['DestinationLocation']['LocationCode'] ?? null);
        $this->assertSame('EK', $segments[0]['MarketingAirline']['Code'] ?? null);
        $this->assertStringNotContainsString('FORGED', json_encode($body) ?: '');
    }
}
