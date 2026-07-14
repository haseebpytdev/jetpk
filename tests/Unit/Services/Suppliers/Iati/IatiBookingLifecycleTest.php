<?php

namespace Tests\Unit\Services\Suppliers\Iati;

use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Iati\IatiBookingService;
use App\Services\Suppliers\Iati\IatiFareRevalidationService;
use App\Services\Suppliers\Iati\IatiResponseNormalizer;
use App\Services\Suppliers\Iati\IatiSelectedOfferKeyResolver;
use App\Services\Suppliers\Iati\IatiTicketingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiBookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_selected_fare_two_with_three_offers_sends_only_index_one_key(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_total_match.json')), true);
        $fare = app(IatiResponseNormalizer::class)->normalizeFareResponse($fixture, [
            'selected_branded_fare_id' => 'iati_brand_1',
            'selected_fare_option_id' => 'iati-fare-2-85158-1',
        ]);

        $resolved = app(IatiSelectedOfferKeyResolver::class)->resolve($fare, $fare['provider_context'], [
            'selected_branded_fare_id' => 'iati_brand_1',
            'selected_fare_option_id' => 'iati-fare-2-85158-1',
        ]);

        $this->assertSame(1, $resolved['offer_index']);
        $this->assertSame('offer-total-1', $resolved['offer_key']);
        $this->assertCount(3, $fare['provider_context']['fare_offers']);
    }

    #[Test]
    public function test_paid_iati_booking_uses_book_endpoint_and_syncs_order(): void
    {
        [$booking, $connection, $actor] = $this->iatiBookingFixture(paid: true);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $offer = NormalizedFlightOfferData::fromArray($snapshot);
        $revalidation = \Mockery::mock(IatiFareRevalidationService::class);
        $revalidation->shouldReceive('revalidate')->once()->andReturn(new OfferValidationResultData(
            is_valid: true,
            status: 'same',
            price_changed: false,
            old_total: (float) $booking->selected_fare_total,
            new_total: (float) $booking->selected_fare_total,
            currency: 'PKR',
            validated_offer: $offer,
        ));
        $this->app->instance(IatiFareRevalidationService::class, $revalidation);

        Http::fake([
            'https://testapi.iati.com/rest/auth/token' => Http::response(['access_token' => 'token-abc'], 200),
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_total_match.json')),
                200,
            ),
            'https://testapi.iati.com/rest/flight/v2/book' => function ($request) {
                $payload = $request->data();
                $this->assertSame(['offer-total-1'], $payload['offers'] ?? null);

                return Http::response([
                    'result' => [
                        'books' => [[
                            'order_id' => 'order-paid-1',
                            'status' => 'BOOKED',
                        ]],
                    ],
                ], 200);
            },
            'https://testapi.iati.com/rest/flight/v2/order/order-paid-1' => Http::response(
                file_get_contents(base_path('tests/Fixtures/iati/order_retrieve_option_info_pnr.json')),
                200,
            ),
        ]);

        $result = app(IatiBookingService::class)->createSupplierBooking($booking, $connection, $actor);

        $this->assertTrue($result->success, (string) ($result->error_message ?: $result->error_code));
        $this->assertSame('created', $result->status);
        $booking->refresh();
        $this->assertSame('order-paid-1', $booking->supplier_reference);
        $this->assertSame('ABC123', $booking->pnr);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/book'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/order/order-paid-1'));
    }

    #[Test]
    public function test_option_va009_with_can_book_returns_direct_book_required(): void
    {
        [$booking, $connection, $actor] = $this->iatiBookingFixture(paid: false);
        $fareFixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_total_match.json')), true);
        $fareFixture['result']['offers'][1]['can_book'] = true;

        Http::fake([
            'https://testapi.iati.com/rest/auth/token' => Http::response(['access_token' => 'token-abc'], 200),
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response($fareFixture, 200),
            'https://testapi.iati.com/rest/flight/v2/option' => Http::response([
                'code' => 'VA009',
                'message' => 'Direct book required',
            ], 409),
        ]);

        $result = app(IatiBookingService::class)->createSupplierBooking($booking, $connection, $actor);

        $this->assertTrue($result->success, (string) ($result->error_message ?: $result->error_code));
        $this->assertSame('direct_book_required', $result->status);
        $booking->refresh();
        $this->assertSame('deferred_book', data_get($booking->meta, 'iati_context.mode'));
        $this->assertTrue((bool) data_get($booking->meta, 'iati_context.deferred_book_after_payment'));
        $this->assertSame('VA009', data_get($booking->meta, 'iati_context.deferred_error_code'));
        $supplierBooking = SupplierBooking::query()->where('booking_id', $booking->id)->first();
        $this->assertSame('direct_book_required', $supplierBooking?->status);
    }

    #[Test]
    public function test_deferred_book_without_order_id_can_post_book_from_ticketing_action(): void
    {
        [$booking, $connection, $actor] = $this->iatiBookingFixture(paid: true);
        $booking->update([
            'meta' => array_merge(is_array($booking->meta) ? $booking->meta : [], [
                'iati_context' => [
                    'mode' => 'deferred_book',
                    'fare_detail_key' => 'fare-detail-total-match',
                    'selected_offer_key' => 'offer-total-1',
                ],
            ]),
        ]);
        $supplierBooking = SupplierBooking::query()->create([
            'booking_id' => $booking->id,
            'agency_id' => $booking->agency_id,
            'provider' => SupplierProvider::Iati->value,
            'supplier_connection_id' => $connection->id,
            'status' => 'direct_book_required',
            'created_by' => $actor->id,
        ]);

        Http::fake([
            'https://testapi.iati.com/rest/auth/token' => Http::response(['access_token' => 'token-abc'], 200),
            'https://testapi.iati.com/rest/flight/v2/book' => function ($request) {
                $payload = $request->data();
                $this->assertSame(['offer-total-1'], $payload['offers'] ?? null);

                return Http::response([
                    'result' => [
                        'books' => [[
                            'order_id' => 'order-deferred-1',
                            'pnr' => 'DEF123',
                            'status' => 'BOOKED',
                        ]],
                    ],
                ], 200);
            },
            'https://testapi.iati.com/rest/flight/v2/order/order-deferred-1' => Http::response([
                'result' => [
                    'order_id' => 'order-deferred-1',
                    'status' => 'BOOKED',
                    'booking_info' => ['pnr' => 'DEF123'],
                ],
            ], 200),
        ]);

        $result = app(IatiTicketingService::class)->issueTickets($booking, $supplierBooking, $actor);

        $this->assertTrue($result->success);
        $booking->refresh();
        $this->assertSame('order-deferred-1', $booking->supplier_reference);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/book'));
    }

    #[Test]
    public function test_existing_order_id_uses_option_book_endpoint(): void
    {
        [$booking, $connection, $actor] = $this->iatiBookingFixture(paid: true);
        $booking->update([
            'supplier_reference' => 'existing-order-1',
            'meta' => array_merge(is_array($booking->meta) ? $booking->meta : [], [
                'iati_context' => ['order_id' => 'existing-order-1', 'mode' => 'option'],
            ]),
        ]);
        SupplierBooking::query()->create([
            'booking_id' => $booking->id,
            'agency_id' => $booking->agency_id,
            'provider' => SupplierProvider::Iati->value,
            'supplier_connection_id' => $connection->id,
            'supplier_reference' => 'existing-order-1',
            'supplier_api_booking_id' => 'existing-order-1',
            'status' => 'pending_ticketing',
            'created_by' => $actor->id,
        ]);

        Http::fake([
            'https://testapi.iati.com/rest/auth/token' => Http::response(['access_token' => 'token-abc'], 200),
            'https://testapi.iati.com/rest/flight/v2/fare' => Http::response(
                file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_total_match.json')),
                200,
            ),
            'https://testapi.iati.com/rest/flight/v2/option/existing-order-1/book' => Http::response([
                'result' => [
                    'books' => [[
                        'order_id' => 'existing-order-1',
                        'pnr' => 'OPT123',
                        'status' => 'BOOKED',
                    ]],
                ],
            ], 200),
            'https://testapi.iati.com/rest/flight/v2/order/existing-order-1' => Http::response([
                'result' => [
                    'order_id' => 'existing-order-1',
                    'status' => 'BOOKED',
                    'booking_info' => ['pnr' => 'OPT123'],
                ],
            ], 200),
        ]);

        $result = app(IatiBookingService::class)->createSupplierBooking($booking, $connection, $actor);

        $this->assertTrue($result->success, (string) ($result->error_message ?: $result->error_code));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/option/existing-order-1/book'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/flight/v2/fare'));
    }

    #[Test]
    public function test_duplicate_guard_blocks_when_order_id_exists(): void
    {
        [$booking, $connection, $actor] = $this->iatiBookingFixture(paid: true);
        $booking->update([
            'supplier_reference' => 'existing-order-2',
            'meta' => array_merge(is_array($booking->meta) ? $booking->meta : [], [
                'iati_context' => ['order_id' => 'existing-order-2', 'mode' => 'book'],
            ]),
        ]);
        SupplierBooking::query()->create([
            'booking_id' => $booking->id,
            'agency_id' => $booking->agency_id,
            'provider' => SupplierProvider::Iati->value,
            'supplier_connection_id' => $connection->id,
            'supplier_reference' => 'existing-order-2',
            'supplier_api_booking_id' => 'existing-order-2',
            'status' => 'created',
            'created_by' => $actor->id,
        ]);

        Http::fake();

        $result = app(IatiBookingService::class)->createSupplierBooking($booking, $connection, $actor);

        $this->assertFalse($result->success);
        $this->assertSame('duplicate_booking_guard', $result->error_code);
        Http::assertNothingSent();
    }

    #[Test]
    public function test_retrieve_extracts_pnr_from_option_info_and_legs(): void
    {
        $normalizer = app(IatiResponseNormalizer::class);

        $fromOptionInfo = $normalizer->normalizeRetrieveResponse(
            json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/order_retrieve_option_info_pnr.json')), true),
            [],
        );
        $this->assertSame('ABC123', $fromOptionInfo['pnr']);
        $this->assertSame('2026-07-10', $fromOptionInfo['last_ticketing_date']);

        $fromLegs = $normalizer->normalizeRetrieveResponse(
            json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/order_retrieve_legs_airline_pnr.json')), true),
            [],
        );
        $this->assertSame('LEGPNR1', $fromLegs['pnr']);
    }

    /**
     * @return array{0: Booking, 1: SupplierConnection, 2: User}
     */
    protected function iatiBookingFixture(bool $paid): array
    {
        $agency = Agency::factory()->create();
        $actor = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['auth_code' => 'test-code', 'secret' => 'test-secret'],
        ]);

        $fareFixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response_multi_offers_total_match.json')), true);
        $normalizedFare = app(IatiResponseNormalizer::class)->normalizeFareResponse($fareFixture, [
            'selected_branded_fare_id' => 'iati_brand_1',
            'selected_fare_option_id' => 'iati-fare-2-85158-1',
            'departure_fare_key' => 'dep-match-key',
        ]);
        $offerSnapshot = [
            'offer_id' => 'offer-58',
            'supplier_provider' => 'iati',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'airline_code' => 'PK',
            'fare_breakdown' => [
                'supplier_total' => (float) ($normalizedFare['total'] ?? 85158),
                'currency' => (string) ($normalizedFare['currency'] ?? 'PKR'),
            ],
            'raw_payload' => [
                'provider_context' => array_merge($normalizedFare['provider_context'], [
                    'departure_fare_key' => 'dep-match-key',
                    'fare_detail_key' => $normalizedFare['fare_detail_key'],
                    'selected_branded_fare_id' => 'iati_brand_1',
                    'selected_fare_option_id' => 'iati-fare-2-85158-1',
                ]),
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'selected_fare_total' => (float) ($normalizedFare['total'] ?? 85158),
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'supplier_connection_id' => $connection->id,
                'offer_validation_status' => 'valid',
                'requires_instant_payment' => $paid,
                'hold_supported' => ! $paid,
                'protection_mode' => $paid ? 'instant_payment_required' : 'hold_price_guaranteed',
                'supplier_currency' => 'PKR',
                'search_criteria' => ['origin' => 'LHE', 'destination' => 'DXB', 'adults' => 1],
                'selected_branded_fare_id' => 'iati_brand_1',
                'selected_fare_option_id' => 'iati-fare-2-85158-1',
                'validated_offer_snapshot' => $offerSnapshot,
                'iati_reservation' => [
                    'requires_instant_payment' => $paid,
                    'hold_supported' => ! $paid,
                    'protection_mode' => $paid ? 'instant_payment_required' : 'hold_price_guaranteed',
                    'local_checkout_expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ],
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'pax@example.com',
            'phone' => '3001234567',
            'phone_country_code' => '92',
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => '1990-01-01',
            'passport_number' => 'AB1234567',
            'passport_expiry_date' => '2030-01-01',
            'nationality' => 'PK',
            'passenger_type' => 'adult',
        ]);

        return [$booking, $connection, $actor];
    }
}
