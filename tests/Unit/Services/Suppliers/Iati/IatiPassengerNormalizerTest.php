<?php

namespace Tests\Unit\Services\Suppliers\Iati;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Iati\Exceptions\IatiValidationException;
use App\Services\Suppliers\Iati\IatiBookingService;
use App\Services\Suppliers\Iati\IatiPassengerNormalizer;
use App\Services\Suppliers\Iati\IatiPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiPassengerNormalizerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_maps_booking_passenger_columns_to_iati_supplier_fields(): void
    {
        $passenger = $this->passenger([
            'passenger_type' => 'adult',
            'title' => 'Mr',
            'first_name' => 'Haseev',
            'last_name' => 'Asif',
            'date_of_birth' => '1999-12-19',
            'nationality' => 'PK',
            'gender' => 'M',
            'passport_number' => 'AB1234567',
            'passport_issue_date' => '2020-10-10',
            'passport_expiry_date' => '2030-10-10',
        ]);

        $normalized = app(IatiPassengerNormalizer::class)->normalize($passenger);

        $this->assertSame('ADULT', $normalized['type']);
        $this->assertSame('Mr', $normalized['title']);
        $this->assertSame('Haseev', $normalized['given_name']);
        $this->assertSame('Asif', $normalized['surname']);
        $this->assertSame('M', $normalized['gender']);
        $this->assertSame('1999-12-19', $normalized['date_of_birth']);
        $this->assertSame('PK', $normalized['nationality']);
        $this->assertTrue($normalized['passport_number_present']);
        $this->assertSame('2020-10-10', $normalized['passport_issue_date']);
        $this->assertSame('2030-10-10', $normalized['passport_expiry_date']);
    }

    #[Test]
    public function test_complete_passenger_passes_readiness(): void
    {
        $booking = $this->bookingWithPassenger($this->completePassengerAttributes());

        $normalizer = app(IatiPassengerNormalizer::class);

        $this->assertTrue($normalizer->isBookingComplete($booking));
        $this->assertSame([], $normalizer->missingSupplierFieldsForBooking($booking));
    }

    #[Test]
    public function test_missing_first_name_reports_passengers_zero_given_name(): void
    {
        $booking = $this->bookingWithPassenger($this->completePassengerAttributes([
            'first_name' => '',
        ]));

        $missing = app(IatiPassengerNormalizer::class)->missingSupplierFieldsForBooking($booking);

        $this->assertContains('passengers.0.given_name', $missing);
    }

    #[Test]
    public function test_missing_last_name_reports_passengers_zero_surname(): void
    {
        $booking = $this->bookingWithPassenger($this->completePassengerAttributes([
            'last_name' => '',
        ]));

        $missing = app(IatiPassengerNormalizer::class)->missingSupplierFieldsForBooking($booking);

        $this->assertContains('passengers.0.surname', $missing);
    }

    #[Test]
    public function test_missing_passenger_type_reports_passengers_zero_type(): void
    {
        $booking = $this->bookingWithPassenger($this->completePassengerAttributes([
            'passenger_type' => '',
        ]));

        $missing = app(IatiPassengerNormalizer::class)->missingSupplierFieldsForBooking($booking);

        $this->assertContains('passengers.0.type', $missing);
    }

    #[Test]
    public function test_payload_builder_uses_normalized_first_and_last_names(): void
    {
        $booking = $this->bookingWithPassenger($this->completePassengerAttributes([
            'first_name' => 'Haseev',
            'last_name' => 'Asif',
            'passenger_type' => 'adult',
        ]));

        $rows = app(IatiPayloadBuilder::class)->buildPassengersFromBooking($booking);

        $this->assertSame('Haseev', $rows[0]['name']);
        $this->assertSame('Asif', $rows[0]['lastname']);
        $this->assertSame('ADULT', $rows[0]['type']);
        $this->assertSame('MALE', $rows[0]['gender']);
    }

    #[Test]
    public function test_validation_exception_safe_summary_never_includes_raw_passport_number(): void
    {
        $passenger = $this->passenger($this->completePassengerAttributes([
            'first_name' => '',
            'passport_number' => 'SECRET-PASSPORT-123',
        ]));

        try {
            app(IatiPassengerNormalizer::class)->assertBookingPassengersReady($passenger->booking);
            $this->fail('Expected IatiValidationException');
        } catch (IatiValidationException $exception) {
            $encoded = json_encode($exception->context);
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString('SECRET-PASSPORT-123', $encoded);
            $this->assertSame('iati_passenger_payload_incomplete', $exception->normalizedCode);
            $this->assertSame('passenger_payload_validation', $exception->context['step'] ?? null);
            $this->assertContains('passengers.0.given_name', $exception->context['missing_fields'] ?? []);
            $this->assertArrayHasKey('passenger_id', $exception->context);
            $this->assertArrayHasKey('passenger_index', $exception->context);
        }
    }

    #[Test]
    public function test_supplier_booking_does_not_call_iati_when_passenger_payload_incomplete(): void
    {
        [$booking, $connection, $actor] = $this->iatiBookingFixture();
        $booking->passengers()->update(['first_name' => '']);

        Http::fake();

        $result = app(IatiBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact', 'supplierBookings']),
            $connection,
            $actor,
        );

        $this->assertFalse($result->success);
        $this->assertSame('iati_passenger_payload_incomplete', $result->error_code);
        $this->assertSame('passenger_payload_validation', $result->safe_summary['step'] ?? null);
        $this->assertContains('passengers.0.given_name', $result->safe_summary['missing_fields'] ?? []);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function completePassengerAttributes(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Haseev',
            'last_name' => 'Asif',
            'passenger_type' => 'adult',
            'title' => 'Mr',
            'gender' => 'M',
            'date_of_birth' => '1999-12-19',
            'nationality' => 'PK',
            'passport_number' => 'AB1234567',
            'passport_issue_date' => '2020-10-10',
            'passport_expiry_date' => '2030-10-10',
            'passenger_index' => 0,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function bookingWithPassenger(array $attributes): Booking
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'status' => BookingStatus::Pending,
            'payment_status' => 'paid',
            'meta' => ['supplier_provider' => SupplierProvider::Iati->value],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
        ], $attributes));

        return $booking->fresh('passengers');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function passenger(array $attributes): BookingPassenger
    {
        return $this->bookingWithPassenger($attributes)->passengers->first();
    }

    /**
     * @return array{0: Booking, 1: SupplierConnection, 2: User}
     */
    protected function iatiBookingFixture(): array
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

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'supplier_connection_id' => $connection->id,
                'offer_validation_status' => 'valid',
                'iati_context' => [
                    'departure_fare_key' => 'dep-key',
                    'fare_detail_key' => 'fare-detail-key',
                ],
                'validated_offer_snapshot' => [
                    'offer_id' => 'offer-1',
                    'raw_payload' => [
                        'provider_context' => [
                            'departure_fare_key' => 'dep-key',
                            'fare_detail_key' => 'fare-detail-key',
                        ],
                    ],
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
        ], $this->completePassengerAttributes()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'pax@example.com',
            'phone' => '3001234567',
            'phone_country_code' => '92',
        ]);

        return [$booking->fresh(['passengers', 'contact', 'supplierBookings']), $connection, $actor];
    }
}
