<?php

namespace Tests\Feature\Rbac;

use App\Data\TicketingResultData;
use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\TicketingAdapters\PiaNdcSupplierTicketingAdapter;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_without_meta_staff_permissions_keeps_legacy_ticketing_access(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking] = $this->eligibleBookingWithDuffelTicketingStub();
        $staff = $this->legacyStaffUser();

        $this->assertTrue($staff->usesLegacyStaffPermissions());

        $this->actingAs($staff)
            ->post(route('staff.bookings.issue-ticket', $booking))
            ->assertRedirect();
    }

    public function test_staff_with_empty_staff_permissions_cannot_issue_ticket(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking] = $this->eligibleBookingWithDuffelTicketingStub();
        $staff = $this->staffWithPermissions([]);

        $this->actingAs($staff)
            ->post(route('staff.bookings.issue-ticket', $booking))
            ->assertForbidden();
    }

    public function test_staff_with_ticketing_permission_can_issue_ticket(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking] = $this->eligibleBookingWithDuffelTicketingStub();
        $staff = $this->staffWithPermissions([StaffPermission::TicketingIssue]);

        $this->actingAs($staff)
            ->post(route('staff.bookings.issue-ticket', $booking))
            ->assertRedirect();
    }

    public function test_staff_with_payments_verify_can_verify_payment(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $staff] = $this->bookingWithSubmittedPayment();
        $staff = $this->staffWithPermissions([StaffPermission::PaymentsVerify], $staff);
        $payment = BookingPayment::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->actingAs($staff)
            ->patch(route('staff.bookings.payments.verify', $payment))
            ->assertRedirect();
    }

    public function test_staff_without_payments_verify_cannot_verify_payment(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $staff] = $this->bookingWithSubmittedPayment();
        $staff = $this->staffWithPermissions([], $staff);
        $payment = BookingPayment::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->actingAs($staff)
            ->patch(route('staff.bookings.payments.verify', $payment))
            ->assertForbidden();
    }

    public function test_staff_with_refunds_approve_can_approve_refund(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$refund, $staff] = $this->pendingRefund();
        $staff = $this->staffWithPermissions([StaffPermission::RefundsApprove], $staff);

        $this->actingAs($staff)
            ->patch(route('staff.bookings.refunds.approve', $refund))
            ->assertRedirect();
    }

    public function test_staff_without_refunds_approve_cannot_approve_refund(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$refund, $staff] = $this->pendingRefund();
        $staff = $this->staffWithPermissions([], $staff);

        $this->actingAs($staff)
            ->patch(route('staff.bookings.refunds.approve', $refund))
            ->assertForbidden();
    }

    public function test_staff_with_cancellations_process_can_process_cancellation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$request, $staff] = $this->approvedCancellationRequest();
        $staff = $this->staffWithPermissions([StaffPermission::CancellationsProcess], $staff);

        $this->actingAs($staff)
            ->patch(route('staff.bookings.cancellations.process', $request))
            ->assertRedirect();
    }

    public function test_staff_without_cancellations_process_cannot_process_cancellation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$request, $staff] = $this->approvedCancellationRequest();
        $staff = $this->staffWithPermissions([], $staff);

        $this->actingAs($staff)
            ->patch(route('staff.bookings.cancellations.process', $request))
            ->assertForbidden();
    }

    public function test_platform_admin_still_has_full_admin_access(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_agent_portal_unchanged(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)->get(route('agent.dashboard'))->assertOk();
    }

    public function test_customer_portal_unchanged(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $customer = User::query()->where('account_type', AccountType::Customer)->first();
        if ($customer === null) {
            $customer = User::factory()->customer()->create();
        }

        $this->actingAs($customer)->get(route('customer.bookings.index'))->assertOk();
    }

    protected function legacyStaffUser(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $meta = $staff->meta ?? [];
        unset($meta['staff_permissions']);
        $staff->forceFill(['meta' => $meta])->save();

        return $staff->fresh();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function staffWithPermissions(array $permissions, ?User $base = null): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = $base ?? User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $meta = is_array($staff->meta) ? $staff->meta : [];
        $meta['staff_permissions'] = $permissions;
        $staff->forceFill(['meta' => $meta])->save();

        return $staff->fresh();
    }

    /**
     * @return array{0: Booking}
     */
    protected function eligibleBookingWithDuffelTicketingStub(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->mock(PiaNdcSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->andReturnUsing(function (Booking $booking, SupplierBooking $supplierBooking): TicketingResultData {
                $tickets = [];
                foreach ($booking->passengers as $passenger) {
                    $tickets[] = [
                        'passenger_id' => $passenger->id,
                        'ticket_number' => 'TKT'.$passenger->id,
                        'pnr' => $booking->pnr,
                        'airline_code' => 'PK',
                        'issued_at' => now(),
                        'passenger_name' => trim((string) $passenger->first_name.' '.(string) $passenger->last_name),
                    ];
                }

                return new TicketingResultData(
                    success: true,
                    status: 'issued',
                    provider: $supplierBooking->provider,
                    tickets: $tickets,
                    safe_summary: ['stub' => true],
                );
            });
        });

        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'pnr' => 'PNR123',
            'supplier_reference' => 'SUPP123',
            'supplier_booking_status' => 'pending_ticketing',
        ]);

        $booking->passengers()->createMany([
            ['passenger_index' => 0, 'title' => 'Mr', 'first_name' => 'Ali', 'last_name' => 'Khan'],
        ]);

        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Duffel->value,
            'supplier_reference' => 'SUPP123',
            'pnr' => 'PNR123',
            'status' => 'pending_ticketing',
            'raw_summary' => ['seeded' => true],
            'created_by' => $admin->id,
            'created_at_supplier' => now(),
        ]);

        return [$booking->fresh()];
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    protected function bookingWithSubmittedPayment(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
        ]);
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        return [$booking, $staff];
    }

    /**
     * @return array{0: BookingRefund, 1: User}
     */
    protected function pendingRefund(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
            'status' => BookingStatus::Cancelled,
            'payment_status' => 'paid',
        ]);
        $refund = BookingRefund::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => 'pending',
            'amount' => 500,
            'currency' => 'PKR',
            'method' => 'cash',
        ]);

        return [$refund, $staff];
    }

    /**
     * @return array{0: BookingCancellationRequest, 1: User}
     */
    protected function approvedCancellationRequest(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
            'status' => BookingStatus::Pending,
        ]);
        $request = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'requested_by' => $staff->id,
            'request_source' => 'admin',
            'status' => 'approved',
            'cancellation_type' => 'booking_cancel',
        ]);

        return [$request, $staff];
    }
}
