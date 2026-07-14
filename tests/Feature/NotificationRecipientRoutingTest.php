<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\OtaNotificationEvent;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencySetting;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\User;
use App\Services\Communication\NotificationPayloadSanitizer;
use App\Services\Communication\NotificationRecipientResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class NotificationRecipientRoutingTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    private NotificationRecipientResolver $resolver;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->platformAdmin = $this->platformAdmin();
        $this->resolver = app(NotificationRecipientResolver::class);
    }

    public function test_customer_booking_request_includes_admin_recipients(): void
    {
        [$agency, $booking] = $this->customerBooking();

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::BookingRequestReceived->value,
            $booking,
        );

        $this->assertContains(strtolower($this->platformAdmin->email), $resolved['to']);
        $this->assertNotContains('traveler@example.test', $resolved['to']);
    }

    public function test_agent_booking_request_includes_agent_and_admin(): void
    {
        [$agency, $booking, $agentUser] = $this->agentBooking();

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::BookingRequestReceived->value,
            $booking,
        );

        $this->assertContains(strtolower($this->platformAdmin->email), $resolved['to']);
        $this->assertContains(strtolower($agentUser->email), $resolved['to']);
    }

    public function test_agent_deposit_submitted_routes_to_finance_and_admin(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true, 'meta' => ['finance_email' => 'finance@example.test']],
        );

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::AgentDepositSubmitted->value,
        );

        $this->assertContains('finance@example.test', $resolved['to']);
        $this->assertContains(strtolower($this->platformAdmin->email), $resolved['to']);
    }

    public function test_inactive_platform_admin_excluded_from_admin_recipients(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $inactive = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
            'email' => 'inactive.platform@ota.demo',
            'status' => UserAccountStatus::Suspended,
        ]);
        $agency->users()->attach($inactive->id, ['role' => 'platform_admin']);

        [$agency, $booking] = $this->customerBooking();

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::BookingRequestReceived->value,
            $booking,
        );

        $this->assertNotContains(strtolower($inactive->email), $resolved['to']);
        $this->assertContains(strtolower($this->platformAdmin->email), $resolved['to']);
    }

    public function test_payment_proof_routes_to_finance_admin_and_assigned_staff(): void
    {
        [$agency, $booking, $staff] = $this->bookingWithAssignedStaff();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'email_enabled' => true,
                'meta' => ['finance_email' => 'finance@example.test'],
            ],
        );

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::PaymentProofSubmitted->value,
            $booking->fresh(['assignedStaff']),
        );

        $this->assertContains('finance@example.test', $resolved['to']);
        $this->assertContains(strtolower($this->platformAdmin->email), $resolved['to']);
        $this->assertContains(strtolower($staff->email), $resolved['to']);
    }

    public function test_pnr_sync_failed_excludes_customer(): void
    {
        [$agency, $booking] = $this->customerBooking();

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::PnrItinerarySyncFailed->value,
            $booking,
        );

        $this->assertContains(strtolower($this->platformAdmin->email), $resolved['to']);
        $this->assertNotContains('traveler@example.test', $resolved['to']);
    }

    public function test_recipients_are_deduplicated(): void
    {
        [$agency, $booking] = $this->customerBooking();
        $agency->agencySetting?->update(['support_email' => 'admin@ota.demo']);

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::PaymentProofSubmitted->value,
            $booking,
        );

        $this->assertSame(
            count($resolved['to']),
            count(array_unique($resolved['to'])),
        );
    }

    public function test_admin_login_success_routes_only_to_logged_in_user(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $otherAdmin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
            'email' => 'other.platform@example.test',
        ]);
        $agency->users()->attach($otherAdmin->id, ['role' => 'platform_admin']);
        $actor = $this->platformAdmin;

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::AdminLoginSuccess->value,
            null,
            $actor,
        );

        $this->assertSame([strtolower($actor->email)], $resolved['to']);
        $this->assertNotContains('other.platform@example.test', $resolved['to']);
    }

    public function test_staff_login_success_routes_only_to_logged_in_user(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Staff,
            'email' => 'staff.login@example.test',
        ]);

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::StaffLoginSuccess->value,
            null,
            $staff,
        );

        $this->assertSame(['staff.login@example.test'], $resolved['to']);
        $this->assertNotContains(strtolower($this->platformAdmin->email), $resolved['to']);
    }

    public function test_agent_login_success_routes_only_to_logged_in_user(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agentUser = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Agent,
            'email' => 'agent.login@example.test',
        ]);

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::AgentLoginSuccess->value,
            null,
            $agentUser,
        );

        $this->assertSame(['agent.login@example.test'], $resolved['to']);
        $this->assertNotContains(strtolower($this->platformAdmin->email), $resolved['to']);
    }

    public function test_login_failed_sensitive_routes_to_admin_not_logged_in_user(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $otherAdmin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
            'email' => 'security.admin@example.test',
        ]);
        $agency->users()->attach($otherAdmin->id, ['role' => 'platform_admin']);
        $actor = $this->platformAdmin;

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::LoginFailedSensitive->value,
            null,
            $actor,
        );

        $this->assertContains(strtolower($this->platformAdmin->email), $resolved['to']);
        $this->assertContains('security.admin@example.test', $resolved['to']);
        $this->assertCount(2, $resolved['to']);
    }

    public function test_customer_login_success_routes_only_to_logged_in_user(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Customer,
            'email' => 'customer.login@example.test',
            'email_verified_at' => now(),
        ]);

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::CustomerLoginSuccess->value,
            null,
            $customer,
        );

        $this->assertSame(['customer.login@example.test'], $resolved['to']);
    }

    public function test_login_failed_alert_routes_only_to_logged_in_user(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Staff,
            'email' => 'staff.failed@example.test',
        ]);

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::LoginFailedAlert->value,
            null,
            $staff,
        );

        $this->assertSame(['staff.failed@example.test'], $resolved['to']);
        $this->assertNotContains(strtolower($this->platformAdmin->email), $resolved['to']);
    }

    public function test_support_email_fallback_when_no_platform_admin_attached(): void
    {
        $this->platformAdmin->forceFill(['account_type' => AccountType::AgencyAdmin])->save();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['support_email' => 'ops-only@example.test'],
        );

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::BookingManualReviewRequired->value,
        );

        $this->assertContains('ops-only@example.test', $resolved['to']);
        $this->assertNotContains('admin@ota.demo', $resolved['to']);
    }

    public function test_customer_scope_payload_strips_internal_supplier_fields(): void
    {
        $sanitizer = app(NotificationPayloadSanitizer::class);
        $payload = $sanitizer->sanitizeForScope([
            'booking_reference' => 'ASIF-1',
            'supplier_error' => 'raw sabre failure',
            'passport_number' => 'AB1234567',
            'token' => 'secret',
        ], 'customer');

        $this->assertArrayNotHasKey('supplier_error', $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertStringContainsString('*', (string) ($payload['passport_number'] ?? ''));
    }

    /**
     * @return array{0: Agency, 1: Booking}
     */
    private function customerBooking(): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create(['current_agency_id' => $agency->id]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Draft,
        ]);
        $booking->contact()->create([
            'email' => 'traveler@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
            'meta' => ['name' => 'Traveler'],
        ]);

        return [$agency, $booking->fresh(['contact', 'customer'])];
    }

    /**
     * @return array{0: Agency, 1: Booking, 2: User}
     */
    private function agentBooking(): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agentUser = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Agent,
            'email' => 'agent.booking@example.test',
        ]);
        $agent = Agent::query()->create([
            'user_id' => $agentUser->id,
            'agency_id' => $agency->id,
            'code' => 'AGT-TEST01',
            'commission_percent' => 0,
            'is_active' => true,
        ]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::Draft,
        ]);

        return [$agency, $booking->fresh(['agent.user']), $agentUser];
    }

    /**
     * @return array{0: Agency, 1: Booking, 2: User}
     */
    private function bookingWithAssignedStaff(): array
    {
        [$agency, $booking] = $this->customerBooking();
        $staff = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Staff,
            'email' => 'staff.assignee@example.test',
        ]);
        $booking->forceFill(['assigned_staff_id' => $staff->id])->save();

        return [$agency, $booking->fresh(['assignedStaff', 'contact', 'customer']), $staff];
    }
}
