<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class Wave9ReviewEditPassengersIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function passengersPayload(string $lastName): array
    {
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        return array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'first_name' => 'Haseeb',
                'last_name' => $lastName,
                'email' => 'wave9-edit@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        );
    }

    public function test_edit_travelers_from_review_reuses_booking_and_updates_surname(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', $this->passengersPayload('Asif'))
            ->assertRedirect(route('booking.review'));

        $bookingId = session(PublicBooking::SESSION_BOOKING_ID);
        $this->assertNotNull($bookingId);
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, BookingPassenger::query()->count());

        // Simulate Review → Edit (draft cleared after first passengers POST).
        app(\App\Services\Booking\BookingDraftService::class)->clear();

        $this->getJson('/booking/passengers?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('existing_values.passengers.0.last_name', 'Asif');

        $this->post('/booking/passengers', $this->passengersPayload('Khan'))
            ->assertRedirect(route('booking.review'));

        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, BookingPassenger::query()->count());
        $this->assertSame((int) $bookingId, (int) session(PublicBooking::SESSION_BOOKING_ID));
        $this->assertSame('Khan', BookingPassenger::query()->value('last_name'));

        $this->getJson('/booking/review?format=json')
            ->assertOk()
            ->assertJsonPath('passengers.0.last_name', 'Khan');
    }

    public function test_edit_same_passenger_twice_still_one_row(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', $this->passengersPayload('One'))
            ->assertRedirect(route('booking.review'));
        app(\App\Services\Booking\BookingDraftService::class)->clear();
        $this->post('/booking/passengers', $this->passengersPayload('Two'))
            ->assertRedirect(route('booking.review'));
        app(\App\Services\Booking\BookingDraftService::class)->clear();
        $this->post('/booking/passengers', $this->passengersPayload('Three'))
            ->assertRedirect(route('booking.review'));

        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, BookingPassenger::query()->count());
        $this->assertSame('Three', BookingPassenger::query()->value('last_name'));
    }
}
