<?php

namespace Tests\Unit\Services\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Pricing\PricingRuleService;
use App\Services\Suppliers\OfferValidationService;
use App\Services\Suppliers\SupplierAdapterResolver;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OfferValidationRecentRevalidationSkipTest extends TestCase
{
    use RefreshDatabase;

    public function test_sabre_skips_live_adapter_when_recent_revalidation_present(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);

        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'active',
        ]);

        $resolver = Mockery::mock(SupplierAdapterResolver::class);
        $resolver->shouldReceive('resolve')->never();

        $service = new OfferValidationService(
            $resolver,
            app(PricingRuleService::class),
            app(PlatformModuleEnforcer::class),
        );

        // Force connection resolution via real DB path by using matching snapshot ids.
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

        $result = $service->validateSelectedOffer($agency, $offer, [
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
            'offer_freshness' => [
                'last_revalidated_at' => now()->toIso8601String(),
                'revalidation_status' => 'success',
            ],
        ]);

        $this->assertTrue($result->is_valid);
        $this->assertNotNull($result->validated_offer);
    }
}
