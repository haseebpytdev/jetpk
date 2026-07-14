<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtaBackendFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_agency(): void
    {
        $agency = Agency::factory()->create([
            'name' => 'Test Airways',
            'slug' => 'test-airways',
        ]);

        $this->assertDatabaseHas('agencies', [
            'id' => $agency->id,
            'slug' => 'test-airways',
        ]);
    }

    public function test_booking_belongs_to_agency(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->for($agency)->create();

        $this->assertTrue($booking->agency->is($agency));
        $this->assertSame($agency->id, $booking->agency_id);
    }

    public function test_supplier_connections_belong_to_agency(): void
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->for($agency)->create();

        $this->assertTrue($connection->agency->is($agency));
    }

    public function test_can_create_booking_with_passenger_contact_and_fare(): void
    {
        $agency = Agency::factory()->create();
        $customer = User::factory()->create();

        $service = app(BookingService::class);
        $booking = $service->createDraftBooking($agency, $customer);

        $service->attachPassenger($booking, [
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
        ]);

        $service->attachContact($booking, [
            'email' => 'ali@example.com',
            'phone' => '+923001112233',
            'country' => 'PK',
        ]);

        $service->attachFareBreakdown($booking, [
            'base_fare' => 100_000,
            'taxes' => 18_500,
            'fees' => 2500,
            'markup' => 5000,
            'discount' => 0,
            'total' => 126_000,
            'currency' => 'PKR',
            'breakdown' => [
                ['label' => 'YQ', 'amount' => 8500],
            ],
        ]);

        $booking->refresh();

        $this->assertSame(BookingStatus::Draft, $booking->status);
        $this->assertCount(1, $booking->passengers);
        $this->assertNotNull($booking->contact);
        $this->assertNotNull($booking->fareBreakdown);
        $this->assertSame('126000.00', $booking->fareBreakdown->total);
    }

    public function test_can_change_booking_status_and_create_status_log(): void
    {
        $agency = Agency::factory()->create();
        $actor = User::factory()->create();

        $service = app(BookingService::class);
        $booking = $service->createDraftBooking($agency);

        $booking = $service->submitBookingRequest($booking, $actor);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNotNull($booking->booking_reference);

        $service->changeStatus($booking, BookingStatus::FareReview, $actor, 'Manual fare review');

        $booking->refresh();

        $this->assertSame(BookingStatus::FareReview, $booking->status);

        $this->assertDatabaseHas('booking_status_logs', [
            'booking_id' => $booking->id,
            'to_status' => BookingStatus::FareReview->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'agency_id' => $agency->id,
            'action' => 'booking.status_changed',
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
        ]);
    }
}
