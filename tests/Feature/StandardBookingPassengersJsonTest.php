<?php

namespace Tests\Feature;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class StandardBookingPassengersJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_passengers_get_json_returns_authoritative_context(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $response = $this->getJson('/booking/passengers?'.http_build_query([
            'format' => 'json',
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'search_id' => 'test-search-1',
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('booking_session.status', 'passenger_details')
            ->assertJsonPath('travellers.adults', 1)
            ->assertJsonPath('travellers.total', 1)
            ->assertJsonStructure([
                'booking_session' => ['id', 'progress', 'server_time'],
                'selection' => ['search_id', 'offer_id'],
                'itinerary',
                'passenger_requirements',
                'contact_requirements',
                'document_requirements',
                'seat_extras_capability',
            ]);
    }

    public function test_passengers_json_preserves_selected_branded_fare_over_base_offer(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $presenter = app(\App\Support\Booking\StandardBookingJsonPresenter::class);
        $payload = $presenter->presentPassengersContext([
            'draft' => [
                'search_id' => 'search-branded-1',
                'fare_option_key' => 'economy-comfort',
                'selected_fare_family_option' => [
                    'option_key' => 'economy-comfort',
                    'name' => 'ECONOMY COMFORT',
                    'brand_name' => 'ECONOMY COMFORT',
                    'displayed_price' => 168478,
                    'displayed_currency' => 'PKR',
                    'price_display' => 'PKR 168,478',
                    'baggage_summary' => '30 kg',
                    'price_is_approximate' => false,
                    'is_price_approximate' => false,
                    'validation_note' => 'Estimated selected fare pending airline confirmation.',
                ],
                'search_from' => 'ISB',
                'search_to' => 'DXB',
                'search_depart' => now()->addWeek()->format('Y-m-d'),
                'trip_type' => 'one_way',
                'cabin' => 'economy',
            ],
            'flightId' => 'offer-base',
            // Base/validated offer deliberately still looks like BASIC / 0 kg.
            'offer' => [
                'airline_name' => 'Etihad Airways',
                'airline_code' => 'EY',
                'flight_number' => 'EY231',
                'fare_family' => 'ECONOMY BASIC',
                'baggage' => '0 kg',
                'currency' => 'PKR',
                'final_customer_price' => 150766,
                'stops' => 0,
                'segments' => [],
            ],
            'criteria' => [
                'origin' => 'ISB',
                'destination' => 'DXB',
                'depart_date' => now()->addWeek()->format('Y-m-d'),
                'trip_type' => 'one_way',
                'cabin' => 'economy',
            ],
            'checkoutPresentation' => [
                'route_label' => 'ISB → DXB',
                'duration_label' => '3h 30m',
                'segments' => [],
                'return_segments' => [],
            ],
            'checkoutFareBreakdown' => [
                'total_formatted' => 'PKR 150,766',
                'currency' => 'PKR',
            ],
            'passengerCountSummary' => ['adults' => 1, 'children' => 0, 'infants' => 0, 'total' => 1],
            'expectedPassengers' => [['index' => 0, 'type' => 'adult', 'label' => 'Adult']],
            'checkoutCountries' => [],
            'checkoutPhoneDialCodes' => [],
            'checkoutContactPrefill' => [],
            'checkoutContactPhone' => [],
        ], request());

        $this->assertTrue($payload['ok']);
        $this->assertSame('ECONOMY COMFORT', $payload['itinerary']['fare_family']);
        $this->assertSame('30 kg', $payload['itinerary']['baggage']);
        $this->assertSame('PKR 168,478', $payload['itinerary']['total_formatted']);
        $this->assertSame('economy-comfort', $payload['itinerary']['selected_fare_option_key']);
        $this->assertNotSame('ECONOMY BASIC', $payload['itinerary']['fare_family']);
        $this->assertNotSame('0 kg', $payload['itinerary']['baggage']);
        $this->assertIsArray($payload['selected_fare']);
        $this->assertSame('ECONOMY COMFORT', $payload['selected_fare']['fare_family']);
        $this->assertSame('30 kg', $payload['selected_fare']['checked_baggage']);
        $this->assertSame(168478, $payload['selected_fare']['customer_total']);
        $this->assertSame('economy-comfort', $payload['selected_fare']['fare_option_key']);
        $this->assertSame('ECONOMY COMFORT', $payload['itinerary']['selected_fare']['fare_family']);
    }

    public function test_passengers_get_json_missing_session_returns_404(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->getJson('/booking/passengers?format=json')
            ->assertStatus(404)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'missing_session');
    }

    public function test_passengers_post_json_returns_next_url_on_success(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $payload = array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'search_id' => 'test-search-1',
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'passengers' => [[
                    'passenger_type' => 'adult',
                    'title' => 'Mr',
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'date_of_birth' => '1990-05-10',
                    'nationality' => 'PK',
                    'gender' => 'male',
                    'document_type' => 'passport',
                    'passport_number' => 'AB9988776',
                    'passport_issuing_country' => 'PK',
                    'passport_expiry_date' => now()->addYears(7)->format('Y-m-d'),
                    'passport_issue_date' => '2018-01-15',
                ]],
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        );

        $this->postJson('/booking/passengers', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('next_url', route('booking.review', absolute: false));
    }
}
