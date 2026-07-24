<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\BookingRefund;
use App\Services\Dashboard\AgencyDashboardService;
use App\Services\Reports\BookingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminGrossSalesSemanticsPhase17DTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_paid_and_ticketed_sales_exclude_cancelled_and_unpaid(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $cancelledPaid = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Cancelled,
            'payment_status' => 'paid',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $cancelledPaid->id, 'total' => 99_000, 'currency' => 'PKR']);

        $pendingUnpaid = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $pendingUnpaid->id, 'total' => 50_000, 'currency' => 'PKR']);

        $paidConfirmed = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $paidConfirmed->id, 'total' => 75_000, 'currency' => 'PKR']);

        $ticketed = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Ticketed,
            'payment_status' => 'paid',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $ticketed->id, 'total' => 120_000, 'currency' => 'PKR']);

        $stats = app(AgencyDashboardService::class)->build($admin)['stats'];

        $this->assertSame(245_000.0, (float) $stats['gross_sales']);
        $this->assertSame(195_000.0, (float) $stats['paid_sales']);
        $this->assertSame(120_000.0, (float) $stats['ticketed_sales']);
        $this->assertSame(99_000.0, (float) $stats['cancelled_booking_value']);
    }

    public function test_production_shape_bookings_one_to_three_paid_and_ticketed_sales_are_zero(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        foreach ([150_000, 88_602, 120_000] as $i => $total) {
            $status = $i === 1 ? BookingStatus::Pending : BookingStatus::Cancelled;
            $booking = Booking::factory()->create([
                'agency_id' => $agency->id,
                'status' => $status,
                'payment_status' => 'unpaid',
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
        $this->assertSame(0.0, (float) $stats['paid_sales']);
        $this->assertSame(0.0, (float) $stats['ticketed_sales']);
    }

    public function test_booking_report_gross_sales_excludes_cancelled(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $cancelled = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Cancelled,
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $cancelled->id, 'total' => 200_000, 'currency' => 'PKR']);

        $active = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $active->id, 'total' => 30_000, 'currency' => 'PKR']);

        $summary = app(BookingReportService::class)->build($admin, Request::create('/admin/reports'))['summary'];

        $this->assertSame(30_000.0, (float) $summary['gross_sales']);
        $this->assertSame(200_000.0, (float) ($summary['cancelled_booking_value'] ?? 0));
    }

    public function test_refund_paid_amount_tracks_approved_refunds_separately_from_gross_sales(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Ticketed,
            'payment_status' => 'paid',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $booking->id, 'total' => 80_000, 'currency' => 'PKR']);

        BookingRefund::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'amount' => 15_000,
            'currency' => 'PKR',
            'status' => 'paid',
            'method' => 'bank_transfer',
        ]);

        $stats = app(AgencyDashboardService::class)->build($admin)['stats'];
        $this->assertSame(80_000.0, (float) $stats['gross_sales']);
        $this->assertSame(15_000.0, (float) $stats['refund_amount_paid']);
    }
}
