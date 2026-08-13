<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BookingStatus;
use App\Http\Resources\Dashboard\DashboardOverviewResource;
use App\Http\Resources\Dashboard\DashboardPaymentResource;
use App\Http\Resources\Dashboard\DashboardReportResource;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPayment;
use App\Services\Dashboard\AgencyDashboardService;
use App\Services\Reports\BookingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JP-DASH-03 checkpoint 11 — payment/report/KPI currency contracts (fixture-backed).
 */
class JpDash03MoneyContractTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_mixed_currency_report_flags_multiple_fare_currencies(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $usd = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $usd->id, 'total' => 624, 'currency' => 'USD']);

        $pkr = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $pkr->id, 'total' => 50_000, 'currency' => 'PKR']);

        $payload = app(BookingReportService::class)->build($admin, Request::create('/admin/reports'));

        $this->assertSame(2, (int) ($payload['summary']['fare_currency_count'] ?? 0));

        $reportView = DashboardReportResource::fromBookingReport('bookings', $payload, 'PKR');
        $warnings = $reportView['warnings'] ?? [];
        $this->assertNotEmpty($warnings);
        $grossMetric = collect($reportView['metrics'] ?? [])->firstWhere('key', 'gross_booking_value');
        $this->assertNotNull($grossMetric);
        $this->assertSame('Multiple currencies', $grossMetric['formattedValue']);
    }

    public function test_usd_only_report_does_not_relabel_as_pkr(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $usd = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $usd->id, 'total' => 590, 'currency' => 'USD']);

        $payload = app(BookingReportService::class)->build($admin, Request::create('/admin/reports'));
        $this->assertSame('USD', $payload['summary']['fare_currency_iso'] ?? null);

        $reportView = DashboardReportResource::fromBookingReport('bookings', $payload, (string) $payload['summary']['fare_currency_iso']);
        $grossMetric = collect($reportView['metrics'] ?? [])->firstWhere('key', 'gross_booking_value');
        $this->assertStringContainsString('USD', (string) ($grossMetric['formattedValue'] ?? ''));
        $this->assertStringNotContainsString('Rs.', (string) ($grossMetric['formattedValue'] ?? ''));
    }

    public function test_kpi_usd_only_bookings_show_pkr_zero_and_exclusion(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $usd = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $usd->id, 'total' => 590, 'currency' => 'USD']);

        $dashboard = app(AgencyDashboardService::class)->build($admin);
        $overview = DashboardOverviewResource::fromAgencyDashboard($dashboard, $admin);

        $grossCard = collect($overview['summaryStats'] ?? [])->firstWhere('key', 'gross_sales');
        $this->assertNotNull($grossCard);
        $this->assertStringContainsString('Rs.', (string) ($grossCard['value'] ?? ''));
        $this->assertStringContainsString('0.00', (string) ($grossCard['value'] ?? ''));
        $this->assertStringContainsString('excluded', (string) ($grossCard['delta'] ?? ''));
        $this->assertStringNotContainsString('USD', (string) ($grossCard['value'] ?? ''));
    }

    public function test_kpi_gross_sales_multi_currency_label_not_blended_pkr_total(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $usd = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $usd->id, 'total' => 100, 'currency' => 'USD']);

        $pkr = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create(['booking_id' => $pkr->id, 'total' => 200, 'currency' => 'PKR']);

        $dashboard = app(AgencyDashboardService::class)->build($admin);
        $overview = DashboardOverviewResource::fromAgencyDashboard($dashboard, $admin);

        $grossCard = collect($overview['summaryStats'] ?? [])->firstWhere('key', 'gross_sales');
        $this->assertNotNull($grossCard);
        $this->assertStringContainsString('Rs.', (string) ($grossCard['value'] ?? ''));
        $this->assertStringContainsString('200', (string) ($grossCard['value'] ?? ''));
        $this->assertStringNotContainsString('300', (string) ($grossCard['value'] ?? ''));
        $this->assertStringNotContainsString('100', (string) ($grossCard['value'] ?? ''));
        $this->assertStringContainsString('Non-PKR', (string) ($grossCard['delta'] ?? ''));
    }

    public function test_payment_without_currency_uses_fare_over_stale_booking_pkr(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => 624,
            'currency' => 'USD',
        ]);

        $payment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'amount' => 624,
            'currency' => '',
            'status' => 'verified',
            'method' => 'bank_transfer',
        ]);

        $payment->load('booking.fareBreakdown');
        $row = DashboardPaymentResource::fromModel($payment);

        $this->assertSame('USD', $row['currency'] ?? null);
        $this->assertStringContainsString('USD', (string) ($row['paidMoney']['displayLabel'] ?? ''));
    }

    public function test_payment_with_explicit_currency_uses_payment_currency_field(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => 624,
            'currency' => 'USD',
        ]);

        $payment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'amount' => 624,
            'currency' => 'PKR',
            'status' => 'verified',
            'method' => 'bank_transfer',
        ]);

        $payment->load('booking.fareBreakdown');
        $row = DashboardPaymentResource::fromModel($payment);

        $this->assertSame('PKR', $row['currency'] ?? null);
        $this->assertSame('payment.currency', $row['currencySource'] ?? null);
    }

    public function test_payment_currency_contract_when_payment_currency_explicit(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'currency' => 'USD',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => 500,
            'currency' => 'USD',
        ]);

        $payment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'amount' => 500,
            'currency' => 'USD',
            'status' => 'verified',
            'method' => 'cash',
        ]);

        $payment->load('booking.fareBreakdown');
        $row = DashboardPaymentResource::fromModel($payment);

        $this->assertSame('USD', $row['currency']);
        $this->assertSame('resolved', $row['paidMoney']['currencyStatus'] ?? null);
    }

    public function test_payment_resource_includes_review_capabilities_for_viewer(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'currency' => 'PKR',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => 50_000,
            'currency' => 'PKR',
        ]);

        $payment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'amount' => 50_000,
            'currency' => 'PKR',
            'status' => 'submitted',
            'method' => 'bank_transfer',
        ]);

        $payment->load('booking.fareBreakdown');
        $row = DashboardPaymentResource::fromModel($payment, $admin);

        $this->assertArrayHasKey('capabilities', $row);
        $this->assertTrue($row['capabilities']['can_verify'] ?? false);
        $this->assertTrue($row['capabilities']['can_reject'] ?? false);
        $this->assertFalse($row['capabilities']['already_processed'] ?? true);
        $this->assertSame((string) $payment->id, $row['laravelPaymentId'] ?? null);
    }

    public function test_payment_resource_hides_capabilities_without_viewer(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'currency' => 'PKR',
        ]);
        $payment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'amount' => 10_000,
            'currency' => 'PKR',
            'status' => 'submitted',
            'method' => 'cash',
        ]);

        $row = DashboardPaymentResource::fromModel($payment);

        $this->assertNull($row['capabilities'] ?? null);
    }
}
