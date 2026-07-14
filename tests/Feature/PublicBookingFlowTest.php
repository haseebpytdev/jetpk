<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class PublicBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_passenger_post_creates_draft_booking_in_database(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'guest.tester@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $this->assertDatabaseHas('bookings', [
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
            'payment_status' => 'unpaid',
        ]);

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame(BookingStatus::Draft, $booking->status);
        $this->assertSame($booking->id, session(PublicBooking::SESSION_BOOKING_ID));
    }

    public function test_review_page_loads_database_booking_without_auth(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'first_name' => 'Review',
                'last_name' => 'Reader',
                'email' => 'review@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $this->get(route('booking.review'))
            ->assertOk()
            ->assertSee('Review your booking', false)
            ->assertSee('Review', false);
    }

    public function test_review_confirm_submits_booking_and_sets_pending_reference(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'first_name' => 'Confirm',
                'last_name' => 'Flow',
                'email' => 'confirm@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $this->post('/booking/review', [
            'booking_method' => 'bank_transfer',
        ])->assertRedirect(route('booking.confirmation'));

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertNotNull($booking->booking_reference);
        $this->assertStringStartsWith('OTA-', $booking->booking_reference);
    }

    public function test_confirmation_page_shows_booking_reference_without_auth(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'title' => 'Ms',
                'first_name' => 'Show',
                'last_name' => 'Ref',
                'email' => 'showref@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));
        $this->post('/booking/review', ['booking_method' => 'pay_later']);

        $booking = Booking::query()->firstOrFail();

        $this->get(route('booking.confirmation'))
            ->assertOk()
            ->assertSee($booking->booking_reference, false)
            ->assertDontSee('AB9988776', false);
    }

    public function test_review_redirects_when_session_booking_missing(): void
    {
        $this->get(route('booking.review'))
            ->assertRedirect(route('flights.search'));
    }

    public function test_confirmation_redirects_when_session_booking_missing(): void
    {
        $this->get(route('booking.confirmation'))
            ->assertRedirect(route('flights.search'));
    }

    public function test_agency_admin_sees_database_booking_on_admin_bookings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'first_name' => 'Admin',
                'last_name' => 'Visible',
                'email' => 'adminvis@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));
        $this->post('/booking/review', ['booking_method' => 'pay_later']);

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/admin/bookings')->assertOk()->assertSee('Admin Visible', false);
    }
}
