<?php

namespace Tests\Feature;

use App\Data\SupplierBookingResultData;
use App\Enums\BookingStatus;
use App\Enums\OtaNotificationEvent;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Communication\NotificationRecipientResolver;
use App\Services\Suppliers\BookingAdapters\DuffelSupplierBookingAdapter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class NotificationOperationalCoverageTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->enableAgencyEmail();
    }

    public function test_new_booking_creates_admin_and_customer_communication_logs(): void
    {
        Mail::fake();
        $booking = $this->draftBookingWithContact();

        app(BookingService::class)->submitBookingRequest($booking);

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => OtaNotificationEvent::BookingRequestReceived->value,
            'channel' => 'email',
        ]);
        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => 'booking_request_received',
        ]);
    }

    public function test_fare_update_requires_acceptance_logs_operational_notification(): void
    {
        Mail::fake();
        $booking = $this->draftBookingWithContact();
        $booking->update(['status' => BookingStatus::Draft]);

        app(BookingCommunicationService::class)->notifyFareUpdateRequiresAcceptance($booking);

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => OtaNotificationEvent::BookingFareUpdatedRequiresAcceptance->value,
        ]);
    }

    public function test_manual_review_notification_is_admin_staff_only_payload(): void
    {
        Mail::fake();
        $booking = $this->draftBookingWithContact();

        app(BookingCommunicationService::class)->notifyManualReviewRequired($booking);

        $log = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', OtaNotificationEvent::BookingManualReviewRequired->value)
            ->where('meta->notification_type', 'staff_review_required')
            ->first();

        $this->assertNotNull($log);
        $payload = is_array($log->meta) ? ($log->meta['payload'] ?? []) : [];
        $this->assertArrayNotHasKey('error_message', $payload);
        $this->assertSame('This booking requires staff review before it can proceed.', $payload['message'] ?? null);
        $this->assertSame('staff_review_required', is_array($log->meta) ? ($log->meta['notification_type'] ?? null) : null);

        $platformAdmin = $this->platformAdmin();
        $resolved = app(NotificationRecipientResolver::class)->resolve(
            $booking->agency,
            OtaNotificationEvent::BookingManualReviewRequired->value,
            $booking,
        );
        $this->assertContains(strtolower($platformAdmin->email), $resolved['to']);
        $this->assertNotContains('traveler@example.test', $resolved['to']);
    }

    public function test_admin_login_notification_when_flag_enabled(): void
    {
        Mail::fake();
        config(['ota.notify_admin_login' => true]);
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $admin = $this->platformAdmin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertDatabaseHas('communication_logs', [
            'event' => OtaNotificationEvent::AdminLoginSuccess->value,
        ]);
    }

    public function test_admin_login_skipped_when_flag_disabled(): void
    {
        Mail::fake();
        config(['ota.notify_admin_login' => false]);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $this->post('/login', [
            'email' => 'admin@ota.demo',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertDatabaseMissing('communication_logs', [
            'event' => OtaNotificationEvent::AdminLoginSuccess->value,
            'status' => 'sent',
        ]);
    }

    public function test_staff_login_notification_when_flag_enabled(): void
    {
        Mail::fake();
        config(['ota.notify_staff_login' => true]);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $this->post('/login', [
            'email' => 'staff@ota.demo',
            'password' => 'password',
        ])->assertRedirect(route('staff.dashboard', absolute: false));

        $this->assertDatabaseHas('communication_logs', [
            'event' => OtaNotificationEvent::StaffLoginSuccess->value,
        ]);
    }

    public function test_agent_login_notification_when_flag_enabled(): void
    {
        Mail::fake();
        config(['ota.notify_agent_login' => true]);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $this->post('/login', [
            'email' => 'agent@ota.demo',
            'password' => 'password',
        ])->assertRedirect(route('agent.dashboard', absolute: false));

        $this->assertDatabaseHas('communication_logs', [
            'event' => OtaNotificationEvent::AgentLoginSuccess->value,
        ]);
    }

    public function test_failed_admin_login_notification_and_audit_when_flag_enabled(): void
    {
        Mail::fake();
        config([
            'ota.notify_failed_admin_login' => true,
            'ota.auth_failed_login_email_threshold' => 1,
        ]);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $this->post('/login', [
            'email' => 'admin@ota.demo',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.admin_login_failed']);
        $this->assertDatabaseHas('communication_logs', [
            'event' => OtaNotificationEvent::LoginFailedSensitive->value,
        ]);
    }

    public function test_payment_verified_routes_customer_and_admin_without_duplicate(): void
    {
        $platformAdmin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->draftBookingWithContact();
        $booking->update(['status' => BookingStatus::PaymentPending]);

        $resolved = app(NotificationRecipientResolver::class)->resolve(
            $agency,
            OtaNotificationEvent::PaymentVerified->value,
            $booking,
        );

        $emails = $resolved['to'];
        $this->assertContains(strtolower($platformAdmin->email), $emails);
        $this->assertContains('traveler@example.test', $emails);
        $this->assertSame(count($emails), count(array_unique($emails)));
    }

    public function test_ota_notification_service_queues_mail_when_queue_not_sync(): void
    {
        Mail::fake();
        Queue::fake();
        config(['queue.default' => 'database', 'mail.default' => 'smtp']);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->draftBookingWithContact();

        app(BookingCommunicationService::class)->notifyManualReviewRequired($booking);

        Queue::assertPushed(CallQueuedClosure::class);
    }

    public function test_communication_log_error_message_does_not_contain_smtp_password(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'email_enabled' => true,
                'smtp_password' => 'super-secret-smtp-pass',
            ],
        );
        config(['mail.default' => 'smtp', 'queue.default' => 'sync']);
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP auth failed: super-secret-smtp-pass'));

        $booking = $this->draftBookingWithContact();
        app(BookingCommunicationService::class)->notifyManualReviewRequired($booking);

        $log = CommunicationLog::query()
            ->where('event', OtaNotificationEvent::BookingManualReviewRequired->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('super-secret-smtp-pass', (string) $log->error_message);
    }

    public function test_supplier_manual_review_triggers_booking_manual_review_event(): void
    {
        Mail::fake();
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->andReturn(new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: SupplierProvider::Duffel->value,
                error_code: 'manual_review',
                error_message: 'Sabre host detail',
            ));
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = $this->supplierEligibleBooking($admin->current_agency_id);

        $this->actingAs($admin)->post(route('admin.bookings.supplier-booking', $booking))->assertRedirect();

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => OtaNotificationEvent::BookingManualReviewRequired->value,
        ]);
    }

    protected function enableAgencyEmail(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true],
        );
    }

    protected function draftBookingWithContact(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create(['current_agency_id' => $agency->id]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Draft,
            'route' => 'LHE-KHI',
        ]);
        $booking->contact()->create([
            'email' => 'traveler@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
            'meta' => ['name' => 'Test Traveler'],
        ]);

        return $booking->fresh(['agency', 'contact', 'customer']);
    }

    protected function supplierEligibleBooking(int $agencyId): Booking
    {
        $conn = SupplierConnection::query()->where('agency_id', $agencyId)->where('provider', SupplierProvider::Duffel)->firstOrFail();
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        return Booking::factory()->create([
            'agency_id' => $agencyId,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $conn->id,
                'validated_offer_snapshot' => ['offer_id' => 'duffel-offer-1'],
            ],
        ]);
    }
}
