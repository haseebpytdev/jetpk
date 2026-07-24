<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 17B: prove public review → runPublicReviewDryRun → createBooking chain (no live HTTP).
 */
class SabreGdsPublicCreatePnrCanonicalPathPhase17BTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_run_public_review_dry_run_invokes_create_booking_entrypoint_in_source(): void
    {
        $source = (string) file_get_contents(app_path('Services/Suppliers/Sabre/Booking/SabreBookingService.php'));
        $this->assertStringContainsString('public function runPublicReviewDryRun', $source);
        $this->assertStringContainsString('$this->createBooking(', $source);
    }

    public function test_may_perform_live_sabre_booking_call_requires_both_booking_flags(): void
    {
        $svc = app(SabreBookingService::class);

        Config::set(['suppliers.sabre.booking_enabled' => false, 'suppliers.sabre.booking_live_call_enabled' => false]);
        $this->assertFalse($svc->mayPerformLiveSabreBookingCall());

        Config::set(['suppliers.sabre.booking_enabled' => true, 'suppliers.sabre.booking_live_call_enabled' => false]);
        $this->assertFalse($svc->mayPerformLiveSabreBookingCall());

        Config::set(['suppliers.sabre.booking_enabled' => true, 'suppliers.sabre.booking_live_call_enabled' => true]);
        $this->assertTrue($svc->mayPerformLiveSabreBookingCall());
    }

    public function test_create_booking_with_live_disabled_does_not_dispatch_http(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $connection = SupplierConnection::query()->where('provider', SupplierProvider::Sabre)->first()
            ?? SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->minimalSabreDraftBooking($agency, (int) $connection->id);

        $offer = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot']
            : $booking->meta['flight_offer_snapshot'];

        $result = app(SabreBookingService::class)->createBooking(
            $offer,
            app(SabreBookingService::class)->passengerDataFromBookingForCommand($booking),
            $booking->id,
        );

        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertCount(0, Http::recorded());
    }

    public function test_agent_and_guest_share_booking_review_post_route(): void
    {
        $route = Route::getRoutes()->getByName('booking.review');
        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertSame(
            \App\Http\Controllers\Frontend\BookingController::class.'@review',
            $route->getAction('uses'),
        );
    }

    public function test_booking_review_controller_calls_run_public_review_dry_run_for_sabre(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));
        $this->assertStringContainsString('runPublicReviewDryRun', $source);
        $this->assertStringContainsString("SupplierProvider::Sabre->value", $source);
    }

    protected function minimalSabreDraftBooking(Agency $agency, int $connectionId): Booking
    {
        $depart = now()->addDays(14)->toDateString();
        $offer = [
            'offer_id' => 'fixture-offer-17b',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $connectionId,
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T12:00:00Z',
            'currency' => 'PKR',
            'total' => 110000,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T08:00:00Z',
                    'arrival_at' => $depart.'T12:00:00Z',
                    'airline_code' => 'PK',
                    'flight_number' => '301',
                    'booking_class' => 'Y',
                ],
            ],
            'fare_breakdown' => [
                'total' => 110000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
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
