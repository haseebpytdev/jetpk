<?php

namespace Tests\Support;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Pricing\PricingRuleService;
use App\Services\Suppliers\OfferValidationService;
use App\Support\Pricing\PublicCustomerPricing;
use Illuminate\Support\Facades\App;
use Mockery;
use Tests\TestCase;

/**
 * Fakes flight search + offer validation so feature tests do not depend on a removed mock supplier API.
 */
final class PublicCheckoutTestDoubles
{
    public const OFFER_ID = 'fixture-offer-1';

    /**
     * @return array<string, mixed>
     */
    public static function searchOfferPayload(string $departDate, string $from = 'LHE', string $to = 'DXB'): array
    {
        $departIso = $departDate.'T08:00:00Z';
        $arriveIso = $departDate.'T12:30:00Z';

        return [
            'id' => self::OFFER_ID,
            'offer_id' => self::OFFER_ID,
            'supplier_provider' => SupplierProvider::Duffel->value,
            'supplier_connection_id' => 1,
            'airline_code' => 'TA',
            'carrier_code' => 'TA',
            'airline_name' => 'TestAir',
            'flight_number' => '101',
            'origin' => $from,
            'destination' => $to,
            'depart_at' => $departIso,
            'arrive_at' => $arriveIso,
            'duration_h' => 4,
            'duration_m' => 30,
            'stops' => 0,
            'baggage' => '20kg',
            'refundable' => true,
            'cabin' => 'economy',
            'fare_family' => 'economy_flex',
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'supplier_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'base_fare' => 100000,
            'taxes' => 10000,
            'markup' => 2500,
            'service_fee' => 2499,
            'total' => 114999,
            'final_customer_price' => 114999,
            'segments' => [
                [
                    'origin' => $from,
                    'destination' => $to,
                    'departure_at' => $departIso,
                    'arrival_at' => $arriveIso,
                    'airline_code' => 'TA',
                    'airline_name' => 'TestAir',
                    'flight_number' => '101',
                ],
            ],
        ];
    }

    public static function validatedNormalizedOffer(string $departDate, string $from = 'LHE', string $to = 'DXB'): NormalizedFlightOfferData
    {
        $departIso = $departDate.'T08:00:00Z';
        $arriveIso = $departDate.'T12:30:00Z';

        return new NormalizedFlightOfferData(
            offer_id: self::OFFER_ID,
            supplier_provider: SupplierProvider::Duffel->value,
            supplier_connection_id: 1,
            airline_code: 'TA',
            airline_name: 'TestAir',
            flight_number: '101',
            origin: $from,
            destination: $to,
            departure_at: $departIso,
            arrival_at: $arriveIso,
            duration_minutes: 270,
            stops: 0,
            cabin: 'economy',
            fare_family: 'economy_flex',
            refundable: true,
            seats_left: 9,
            segments: [
                [
                    'origin' => $from,
                    'destination' => $to,
                    'departure_at' => $departIso,
                    'arrival_at' => $arriveIso,
                    'airline_code' => 'TA',
                    'airline_name' => 'TestAir',
                    'flight_number' => '101',
                ],
            ],
            baggage: new BaggageAllowanceData(checked: '20kg', cabin: '7kg', summary: null),
            fare_breakdown: new FareBreakdownData(
                base_fare: 100000.0,
                taxes: 10000.0,
                supplier_fees: 0.0,
                supplier_total: 110000.0,
                currency: 'PKR',
            ),
            expires_at: now()->addHour()->toIso8601String(),
            raw_reference: self::OFFER_ID,
            raw_payload: ['payment_requirements' => ['requires_instant_payment' => true]],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function pricingSnapshot(): array
    {
        return [
            'base_fare' => 100000.0,
            'taxes' => 10000.0,
            'supplier_total' => 110000.0,
            'supplier_currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'fx_rate' => null,
            'fx_fetched_at' => null,
            'admin_markup' => 3500.0,
            'route_markup' => 1200.0,
            'airline_markup' => 0.0,
            'agent_markup_or_commission' => 0.0,
            'service_fee' => 2499.0,
            'final_total' => 116199.0,
            'applied_rules' => [],
        ];
    }

    public static function bind(TestCase $case, string $departDate, string $from = 'LHE', string $to = 'DXB', string $sourceChannel = 'public_guest', ?int $agentId = null): void
    {
        $offer = self::searchOfferPayload($departDate, $from, $to);
        $validated = self::validatedNormalizedOffer($departDate, $from, $to);
        $validatedArray = $validated->toArray();
        $fareRaw = $validatedArray['fare_breakdown'] ?? [];
        $fareArr = is_array($fareRaw) ? $fareRaw : (array) $fareRaw;
        $agency = Agency::query()->where('slug', (string) config('ota.default_agency_slug'))->first();

        if ($agency !== null) {
            $pricing = app(PricingRuleService::class)->calculateMarkup($agency, [
                'base_fare' => (float) ($fareArr['base_fare'] ?? 100000),
                'taxes' => (float) ($fareArr['taxes'] ?? 10000),
                'supplier_total' => (float) ($fareArr['supplier_total'] ?? 0),
                'currency' => (string) ($fareArr['currency'] ?? 'PKR'),
            ], [
                'route' => strtoupper($from).'-'.strtoupper($to),
                'origin' => $from,
                'destination' => $to,
                'airline' => strtolower((string) ($validatedArray['airline_code'] ?? 'ta')),
                'supplier' => SupplierProvider::Duffel->value,
                'agent_id' => $agentId,
                'cabin' => $validatedArray['cabin'] ?? null,
                'fare_family' => $validatedArray['fare_family'] ?? null,
                'travel_date' => $departDate,
                'source_channel' => $sourceChannel,
            ]);

            if (PublicCustomerPricing::isPublicChannel($sourceChannel)) {
                $pricing = PublicCustomerPricing::sanitizeIfPublicChannel($pricing, $sourceChannel, [
                    'offer_id' => self::OFFER_ID,
                ]);
            }
        } else {
            $pricing = self::pricingSnapshot();
        }

        $result = new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: self::OFFER_ID,
            validated_offer: $validated,
            currency: 'PKR',
            meta: [
                'pricing_snapshot' => $pricing,
                'applied_rules' => $pricing['applied_rules'] ?? [],
            ],
        );

        $ovs = Mockery::mock(OfferValidationService::class);
        $ovs->shouldReceive('validateSelectedOffer')->andReturn($result);
        $ovs->shouldReceive('pricingSnapshotForCachedOffer')->andReturn($pricing);
        App::instance(OfferValidationService::class, $ovs);

        $flightSearch = Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('search')->andReturn([$offer]);
        $flightSearch->shouldReceive('searchWithMeta')->andReturn([
            'offers' => [$offer],
            'warnings' => [],
        ]);
        App::instance(FlightSearchService::class, $flightSearch);
    }
}
