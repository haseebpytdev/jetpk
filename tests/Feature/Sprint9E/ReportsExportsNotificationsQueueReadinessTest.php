<?php

namespace Tests\Feature\Sprint9E;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\OtaNotificationEvent;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencyMessageTemplate;
use App\Models\AgencyNotificationSetting;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * Sprint 9E — reports/exports RBAC, notification queue safety, operational readiness.
 */
class ReportsExportsNotificationsQueueReadinessTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_platform_admin_can_access_admin_reports_and_export(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.reports'))->assertOk()
            ->assertSee('Platform Reports', false);

        $this->actingAs($admin)->get(route('admin.reports.export', 'sales'))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_agency_admin_cannot_access_admin_reports_or_export(): void
    {
        $legacy = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($legacy)->get(route('admin.reports'))->assertForbidden();
        $this->actingAs($legacy)->get(route('admin.reports.export', 'sales'))->assertForbidden();
    }

    public function test_agent_reports_are_scoped_to_own_agency_only(): void
    {
        $agencyA = Agency::factory()->create(['name' => 'Agency Alpha', 'slug' => 'agency-alpha-9e']);
        $agencyB = Agency::factory()->create(['name' => 'Agency Beta', 'slug' => 'agency-beta-9e']);

        $this->seedBookingForAgency($agencyA, 'REF-AGENCY-A-9E', null, 100_000);
        $this->seedBookingForAgency($agencyB, 'REF-AGENCY-B-9E', null, 500_000);

        [$agentUserA] = $this->seedAgentForAgency($agencyA);

        $this->actingAs($agentUserA)->get(route('agent.reports.index'))->assertOk()
            ->assertSee('data-testid="agent-reports-summary"', false)
            ->assertSee('100,000.00', false)
            ->assertDontSee('500,000.00', false);
    }

    public function test_staff_without_reports_export_cannot_export(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::ReportsView]);

        $this->actingAs($staff)->get(route('staff.reports.index'))->assertOk();
        $this->actingAs($staff)->get(route('staff.reports.export', 'sales'))->assertForbidden();
    }

    public function test_disabled_pdf_export_is_not_an_active_cta(): void
    {
        $admin = $this->platformAdmin();

        $html = $this->actingAs($admin)->get(route('admin.reports'))->assertOk()->getContent();

        $this->assertStringContainsString('data-testid="ota-reports-pdf-unavailable"', $html);
        $this->assertStringContainsString('PDF export not enabled yet', $html);
        $this->assertStringNotContainsString('btn btn-primary">Export PDF', $html);
        $this->assertStringNotContainsString('Export PDF (coming soon)', $html);
    }

    public function test_notification_action_does_not_throw_when_mail_is_faked(): void
    {
        Mail::fake();
        $this->enableOutboundNotifications();

        $booking = $this->draftBookingWithContact();

        app(BookingCommunicationService::class)->notifyManualReviewRequired($booking);

        $log = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', OtaNotificationEvent::BookingManualReviewRequired->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertNotSame('failed', $log->status);
        Mail::assertNothingSent();
    }

    public function test_duplicate_booking_request_does_not_queue_duplicate_operational_email(): void
    {
        Mail::fake();
        $this->enableOutboundNotifications();
        $booking = $this->draftBookingWithContact();
        $communication = app(BookingCommunicationService::class);

        $communication->sendBookingRequestReceived($booking);
        $communication->sendBookingRequestReceived($booking->fresh());

        $sentLike = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', OtaNotificationEvent::BookingRequestReceived->value)
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->count();

        $this->assertSame(1, $sentLike);
    }

    public function test_queued_mail_dispatch_uses_fakes_without_real_email(): void
    {
        Mail::fake();
        config(['queue.default' => 'database', 'mail.default' => 'smtp']);
        $this->enableOutboundNotifications();

        $booking = $this->draftBookingWithContact();
        app(BookingCommunicationService::class)->notifyManualReviewRequired($booking);

        $log = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', OtaNotificationEvent::BookingManualReviewRequired->value)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame(
            'queued',
            $log->status,
            'Expected queued delivery when queue is database and mail is smtp; got: '.($log->error_message ?? 'n/a'),
        );
        Mail::assertNothingSent();
    }

    public function test_bookings_csv_export_respects_agent_filter_and_excludes_other_agency_bookings(): void
    {
        $agencyA = Agency::factory()->create(['slug' => 'export-a-9e']);
        $agencyB = Agency::factory()->create(['slug' => 'export-b-9e']);
        [, $agentA] = $this->seedAgentForAgency($agencyA);
        [, $agentB] = $this->seedAgentForAgency($agencyB);

        $this->seedBookingForAgency($agencyA, 'REF-EXPORT-A-9E', $agentA);
        $this->seedBookingForAgency($agencyB, 'REF-EXPORT-B-9E', $agentB);

        $content = $this->actingAs($this->platformAdmin())
            ->get(route('admin.reports.export', ['type' => 'bookings', 'agent_id' => $agentA->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('REF-EXPORT-A-9E', $content);
        $this->assertStringNotContainsString('REF-EXPORT-B-9E', $content);
    }

    public function test_communication_settings_shows_queue_worker_readiness_note(): void
    {
        config(['queue.default' => 'database']);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.settings.communications.index'))
            ->assertOk()
            ->assertSee('data-testid="ota-queue-worker-readiness"', false)
            ->assertSee('requires a queue worker', false);
    }

    protected function seedBookingForAgency(Agency $agency, string $reference, ?Agent $agent = null, int $total = 100_000): Booking
    {
        $booking = Booking::factory()->for($agency)->create([
            'status' => BookingStatus::Ticketed,
            'payment_status' => 'paid',
            'booking_reference' => $reference,
            'route' => 'LHE-DXB',
            'agent_id' => $agent?->id,
        ]);
        $booking->fareBreakdown()->create([
            'base_fare' => max(0, $total - 20_000),
            'taxes' => 10_000,
            'fees' => 5_000,
            'markup' => 5_000,
            'discount' => 0,
            'total' => $total,
        ]);

        return $booking;
    }

    /**
     * @return array{0: User, 1: Agent}
     */
    protected function seedAgentForAgency(Agency $agency): array
    {
        $user = User::query()->create([
            'name' => 'Agent '.$agency->slug,
            'username' => 'agent-'.$agency->slug,
            'email' => 'agent-'.$agency->slug.'@9e.test',
            'password' => bcrypt('password'),
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);
        $agent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'code' => 'AGT-'.strtoupper(substr($agency->slug, 0, 6)),
        ]);

        return [$user->fresh(), $agent];
    }

    protected function staffWithPermissions(array $permissions): User
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => array_merge($staff->meta ?? [], ['staff_permissions' => $permissions]),
        ])->save();

        return $staff->fresh();
    }

    protected function enableOutboundNotifications(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true, 'smtp_enabled' => false],
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
                'digest_mode' => 'immediate',
                'recipient_emails' => ['admin@ota.demo'],
            ],
        );
        AgencyMessageTemplate::query()
            ->where('agency_id', $agency->id)
            ->where('event', OtaNotificationEvent::BookingManualReviewRequired->value)
            ->where('channel', 'email')
            ->where('is_enabled', false)
            ->delete();
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
            'booking_reference' => '9E-TEST-'.strtoupper(bin2hex(random_bytes(2))),
        ]);
        $booking->contact()->create([
            'email' => 'traveler-9e@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
            'meta' => ['name' => 'Test Traveler'],
        ]);
        $booking->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Test',
            'last_name' => 'Traveler',
        ]);

        return $booking;
    }
}
