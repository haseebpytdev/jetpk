<?php

namespace Tests\Unit\Services\Communication;

use App\Models\Agency;
use App\Models\Booking;
use App\Services\Communication\AdminReportMailerService;
use App\Services\Communication\BookingEmailPayloadFactory;
use App\Services\Communication\OtaNotificationService;
use App\Services\Reports\BookingReportService;
use App\Support\Bookings\QaFixtureBookingScope;
use Carbon\CarbonImmutable;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminReportMailerQaMoneyExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_gross_sales_excludes_qa_fixture_amount_paid(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $now = CarbonImmutable::now($agency->timezone ?? config('app.timezone'));

        Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'amount_paid' => 10000,
            'meta' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'local_qa_inert',
            'amount_paid' => 99999,
            'meta' => ['qa_fixture' => true],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $capturedPayload = null;
        $notifications = Mockery::mock(OtaNotificationService::class);
        $notifications->shouldReceive('send')
            ->once()
            ->andReturnUsing(function () use (&$capturedPayload): void {
                $args = func_get_args();
                foreach ($args as $arg) {
                    if (is_array($arg) && array_key_exists('gross_sales', $arg)) {
                        $capturedPayload = $arg;
                    }
                }
            });

        $service = new AdminReportMailerService(
            $notifications,
            app(BookingReportService::class),
            app(BookingEmailPayloadFactory::class),
        );

        $service->sendWeeklyReport($agency, $now);

        $this->assertIsArray($capturedPayload);
        $this->assertSame(10000.0, (float) $capturedPayload['gross_sales']);
        $this->assertStringContainsString(
            'QaFixtureBookingScope::excludeFromOperationalMetrics',
            file_get_contents(base_path('app/Services/Communication/AdminReportMailerService.php'))
        );
    }

    public function test_daily_weekly_monthly_money_queries_exclude_qa(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $now = CarbonImmutable::now($agency->timezone ?? config('app.timezone'));

        Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'amount_paid' => 5000,
            'balance_due' => 100,
            'meta' => null,
            'created_at' => $now,
        ]);
        Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'local_qa_inert',
            'amount_paid' => 88888,
            'balance_due' => 77777,
            'meta' => ['qa_fixture' => true],
            'created_at' => $now,
        ]);

        $dailyGross = (float) QaFixtureBookingScope::excludeFromOperationalMetrics(
            Booking::query()->where('agency_id', $agency->id)
        )->sum('amount_paid');
        $weeklyGross = (float) QaFixtureBookingScope::excludeFromOperationalMetrics(
            Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$now->startOfWeek(), $now->endOfWeek()])
        )->sum('amount_paid');
        $monthlyGross = (float) QaFixtureBookingScope::excludeFromOperationalMetrics(
            Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$now->startOfMonth(), $now->endOfMonth()])
        )->sum('amount_paid');
        $unpaid = (float) QaFixtureBookingScope::excludeFromOperationalMetrics(
            Booking::query()->where('agency_id', $agency->id)
        )->sum('balance_due');

        $this->assertSame(5000.0, $dailyGross);
        $this->assertSame(5000.0, $weeklyGross);
        $this->assertSame(5000.0, $monthlyGross);
        $this->assertSame(100.0, $unpaid);
    }
}
