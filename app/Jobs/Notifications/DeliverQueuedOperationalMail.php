<?php

namespace App\Jobs\Notifications;

use App\Mail\BookingUniversalNotification;
use App\Mail\OtaOperationalNotificationMail;
use App\Models\CommunicationLog;
use App\Models\NotificationDelivery;
use App\Services\Notifications\NotificationDeliveryService;
use App\Support\Emails\AuthEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Concrete replacement for anonymous Mail queue closures.
 */
class DeliverQueuedOperationalMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [15, 45, 90];

    /**
     * @param  array<int, string>  $to
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  list<array{name: string, mime: string, content: string}>  $attachments
     * @param  array<string, mixed>|null  $universalPayload
     */
    public function __construct(
        public array $to,
        public array $cc,
        public array $bcc,
        public string $subject,
        public string $htmlBody,
        public string $plainBody,
        public array $attachments = [],
        public ?int $communicationLogId = null,
        public ?array $universalPayload = null,
        public ?int $deliveryId = null,
    ) {
        $queue = (bool) config('notifications.pipeline.async', false)
            ? (string) config('notifications.queues.transactional', 'notifications-transactional')
            : (string) config('notifications.pipeline.compat_queue', 'default');
        $this->onQueue($queue);
    }

    public function handle(NotificationDeliveryService $deliveries): void
    {
        $delivery = $this->deliveryId !== null
            ? NotificationDelivery::query()->find($this->deliveryId)
            : null;

        if ($delivery !== null && in_array($delivery->status, ['sent', 'delivered'], true)) {
            return;
        }

        if ($delivery !== null && ! $deliveries->beginAttempt($delivery)) {
            return;
        }

        try {
            $mail = $this->mailable();
            $pending = Mail::to($this->to);
            if ($this->cc !== []) {
                $pending->cc($this->cc);
            }
            if ($this->bcc !== []) {
                $pending->bcc($this->bcc);
            }
            $pending->send($mail);

            if ($this->communicationLogId !== null) {
                CommunicationLog::query()->whereKey($this->communicationLogId)->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
            }
            if ($delivery !== null) {
                $deliveries->markSent($delivery);
            }
        } catch (Throwable $e) {
            if ($this->communicationLogId !== null) {
                CommunicationLog::query()->whereKey($this->communicationLogId)->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                ]);
            }
            if ($delivery !== null) {
                $deliveries->markFailed($delivery, $e->getMessage());
            }

            throw $e;
        }
    }

    private function mailable(): BookingUniversalNotification|OtaOperationalNotificationMail
    {
        if ($this->universalPayload !== null) {
            $type = (string) ($this->universalPayload['type'] ?? '');
            if ($type !== '' && str_starts_with($type, 'auth_')) {
                $rendered = app(AuthEmailRenderer::class)->loginSecurity($this->universalPayload);

                return new OtaOperationalNotificationMail(
                    $rendered->html,
                    $this->subject !== '' ? $this->subject : (string) ($this->universalPayload['subject'] ?? 'Security notice'),
                    $rendered->plainBody,
                    $this->attachments,
                );
            }

            return new BookingUniversalNotification($this->universalPayload);
        }

        return new OtaOperationalNotificationMail($this->htmlBody, $this->subject, $this->plainBody, $this->attachments);
    }
}
