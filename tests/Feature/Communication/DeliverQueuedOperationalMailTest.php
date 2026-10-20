<?php

namespace Tests\Feature\Communication;

use App\Jobs\Notifications\DeliverQueuedOperationalMail;
use App\Mail\OtaOperationalNotificationMail;
use App\Models\Agency;
use App\Models\CommunicationLog;
use App\Services\Communication\NotificationRecipientResolver;
use App\Services\Communication\OtaNotificationService;
use App\Support\Communication\SmtpDeliveryFailureClassifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class DeliverQueuedOperationalMailTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    #[Test]
    public function ota_notification_service_dispatches_named_job_on_notification_queue(): void
    {
        Mail::fake();
        Queue::fake();
        config([
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.notification_queue' => 'notifications-transactional',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        app(OtaNotificationService::class)->send(
            $agency,
            'ops.wave1c.canary',
            ['message' => 'queue routing canary'],
            null,
            fallbackSubject: 'Wave1C canary',
            fallbackBody: 'QA routing proof only.',
        );

        Queue::assertPushed(DeliverQueuedOperationalMail::class, function (DeliverQueuedOperationalMail $job): bool {
            return $job->queue === 'notifications-transactional'
                && $job->subject === 'Wave1C canary';
        });
        Queue::assertNotPushed(\Illuminate\Queue\CallQueuedClosure::class);
    }

    #[Test]
    public function job_sends_mail_and_marks_communication_log_sent(): void
    {
        Mail::fake();

        $log = $this->makeLog('ops.wave1c.test', 'queued');

        $job = $this->makeJob($log->id);
        $job->handle();

        Mail::assertSent(OtaOperationalNotificationMail::class);
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
        $this->assertNull($log->fresh()->error_message);
    }

    #[Test]
    public function job_skips_send_when_communication_log_already_sent(): void
    {
        Mail::fake();

        $log = $this->makeLog('ops.wave1c.dup', 'sent', sentAt: now());

        $this->makeJob($log->id)->handle();

        Mail::assertNothingSent();
    }

    #[Test]
    public function permanent_550_5_1_1_marks_failed_without_rethrow(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new UnexpectedResponseException(
            'Expected response code "250/251/252" but got code "550", with message "550 5.1.1 <qa@example.test>: Recipient address rejected: User unknown"'
        ));

        $log = $this->makeLog('ops.wave1c.perm', 'queued', meta: ['bucket' => 'operations_queue']);

        $this->makeJob($log->id)->handle();

        $fresh = $log->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame(SmtpDeliveryFailureClassifier::PERMANENT_RECIPIENT_REJECT, $fresh->meta['delivery_failure_class'] ?? null);
        $this->assertSame('550', $fresh->meta['smtp_code'] ?? null);
        $this->assertSame('5.1.1', $fresh->meta['smtp_enhanced_status'] ?? null);
        $this->assertFalse($fresh->meta['retry_recommended'] ?? true);
        $this->assertStringNotContainsString('qa@example.test', (string) $fresh->error_message);
        $this->assertSame('operations_queue', $fresh->meta['bucket'] ?? null);
    }

    #[Test]
    public function transient_450_4_7_1_marks_failed_and_rethrows(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new UnexpectedResponseException(
            'Expected response code "250/251/252" but got code "450", with message "450 4.7.1 <qa@example.test>: Recipient address rejected: Greylisted"'
        ));

        $log = $this->makeLog('ops.wave1c.transient', 'queued');

        try {
            $this->makeJob($log->id)->handle();
            $this->fail('Expected transient SMTP exception to rethrow');
        } catch (UnexpectedResponseException $e) {
            $this->assertStringContainsString('450', $e->getMessage());
        }

        $fresh = $log->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame(SmtpDeliveryFailureClassifier::TRANSIENT, $fresh->meta['delivery_failure_class'] ?? null);
        $this->assertTrue($fresh->meta['retry_recommended'] ?? false);
    }

    #[Test]
    public function generic_runtime_exception_marks_failed_and_rethrows(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp reject canary'));

        $log = $this->makeLog('ops.wave1c.fail', 'queued');

        try {
            $this->makeJob($log->id)->handle();
            $this->fail('Expected exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('smtp reject canary', $e->getMessage());
        }

        $this->assertSame('failed', $log->fresh()->status);
        $this->assertSame(SmtpDeliveryFailureClassifier::UNKNOWN, $log->fresh()->meta['delivery_failure_class'] ?? null);
    }

    #[Test]
    public function later_retry_after_transient_failure_can_mark_sent(): void
    {
        $log = $this->makeLog('ops.wave1c.retry', 'failed', meta: [
            'delivery_failure_class' => SmtpDeliveryFailureClassifier::TRANSIENT,
            'smtp_code' => '450',
            'smtp_enhanced_status' => '4.7.1',
            'retry_recommended' => true,
            'bucket' => 'keep_me',
        ]);
        $log->forceFill(['error_message' => 'prior transient'])->save();

        Mail::fake();
        $this->makeJob($log->id)->handle();

        $fresh = $log->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNull($fresh->error_message);
        $this->assertNotNull($fresh->sent_at);
        $this->assertArrayNotHasKey('delivery_failure_class', $fresh->meta ?? []);
        $this->assertArrayNotHasKey('smtp_code', $fresh->meta ?? []);
        $this->assertArrayNotHasKey('smtp_enhanced_status', $fresh->meta ?? []);
        $this->assertArrayNotHasKey('retry_recommended', $fresh->meta ?? []);
        $this->assertSame('keep_me', $fresh->meta['bucket'] ?? null);
    }

    #[Test]
    public function queued_mail_error_message_sanitizes_secrets_and_emails(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException(
            'Bearer abc.def.ghi password=supersecret api_key=xyz leaked@example.test'
        ));

        $log = $this->makeLog('ops.wave1c.sanitize', 'queued');

        try {
            $this->makeJob($log->id)->handle();
            $this->fail('Expected exception');
        } catch (RuntimeException) {
            // expected
        }

        $msg = (string) $log->fresh()->error_message;
        $this->assertStringNotContainsString('supersecret', $msg);
        $this->assertStringNotContainsString('abc.def.ghi', $msg);
        $this->assertStringNotContainsString('leaked@example.test', $msg);
        $this->assertStringContainsString('[redacted]', strtolower($msg));
    }

    #[Test]
    public function resolver_normalize_emails_drops_malformed_addresses(): void
    {
        $resolver = app(NotificationRecipientResolver::class);
        $ref = new ReflectionClass($resolver);
        $method = $ref->getMethod('normalizeEmails');
        $method->setAccessible(true);

        $normalized = $method->invoke($resolver, [
            'valid@example.test',
            'not-an-email',
            'a@',
            ' spaced @example.test ',
            'also.valid@example.test',
        ]);

        $this->assertSame(['valid@example.test', 'also.valid@example.test'], $normalized);
    }

    private function makeLog(string $event, string $status, mixed $sentAt = null, array $meta = []): CommunicationLog
    {
        return CommunicationLog::query()->create([
            'agency_id' => Agency::query()->where('slug', 'asif-travels')->value('id'),
            'channel' => 'email',
            'event' => $event,
            'status' => $status,
            'recipient_email' => 'qa@example.test',
            'subject' => $event,
            'sent_at' => $sentAt,
            'meta' => $meta,
        ]);
    }

    private function makeJob(int $logId): DeliverQueuedOperationalMail
    {
        return new DeliverQueuedOperationalMail(
            ['qa@example.test'],
            [],
            [],
            'test',
            '<p>body</p>',
            'body',
            [],
            $logId,
            null,
        );
    }
}
