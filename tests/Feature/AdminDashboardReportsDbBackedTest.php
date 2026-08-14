<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Dashboard\AgencyDashboardService;
use App\Services\Reports\BookingReportService;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminDashboardReportsDbBackedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_db_booking_stats_for_current_agency(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();

        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 120_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 80_000, 'sabre');
        $this->createBooking($agency, BookingStatus::Ticketed, 'partial', null, 40_000, 'pia_ndc');

        $stats = $this->dashboardStats($admin);
        $this->assertSame(3, $stats['total_bookings']);
        $this->assertSame(1, $stats['pending_bookings']);
        $this->assertSame(2, $stats['ticketed_bookings']);
        $this->assertSame(2, $stats['unpaid_partial_bookings']);
        $this->assertSame(240000, (int) $stats['gross_sales']);
    }

    public function test_admin_dashboard_does_not_include_another_agency_bookings(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();
        $otherAgency = Agency::factory()->create();

        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($otherAgency, BookingStatus::Pending, 'unpaid', null, 150_000, 'duffel');

        $stats = $this->dashboardStats($admin);
        $this->assertSame(2, $stats['total_bookings']);
    }

    public function test_platform_admin_can_see_dashboard_metrics_across_agencies(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $platform = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->createBooking($agencyA, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($agencyB, BookingStatus::Ticketed, 'paid', null, 250_000, 'sabre');

        $stats = $this->dashboardStats($platform);
        $this->assertSame(2, $stats['total_bookings']);
    }

    public function test_reports_summary_uses_db_totals(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();

        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 120_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 180_000, 'sabre');

        $summary = $this->reportsPayload($admin)['summary'];
        $this->assertSame(2, $summary['total_bookings']);
        $this->assertSame(1, $summary['pending_bookings']);
        $this->assertSame(1, $summary['ticketed_bookings']);
        $this->assertSame(300000, (int) $summary['gross_sales']);
    }

    public function test_reports_filter_by_date_range(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();

        $old = $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 90_000, 'duffel');
        $new = $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 110_000, 'duffel');
        $old->forceFill(['created_at' => now()->subMonths(3)])->save();
        $new->forceFill(['created_at' => now()->subDays(2)])->save();

        $summary = $this->reportsPayload($admin, [
            'date_from' => now()->subWeek()->toDateString(),
        ])['summary'];
        $this->assertSame(1, $summary['total_bookings']);
    }

    public function test_reports_filter_by_channel_direct_and_agent(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();
        $agent = Agent::factory()->for($agency)->create();

        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', $agent, 200_000, 'duffel');

        $directSummary = $this->reportsPayload($admin, ['channel' => 'direct'])['summary'];
        $this->assertSame(1, $directSummary['total_bookings']);

        $agentSummary = $this->reportsPayload($admin, ['channel' => 'agent'])['summary'];
        $this->assertSame(1, $agentSummary['total_bookings']);
    }

    public function test_reports_filter_by_supplier(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();

        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'sabre');

        $summary = $this->reportsPayload($admin, ['supplier' => 'sabre'])['summary'];
        $this->assertSame(1, $summary['total_bookings']);
    }

    public function test_reports_empty_state_works_when_no_bookings_exist(): void
    {
        [, $admin] = $this->makePlatformAdmin();

        $payload = $this->reportsPayload($admin);
        $this->assertFalse($payload['hasLiveData']);
    }

    public function test_platform_admin_can_access_reports(): void
    {
        $platform = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        $agency = Agency::factory()->create();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel', '');

        $topRoutes = $this->reportsPayload($platform)['topRoutes'];
        $this->assertTrue(collect($topRoutes)->contains(
            fn (array $row): bool => $row['route'] === 'Unknown route'
        ));
    }

    public function test_reports_route_grouping_renders_unknown_route_for_blank_route(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel', '');

        $topRoutes = $this->reportsPayload($admin)['topRoutes'];
        $this->assertTrue(collect($topRoutes)->contains(
            fn (array $row): bool => $row['route'] === 'Unknown route' && $row['bookings'] === 1
        ));
    }

    public function test_guest_cannot_access_dashboard_or_reports(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/reports')->assertRedirect(route('login'));
    }

    public function test_staff_cannot_access_admin_reports(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->staff()->create([
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($staff->id, ['role' => AccountType::Staff->value]);

        $this->actingAs($staff)
            ->get('/admin/reports')
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    protected function dashboardStats(User $user): array
    {
        $this->actingAs($user);

        return app(AgencyDashboardService::class)->build($user)['stats'];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function reportsPayload(User $user, array $query = []): array
    {
        $this->actingAs($user);
        $request = Request::create('/admin/reports', 'GET', $query);
        $request->setUserResolver(fn () => $user);

        return app(BookingReportService::class)->build($user, $request);
    }

    /**
     * @return array{Agency, User}
     */
    protected function makePlatformAdmin(): array
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        return [$agency, $admin];
    }

    /** @deprecated use makePlatformAdmin() */
    protected function makeAgencyAdmin(): array
    {
        return $this->makePlatformAdmin();
    }

    protected function createBooking(
        Agency $agency,
        BookingStatus $status,
        string $paymentStatus,
        ?Agent $agent,
        int $total,
        string $supplier,
        ?string $route = 'LHE-DXB',
    ): Booking {
        $booking = Booking::factory()->for($agency)->create([
            'status' => $status,
            'payment_status' => $paymentStatus,
            'agent_id' => $agent?->id,
            'supplier' => $supplier,
            'route' => $route,
            'booking_reference' => 'REF-'.strtoupper(bin2hex(random_bytes(3))),
        ]);

        $booking->fareBreakdown()->create([
            'base_fare' => max(0, $total - 10000),
            'taxes' => 7000,
            'fees' => 1000,
            'markup' => 2000,
            'discount' => 0,
            'total' => $total,
            'currency' => 'PKR',
        ]);

        return $booking;
    }
}
