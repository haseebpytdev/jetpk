<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Services\Dashboard\AgencyDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminGrossSalesAggregationPhase17DTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_gross_sales_excludes_cancelled_bookings(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $cancelled = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Cancelled,
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $cancelled->id,
            'total' => 150_000,
            'currency' => 'PKR',
        ]);

        $pending = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $pending->id,
            'total' => 50_000,
            'currency' => 'PKR',
        ]);

        $data = app(AgencyDashboardService::class)->build($admin);
        $stats = $data['stats'] ?? [];

        $this->assertSame(50_000.0, (float) ($stats['gross_sales'] ?? -1));
        $this->assertSame(150_000.0, (float) ($stats['cancelled_booking_value'] ?? -1));
    }

    public function test_orphan_pending_booking_counts_toward_gross_sales_when_not_cancelled(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $orphan = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'pnr' => null,
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $orphan->id,
            'total' => 88_602,
            'currency' => 'PKR',
        ]);

        $data = app(AgencyDashboardService::class)->build($admin);
        $this->assertSame(88_602.0, (float) ($data['stats']['gross_sales'] ?? 0));
    }

    public function test_production_shape_three_bookings_gross_sales_is_pending_only(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        foreach ([150_000, 88_602, 120_000] as $i => $total) {
            $status = $i === 1 ? BookingStatus::Pending : BookingStatus::Cancelled;
            $booking = Booking::factory()->create([
                'agency_id' => $agency->id,
                'status' => $status,
                'cancellation_status' => $status === BookingStatus::Cancelled ? 'cancelled' : null,
            ]);
            BookingFareBreakdown::query()->create([
                'booking_id' => $booking->id,
                'total' => $total,
                'currency' => 'PKR',
            ]);
        }

        $stats = app(AgencyDashboardService::class)->build($admin)['stats'];
        $this->assertSame(88_602.0, (float) $stats['gross_sales']);
        $this->assertSame(270_000.0, (float) $stats['cancelled_booking_value']);
    }
}
