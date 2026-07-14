<?php

namespace Tests\Feature\Reports;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Services\Reports\BookingReportService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BookingReportCreatedAtQualificationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_booking_report_with_date_filter_and_fare_join_does_not_throw(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Confirmed,
            'payment_status' => 'paid',
            'created_at' => Carbon::parse('2026-06-10 12:00:00'),
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 10000,
            'taxes' => 1500,
            'fees' => 500,
            'markup' => 800,
            'discount' => 0,
            'total' => 12800,
            'currency' => 'PKR',
        ]);

        $admin = $this->platformAdmin();
        $request = Request::create('/admin/reports', 'GET', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]);

        $report = app(BookingReportService::class)->build($admin, $request);

        $this->assertSame(12800.0, (float) $report['summary']['gross_sales']);
        $this->assertSame(1, (int) $report['summary']['total_bookings']);
    }

    public function test_pnr_manual_review_digest_uses_qualified_created_at(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'supplier_booking_status' => 'failed',
            'created_at' => Carbon::parse('2026-06-12 09:00:00'),
        ]);

        $start = Carbon::parse('2026-06-01 00:00:00');
        $end = Carbon::parse('2026-06-30 23:59:59');

        $summary = app(BookingReportService::class)->buildPnrManualReviewDigestSummary(
            $agency,
            $start,
            $end,
        );

        $this->assertSame(1, $summary['total_bookings']);
        $this->assertSame(1, $summary['supplier_failed_count']);
    }
}
