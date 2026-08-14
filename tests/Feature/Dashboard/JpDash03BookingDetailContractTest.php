<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\BookingNote;
use App\Models\BookingStatusLog;
use App\Models\CommunicationLog;
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
        $this->assertNull($fare['currency'] ?? null);
        $this->assertSame('unresolved', $fare['currencyStatus'] ?? null);
        $this->assertSame('Amount unavailable', $fare['totalMoney']['displayLabel'] ?? null);
        $this->assertSame('USD 624.00', $fare['totalMoney']['originalAmountLabel'] ?? null);
        $this->assertSame('', $listRow['currency'] ?? null);
        $this->assertSame(0, (int) ($fare['total'] ?? 0));
    }

    public function test_booking_detail_includes_timeline_notes_and_communications(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $booking = Booking::factory()->for($agency)->create([
            'booking_reference' => 'BK-TIMELINE-01',
            'status' => BookingStatus::Confirmed,
        ]);

        BookingStatusLog::query()->create([
            'booking_id' => $booking->id,
            'from_status' => 'pending',
            'to_status' => 'confirmed',
            'user_id' => $admin->id,
            'note' => 'Payment verified',
        ]);

        BookingNote::query()->create([
            'booking_id' => $booking->id,
            'agency_id' => $agency->id,
            'user_id' => $admin->id,
            'note_type' => 'internal',
            'note' => 'Follow up with customer',
            'is_customer_visible' => false,
        ]);

        CommunicationLog::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'user_id' => $admin->id,
            'channel' => 'email',
            'event' => 'booking_confirmation',
            'recipient_email' => 'guest@example.com',
            'status' => 'sent',
            'subject' => 'Booking confirmed',
        ]);

        $detail = $this->actingAs($admin)
            ->getJson(route('api.dashboard.bookings.show', ['booking' => 'BK-TIMELINE-01']))
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $detail['statusTimeline'] ?? []);
        $this->assertSame('pending → confirmed', $detail['statusTimeline'][0]['summary'] ?? null);
        $this->assertCount(1, $detail['internalNotes'] ?? []);
        $this->assertSame('Follow up with customer', $detail['internalNotes'][0]['note'] ?? null);
        $this->assertCount(1, $detail['communications'] ?? []);
        $this->assertSame('booking_confirmation', $detail['communications'][0]['event'] ?? null);
        $this->assertArrayHasKey('documents', $detail);
    }
}
