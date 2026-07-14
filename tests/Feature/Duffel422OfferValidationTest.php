<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\SupplierDiagnosticLog;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Duffel422OfferValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_offer_422_stores_safe_duffel_errors_and_reason_supplier_request_invalid(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => ['access_token' => 'duffel_validate_token'],
        ]);

        Http::fake([
            'https://api.duffel.com/air/offers/*' => Http::response([
                'errors' => [[
                    'type' => 'validation_error',
                    'title' => 'Malformed payload',
                    'detail' => 'selected_offers must reference a valid offer id',
                    'code' => 'validation_error',
                    'source' => ['pointer' => '/data'],
                ]],
            ], 422),
        ]);

        $offerData = $this->minimalDuffelOfferArray((int) $connection->id, 'off_bad');
        $offerDto = NormalizedFlightOfferData::fromArray($offerData);

        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-01',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'trip_type' => 'one_way',
        ], $agency->id, 'public_guest');

        $adapter = app(DuffelFlightSupplierAdapter::class);
        $result = $adapter->validateOffer($offerDto, $request, $connection);

        $this->assertFalse($result->is_valid);
        $this->assertSame('provider_error', $result->status);
        $this->assertSame('Fare validation is temporarily unavailable. Please try again.', $result->warnings[0]);
        $this->assertSame('supplier_request_invalid', $result->meta['reason_code']);
        $this->assertSame('supplier_request_invalid', $result->meta['error_code']);
        $this->assertSame(422, $result->meta['http_status']);
        $this->assertTrue($result->meta['supplier_offer_id_present']);
        $this->assertNotEmpty($result->meta['duffel_errors']);

        $payloadJson = json_encode(SupplierDiagnosticLog::query()->latest()->first()?->meta ?? []);
        $this->assertStringNotContainsStringIgnoringCase('Bearer', $payloadJson);
        $this->assertStringNotContainsString('duffel_validate_token', $payloadJson);
    }

    public function test_validate_offer_422_offer_expired_maps_to_unavailable_for_recovery_flow(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = $this->configureDuffelConnection((int) $agency->id, [
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.duffel.com',
            'credentials' => ['access_token' => 'duffel_validate_token'],
        ]);

        Http::fake([
            'https://api.duffel.com/air/offers/*' => Http::response([
                'errors' => [[
                    'type' => 'airline_error',
                    'title' => 'Offer expired',
                    'detail' => 'This offer has expired',
                    'code' => 'offer_expired',
                ]],
            ], 422),
        ]);

        $offerDto = NormalizedFlightOfferData::fromArray($this->minimalDuffelOfferArray((int) $connection->id, 'off_exp'));
        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-01',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'trip_type' => 'one_way',
        ], $agency->id, 'public_guest');

        $adapter = app(DuffelFlightSupplierAdapter::class);
        $result = $adapter->validateOffer($offerDto, $request, $connection);

        $this->assertSame('unavailable', $result->status);
        $this->assertSame('offer_unavailable', $result->meta['reason_code']);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalDuffelOfferArray(int $connectionId, string $offerId): array
    {
        return [
            'offer_id' => $offerId,
            'id' => $offerId,
            'supplier_provider' => SupplierProvider::Duffel->value,
            'supplier_connection_id' => $connectionId,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'flight_number' => 'EK262',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-07-01T09:00:00Z',
            'arrival_at' => '2026-07-01T14:00:00Z',
            'duration_minutes' => 300,
            'stops' => 0,
            'cabin' => 'economy',
            'segments' => [],
            'baggage' => [],
            'fare_breakdown' => [
                'base_fare' => 100,
                'taxes' => 20,
                'supplier_fees' => 0,
                'supplier_total' => 120,
                'currency' => 'USD',
            ],
            'raw_reference' => $offerId,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function configureDuffelConnection(int $agencyId, array $attributes): SupplierConnection
    {
        $connection = SupplierConnection::query()
            ->where('agency_id', $agencyId)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->forceFill($attributes)->save();

        return $connection->fresh();
    }
}
