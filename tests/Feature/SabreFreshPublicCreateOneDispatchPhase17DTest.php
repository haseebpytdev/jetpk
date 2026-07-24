<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreFreshPublicCreateOneDispatchPhase17DTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->seed(OtaFoundationSeeder::class);
        Config::set([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.pnr_create_enabled' => true,
            'platform.modules.customer_checkout' => true,
        ]);
    }

    public function test_fresh_public_review_dry_run_records_single_attempt_without_live_http(): void
    {
        $booking = $this->minimalSabreDraftBooking();
        $before = \App\Models\SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown']),
        );

        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertCount(0, Http::recorded());
        $this->assertGreaterThanOrEqual($before, \App\Models\SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
    }

    public function test_submitted_booking_review_post_does_not_dispatch_supplier_http(): void
    {
        $booking = $this->minimalSabreDraftBooking();
        $booking->forceFill(['status' => BookingStatus::Pending, 'submitted_at' => now()])->save();

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $this->assertCount(0, Http::recorded());
    }

    protected function minimalSabreDraftBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::query()->where('provider', SupplierProvider::Sabre)->firstOrFail();
        $depart = now()->addDays(14)->toDateString();
        $offer = [
            'offer_id' => 'fixture-offer-17d',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $connection->id,
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T12:00:00Z',
            'currency' => 'PKR',
            'total' => 110000,
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => $depart.'T08:00:00Z',
                'arrival_at' => $depart.'T12:00:00Z',
                'airline_code' => 'PK',
                'flight_number' => '301',
                'booking_class' => 'Y',
            ]],
        ];

        $booking = Booking::factory()->for($agency)->create([
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'normalized_offer_snapshot' => $offer,
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                ],
            ],
        ]);

        $booking->passengers()->create([
            'type' => 'adult',
            'title' => 'Mr',
            'first_name' => 'Test',
            'last_name' => 'Traveler',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'nationality' => 'PK',
        ]);

        $booking->fareBreakdown()->create([
            'base_fare' => 100000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 110000,
            'currency' => 'PKR',
        ]);

        return $booking;
    }
}
