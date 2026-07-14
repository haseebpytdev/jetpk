<?php

namespace Tests\Feature\Sprint9F;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencyNotificationSetting;
use App\Models\AgencySetting;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Communication\NotificationRecipientResolver;
use App\Services\Communication\OtaNotificationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * Sprint 9F — notification recipient fallback, RBAC on communications/reports, queue/mail safety.
 */
class NotificationRecipientHardeningTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    private NotificationRecipientResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->resolver = app(NotificationRecipientResolver::class);
    }

    public function test_explicit_recipient_emails_are_used_when_configured(): void
    {
        $agency = $this->asifAgency();
        AgencyNotificationSetting::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'event_key' => OtaNotificationEvent::BookingManualReviewRequired->value,
                'channel' => 'email',
            ],
            [
                'enabled' => true,
                'recipient_emails' => ['ops.explicit@example.test'],
            ],
        );

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::BookingManualReviewRequired->value,
        );

        $this->assertSame(['ops.explicit@example.test'], $resolved['to']);
    }

    public function test_duplicate_recipient_emails_are_deduplicated(): void
    {
        $agency = $this->asifAgency();
        AgencyNotificationSetting::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'event_key' => OtaNotificationEvent::BookingManualReviewRequired->value,
                'channel' => 'email',
            ],
            [
                'enabled' => true,
                'recipient_emails' => ['Ops@Example.test', 'ops@example.test', 'OPS@EXAMPLE.TEST'],
            ],
        );

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::BookingManualReviewRequired->value,
        );

        $this->assertSame(['ops@example.test'], $resolved['to']);
    }

    public function test_support_email_fallback_when_no_platform_admin_on_agency(): void
    {
        $agency = $this->asifAgency();
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['support_email' => 'ops.fallback@example.test'],
        );

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::PnrItinerarySyncFailed->value,
        );

        $this->assertContains('ops.fallback@example.test', $resolved['to']);
        $this->assertNotContains('admin@ota.demo', $resolved['to']);
    }

    public function test_brand_support_email_fallback_when_agency_support_missing(): void
    {
        config(['ota-brand.support_email' => 'brand.ops@example.test']);
        $agency = $this->asifAgency();

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::SupplierBookingFailed->value,
        );

        $this->assertContains('brand.ops@example.test', $resolved['to']);
    }

    public function test_no_recipients_logs_skipped_when_no_fallback_available(): void
    {
        Mail::fake();
        config(['ota-brand.support_email' => '']);
        $agency = $this->asifAgency();
        User::query()
            ->where('current_agency_id', $agency->id)
            ->where('account_type', AccountType::PlatformAdmin)
            ->update(['account_type' => AccountType::AgencyAdmin]);
        AgencySetting::query()->where('agency_id', $agency->id)->delete();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true],
        );
        AgencyNotificationSetting::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'event_key' => OtaNotificationEvent::BookingManualReviewRequired->value,
                'channel' => 'email',
            ],
            ['enabled' => true, 'recipient_emails' => null],
        );

        $booking = Booking::factory()->for($agency)->create();

        app(OtaNotificationService::class)->send(
            $agency,
            OtaNotificationEvent::BookingManualReviewRequired->value,
            [],
            $booking,
        );

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => OtaNotificationEvent::BookingManualReviewRequired->value,
            'status' => 'skipped',
        ]);
        Mail::assertNothingSent();
    }

    public function test_legacy_agency_admin_cannot_access_admin_communications_or_reports(): void
    {
        $legacy = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($legacy)->get(route('admin.settings.communications.index'))->assertForbidden();
        $this->actingAs($legacy)->get(route('admin.reports'))->assertForbidden();
    }

    public function test_platform_admin_can_access_admin_communications_and_reports(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.settings.communications.index'))->assertOk()
            ->assertSee('data-testid="ota-notification-recipient-guidance"', false);
        $this->actingAs($admin)->get(route('admin.reports'))->assertOk();
    }

    public function test_communication_settings_explains_recipient_fallback_and_queue_worker(): void
    {
        config(['queue.default' => 'database']);

        $html = $this->actingAs($this->platformAdmin())
            ->get(route('admin.settings.communications.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="ota-notification-recipient-guidance"', $html);
        $this->assertStringContainsString('recipient emails', strtolower($html));
        $this->assertStringContainsString('support email', strtolower($html));
        $this->assertStringContainsString('data-testid="ota-queue-worker-readiness"', $html);
    }

    public function test_mail_failure_does_not_break_manual_review_trigger(): void
    {
        config(['mail.default' => 'log']);
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('smtp down'));
        $this->enableOutboundNotifications();

        $booking = $this->draftBookingWithContact();

        app(BookingCommunicationService::class)->notifyManualReviewRequired($booking);

        $log = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', OtaNotificationEvent::BookingManualReviewRequired->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
    }

    public function test_duplicate_manual_review_notification_is_guarded(): void
    {
        Mail::fake();
        $this->enableOutboundNotifications();
        $booking = $this->draftBookingWithContact();
        $service = app(BookingCommunicationService::class);

        $service->notifyManualReviewRequired($booking);
        $service->notifyManualReviewRequired($booking->fresh());

        $staffLike = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', OtaNotificationEvent::BookingManualReviewRequired->value)
            ->where('meta->notification_type', 'staff_review_required')
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->count();

        $this->assertSame(1, $staffLike);
    }

    public function test_agency_admin_users_are_never_used_as_admin_recipient_bucket(): void
    {
        $agency = $this->asifAgency();
        $legacy = $this->legacyAgencyAdminFromSeed();
        $agency->users()->syncWithoutDetaching([
            $legacy->id => ['role' => 'agency_admin'],
        ]);

        $resolved = $this->resolver->resolve(
            $agency,
            OtaNotificationEvent::BookingRequestReceived->value,
        );

        $this->assertNotContains(strtolower($legacy->email), $resolved['to']);
    }

    protected function asifAgency(): Agency
    {
        return Agency::query()->where('slug', 'asif-travels')->firstOrFail();
    }

    protected function enableOutboundNotifications(): void
    {
        $agency = $this->asifAgency();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true],
        );
        AgencyNotificationSetting::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'event_key' => OtaNotificationEvent::BookingManualReviewRequired->value,
                'channel' => 'email',
            ],
            [
                'enabled' => true,
                'recipient_scope' => 'admin',
                'recipient_emails' => ['admin@ota.demo'],
            ],
        );
    }

    protected function draftBookingWithContact(): Booking
    {
        $agency = $this->asifAgency();
        $customer = User::factory()->create(['current_agency_id' => $agency->id]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
        ]);
        $booking->contact()->create([
            'email' => 'traveler-9f@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
            'meta' => ['name' => 'Traveler'],
        ]);

        return $booking->fresh(['agency', 'contact']);
    }
}
