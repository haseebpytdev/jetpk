<?php

namespace Tests\Unit\Policies;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BookingPolicyUpdateAndPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    private BookingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->policy = new BookingPolicy;
    }

    public function test_same_agency_staff_with_update_status_can_update(): void
    {
        [$booking, $staff] = $this->bookingAndStaff([StaffPermission::BookingsUpdateStatus]);

        $this->assertTrue($this->policy->update($staff, $booking));
        $this->assertTrue(Gate::forUser($staff)->allows('update', $booking));
    }

    public function test_read_only_support_staff_cannot_update(): void
    {
        [$booking, $staff] = $this->bookingAndStaff(StaffPermission::presetPermissions(StaffPermission::PresetSupport));

        $this->assertFalse($this->policy->update($staff, $booking));
    }

    public function test_cross_agency_staff_cannot_update(): void
    {
        [$booking] = $this->bookingAndStaff([StaffPermission::BookingsUpdateStatus]);
        $otherAgency = Agency::factory()->create();
        $staff = $this->staffWithPermissions(
            [StaffPermission::BookingsUpdateStatus],
            currentAgencyId: (int) $otherAgency->id,
        );

        $this->assertFalse($this->policy->update($staff, $booking));
    }

    public function test_customer_cannot_update_staff_managed_attributes(): void
    {
        [$booking] = $this->bookingAndStaff([StaffPermission::BookingsUpdateStatus]);
        $customer = User::factory()->customer()->create();

        $this->assertFalse($this->policy->update($customer, $booking));
    }

    public function test_platform_admin_can_update(): void
    {
        [$booking] = $this->bookingAndStaff([StaffPermission::BookingsUpdateStatus]);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        $this->assertTrue($this->policy->update($admin, $booking));
    }

    public function test_staff_with_payments_record_can_submit_payment_proof(): void
    {
        [$booking, $staff] = $this->bookingAndStaff([StaffPermission::PaymentsRecord]);

        $this->assertTrue($this->policy->submitPaymentProof($staff, $booking));
    }

    public function test_read_only_staff_cannot_submit_payment_proof(): void
    {
        [$booking, $staff] = $this->bookingAndStaff([StaffPermission::BookingsView]);

        $this->assertFalse($this->policy->submitPaymentProof($staff, $booking));
    }

    public function test_cross_agency_staff_cannot_submit_payment_proof(): void
    {
        [$booking] = $this->bookingAndStaff([StaffPermission::PaymentsRecord]);
        $otherAgency = Agency::factory()->create();
        $staff = $this->staffWithPermissions(
            [StaffPermission::PaymentsRecord],
            currentAgencyId: (int) $otherAgency->id,
        );

        $this->assertFalse($this->policy->submitPaymentProof($staff, $booking));
    }

    public function test_customer_owning_booking_can_submit_payment_proof(): void
    {
        $customer = User::factory()->customer()->create();
        $booking = Booking::factory()->create([
            'agency_id' => Agency::query()->firstOrFail()->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertTrue($this->policy->submitPaymentProof($customer, $booking));
    }

    public function test_agent_staff_requires_payments_upload_permission(): void
    {
        $agency = Agency::query()->firstOrFail();
        $owner = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);
        $agent = Agent::factory()->create([
            'user_id' => $owner->id,
            'agency_id' => $agency->id,
        ]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
        ]);

        $denied = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => [AgentPermission::BookingsView],
            ],
        ]);
        $allowed = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => [AgentPermission::PaymentsUpload],
            ],
        ]);

        $this->assertFalse($this->policy->submitPaymentProof($denied, $booking));
        $this->assertTrue($this->policy->submitPaymentProof($allowed, $booking));
    }

    public function test_verify_permission_remains_separate_from_submit(): void
    {
        [$booking, $submitter] = $this->bookingAndStaff([StaffPermission::PaymentsRecord]);
        $verifier = $this->staffWithPermissions([StaffPermission::PaymentsVerify]);

        $this->assertTrue($this->policy->submitPaymentProof($submitter, $booking));
        $this->assertFalse($this->policy->verifyPayment($submitter, $booking));
        $this->assertFalse($this->policy->submitPaymentProof($verifier, $booking));
        $this->assertTrue($this->policy->verifyPayment($verifier, $booking));
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Booking, 1: User}
     */
    private function bookingAndStaff(array $permissions): array
    {
        $staff = $this->staffWithPermissions($permissions);
        $booking = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
        ]);

        return [$booking, $staff];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staffWithPermissions(array $permissions, ?int $currentAgencyId = null): User
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $meta = is_array($staff->meta) ? $staff->meta : [];
        $meta['staff_permissions'] = $permissions;
        $staff->forceFill([
            'meta' => $meta,
            'current_agency_id' => $currentAgencyId ?? $staff->current_agency_id,
        ])->save();

        return $staff->fresh();
    }
}
