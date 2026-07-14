<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\GuestBookingAccessToken;
use App\Models\User;
use App\Services\Suppliers\Sabre\Cancel\SabreBookingCancelService;
use App\Services\Suppliers\Sabre\Cancel\SabreCancelPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CancellationRefundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', false);

        parent::tearDown();
    }

    public function test_agency_admin_can_request_approve_and_process_cancellation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeBooking($admin->current_agency_id, BookingStatus::Confirmed);

        $this->actingAs($admin)->post(route('admin.bookings.cancellations.store', $booking), [
            'cancellation_type' => 'booking_cancel',
            'reason' => 'Customer requested cancellation',
        ])->assertRedirect();

        $request = BookingCancellationRequest::query()->where('booking_id', $booking->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.bookings.cancellations.approve', $request))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.bookings.cancellations.process', $request))->assertRedirect();

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status->value);
        $this->assertSame('processed', $booking->cancellation_status);
    }

    public function test_staff_can_process_own_agency_cancellation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $staff] = $this->seededUsers();
        $booking = $this->makeBooking($admin->current_agency_id, BookingStatus::Pending);
        $request = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'requested_by' => $admin->id,
            'request_source' => 'admin',
            'status' => 'approved',
            'cancellation_type' => 'booking_cancel',
        ]);

        $this->actingAs($staff)->patch(route('staff.bookings.cancellations.process', $request))->assertRedirect();
        $this->assertSame('processed', $request->fresh()->status->value);
    }

    public function test_agent_can_request_cancellation_for_own_booking_and_cannot_approve(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [, , $agentUser] = $this->seededUsers();
        $agentProfile = $agentUser->agent();
        $booking = $this->makeBooking($agentUser->current_agency_id, BookingStatus::Confirmed, null, $agentProfile?->id);

        $this->actingAs($agentUser)->post(route('agent.bookings.cancellations.store', $booking), [
            'cancellation_type' => 'booking_cancel',
        ])->assertRedirect();
        $request = BookingCancellationRequest::query()->where('booking_id', $booking->id)->latest('id')->firstOrFail();

        $this->actingAs($agentUser)->patch(route('admin.bookings.cancellations.approve', $request))->assertForbidden();
    }

    public function test_customer_can_request_cancellation_for_own_booking(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [, , , $customer] = $this->seededUsers();
        $booking = $this->makeBooking($customer->current_agency_id, BookingStatus::PaymentPending, $customer->id, null);

        $this->actingAs($customer)->post(route('customer.bookings.cancellations.store', $booking), [
            'cancellation_type' => 'booking_cancel',
            'reason' => 'Need date change',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_cancellation_requests', [
            'booking_id' => $booking->id,
            'request_source' => 'customer',
        ]);
    }

    public function test_guest_with_valid_token_can_request_cancellation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeBooking($admin->current_agency_id, BookingStatus::Pending);
        $tokenRaw = 'guest-cancel-token';
        GuestBookingAccessToken::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'token_hash' => hash('sha256', $tokenRaw),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->post(route('guest.bookings.cancellations.store', ['booking' => $booking, 'token' => $tokenRaw]), [
            'cancellation_type' => 'booking_cancel',
            'reason' => 'Guest request',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_cancellation_requests', [
            'booking_id' => $booking->id,
            'request_source' => 'guest',
        ]);
    }

    public function test_cross_agency_cancellation_and_refund_are_denied(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [, $staff] = $this->seededUsers();
        $otherAgency = Agency::factory()->create();
        $foreignBooking = $this->makeBooking($otherAgency->id, BookingStatus::Confirmed);

        $this->actingAs($staff)->post(route('staff.bookings.cancellations.store', $foreignBooking), [
            'cancellation_type' => 'booking_cancel',
        ])->assertForbidden();

        $this->actingAs($staff)->post(route('staff.bookings.refunds.store', $foreignBooking), [
            'amount' => 1000,
            'method' => 'cash',
        ])->assertForbidden();
    }

    public function test_ticketed_booking_cancellation_process_keeps_status_and_sets_manual_warning(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeBooking($admin->current_agency_id, BookingStatus::Ticketed);
        $request = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'requested_by' => $admin->id,
            'request_source' => 'admin',
            'status' => 'approved',
            'cancellation_type' => 'ticket_refund',
        ]);

        $this->actingAs($admin)->patch(route('admin.bookings.cancellations.process', $request))->assertRedirect();

        $booking->refresh();
        $request->refresh();
        $this->assertSame('ticketed', $booking->status->value);
        $this->assertSame('processed', $booking->cancellation_status);
        $this->assertNotEmpty($request->meta['manual_warning'] ?? null);
    }

    public function test_admin_can_process_sabre_cancellation_after_air_segments_removed_confirmation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeSabreBooking($admin->current_agency_id, BookingStatus::Confirmed, [
            'payment_status' => 'paid',
            'ticketing_status' => 'not_started',
        ]);
        $request = $this->approvedCancellationRequest($booking, $admin);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldReceive('cancelBookingForBooking')
                ->once()
                ->andReturn($this->sabreCancelOutcome(SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED));
        });

        $this->actingAs($admin)->patch(route('admin.bookings.cancellations.process', $request))->assertRedirect();

        $booking->refresh();
        $request->refresh();
        $this->assertSame('cancelled', $booking->status->value);
        $this->assertSame('cancelled', $booking->supplier_booking_status);
        $this->assertSame('not_started', $booking->ticketing_status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('processed', $request->status->value);
        $safeMeta = $booking->meta['sabre_cancel_outcome'] ?? [];
        $this->assertSame(SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED, $safeMeta['classification'] ?? null);
        $this->assertSame(200, $safeMeta['http_status'] ?? null);
        $this->assertSame(SabreCancelPayloadBuilder::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL, $safeMeta['cancel_payload_style'] ?? null);
        $this->assertTrue($safeMeta['cancelled_air_segments_removed'] ?? false);
        $this->assertSame(0, $safeMeta['post_cancel_segment_count'] ?? null);
        $this->assertFalse($safeMeta['ticket_numbers_present'] ?? true);
        $encoded = json_encode($safeMeta);
        $this->assertStringNotContainsString('bookingSignature', $encoded);
        $this->assertStringNotContainsString('raw_response', $encoded);
        $this->assertStringNotContainsString('token', strtolower((string) $encoded));
    }

    public function test_admin_live_gate_disabled_blocks_sabre_cancel_without_local_mutation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeSabreBooking($admin->current_agency_id, BookingStatus::Confirmed);
        $request = $this->approvedCancellationRequest($booking, $admin);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', false);

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldNotReceive('cancelBookingForBooking');
        });

        $this->actingAs($admin)->patch(route('admin.bookings.cancellations.process', $request))
            ->assertSessionHas('status', 'cancellation-processed-manual-review')
            ->assertSessionHas('cancellation_warning', 'Supplier cancellation execution is not enabled for admin/staff yet. Booking status was not changed.');

        $booking->refresh();
        $request->refresh();
        $safeMeta = $booking->meta['sabre_cancel_outcome'] ?? [];
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame('approved', $booking->cancellation_status);
        $this->assertSame('approved', $request->status->value);
        $this->assertFalse($safeMeta['sabre_cancel_execution_attempted'] ?? true);
        $this->assertSame('admin_staff_live_gate_disabled', $safeMeta['sabre_cancel_execution_blocked_reason'] ?? null);
        $this->assertSame('not_run', $safeMeta['sabre_cancel_precheck_status'] ?? null);
        $this->assertSame('LIVE_CANCEL_DISABLED', $safeMeta['sabre_cancel_classification'] ?? null);
        $encoded = json_encode($safeMeta);
        $this->assertStringNotContainsString('bookingSignature', (string) $encoded);
        $this->assertStringNotContainsString('raw_response', (string) $encoded);
        $this->assertStringNotContainsString('response_payload', (string) $encoded);
        $this->assertStringNotContainsString('token', strtolower((string) $encoded));
    }

    public function test_staff_can_process_sabre_cancellation_when_admin_live_gate_enabled(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, $staff] = $this->seededUsers();
        $booking = $this->makeSabreBooking($admin->current_agency_id, BookingStatus::Confirmed);
        $request = $this->approvedCancellationRequest($booking, $admin);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldReceive('cancelBookingForBooking')
                ->once()
                ->andReturn($this->sabreCancelOutcome(SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED));
        });

        $this->actingAs($staff)->patch(route('staff.bookings.cancellations.process', $request))->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status->value);
        $this->assertSame('processed', $request->fresh()->status->value);
    }

    public function test_sabre_http_200_still_active_does_not_update_local_booking_status(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeSabreBooking($admin->current_agency_id, BookingStatus::Confirmed);
        $request = $this->approvedCancellationRequest($booking, $admin);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldReceive('cancelBookingForBooking')
                ->once()
                ->andReturn($this->sabreCancelOutcome(SabreBookingCancelService::CLASSIFICATION_HTTP_200_BUT_STILL_ACTIVE, 1, false));
        });

        $this->actingAs($admin)->patch(route('admin.bookings.cancellations.process', $request))->assertSessionHas('status', 'cancellation-processed-manual-review');

        $booking->refresh();
        $request->refresh();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame('approved', $request->status->value);
        $this->assertNull($booking->cancelled_at);
        $this->assertSame(SabreBookingCancelService::CLASSIFICATION_HTTP_200_BUT_STILL_ACTIVE, $booking->meta['sabre_cancel_outcome']['classification'] ?? null);
    }

    public function test_sabre_no_active_air_segments_blocks_without_local_mutation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeSabreBooking($admin->current_agency_id, BookingStatus::Confirmed);
        $request = $this->approvedCancellationRequest($booking, $admin);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldReceive('cancelBookingForBooking')
                ->once()
                ->andReturn($this->sabreCancelOutcome('no_active_air_segments', 0, false, [
                    'success' => false,
                    'safe_summary_category' => SabreBookingCancelService::CATEGORY_CANCEL_NOT_ELIGIBLE,
                    'status' => 'no_active_air_segments',
                    'message' => 'Supplier booking has no active air segments to cancel.',
                    'live_call_attempted' => false,
                ]));
        });

        $this->actingAs($admin)->patch(route('admin.bookings.cancellations.process', $request))
            ->assertSessionHas('cancellation_warning', 'Supplier booking has no active air segments to cancel.');

        $booking->refresh();
        $request->refresh();
        $safeMeta = $booking->meta['sabre_cancel_outcome'] ?? [];
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame('approved', $booking->cancellation_status);
        $this->assertSame('approved', $request->status->value);
        $this->assertSame('no_active_air_segments', $safeMeta['sabre_cancel_execution_blocked_reason'] ?? null);
        $this->assertSame('no_active_air_segments', $safeMeta['sabre_cancel_precheck_status'] ?? null);
    }

    public function test_sabre_ticketed_booking_is_blocked_before_cancel_call(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeSabreBooking($admin->current_agency_id, BookingStatus::Ticketed, [
            'ticketing_status' => 'ticketed',
        ]);
        $request = $this->approvedCancellationRequest($booking, $admin);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldNotReceive('cancelBookingForBooking');
        });

        $this->actingAs($admin)->patch(route('admin.bookings.cancellations.process', $request))->assertRedirect();

        $booking->refresh();
        $request->refresh();
        $this->assertSame('ticketed', $booking->status->value);
        $this->assertSame('approved', $request->status->value);
        $this->assertNotEmpty($request->fresh()->meta['manual_warning'] ?? null);
    }

    public function test_sabre_booking_without_pnr_is_blocked_before_cancel_call(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeSabreBooking($admin->current_agency_id, BookingStatus::Confirmed, [
            'pnr' => null,
            'supplier_reference' => null,
        ]);
        $request = $this->approvedCancellationRequest($booking, $admin);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldNotReceive('cancelBookingForBooking');
        });

        $this->actingAs($admin)->patch(route('admin.bookings.cancellations.process', $request))->assertSessionHas('status', 'cancellation-processed-manual-review');

        $booking->refresh();
        $request->refresh();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame('approved', $request->status->value);
        $this->assertSame('PNR_MISSING', $booking->meta['sabre_cancel_outcome']['classification'] ?? null);
    }

    public function test_customer_guest_and_agent_cannot_execute_live_cancellation_even_when_gate_enabled(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin, , $agentUser, $customer] = $this->seededUsers();
        $booking = $this->makeSabreBooking($admin->current_agency_id, BookingStatus::Confirmed, [
            'customer_id' => $customer->id,
        ]);
        $request = $this->approvedCancellationRequest($booking, $admin);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldNotReceive('cancelBookingForBooking');
        });

        $this->actingAs($customer)->patch(route('admin.bookings.cancellations.process', $request))->assertForbidden();
        auth()->logout();
        $this->actingAs($agentUser)->patch(route('admin.bookings.cancellations.process', $request))->assertForbidden();
        auth()->logout();
        $this->patch(route('admin.bookings.cancellations.process', $request))->assertRedirect();
    }

    public function test_audit_and_communication_logs_created_for_cancellation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeBooking($admin->current_agency_id, BookingStatus::Pending);

        $this->actingAs($admin)->post(route('admin.bookings.cancellations.store', $booking), [
            'cancellation_type' => 'booking_cancel',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.cancellation_requested', 'auditable_id' => $booking->id]);
        $this->assertDatabaseHas('communication_logs', ['event' => 'cancellation_requested', 'booking_id' => $booking->id]);
    }

    public function test_agency_admin_can_create_approve_and_mark_refund_paid(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeBooking($admin->current_agency_id, BookingStatus::Cancelled);
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => 'verified',
            'method' => 'cash',
            'amount' => 5000,
            'currency' => 'PKR',
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => 2500,
            'method' => 'bank_transfer',
        ])->assertRedirect();
        $refund = BookingRefund::query()->where('booking_id', $booking->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.bookings.refunds.approve', $refund))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.bookings.refunds.mark-paid', $refund), [
            'reference' => 'REF-001',
        ])->assertRedirect();

        $this->assertSame('paid', $refund->fresh()->status->value);
        $this->assertSame('partial', $booking->fresh()->refund_status);
    }

    public function test_rejected_refund_does_not_count_as_refunded_and_report_page_loads_new_metrics(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->seededUsers();
        $booking = $this->makeBooking($admin->current_agency_id, BookingStatus::Cancelled);
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => 'verified',
            'method' => 'cash',
            'amount' => 5000,
            'currency' => 'PKR',
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => 2000,
            'method' => 'cash',
        ])->assertRedirect();
        $refund = BookingRefund::query()->where('booking_id', $booking->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.bookings.refunds.reject', $refund), [
            'reason' => 'Invalid request',
        ])->assertRedirect();

        $this->assertSame('rejected', $refund->fresh()->status->value);
        $this->assertNotSame('refunded', $booking->fresh()->refund_status);
        $this->actingAs($admin)->get(route('admin.reports'))->assertOk()->assertSee('Cancellations')->assertSee('Pending refunds');
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User}
     */
    protected function seededUsers(): array
    {
        $this->seed(OtaFoundationSeeder::class);

        $seededAgencyAdmin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin = User::query()->updateOrCreate(
            ['email' => 'platform-admin@ota.demo'],
            [
                'name' => 'Platform Admin',
                'password' => bcrypt('password'),
                'account_type' => AccountType::PlatformAdmin,
                'status' => UserAccountStatus::Active,
                'current_agency_id' => $seededAgencyAdmin->current_agency_id,
            ]
        );
        $admin->forceFill(['email_verified_at' => now()])->save();
        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@aurora-sky-travel.demo'],
            [
                'name' => 'Aurora Customer',
                'password' => bcrypt('password'),
                'account_type' => AccountType::Customer,
                'status' => UserAccountStatus::Active,
                'current_agency_id' => $seededAgencyAdmin->current_agency_id,
            ]
        );
        $customer->forceFill(['email_verified_at' => now()])->save();

        return [
            $admin,
            User::query()->where('email', 'staff@ota.demo')->firstOrFail(),
            User::query()->where('email', 'agent@ota.demo')->firstOrFail(),
            $customer,
        ];
    }

    protected function makeBooking(?int $agencyId, BookingStatus $status, ?int $customerId = null, ?int $agentId = null): Booking
    {
        return Booking::factory()->create([
            'agency_id' => $agencyId,
            'customer_id' => $customerId,
            'agent_id' => $agentId,
            'status' => $status,
            'payment_status' => 'unpaid',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSabreBooking(?int $agencyId, BookingStatus $status, array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'agency_id' => $agencyId,
            'status' => $status,
            'supplier' => 'sabre',
            'pnr' => 'TNLDUZ',
            'supplier_reference' => 'TNLDUZ',
            'payment_status' => 'unpaid',
            'ticketing_status' => 'not_started',
            'meta' => ['supplier_provider' => 'sabre'],
        ], $overrides));
    }

    protected function approvedCancellationRequest(Booking $booking, User $actor): BookingCancellationRequest
    {
        $booking->forceFill(['cancellation_status' => 'approved'])->save();

        return BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'requested_by' => $actor->id,
            'request_source' => 'admin',
            'status' => 'approved',
            'cancellation_type' => 'supplier_cancel',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sabreCancelOutcome(string $classification, int $segmentCount = 0, bool $airSegmentsRemoved = true, array $overrides = []): array
    {
        return array_merge([
            'success' => in_array($classification, [
                SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
                SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            ], true),
            'supplier_cancel_verified' => true,
            'live_call_attempted' => true,
            'safe_summary_category' => 'CANCEL_VERIFIED',
            'payload_style' => SabreCancelPayloadBuilder::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
            'cancel_probe' => ['http_status' => 200],
            'post_cancel_verification' => [
                'classification' => $classification,
                'http_status' => 200,
                'cancel_air_segments_removed' => $airSegmentsRemoved,
                'post_cancel_segment_count' => $segmentCount,
                'ticket_numbers_present' => false,
            ],
        ], $overrides);
    }
}
