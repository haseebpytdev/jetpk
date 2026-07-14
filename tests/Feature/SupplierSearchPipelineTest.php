<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\SupplierAdapterResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class SupplierSearchPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_duffel_adapter_returns_empty_offers_when_connection_inactive(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $connection = SupplierConnection::query()->where('provider', SupplierProvider::Duffel)->firstOrFail();
        $adapter = app()->make(DuffelFlightSupplierAdapter::class);

        $result = $adapter->search(
            FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => now()->addDays(10)->toDateString()]),
            $connection
        );

        $this->assertSame(SupplierProvider::Duffel, $result->supplier_provider);
        $this->assertSame([], $result->offers);
        $this->assertNotSame([], $result->warnings);
    }

    public function test_sabre_adapter_returns_warning_when_credentials_missing(): void
    {
        Http::fake();
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
        ]);

        $adapter = app()->make(SabreFlightSupplierAdapter::class);
        $result = $adapter->search(
            FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => now()->addDays(10)->toDateString()]),
            $connection
        );

        $this->assertSame([], $result->offers);
        $this->assertSame(['Sabre credentials are not configured.'], $result->warnings);
        Http::assertNothingSent();
    }

    public function test_supplier_adapter_resolver_returns_correct_adapter(): void
    {
        $resolver = app()->make(SupplierAdapterResolver::class);

        $this->assertInstanceOf(
            DuffelFlightSupplierAdapter::class,
            $resolver->resolve(SupplierProvider::Duffel)
        );
        $this->assertInstanceOf(
            SabreFlightSupplierAdapter::class,
            $resolver->resolve(SupplierProvider::Sabre)
        );
    }

    public function test_flight_search_service_searches_only_active_supplier_connections(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);

        SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $depart = now()->addDays(10)->toDateString();
        $normalized = PublicCheckoutTestDoubles::validatedNormalizedOffer($depart, 'LHE', 'DXB');

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock) use ($normalized): void {
            $mock->shouldReceive('search')->andReturn(new FlightSearchResultData(
                SupplierProvider::Duffel,
                [$normalized],
                [],
                [],
            ));
        });

        $offers = app()->make(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
        ], $agency, 'public_guest');

        $this->assertNotEmpty($offers);
        $this->assertTrue(collect($offers)->contains(fn (array $offer): bool => ($offer['supplier_provider'] ?? '') === SupplierProvider::Duffel->value));
    }

    public function test_inactive_supplier_connections_are_skipped(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);

        $offers = app()->make(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
        ], $agency, 'public_guest');

        $this->assertSame([], $offers);
    }

    public function test_flight_search_service_returns_normalized_offers_with_pricing_applied(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $depart = now()->addDays(10)->toDateString();
        $normalized = PublicCheckoutTestDoubles::validatedNormalizedOffer($depart, 'LHE', 'DXB');

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock) use ($normalized): void {
            $mock->shouldReceive('search')->andReturn(new FlightSearchResultData(
                SupplierProvider::Duffel,
                [$normalized],
                [],
                [],
            ));
        });

        $offer = app()->make(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
        ], $agency, 'public_guest')[0];

        $this->assertArrayHasKey('final_customer_price', $offer);
        $this->assertArrayHasKey('applied_rules', $offer);
        $this->assertGreaterThan(0, (float) $offer['final_customer_price']);
    }

    public function test_public_results_page_still_works(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->get('/flights/results?from=LHE&to=DXB&depart='.now()->addDays(10)->toDateString().'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk();
    }

    public function test_agent_create_booking_page_still_works(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agentUser)->get('/agent/bookings/create')
            ->assertOk();
    }

    public function test_booking_created_from_selected_offer_stores_normalized_offer_snapshot(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, $depart);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'title' => 'Mr',
                'first_name' => 'Snapshot',
                'last_name' => 'Test',
                'email' => 'snapshot@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $booking = Booking::query()->firstOrFail();
        $meta = $booking->meta ?? [];

        $this->assertArrayHasKey('normalized_offer_snapshot', $meta);
        $this->assertArrayHasKey('supplier_provider', $meta);
        $this->assertArrayHasKey('pricing_snapshot', $meta);
        $this->assertArrayHasKey('applied_rules', $meta);
    }

    public function test_no_credentials_appear_in_offer_snapshots(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', SupplierProvider::Duffel)->update([
            'credentials' => ['access_token' => 'SHOULD_NOT_APPEAR'],
        ]);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'title' => 'Mr',
                'first_name' => 'Secret',
                'last_name' => 'Check',
                'email' => 'secret@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $booking = Booking::query()->firstOrFail();
        $snapshotJson = json_encode($booking->meta['normalized_offer_snapshot'] ?? []);

        $this->assertIsString($snapshotJson);
        $this->assertStringNotContainsString('SHOULD_NOT_APPEAR', $snapshotJson);
        $this->assertStringNotContainsString('api_key', $snapshotJson);
    }
}
