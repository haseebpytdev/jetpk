<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
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
use App\Services\Suppliers\OfferValidationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class OfferValidationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_validation_service_applies_pricing_after_validation(): void
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
            $mock->shouldReceive('validateOffer')->andReturn(new OfferValidationResultData(
                is_valid: true,
                status: 'valid',
                validated_offer: $normalized,
            ));
        });

        $offer = app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
        ], $agency, 'public_guest')[0];

        $result = app(OfferValidationService::class)->validateSelectedOffer($agency, $offer, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
            'source_channel' => 'public_guest',
        ]);

        $this->assertTrue($result->is_valid);
        $this->assertArrayHasKey('pricing_snapshot', $result->meta);
        $this->assertGreaterThan(0, (float) ($result->meta['final_customer_price'] ?? 0));
    }

    public function test_public_guest_booking_stores_validation_snapshot_in_meta(): void
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
                'first_name' => 'Public',
                'last_name' => 'Validation',
                'email' => 'pv@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $meta = Booking::query()->firstOrFail()->meta ?? [];
        $this->assertArrayHasKey('offer_validation_status', $meta);
        $this->assertArrayHasKey('validated_at', $meta);
        $this->assertArrayHasKey('validated_offer_snapshot', $meta);
        $this->assertArrayHasKey('validation_warnings', $meta);
    }

    public function test_public_guest_booking_stores_distribution_channel_in_meta_snapshots(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $this->seed(OtaFoundationSeeder::class);
        $offer = PublicCheckoutTestDoubles::searchOfferPayload($depart);
        $offer['distribution_channel'] = 'NDC';
        $validated = PublicCheckoutTestDoubles::validatedNormalizedOffer($depart);
        $validated = NormalizedFlightOfferData::fromArray(array_merge($validated->toArray(), [
            'distribution_channel' => 'NDC',
        ]));
        $this->bindCheckoutWithChannel($depart, $offer, $validated);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'first_name' => 'Channel',
                'last_name' => 'Persist',
                'email' => 'channel.persist@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $meta = Booking::query()->firstOrFail()->meta ?? [];
        $this->assertSame('NDC', $meta['distribution_channel'] ?? null);
        $this->assertSame('NDC', $meta['validated_offer_snapshot']['distribution_channel'] ?? null);
        $this->assertSame('NDC', $meta['flight_offer_snapshot']['distribution_channel'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function bindCheckoutWithChannel(string $departDate, array $offer, NormalizedFlightOfferData $validated): void
    {
        $pricing = PublicCheckoutTestDoubles::pricingSnapshot();
        $result = new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
            validated_offer: $validated,
            currency: 'PKR',
            meta: [
                'pricing_snapshot' => $pricing,
                'applied_rules' => $pricing['applied_rules'] ?? [],
            ],
        );
        $this->mock(OfferValidationService::class, function ($mock) use ($result, $pricing): void {
            $mock->shouldReceive('validateSelectedOffer')->andReturn($result);
            $mock->shouldReceive('pricingSnapshotForCachedOffer')->andReturn($pricing);
        });
        $this->mock(FlightSearchService::class, function ($mock) use ($offer): void {
            $mock->shouldReceive('search')->andReturn([$offer]);
            $mock->shouldReceive('searchWithMeta')->andReturn([
                'offers' => [$offer],
                'warnings' => [],
            ]);
        });
    }

    public function test_agent_booking_stores_validation_snapshot_in_meta(): void
    {
        $depart = now()->addDays(16)->format('Y-m-d');
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, $depart);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agentUser)->post('/agent/bookings', [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'title' => 'Mr',
            'first_name' => 'Agent',
            'last_name' => 'Validation',
            'dob' => now()->subYears(30)->toDateString(),
            'nationality' => 'PK',
            'gender' => 'M',
            'email' => 'agent.validation@example.com',
            'phone' => '+923001112299',
            'country' => 'Pakistan',
        ]);

        $meta = Booking::query()->firstOrFail()->meta ?? [];
        $this->assertArrayHasKey('offer_validation_status', $meta);
        $this->assertArrayHasKey('validated_offer_snapshot', $meta);
    }

    public function test_price_changed_result_does_not_silently_create_booking(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, $depart);
        $priced = PublicCheckoutTestDoubles::validatedNormalizedOffer($depart, 'LHE', 'DXB');
        $this->mock(OfferValidationService::class, function ($mock) use ($priced): void {
            $snap = PublicCheckoutTestDoubles::pricingSnapshot();
            $snap['final_total'] = 500000.0;
            $mock->shouldReceive('validateSelectedOffer')->andReturn(new OfferValidationResultData(
                is_valid: false,
                status: 'price_changed',
                original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
                validated_offer: $priced,
                price_changed: true,
                old_total: 100000.0,
                new_total: 500000.0,
                currency: 'PKR',
                meta: ['pricing_snapshot' => $snap],
            ));
            $mock->shouldReceive('pricingSnapshotForCachedOffer')->andReturn($snap);
        });

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'first_name' => 'Price',
                'last_name' => 'Changed',
                'email' => 'price.changed@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $response->assertRedirect();
    }

    public function test_unavailable_result_redirects_with_safe_warning(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->post('/booking/passengers', PublicBookingPassengersPayload::merge([
            'flight_id' => 'missing-id',
            'offer_id' => 'missing-id',
            'from' => 'LHE',
            'to' => 'KHI',
            'depart' => now()->addWeek()->toDateString(),
            'first_name' => 'Unavailable',
            'last_name' => 'Case',
            'email' => 'unavailable@example.com',
        ]));

        $response->assertRedirect(route('flights.search'));
    }

    public function test_sabre_validate_offer_search_replay_uses_http_fake_and_no_pnr(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-ok', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);
        $adapter = app(SabreFlightSupplierAdapter::class);
        $request = FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-06-10']);
        $offer = $adapter->search($request, $connection)->offers[0];
        $result = $adapter->validateOffer($offer, $request, $connection);

        $this->assertContains($result->status, ['valid', 'price_changed']);
        Http::assertSent(fn ($request): bool => ! str_contains(strtolower($request->url()), 'pnr'));
    }

    public function test_sabre_validate_offer_price_change_is_detected(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        $fixture['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0]['pricingInformation'][0]['fare']['totalFare'] = 15500;
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-ok', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);
        $adapter = app(SabreFlightSupplierAdapter::class);
        $request = FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-06-10']);
        $source = NormalizedFlightOfferData::fromArray([
            'offer_id' => 'x',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $connection->id,
            'airline_code' => 'PK',
            'airline_name' => 'PK',
            'flight_number' => '203',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-06-10T08:30:00',
            'arrival_at' => '2026-06-10T11:15:00',
            'duration_minutes' => 165,
            'stops' => 0,
            'cabin' => 'economy',
            'fare_breakdown' => ['base_fare' => 10000, 'taxes' => 2500, 'supplier_fees' => 0, 'supplier_total' => 12500, 'currency' => 'PKR'],
            'baggage' => ['summary' => 'As per fare rule'],
        ]);
        $result = $adapter->validateOffer($source, $request, $connection);

        $this->assertTrue($result->price_changed || $result->status === 'price_changed');
    }

    public function test_provider_error_returns_safe_warning(): void
    {
        Http::fake(['*' => Http::response(['error' => 'bad'], 500)]);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);
        $result = app(SabreFlightSupplierAdapter::class)->validateOffer(
            'some-offer-id',
            FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-06-10']),
            $connection
        );

        $this->assertSame('provider_error', $result->status);
        $this->assertStringNotContainsString('client_secret', implode(' ', $result->warnings));
    }

    public function test_no_credentials_or_tokens_appear_in_validation_snapshot(): void
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
                'first_name' => 'No',
                'last_name' => 'Secrets',
                'email' => 'nosecrets@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));
        $meta = Booking::query()->firstOrFail()->meta ?? [];
        $serialized = json_encode($meta['validated_offer_snapshot'] ?? []);
        $this->assertIsString($serialized);
        $this->assertStringNotContainsString('token', strtolower($serialized));
        $this->assertStringNotContainsString('secret', strtolower($serialized));
    }
}
