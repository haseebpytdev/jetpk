<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\BookingStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\User;
use App\Support\Agencies\AgencyRolePermissionMatrix;
use App\Support\AgentPortal\AgentPortalBookingDetailPresenter;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * OPS-DEF-0004: agent.bookings.view alone must not authorize cancellation requests.
 */
class OpsDef0004ViewerCancellationAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_viewer_staff_with_bookings_view_only_cannot_request_cancellation(): void
    {
        [, $agent, $booking] = $this->ownedBookingFixture();
        $viewer = $this->createStaffForAgent($agent, 'ops4-viewer@agency.test', [
            AgentPermission::BookingsView,
            AgentPermission::AgencyView,
        ]);

        $this->assertFalse(Gate::forUser($viewer)->allows('request', [BookingCancellationRequest::class, $booking]));

        $beforeCount = BookingCancellationRequest::query()->where('booking_id', $booking->id)->count();
        $beforeStatus = $booking->fresh()->cancellation_status;
        $beforeAudits = AuditLog::query()->where('action', 'like', '%cancellation%')->count();

        $this->actingAs($viewer)
            ->post(route('agent.bookings.cancellations.store', ['booking' => $booking->booking_reference]), [
                'cancellation_type' => 'booking_cancel',
                'reason' => 'viewer attempt',
            ])
            ->assertForbidden();

        $this->assertSame($beforeCount, BookingCancellationRequest::query()->where('booking_id', $booking->id)->count());
        $this->assertSame($beforeStatus, $booking->fresh()->cancellation_status);
        $this->assertSame($beforeAudits, AuditLog::query()->where('action', 'like', '%cancellation%')->count());
    }

    public function test_authorized_staff_with_cancellations_request_can_request(): void
    {
        [, $agent, $booking] = $this->ownedBookingFixture();
        $staff = $this->createStaffForAgent($agent, 'ops4-cancel-staff@agency.test', [
            AgentPermission::BookingsView,
            AgentPermission::CancellationsRequest,
        ]);

        $this->assertTrue(Gate::forUser($staff)->allows('request', [BookingCancellationRequest::class, $booking]));

        $this->actingAs($staff)
            ->post(route('agent.bookings.cancellations.store', ['booking' => $booking->booking_reference]), [
                'cancellation_type' => 'booking_cancel',
                'reason' => 'authorized staff request',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('booking_cancellation_requests', [
            'booking_id' => $booking->id,
            'status' => 'requested',
        ]);
        $this->assertSame('requested', $booking->fresh()->cancellation_status);
    }

    public function test_cancellations_request_without_bookings_view_is_denied(): void
    {
        [, $agent, $booking] = $this->ownedBookingFixture();
        $staff = $this->createStaffForAgent($agent, 'ops4-mutation-only@agency.test', [
            AgentPermission::CancellationsRequest,
        ]);

        $this->assertFalse(Gate::forUser($staff)->allows('request', [BookingCancellationRequest::class, $booking]));
    }

    public function test_owner_can_still_request_cancellation_for_own_booking(): void
    {
        [$owner, , $booking] = $this->ownedBookingFixture();

        $this->assertTrue(Gate::forUser($owner)->allows('request', [BookingCancellationRequest::class, $booking]));

        $this->actingAs($owner)
            ->post(route('agent.bookings.cancellations.store', ['booking' => $booking->booking_reference]), [
                'cancellation_type' => 'booking_cancel',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('booking_cancellation_requests', [
            'booking_id' => $booking->id,
        ]);
    }

    public function test_cross_agency_staff_denied_even_with_cancellations_request(): void
    {
        [, $agent] = $this->ownedBookingFixture();
        $staff = $this->createStaffForAgent($agent, 'ops4-cross@agency.test', [
            AgentPermission::BookingsView,
            AgentPermission::CancellationsRequest,
        ]);

        $otherAgency = Agency::factory()->create();
        $foreignBooking = Booking::factory()->for($otherAgency)->create([
            'status' => BookingStatus::Confirmed,
            'booking_reference' => 'OPS4-XAG-'.fake()->unique()->numberBetween(1000, 9999),
            'agent_id' => $agent->id,
        ]);

        $this->assertFalse(Gate::forUser($staff)->allows('request', [BookingCancellationRequest::class, $foreignBooking]));
        $this->actingAs($staff)
            ->post(route('agent.bookings.cancellations.store', ['booking' => $foreignBooking->booking_reference]), [
                'cancellation_type' => 'booking_cancel',
            ])
            ->assertForbidden();
    }

    public function test_wrong_agent_booking_denied_within_same_agency(): void
    {
        [$ownerA, $agentA] = $this->ownedBookingFixture();
        $agencyId = (int) $ownerA->current_agency_id;

        $otherAgentUser = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agencyId,
            'status' => UserAccountStatus::Active,
            'email_verified_at' => now(),
        ]);
        $agentB = Agent::factory()->create([
            'agency_id' => $agencyId,
            'user_id' => $otherAgentUser->id,
        ]);

        $bookingB = Booking::factory()->create([
            'agency_id' => $agencyId,
            'agent_id' => $agentB->id,
            'status' => BookingStatus::Confirmed,
            'booking_reference' => 'OPS4-WRONG-'.fake()->unique()->numberBetween(1000, 9999),
        ]);

        $staffA = $this->createStaffForAgent($agentA, 'ops4-wrong-agent@agency.test', [
            AgentPermission::BookingsView,
            AgentPermission::CancellationsRequest,
        ]);

        $this->assertFalse(Gate::forUser($staffA)->allows('request', [BookingCancellationRequest::class, $bookingB]));
    }

    public function test_presenter_hides_request_cancellation_for_viewer(): void
    {
        [, $agent, $booking] = $this->ownedBookingFixture();
        $viewer = $this->createStaffForAgent($agent, 'ops4-ui-viewer@agency.test', [
            AgentPermission::BookingsView,
            AgentPermission::AgencyView,
        ]);

        $actions = $this->presentAgentActionsFor($booking, $viewer);
        $action = collect($actions)->firstWhere('code', 'request_cancellation');
        $this->assertNull($action, 'Viewer must not see request_cancellation action');
    }

    public function test_presenter_shows_request_cancellation_for_authorized_staff(): void
    {
        [, $agent, $booking] = $this->ownedBookingFixture();
        $staff = $this->createStaffForAgent($agent, 'ops4-ui-staff@agency.test', [
            AgentPermission::BookingsView,
            AgentPermission::CancellationsRequest,
        ]);

        $actions = $this->presentAgentActionsFor($booking, $staff);
        $action = collect($actions)->firstWhere('code', 'request_cancellation');
        $this->assertNotNull($action);
        $this->assertTrue((bool) ($action['available'] ?? false));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function presentAgentActionsFor(Booking $booking, User $user): array
    {
        $booking->loadMissing(['cancellationRequests', 'documents']);
        $presenter = app(AgentPortalBookingDetailPresenter::class);
        $method = new \ReflectionMethod($presenter, 'presentAgentActions');
        $method->setAccessible(true);

        /** @var list<array<string, mixed>> $actions */
        $actions = $method->invoke($presenter, $booking, $user);

        return $actions;
    }

    public function test_role_templates_grant_cancellations_request_conservatively(): void
    {
        $this->assertContains(AgentPermission::CancellationsRequest, AgencyRolePermissionMatrix::suggestedPermissions(AgencyRole::Manager));
        $this->assertContains(AgentPermission::CancellationsRequest, AgencyRolePermissionMatrix::suggestedPermissions(AgencyRole::SupportStaff));
        $this->assertContains(AgentPermission::CancellationsRequest, AgencyRolePermissionMatrix::suggestedPermissions(AgencyRole::SalesAgent));
        $this->assertContains(AgentPermission::CancellationsRequest, AgencyRolePermissionMatrix::suggestedPermissions(AgencyRole::Owner));

        $this->assertNotContains(AgentPermission::CancellationsRequest, AgencyRolePermissionMatrix::suggestedPermissions(AgencyRole::Viewer));
        $this->assertNotContains(AgentPermission::CancellationsRequest, AgencyRolePermissionMatrix::suggestedPermissions(AgencyRole::Accountant));
        $this->assertNotContains(AgentPermission::CancellationsRequest, AgencyRolePermissionMatrix::suggestedPermissions(AgencyRole::TicketingStaff));

        $this->assertContains(AgentPermission::CancellationsRequest, AgentPermission::staffSelectable());
        $this->assertSame('Request booking cancellations', AgentPermission::labels()[AgentPermission::CancellationsRequest]);
    }

    /**
     * @return array{0: User, 1: Agent, 2: Booking}
     */
    protected function ownedBookingFixture(): array
    {
        $owner = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $owner->id)->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $owner->current_agency_id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::Confirmed,
            'booking_reference' => 'OPS4-'.fake()->unique()->numberBetween(10000, 99999),
            'cancellation_status' => 'none',
        ]);

        return [$owner, $agent, $booking];
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function createStaffForAgent(Agent $agent, string $email, array $permissions = []): User
    {
        $staff = User::query()->create([
            'name' => 'OPS4 Staff',
            'username' => str_replace(['@', '.'], '-', $email),
            'email' => $email,
            'password' => bcrypt('password'),
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agent->agency_id,
            'email_verified_at' => now(),
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => $permissions,
            ],
        ]);

        AgencyUser::query()->updateOrCreate(
            ['agency_id' => $agent->agency_id, 'user_id' => $staff->id],
            ['role' => AccountType::AgentStaff->value],
        );

        return $staff;
    }
}
