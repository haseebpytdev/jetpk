<?php

namespace Tests\Feature\Communication;

use App\Jobs\Notifications\DeliverQueuedOperationalMail;
use App\Mail\OtaOperationalNotificationMail;
use App\Models\Agency;
use App\Models\CommunicationLog;
use App\Services\Communication\OtaNotificationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
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

        $log = CommunicationLog::query()->create([
            'agency_id' => Agency::query()->where('slug', 'asif-travels')->value('id'),
            'channel' => 'email',
            'event' => 'ops.wave1c.test',
            'status' => 'queued',
            'recipient_email' => 'qa@example.test',
            'subject' => 'Wave1C job handle',
        ]);

        $job = new DeliverQueuedOperationalMail(
            ['qa@example.test'],
            [],
            [],
            'Wave1C job handle',
            '<p>body</p>',
            'body',
            [],
            $log->id,
            null,
        );

        $job->handle();

        Mail::assertSent(OtaOperationalNotificationMail::class);
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
    }

    #[Test]
    public function job_skips_send_when_communication_log_already_sent(): void
    {
        Mail::fake();

        $log = CommunicationLog::query()->create([
            'agency_id' => Agency::query()->where('slug', 'asif-travels')->value('id'),
            'channel' => 'email',
            'event' => 'ops.wave1c.dup',
            'status' => 'sent',
            'recipient_email' => 'qa@example.test',
            'subject' => 'already sent',
            'sent_at' => now(),
        ]);

        $job = new DeliverQueuedOperationalMail(
            ['qa@example.test'],
            [],
            [],
            'already sent',
            '<p>body</p>',
            'body',
            [],
            $log->id,
            null,
        );

        $job->handle();

        Mail::assertNothingSent();
    }

    #[Test]
    public function job_marks_failed_and_rethrows_on_mail_exception(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp reject canary'));

        $log = CommunicationLog::query()->create([
            'agency_id' => Agency::query()->where('slug', 'asif-travels')->value('id'),
            'channel' => 'email',
            'event' => 'ops.wave1c.fail',
            'status' => 'queued',
            'recipient_email' => 'qa@example.test',
            'subject' => 'fail path',
        ]);

        $job = new DeliverQueuedOperationalMail(
            ['qa@example.test'],
            [],
            [],
            'fail path',
            '<p>body</p>',
            'body',
            [],
            $log->id,
            null,
        );

        try {
            $job->handle();
            $this->fail('Expected exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('smtp reject canary', $e->getMessage());
        }

        $this->assertSame('failed', $log->fresh()->status);
    }
}
