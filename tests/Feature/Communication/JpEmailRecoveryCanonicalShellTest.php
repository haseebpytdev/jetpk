<?php

namespace Tests\Feature\Communication;

use App\Enums\BookingStatus;
use App\Enums\OtaNotificationEvent;
use App\Jobs\Notifications\DeliverQueuedOperationalMail;
use App\Mail\JetpkOperationalEventMail;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Communication\BookingEmailPayloadFactory;
use App\Services\Communication\OtaNotificationService;
use App\Support\Emails\EmailRecipientRoleSubjectTagger;
use App\Support\Emails\JetpkBookingUniversalPayloadAdapter;
use App\Support\Emails\JetpkEmailEventRenderer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JpEmailRecoveryCanonicalShellTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_booking_request_uses_jetpk_shell_not_legacy_universal(): void
    {
        Mail::fake();
        config(['mail.default' => 'array']);
        $this->seed(OtaFoundationSeeder::class);
        $this->enableOutboundEmail();
        $booking = $this->makeBooking();

        app(BookingCommunicationService::class)->sendBookingRequestReceived($booking);

        Mail::assertSent(JetpkOperationalEventMail::class, function (JetpkOperationalEventMail $mail): bool {
            return str_contains($mail->htmlBody, 'jetpk-container')
                && ! str_contains($mail->htmlBody, 'Online travel assistance')
                && ! str_contains($mail->htmlBody, 'class="email-container"')
                && ! str_starts_with($mail->emailSubject, '[ADMIN]')
                && ! str_starts_with($mail->emailSubject, '[AGENT]')
                && ! str_starts_with($mail->emailSubject, '[STAFF]');
        });
    }

    #[Test]
    public function operational_universal_payload_keeps_pre_rendered_jetpk_html_on_queue_job(): void
    {
        Queue::fake();
        config([
            'mail.default' => 'smtp',
            'queue.default' => 'database',
        ]);
        $this->seed(OtaFoundationSeeder::class);
        $this->enableOutboundEmail();
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $booking = $this->makeBooking();

        $universal = app(BookingEmailPayloadFactory::class)->adminNewBookingAlert($booking);

        app(OtaNotificationService::class)->send(
            agency: $agency,
            eventKey: OtaNotificationEvent::BookingRequestReceived->value,
            payload: [
                'booking_reference' => $booking->reference_code,
                'universal_email' => $universal,
            ],
            booking: $booking,
            fallbackSubject: 'New customer booking — '.$booking->reference_code,
            fallbackBody: 'New booking alert',
            recipientContext: [
                'notify_buckets' => ['platform_admin'],
            ],
        );

        Queue::assertPushed(DeliverQueuedOperationalMail::class, function (DeliverQueuedOperationalMail $job): bool {
            return str_contains($job->htmlBody, 'jetpk-container')
                && ! str_contains($job->htmlBody, 'Online travel assistance')
                && str_starts_with($job->subject, '[ADMIN]');
        });
    }

    #[Test]
    public function multi_role_notify_buckets_fan_out_to_separate_subjects(): void
    {
        Queue::fake();
        config([
            'mail.default' => 'smtp',
            'queue.default' => 'database',
        ]);
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true, 'smtp_enabled' => false],
        );

        $staff = \App\Models\User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => \App\Enums\AccountType::Staff,
            'email' => 'jp-email-staff-fanout@example.test',
            'status' => \App\Enums\UserAccountStatus::Active,
        ]);
        $agency->users()->syncWithoutDetaching([$staff->id]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'JP-EMAIL-FANOUT-01',
            'route' => 'LHE → DXB',
            'travel_date' => now()->addDays(10),
            'status' => BookingStatus::Pending,
            'currency' => 'PKR',
            'assigned_staff_id' => $staff->id,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'jp-email-fanout-customer@example.test',
            'phone' => '+92000000999',
            'meta' => ['name' => 'Fanout Customer'],
        ]);

        $universal = app(BookingEmailPayloadFactory::class)->staffReviewRequired($booking->fresh());

        app(OtaNotificationService::class)->send(
            agency: $agency,
            eventKey: OtaNotificationEvent::BookingManualReviewRequired->value,
            payload: [
                'booking_reference' => $booking->reference_code,
                'universal_email' => $universal,
                'message' => 'Staff review required fan-out proof.',
            ],
            booking: $booking->fresh(['agency.agencySetting', 'assignedStaff', 'contact', 'customer']),
            fallbackSubject: 'Staff review required',
            fallbackBody: 'Review required',
            recipientContext: [
                'notify_buckets' => ['assigned_staff', 'platform_admin'],
            ],
        );

        $jobs = collect(Queue::pushed(DeliverQueuedOperationalMail::class));
        $this->assertGreaterThanOrEqual(2, $jobs->count(), 'Expected separate role delivery jobs');
        $subjects = $jobs->map(fn (DeliverQueuedOperationalMail $job): string => $job->subject);
        $this->assertTrue($subjects->contains(fn (string $s): bool => str_starts_with($s, '[STAFF]')), 'Missing [STAFF] subject: '.$subjects->implode(' | '));
        $this->assertTrue($subjects->contains(fn (string $s): bool => str_starts_with($s, '[ADMIN]')), 'Missing [ADMIN] subject: '.$subjects->implode(' | '));
        foreach ($jobs as $job) {
            $this->assertStringContainsString('jetpk-container', $job->htmlBody);
        }
    }

    #[Test]
    public function adapter_and_renderer_produce_plain_text_without_css_or_html_tags(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $booking = $this->makeBooking();
        $universal = app(BookingEmailPayloadFactory::class)->bookingReceived($booking);
        $payload = JetpkBookingUniversalPayloadAdapter::toJetpkPayload($universal, 'customer');
        $event = JetpkBookingUniversalPayloadAdapter::eventKeyFromUniversal($universal, 'booking_request_received');

        $rendered = app(JetpkEmailEventRenderer::class)->render(
            eventKey: $event,
            agency: $agency,
            payload: $payload,
        );

        $plain = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $rendered->html))));

        $this->assertStringContainsString('jetpk-container', $rendered->html);
        $this->assertStringNotContainsString('<style', $plain);
        $this->assertDoesNotMatchRegularExpression('/<\/?[a-z][^>]*>/i', $plain);
        $this->assertSame('[ADMIN] tagged', EmailRecipientRoleSubjectTagger::apply('tagged', 'admin'));
    }

    protected function makeBooking(): Booking
    {
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'JP-EMAIL-REC-01',
            'route' => 'LHE → DXB',
            'travel_date' => now()->addDays(10),
            'status' => BookingStatus::Pending,
            'currency' => 'PKR',
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'jp-email-recovery-customer@example.test',
            'phone' => '+92000000111',
            'meta' => ['name' => 'Recovery Customer'],
        ]);

        return $booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'customer', 'fareBreakdown']);
    }

    protected function enableOutboundEmail(): void
    {
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true, 'smtp_enabled' => false],
        );
    }
}
