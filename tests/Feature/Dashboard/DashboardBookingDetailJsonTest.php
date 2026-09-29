<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Http\Resources\Dashboard\DashboardBookingDetailResource;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBookingDetailJsonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_detail_resource_exposes_return_date_from_search_criteria(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'meta' => [
                'search_criteria' => [
                    'return_date' => '2026-11-20',
                    'trip_type' => 'round_trip',
                ],
            ],
        ]);

        $payload = DashboardBookingDetailResource::fromModel($booking->fresh());

        $this->assertSame('2026-11-20', $payload['summary']['returnDate']);
        $this->assertSame('2026-11-20', $payload['itinerary']['returnDate']);
        $this->assertSame('round_trip', $payload['summary']['tripType']);
    }

    public function test_detail_resource_return_date_null_when_absent(): void
    {
        $booking = Booking::factory()->create([
            'meta' => ['search_criteria' => ['trip_type' => 'one_way']],
        ]);

        $payload = DashboardBookingDetailResource::fromModel($booking->fresh());

        $this->assertNull($payload['summary']['returnDate']);
        $this->assertNull($payload['itinerary']['returnDate']);
        $this->assertSame('one_way', $payload['summary']['tripType']);
    }

    public function test_admin_can_fetch_booking_detail_api(): void
    {
        [$admin] = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'booking_reference' => 'JP-DETAIL-QA-1',
            'meta' => [
                'search_criteria' => [
                    'return_date' => '2026-12-01',
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/bookings/'.rawurlencode($booking->booking_reference))
            ->assertOk()
            ->assertJsonPath('data.summary.id', 'JP-DETAIL-QA-1')
            ->assertJsonPath('data.itinerary.returnDate', '2026-12-01')
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'itinerary' => ['route', 'airline', 'travelDate', 'returnDate'],
                    'passengers',
                    'fareSummary',
                    'paymentSummary',
                    'pnrSummary',
                    'ticketReadiness',
                    'auditMetadata',
                ],
            ]);
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
