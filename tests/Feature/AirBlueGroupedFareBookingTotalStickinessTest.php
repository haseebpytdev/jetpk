<?php

namespace Tests\Feature;

use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Frontend\BookingController;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Bookings\FareHoldService;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\OfferValidationService;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use App\Support\Payments\BookingPayableResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\TestCase;

/**
 * Permanent gate: selected AirBlue grouped EF customer 26500 must survive draft
 * booking + review + payable persistence while supplier ticketing stays 26000.
 */
class AirBlueGroupedFareBookingTotalStickinessTest extends TestCase
{
    use RefreshDatabase;

    private const PARENT_OFFER_ID = 'airblue-pa201-parent';

    private const SEARCH_ID = 'airblue-stickiness-search';

    /**
     * @return array{parent: array<string, mixed>, ef_key: string, connection_id: int}
     */
    private function seedGroupedAirBlueParent(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Config::set('suppliers.airblue.branded_fares_display_enabled', true);
        Config::set('suppliers.airblue.branded_fares_selection_enabled', true);
        Config::set('ota.universal_fare_choice_enabled', true);

        $agency = Agency::query()->where('slug', (string) config('ota.default_agency_slug'))->firstOrFail();
        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Stickiness Fixture',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'protocol_version' => '3.0',
                'client_id' => 'fixture-client',
                'client_key' => 'fixture-key',
                'agent_type' => '5',
                'agent_id' => 'fixture-agent',
                'agent_password' => 'fixture-secret',
            ],
        ]);

        $members = [
            $this->airbluePa201Member('ab-ev', 'EV', 25000.0, 25500.0, 500.0, 'UEV1', $connection->id),
            $this->airbluePa201Member('ab-ef', 'EF', 26000.0, 26500.0, 500.0, 'UEF1', $connection->id),
            $this->airbluePa201Member('ab-ex', 'EX', 27100.0, 27600.0, 500.0, 'UEX1', $connection->id),
        ];
        $parent = ItineraryFareConsolidator::consolidate($members)[0];
        $parent['id'] = self::PARENT_OFFER_ID;
        $parent['offer_id'] = self::PARENT_OFFER_ID;
        $parent['supplier_connection_id'] = $connection->id;

        $presentation = FlightOfferDisplayPresenter::buildPresentation(
            $parent,
            ['origin' => 'ISB', 'destination' => 'KHI'],
            [],
        );
        $options = is_array($presentation['branded_fares_display_options'] ?? null)
            ? $presentation['branded_fares_display_options']
            : FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($parent);
        $ef = collect($options)->first(
            static fn (array $row): bool => (string) ($row['name'] ?? '') === 'EF',
        );
        $this->assertIsArray($ef);
        $efKey = (string) ($ef['option_key'] ?? '');
        $this->assertNotSame('', $efKey);
        $customerPrice = (int) ($ef['displayed_price'] ?? $ef['price_total'] ?? 0);
        $this->assertSame(26500, $customerPrice);

        return [
            'parent' => $parent,
            'ef_key' => $efKey,
            'connection_id' => (int) $connection->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $parent
     */
    private function bindOfflineCheckout(array $parent): void
    {
        $pricingFromOffer = static function (array $offer): array {
            $supplier = (float) ($offer['supplier_total_source']
                ?? data_get($offer, 'fare_breakdown.supplier_total', 0));
            $customer = (float) ($offer['final_customer_price'] ?? $offer['displayed_price'] ?? 0);
            if ($customer <= 0) {
                $customer = $supplier > 0 ? $supplier + 500 : 0;
            }
            $markup = max(0.0, $customer - $supplier);
            $base = (float) (data_get($offer, 'fare_breakdown.base_fare') ?? ($supplier - 3000));
            $taxes = (float) (data_get($offer, 'fare_breakdown.taxes') ?? 3000);

            return [
                'base_fare' => $base,
                'taxes' => $taxes,
                'supplier_total' => $supplier,
                'supplier_currency' => 'PKR',
                'pricing_currency' => 'PKR',
                'conversion_status' => 'same_currency',
                'fx_rate' => null,
                'fx_fetched_at' => null,
                'admin_markup' => $markup,
                'route_markup' => 0.0,
                'airline_markup' => 0.0,
                'agent_markup_or_commission' => 0.0,
                'service_fee' => 0.0,
                'final_total' => $customer,
                'applied_rules' => $markup > 0
                    ? [['bucket' => 'admin_markup', 'amount' => $markup]]
                    : [],
            ];
        };

        $ovs = Mockery::mock(OfferValidationService::class);
        $ovs->shouldReceive('validateSelectedOffer')->andReturnUsing(
            function ($agency, array $offer, array $criteria) use ($pricingFromOffer) {
                $pricing = $pricingFromOffer($offer);
                $validated = NormalizedFlightOfferData::fromArray($offer);

                return new OfferValidationResultData(
                    is_valid: true,
                    status: 'valid',
                    original_offer_id: (string) ($offer['offer_id'] ?? $offer['id'] ?? ''),
                    validated_offer: $validated,
                    currency: 'PKR',
                    meta: [
                        'pricing_snapshot' => $pricing,
                        'applied_rules' => $pricing['applied_rules'],
                    ],
                );
            }
        );
        $ovs->shouldReceive('pricingSnapshotForCachedOffer')->andReturnUsing(
            function ($agency, array $offer, array $criteria) use ($pricingFromOffer) {
                return $pricingFromOffer($offer);
            }
        );
        App::instance(OfferValidationService::class, $ovs);

        $flightSearch = Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('search')->andReturn([$parent]);
        $flightSearch->shouldReceive('searchWithMeta')->andReturn(['offers' => [$parent], 'warnings' => []]);
        App::instance(FlightSearchService::class, $flightSearch);
    }

    /**
     * @return array<string, mixed>
     */
    private function airbluePa201Member(
        string $id,
        string $fareType,
        float $supplierTotal,
        float $customerPayable,
        float $markup,
        string $fareBasis,
        int $connectionId,
    ): array {
        return [
            'offer_id' => $id,
            'id' => $id,
            'supplier_provider' => 'airblue',
            'supplier_connection_id' => $connectionId,
            'airline_code' => 'PA',
            'airline_name' => 'AirBlue',
            'flight_number' => '201',
            'origin' => 'ISB',
            'destination' => 'KHI',
            'departure_at' => '2026-12-29T10:45:00',
            'arrival_at' => '2026-12-29T12:45:00',
            'cabin' => 'economy',
            'fare_family' => $fareType,
            'refundable' => false,
            'stops' => 0,
            'duration_h' => 2,
            'duration_m' => 0,
            'duration_minutes' => 120,
            'baggage' => '',
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'base_fare' => $supplierTotal - 3000,
            'taxes' => 3000,
            'markup' => $markup,
            'service_fee' => 0,
            'supplier_total_source' => $supplierTotal,
            'final_customer_price' => $customerPayable,
            'displayed_price' => $customerPayable,
            'fare_breakdown' => [
                'base_fare' => $supplierTotal - 3000,
                'taxes' => 3000,
                'supplier_total' => $supplierTotal,
                'currency' => 'PKR',
            ],
            'pricing_components' => [
                'base_fare' => $supplierTotal - 3000,
                'taxes' => 3000,
                'final_total' => $customerPayable,
                'conversion_status' => 'same_currency',
                'pricing_currency' => 'PKR',
                'service_fee' => 0.0,
                'admin_markup' => $markup,
                'applied_rules' => [
                    ['bucket' => 'admin_markup', 'amount' => $markup],
                ],
            ],
            'segments' => [[
                'origin' => 'ISB',
                'destination' => 'KHI',
                'departure_at' => '2026-12-29T10:45:00',
                'arrival_at' => '2026-12-29T12:45:00',
                'airline_code' => 'PA',
                'flight_number' => '201',
                'booking_class' => 'U',
                'fare_basis' => $fareBasis,
                'cabin' => 'economy',
                'fare_type' => $fareType,
                'cabin_class' => 'Y',
            ]],
            'provider_context' => [
                'fare_type_code' => $fareType,
                'rbd' => 'U',
                'fare_basis' => $fareBasis,
                'refundability_known' => false,
            ],
            'raw_payload' => [
                'provider_context' => [
                    'fare_type_code' => $fareType,
                    'rbd' => 'U',
                    'fare_basis' => $fareBasis,
                    'refundability_known' => false,
                    'priced_itineraries' => [['offer_ref' => $id]],
                ],
                'payment_requirements' => ['requires_instant_payment' => true],
                'customer_display_fields' => [
                    'booking_class' => 'U',
                    'fare_basis' => $fareBasis,
                    'fare_type' => $fareType,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function submitEfPassengers(array $fixture): Booking
    {
        $depart = '2026-12-29';
        $efKey = $fixture['ef_key'];

        // POST alone is enough: passengers store re-resolves fare_option_key and
        // projects the selected EF member before draft persistence (no provider calls).
        $this->post('/booking/passengers', PublicBookingPassengersPayload::merge([
            'flight_id' => self::PARENT_OFFER_ID,
            'offer_id' => self::PARENT_OFFER_ID,
            'search_id' => self::SEARCH_ID,
            'fare_option_key' => $efKey,
            'from' => 'ISB',
            'to' => 'KHI',
            'depart' => $depart,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'email' => 'ef.stickiness@example.com',
            'document_type' => 'national_id',
            'national_id_number' => '35202-1234567-1',
            'passport_number' => '',
            'passport_issuing_country' => '',
            'passport_expiry_date' => '',
            'passport_issue_date' => '',
            'nationality' => 'PK',
            'terms_accepted' => '1',
            'terms_version' => (string) config('ota_checkout_consent.terms_version'),
        ]))->assertRedirect(route('booking.review'));

        $booking = Booking::query()->latest('id')->first();
        $this->assertNotNull($booking);

        return $booking->fresh(['fareBreakdown']);
    }

    public function test_airblue_grouped_selected_fare_customer_total_survives_booking_and_review_persistence(): void
    {
        $fixture = $this->seedGroupedAirBlueParent();
        $this->bindOfflineCheckout($fixture['parent']);

        $presentation = FlightOfferDisplayPresenter::buildPresentation(
            $fixture['parent'],
            ['origin' => 'ISB', 'destination' => 'KHI'],
            [],
        );
        $options = is_array($presentation['branded_fares_display_options'] ?? null)
            ? $presentation['branded_fares_display_options']
            : [];
        $byName = collect($options)->keyBy(static fn (array $row): string => (string) ($row['name'] ?? ''));
        $priceOf = static fn (string $name): int => (int) (
            $byName[$name]['displayed_price'] ?? $byName[$name]['price_total'] ?? 0
        );
        $this->assertSame(25500, $priceOf('EV'));
        $this->assertSame(26500, $priceOf('EF'));
        $this->assertSame(27600, $priceOf('EX'));

        $booking = $this->submitEfPassengers($fixture);

        $intent = is_array($booking->meta['selected_fare_family_option'] ?? null)
            ? $booking->meta['selected_fare_family_option']
            : [];
        $this->assertSame('EF', (string) ($intent['name'] ?? ''));
        $this->assertSame(26500, (int) ($intent['displayed_price'] ?? 0));

        $this->assertSame('26500.00', (string) $booking->selected_fare_total);
        $this->assertSame('26500.00', (string) $booking->revalidated_fare_total);
        $this->assertNotNull($booking->fareBreakdown);
        $this->assertSame(26500.0, (float) $booking->fareBreakdown->total);

        $this->assertSame(26000.0, (float) ($booking->meta['supplier_total'] ?? 0));

        $snapshot = is_array($booking->meta['validated_offer_snapshot'] ?? null)
            ? $booking->meta['validated_offer_snapshot']
            : [];
        $this->assertSame(26000.0, (float) data_get($snapshot, 'fare_breakdown.supplier_total', 0));
        $this->assertSame('EF', (string) ($snapshot['fare_family'] ?? ''));

        $this->assertSame(26500.0, BookingPayableResolver::fareTotal($booking));
        $this->assertSame(26500.0, BookingPayableResolver::customerPayableTotal($booking));

        $review = $this->getJson('/booking/review?format=json');
        $review->assertOk()
            ->assertJsonPath('pricing.total', 26500);
        $formatted = (string) $review->json('pricing.formatted_total');
        $this->assertMatchesRegularExpression('/(PKR|Rs\.?)\s*26,?500/', $formatted);
        $this->assertSame(26500.0, (float) $review->json('pricing.total'));

        $fareLabel = (string) (
            $review->json('selected_fare.name')
            ?? $review->json('selected_fare_family')
            ?? $review->json('fare_family')
            ?? data_get($review->json(), 'itinerary.fare_family')
            ?? data_get($review->json(), 'selected_fare_family_option.name')
            ?? $intent['name']
            ?? ''
        );
        $this->assertSame('EF', $fareLabel);

        $ticketXml = (new AirBlueOtaXmlBuilder)->buildAirDemandTicketRequest(
            [
                'client_id' => 'CLIENT',
                'client_key' => 'KEY',
                'agent_type' => '5',
                'agent_id' => 'AGENT',
                'agent_password' => 'secret',
                'service_target' => 'Test',
                'service_version' => '1.04',
                'currency' => 'PKR',
            ],
            'STICKPNR1',
            'INST-1',
            [
                'amount' => (float) data_get($snapshot, 'fare_breakdown.supplier_total', 0),
                'currency' => 'PKR',
                'payment_type' => 'Cash',
            ],
        );
        $this->assertStringContainsString('Amount="26000.00"', $ticketXml);
        $this->assertStringNotContainsString('Amount="26500.00"', $ticketXml);
        $this->assertStringNotContainsString('Amount="25000.00"', $ticketXml);
    }

    public function test_revalidate_checkout_before_confirmation_does_not_overwrite_selected_ef_customer_total(): void
    {
        $fixture = $this->seedGroupedAirBlueParent();
        $this->bindOfflineCheckout($fixture['parent']);
        $booking = $this->submitEfPassengers($fixture);

        $agency = Agency::query()->findOrFail($booking->agency_id);
        $parentPricing = [
            'base_fare' => 22000.0,
            'taxes' => 3000.0,
            'supplier_total' => 25000.0,
            'supplier_currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'admin_markup' => 0.0,
            'route_markup' => 0.0,
            'airline_markup' => 0.0,
            'agent_markup_or_commission' => 0.0,
            'service_fee' => 0.0,
            'final_total' => 25000.0,
            'applied_rules' => [],
        ];

        $parentSnapshot = $fixture['parent'];
        $validated = NormalizedFlightOfferData::fromArray($parentSnapshot);
        $validation = new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: self::PARENT_OFFER_ID,
            validated_offer: $validated,
            price_changed: false,
            currency: 'PKR',
            meta: ['pricing_snapshot' => $parentPricing],
        );

        $fareHold = Mockery::mock(FareHoldService::class);
        $fareHold->shouldReceive('requiresFinalRevalidation')->andReturn(true);
        $fareHold->shouldReceive('revalidateBeforeConfirmation')->andReturn($validation);
        App::instance(FareHoldService::class, $fareHold);

        $controller = app(BookingController::class);
        $method = new \ReflectionMethod($controller, 'revalidateCheckoutBeforeConfirmation');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $booking->fresh(['fareBreakdown']));

        $this->assertSame('ok', $result['status']);
        $booking->refresh()->load('fareBreakdown');

        $this->assertSame('26500.00', (string) $booking->selected_fare_total);
        $this->assertSame('26500.00', (string) $booking->revalidated_fare_total);
        $this->assertSame(26500.0, (float) $booking->fareBreakdown->total);
        $this->assertSame(26500.0, BookingPayableResolver::fareTotal($booking));
        $this->assertNotSame(25000.0, (float) $booking->fareBreakdown->total);
        $this->assertNotSame(26000.0, (float) $booking->fareBreakdown->total);

        $this->assertSame(26000.0, (float) ($booking->meta['supplier_total'] ?? 0));
        $this->assertNotSame(26500.0, (float) ($booking->meta['supplier_total'] ?? 0));
        $this->assertNotSame(25000.0, (float) ($booking->meta['supplier_total'] ?? 0));
    }
}
