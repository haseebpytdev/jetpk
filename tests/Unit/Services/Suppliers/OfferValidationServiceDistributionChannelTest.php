<?php

namespace Tests\Unit\Services\Suppliers;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\OfferValidationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferValidationServiceDistributionChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_preserves_selected_snapshot_distribution_channel_when_validated_offer_omits_it(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        $connection = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $validatedWithoutChannel = new NormalizedFlightOfferData(
            offer_id: 'duffel-offer-9d',
            supplier_provider: SupplierProvider::Duffel->value,
            supplier_connection_id: $connection->id,
            airline_code: 'EK',
            airline_name: 'Emirates',
            flight_number: '201',
            origin: 'LHE',
            destination: 'DXB',
            departure_at: now()->addDays(10)->toIso8601String(),
            arrival_at: now()->addDays(10)->addHours(6)->toIso8601String(),
            duration_minutes: 360,
            stops: 0,
            cabin: 'economy',
            fare_family: null,
            refundable: false,
            seats_left: 9,
            segments: [],
            baggage: new BaggageAllowanceData(null, null, null),
            fare_breakdown: new FareBreakdownData(100000.0, 10000.0, 0.0, 110000.0, 'PKR'),
        );

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock) use ($validatedWithoutChannel): void {
            $mock->shouldReceive('validateOffer')->once()->andReturn(new OfferValidationResultData(
                is_valid: true,
                status: 'valid',
                validated_offer: $validatedWithoutChannel,
            ));
        });

        $selected = [
            'offer_id' => 'duffel-offer-9d',
            'supplier_provider' => SupplierProvider::Duffel->value,
            'supplier_connection_id' => $connection->id,
            'distribution_channel' => 'NDC',
            'airline_code' => 'EK',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => now()->addDays(10)->toIso8601String(),
            'arrival_at' => now()->addDays(10)->addHours(6)->toIso8601String(),
            'fare_breakdown' => ['base_fare' => 100000, 'taxes' => 10000, 'supplier_total' => 110000, 'currency' => 'PKR'],
            'baggage' => [],
        ];

        $result = app(OfferValidationService::class)->validateSelectedOffer($agency, $selected, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'source_channel' => 'public_guest',
        ]);

        $this->assertTrue($result->is_valid);
        $this->assertNotNull($result->validated_offer);
        $this->assertSame('NDC', $result->validated_offer->distribution_channel);
        $this->assertSame('NDC', $result->validated_offer->toArray()['distribution_channel'] ?? null);
    }

    public function test_does_not_overwrite_provider_returned_distribution_channel(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        $connection = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $validatedWithGds = new NormalizedFlightOfferData(
            offer_id: 'duffel-offer-9d-2',
            supplier_provider: SupplierProvider::Duffel->value,
            supplier_connection_id: $connection->id,
            airline_code: 'EK',
            airline_name: 'Emirates',
            flight_number: '201',
            origin: 'LHE',
            destination: 'DXB',
            departure_at: now()->addDays(10)->toIso8601String(),
            arrival_at: now()->addDays(10)->addHours(6)->toIso8601String(),
            duration_minutes: 360,
            stops: 0,
            cabin: 'economy',
            fare_family: null,
            refundable: false,
            seats_left: 9,
            segments: [],
            baggage: new BaggageAllowanceData(null, null, null),
            fare_breakdown: new FareBreakdownData(100000.0, 10000.0, 0.0, 110000.0, 'PKR'),
            distribution_channel: 'GDS',
        );

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock) use ($validatedWithGds): void {
            $mock->shouldReceive('validateOffer')->once()->andReturn(new OfferValidationResultData(
                is_valid: true,
                status: 'valid',
                validated_offer: $validatedWithGds,
            ));
        });

        $selected = [
            'offer_id' => 'duffel-offer-9d-2',
            'supplier_provider' => SupplierProvider::Duffel->value,
            'supplier_connection_id' => $connection->id,
            'distribution_channel' => 'NDC',
            'airline_code' => 'EK',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => now()->addDays(10)->toIso8601String(),
            'arrival_at' => now()->addDays(10)->addHours(6)->toIso8601String(),
            'fare_breakdown' => ['base_fare' => 100000, 'taxes' => 10000, 'supplier_total' => 110000, 'currency' => 'PKR'],
            'baggage' => [],
        ];

        $result = app(OfferValidationService::class)->validateSelectedOffer($agency, $selected, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame('GDS', $result->validated_offer?->distribution_channel);
    }
}
