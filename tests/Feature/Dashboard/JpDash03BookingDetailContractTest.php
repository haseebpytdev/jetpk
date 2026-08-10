<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JP-DASH-03 checkpoint 11 — booking detail API contract (fixture-backed Sabre shape).
 */
class JpDash03BookingDetailContractTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_booking_detail_api_matches_list_row_for_sabre_reference(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $booking = Booking::factory()->for($agency)->create([
            'booking_reference' => 'WL96PKN9',
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => 624,
            'currency' => 'USD',
            'base_fare' => 500,
            'tax' => 124,
        ]);

        $list = $this->actingAs($admin)
            ->getJson(route('api.dashboard.bookings.index', ['q' => 'WL96PKN9']))
            ->assertOk();

        $listRow = collect($list->json('data.bookings') ?? [])->first();
        $this->assertNotNull($listRow);
        $this->assertNotEmpty($listRow['id'] ?? null);

        $detail = $this->actingAs($admin)
            ->getJson(route('api.dashboard.bookings.show', ['booking' => 'WL96PKN9']))
            ->assertOk()
            ->json('data');

        $summary = $detail['summary'] ?? [];
        $fare = $detail['fareSummary'] ?? [];

        $this->assertNotEmpty($summary['id'] ?? null);
        $this->assertSame('USD', $fare['currency'] ?? null);
        $this->assertSame('USD', $listRow['currency'] ?? null);
        $this->assertSame(624, (int) ($fare['total'] ?? 0));
    }
}
