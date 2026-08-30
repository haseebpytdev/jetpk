<?php

namespace Tests\Unit\Services\Suppliers;

use App\Contracts\Suppliers\FlightSupplierInterface;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Pricing\PricingRuleService;
use App\Services\Suppliers\OfferValidationService;
use App\Services\Suppliers\SupplierAdapterResolver;
use App\Support\FlightSearch\SabreOfferFreshness;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OfferValidationRecentRevalidationSkipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Agency, 1: SupplierConnection, 2: array<string, mixed>}
     */
    private function sabreFixture(): array
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'active',
        ]);

        $offer = [
            'id' => 'offer-r5-1',
            'offer_id' => 'offer-r5-1',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $connection->id,
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_at' => now()->addWeek()->toIso8601String(),
            'total' => 100000,
            'currency' => 'PKR',
            'fare_breakdown' => [
                'base_fare' => 80000,
                'taxes' => 20000,
                'supplier_total' => 100000,
                'currency' => 'PKR',
            ],
            'segments' => [],
            'raw_payload' => [],
        ];

        return [$agency, $connection, $offer];
    }

    /**
     * @param  array<string, mixed>  $searchContextExtra
     * @return array<string, mixed>
     */
    private function baseContext(array $searchContextExtra = []): array
    {
        return array_merge([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addWeek()->format('Y-m-d'),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'trip_type' => 'one_way',
            'source_channel' => 'public_guest',
            'search_id' => 'search-r5-1',
        ], $searchContextExtra);
    }

    public function test_sabre_skips_live_adapter_when_recent_revalidation_present(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);

        [$agency, $connection, $offer] = $this->sabreFixture();

        $resolver = Mockery::mock(SupplierAdapterResolver::class);
        $resolver->shouldReceive('resolve')->never();

        $service = new OfferValidationService(
            $resolver,
            app(PricingRuleService::class),
            app(PlatformModuleEnforcer::class),
        );

        $result = $service->validateSelectedOffer($agency, $offer, $this->baseContext([
            'offer_freshness' => [
                'last_revalidated_at' => now()->toIso8601String(),
                'revalidation_status' => 'success',
            ],
        ]));

        $this->assertTrue($result->is_valid);
        $this->assertNotNull($result->validated_offer);
        $this->assertNotNull($connection->id);
    }

    public function test_sabre_calls_live_adapter_when_revalidation_freshness_expired(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'ota.offer_freshness.stale_after_seconds' => 600,
            'ota.offer_freshness.refresh_due_seconds' => 300,
        ]);

        [$agency, , $offer] = $this->sabreFixture();
        $expiredAt = now()->subSeconds(app(SabreOfferFreshness::class)->revalidationValiditySeconds() + 30);

        $adapter = Mockery::mock(FlightSupplierInterface::class);
        $adapter->shouldReceive('validateOffer')->once()->andReturn(new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: 'offer-r5-1',
            validated_offer: NormalizedFlightOfferData::fromArray($offer),
        ));

        $resolver = Mockery::mock(SupplierAdapterResolver::class);
        $resolver->shouldReceive('resolve')->once()->andReturn($adapter);

        $service = new OfferValidationService(
            $resolver,
            app(PricingRuleService::class),
            app(PlatformModuleEnforcer::class),
        );

        $result = $service->validateSelectedOffer($agency, $offer, $this->baseContext([
            'offer_freshness' => [
                'last_revalidated_at' => $expiredAt->toIso8601String(),
                'revalidation_status' => 'success',
            ],
        ]));

        $this->assertTrue($result->is_valid);
        $this->assertNotNull($result->validated_offer);
    }

    public function test_sabre_calls_live_adapter_when_revalidation_freshness_missing(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);

        [$agency, , $offer] = $this->sabreFixture();

        $adapter = Mockery::mock(FlightSupplierInterface::class);
        $adapter->shouldReceive('validateOffer')->once()->andReturn(new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: 'offer-r5-1',
            validated_offer: NormalizedFlightOfferData::fromArray($offer),
        ));

        $resolver = Mockery::mock(SupplierAdapterResolver::class);
        $resolver->shouldReceive('resolve')->once()->andReturn($adapter);

        $service = new OfferValidationService(
            $resolver,
            app(PricingRuleService::class),
            app(PlatformModuleEnforcer::class),
        );

        $result = $service->validateSelectedOffer($agency, $offer, $this->baseContext([
            // intentionally no offer_freshness
        ]));

        $this->assertTrue($result->is_valid);
        $this->assertNotNull($result->validated_offer);
    }
}
