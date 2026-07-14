<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['total_bookings'] === 3
                    && $stats['pending_bookings'] === 1
                    && $stats['ticketed_bookings'] === 2
                    && $stats['unpaid_partial_bookings'] === 2
                    && (int) $stats['gross_sales'] === 240000;
            });
    }

    public function test_admin_dashboard_does_not_include_another_agency_bookings(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();
        $otherAgency = Agency::factory()->create();

        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($otherAgency, BookingStatus::Pending, 'unpaid', null, 150_000, 'duffel');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['total_bookings'] === 2);
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

        $this->actingAs($platform)
            ->get('/admin')
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['total_bookings'] === 2);
    }

    public function test_reports_summary_uses_db_totals(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();

        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 120_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 180_000, 'sabre');

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertViewHas('summary', function (array $summary): bool {
                return $summary['total_bookings'] === 2
                    && $summary['pending_bookings'] === 1
                    && $summary['ticketed_bookings'] === 1
                    && (int) $summary['gross_sales'] === 300000;
            });
    }

    public function test_reports_filter_by_date_range(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();

        $old = $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 90_000, 'duffel');
        $new = $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 110_000, 'duffel');
        $old->forceFill(['created_at' => now()->subMonths(3)])->save();
        $new->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->actingAs($admin)
            ->get('/admin/reports?date_from='.now()->subWeek()->toDateString())
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total_bookings'] === 1);
    }

    public function test_reports_filter_by_channel_direct_and_agent(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();
        $agent = Agent::factory()->for($agency)->create();

        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', $agent, 200_000, 'duffel');

        $this->actingAs($admin)
            ->get('/admin/reports?channel=direct')
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total_bookings'] === 1);

        $this->actingAs($admin)
            ->get('/admin/reports?channel=agent')
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total_bookings'] === 1);
    }

    public function test_reports_filter_by_supplier(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();

        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'sabre');

        $this->actingAs($admin)
            ->get('/admin/reports?supplier=sabre')
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total_bookings'] === 1);
    }

    public function test_reports_empty_state_works_when_no_bookings_exist(): void
    {
        [, $admin] = $this->makePlatformAdmin();

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertViewHas('hasLiveData', false)
            ->assertSee('No live booking data yet', false);
    }

    public function test_platform_admin_can_access_reports(): void
    {
        $platform = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        $agency = Agency::factory()->create();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel', '');

        $this->actingAs($platform)
            ->get('/admin/reports')
            ->assertOk()
            ->assertViewHas('topRoutes', function ($routes): bool {
                return collect($routes)->contains(fn (array $row): bool => $row['route'] === 'Unknown route');
            });
    }

    public function test_reports_route_grouping_renders_unknown_route_for_blank_route(): void
    {
        [$agency, $admin] = $this->makePlatformAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel', '');

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertViewHas('topRoutes', fn ($routes): bool => collect($routes)->contains(
                fn (array $row): bool => $row['route'] === 'Unknown route' && $row['bookings'] === 1
            ));
    }

    public function test_guest_cannot_access_dashboard_or_reports(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
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
