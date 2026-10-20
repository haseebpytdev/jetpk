<?php

namespace Tests\Feature\Communication;

use App\Jobs\Notifications\DeliverQueuedOperationalMail;
use App\Models\Agency;
use App\Models\CommunicationLog;
use App\Services\Communication\OtaNotificationService;
use App\Services\Communication\RecipientDeliverySafetyService;
use App\Support\Communication\SmtpDeliveryFailureClassifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class RecipientDeliverySafetyServiceTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    #[Test]
    public function permanently_rejected_exact_address_is_suppressed_on_later_notification(): void
    {
        $this->seedPermanentReject('bad@example.test');

        Queue::fake();
        config([
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.notification_queue' => 'notifications-transactional',
        ]);

        $this->assertTrue(app(RecipientDeliverySafetyService::class)->isPermanentlyRejected('bad@example.test'));

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->invokeSendWithRecipients($agency, 'ops.def0029.suppress', ['bad@example.test']);

        $skippedLogs = CommunicationLog::query()
            ->where('event', 'ops.def0029.suppress')
            ->where('status', 'skipped')
            ->get();

        $this->assertGreaterThanOrEqual(1, $skippedLogs->count());
        $this->assertStringContainsString('permanent mailbox rejection', (string) $skippedLogs->first()->error_message);
        Queue::assertNotPushed(DeliverQueuedOperationalMail::class, function (DeliverQueuedOperationalMail $job): bool {
            return in_array('bad@example.test', $job->to, true);
        });
    }

    #[Test]
    public function suppression_survives_its_own_skipped_audit_rows_and_clears_after_real_sent(): void
    {
        $this->seedPermanentReject('durable@example.test');

        $safety = app(RecipientDeliverySafetyService::class);
        $this->assertTrue($safety->isPermanentlyRejected('durable@example.test'));

        Queue::fake();
        Mail::fake();
        config([
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.notification_queue' => 'notifications-transactional',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $this->invokeSendWithRecipients($agency, 'ops.def0029.durable.1', ['durable@example.test']);

        $this->assertTrue(
            CommunicationLog::query()
                ->where('event', 'ops.def0029.durable.1')
                ->where('status', 'skipped')
                ->where('recipient_email', 'durable@example.test')
                ->exists()
        );
        $this->assertTrue($safety->isPermanentlyRejected('durable@example.test'));
        Queue::assertNotPushed(DeliverQueuedOperationalMail::class);

        Queue::fake();
        $this->invokeSendWithRecipients($agency, 'ops.def0029.durable.2', ['durable@example.test']);

        $this->assertTrue(
            CommunicationLog::query()
                ->where('event', 'ops.def0029.durable.2')
                ->where('status', 'skipped')
                ->where('recipient_email', 'durable@example.test')
                ->exists()
        );
        $this->assertTrue($safety->isPermanentlyRejected('durable@example.test'));
        Queue::assertNotPushed(DeliverQueuedOperationalMail::class);

        CommunicationLog::query()->create([
            'agency_id' => $agency->id,
            'channel' => 'email',
            'event' => 'ops.def0029.durable.sent',
            'status' => 'sent',
            'recipient_email' => 'durable@example.test',
            'subject' => 'later success',
            'sent_at' => now(),
            'meta' => [],
        ]);
        $this->assertFalse($safety->isPermanentlyRejected('durable@example.test'));

        $this->seedPermanentReject('durable-delivered@example.test');
        $this->assertTrue($safety->isPermanentlyRejected('durable-delivered@example.test'));
        CommunicationLog::query()->create([
            'agency_id' => $agency->id,
            'channel' => 'email',
            'event' => 'ops.def0029.durable.delivered',
            'status' => 'delivered',
            'recipient_email' => 'durable-delivered@example.test',
            'subject' => 'later delivered',
            'sent_at' => now(),
            'meta' => [],
        ]);
        $this->assertFalse($safety->isPermanentlyRejected('durable-delivered@example.test'));
    }

    #[Test]
    public function transient_450_address_is_not_suppressed(): void
    {
        $this->seedFailedClassification(
            'temp@example.test',
            SmtpDeliveryFailureClassifier::TRANSIENT,
            true,
            '450',
            '4.7.1',
        );

        $this->assertFalse(app(RecipientDeliverySafetyService::class)->isPermanentlyRejected('temp@example.test'));
    }

    #[Test]
    public function unknown_failure_address_is_not_suppressed(): void
    {
        $this->seedFailedClassification(
            'unknown@example.test',
            SmtpDeliveryFailureClassifier::UNKNOWN,
            true,
            null,
            null,
        );

        $this->assertFalse(app(RecipientDeliverySafetyService::class)->isPermanentlyRejected('unknown@example.test'));
    }

    #[Test]
    public function corrected_new_email_address_is_allowed(): void
    {
        $this->seedPermanentReject('old@example.test');

        $safety = app(RecipientDeliverySafetyService::class);
        $this->assertTrue($safety->isPermanentlyRejected('old@example.test'));
        $this->assertFalse($safety->isPermanentlyRejected('new@example.test'));

        $partition = $safety->partitionEmails(['old@example.test', 'new@example.test']);
        $this->assertSame(['new@example.test'], $partition['allowed']);
        $this->assertSame(['old@example.test'], $partition['suppressed']);
    }

    #[Test]
    public function one_suppressed_and_one_valid_recipient_still_queues_valid(): void
    {
        $this->seedPermanentReject('bad@example.test');

        Mail::fake();
        Queue::fake();
        config([
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.notification_queue' => 'notifications-transactional',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->invokeSendWithRecipients($agency, 'ops.def0029.multi', ['bad@example.test', 'good@example.test']);

        Queue::assertPushed(DeliverQueuedOperationalMail::class, function (DeliverQueuedOperationalMail $job): bool {
            return $job->queue === 'notifications-transactional'
                && $job->to === ['good@example.test']
                && ! in_array('bad@example.test', $job->to, true);
        });

        $this->assertTrue(
            CommunicationLog::query()
                ->where('event', 'ops.def0029.multi')
                ->where('status', 'skipped')
                ->where('recipient_email', 'bad@example.test')
                ->exists()
        );
    }

    #[Test]
    public function suppressed_recipient_creates_auditable_skipped_outcome(): void
    {
        $this->seedPermanentReject('audit@example.test');
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        Queue::fake();
        config(['queue.default' => 'database', 'mail.default' => 'smtp']);

        $this->invokeSendWithRecipients($agency, 'ops.def0029.audit', ['audit@example.test']);

        $log = CommunicationLog::query()
            ->where('event', 'ops.def0029.audit')
            ->where('status', 'skipped')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(RecipientDeliverySafetyService::SUPPRESSION_REASON, $log->error_message);
        $this->assertTrue((bool) ($log->meta['permanent_recipient_suppressed'] ?? false));
    }

    #[Test]
    public function successful_retry_clears_stale_failure_metadata(): void
    {
        $agencyId = Agency::query()->where('slug', 'asif-travels')->value('id');
        $log = CommunicationLog::query()->create([
            'agency_id' => $agencyId,
            'channel' => 'email',
            'event' => 'ops.def0029.clear',
            'status' => 'failed',
            'recipient_email' => 'retry@example.test',
            'subject' => 'clear meta',
            'error_message' => 'prior',
            'meta' => [
                'delivery_failure_class' => SmtpDeliveryFailureClassifier::TRANSIENT,
                'smtp_code' => '450',
                'smtp_enhanced_status' => '4.7.1',
                'retry_recommended' => true,
                'keep' => 'yes',
            ],
        ]);

        Mail::fake();
        (new DeliverQueuedOperationalMail(
            ['retry@example.test'],
            [],
            [],
            'clear meta',
            '<p>x</p>',
            'x',
            [],
            $log->id,
            null,
        ))->handle();

        $fresh = $log->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNull($fresh->error_message);
        $this->assertArrayNotHasKey('delivery_failure_class', $fresh->meta ?? []);
        $this->assertSame('yes', $fresh->meta['keep'] ?? null);
    }

    #[Test]
    public function agency_booking_activity_summary_does_not_requeue_permanent_agency_admin(): void
    {
        $this->seedPermanentReject('agency-admin-bad@example.test');

        Queue::fake();
        config([
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.notification_queue' => 'notifications-transactional',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->invokeSendWithRecipients($agency, 'agency_booking_activity_summary', ['agency-admin-bad@example.test']);

        Queue::assertNotPushed(DeliverQueuedOperationalMail::class);
        $this->assertTrue(
            CommunicationLog::query()
                ->where('event', 'agency_booking_activity_summary')
                ->where('status', 'skipped')
                ->where('recipient_email', 'agency-admin-bad@example.test')
                ->exists()
        );
    }

    #[Test]
    public function customer_transactional_valid_address_still_queues_on_notification_queue(): void
    {
        Queue::fake();
        config([
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.notification_queue' => 'notifications-transactional',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->invokeSendWithRecipients($agency, 'ops.def0029.customer', ['customer@example.test']);

        Queue::assertPushed(DeliverQueuedOperationalMail::class, function (DeliverQueuedOperationalMail $job): bool {
            return $job->queue === 'notifications-transactional'
                && $job->to === ['customer@example.test'];
        });
    }

    #[Test]
    public function permanent_job_failure_then_suppression_without_rethrow(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new UnexpectedResponseException(
            'Expected response code "250/251/252" but got code "550", with message "550 5.1.1 <perm@example.test>: Recipient address rejected: User unknown"'
        ));

        $agencyId = Agency::query()->where('slug', 'asif-travels')->value('id');
        $log = CommunicationLog::query()->create([
            'agency_id' => $agencyId,
            'channel' => 'email',
            'event' => 'ops.def0029.permjob',
            'status' => 'queued',
            'recipient_email' => 'perm@example.test',
            'subject' => 'perm',
            'meta' => [],
        ]);

        (new DeliverQueuedOperationalMail(
            ['perm@example.test'],
            [],
            [],
            'perm',
            '<p>x</p>',
            'x',
            [],
            $log->id,
            null,
        ))->handle();

        $this->assertSame('failed', $log->fresh()->status);
        $this->assertTrue(app(RecipientDeliverySafetyService::class)->isPermanentlyRejected('perm@example.test'));
    }

    private function seedPermanentReject(string $email): void
    {
        $this->seedFailedClassification(
            $email,
            SmtpDeliveryFailureClassifier::PERMANENT_RECIPIENT_REJECT,
            false,
            '550',
            '5.1.1',
        );
    }

    private function seedFailedClassification(
        string $email,
        string $class,
        bool $retryRecommended,
        ?string $smtpCode,
        ?string $enhanced,
    ): void {
        CommunicationLog::query()->create([
            'agency_id' => Agency::query()->where('slug', 'asif-travels')->value('id'),
            'channel' => 'email',
            'event' => 'ops.def0029.seed',
            'status' => 'failed',
            'recipient_email' => strtolower(trim($email)),
            'subject' => 'seed',
            'meta' => [
                'delivery_failure_class' => $class,
                'smtp_code' => $smtpCode,
                'smtp_enhanced_status' => $enhanced,
                'retry_recommended' => $retryRecommended,
            ],
        ]);
    }

    /**
     * @param  list<string>  $to
     */
    private function invokeSendWithRecipients(Agency $agency, string $eventKey, array $to): void
    {
        // Use reflection-free path: create a minimal send by temporarily stubbing resolver via direct
        // OtaNotificationService internals is heavy — call apply via public send after forcing
        // recipients through AgencyNotificationSetting explicit recipient_emails.
        \App\Models\AgencyNotificationSetting::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'event_key' => $eventKey,
                'channel' => 'email',
            ],
            [
                'enabled' => true,
                'recipient_emails' => $to,
            ],
        );

        \App\Models\AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true],
        );

        app(OtaNotificationService::class)->send(
            $agency,
            $eventKey,
            ['message' => 'test'],
            null,
            fallbackSubject: 'Test '.$eventKey,
            fallbackBody: 'Body',
        );
    }
}
