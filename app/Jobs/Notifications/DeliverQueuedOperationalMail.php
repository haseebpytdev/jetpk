<?php

namespace App\Jobs\Notifications;

use App\Mail\BookingUniversalNotification;
use App\Mail\OtaOperationalNotificationMail;
use App\Models\CommunicationLog;
use App\Support\Communication\NotificationErrorSanitizer;
use App\Support\Communication\SmtpDeliveryFailureClassifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Named replacement for anonymous Mail queue Closures.
 *
 * Always targets the notification worker queues (not implicit default).
 */
class DeliverQueuedOperationalMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 90;

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
    ) {
        $this->onQueue((string) config('mail.notification_queue', 'notifications-transactional'));
    }

    public function handle(): void
    {
        if ($this->communicationLogId !== null) {
            $status = CommunicationLog::query()->whereKey($this->communicationLogId)->value('status');
            if (in_array((string) $status, ['sent', 'delivered'], true)) {
                return;
            }
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
                $log = CommunicationLog::query()->whereKey($this->communicationLogId)->first();
                if ($log !== null) {
                    $meta = is_array($log->meta) ? $log->meta : [];
                    unset(
                        $meta['delivery_failure_class'],
                        $meta['smtp_code'],
                        $meta['smtp_enhanced_status'],
                        $meta['retry_recommended'],
                    );

                    $log->forceFill([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'error_message' => null,
                        'meta' => $meta,
                    ])->save();
                }
            }
        } catch (Throwable $e) {
            $classification = (new SmtpDeliveryFailureClassifier)->classify($e);
            $smtpPassword = config('mail.mailers.'.config('mail.default').'.password');
            $sanitized = NotificationErrorSanitizer::sanitize(
                $e->getMessage(),
                is_string($smtpPassword) ? $smtpPassword : null,
            );

            if ($this->communicationLogId !== null) {
                $log = CommunicationLog::query()->whereKey($this->communicationLogId)->first();
                if ($log !== null) {
                    $meta = is_array($log->meta) ? $log->meta : [];
                    $meta['delivery_failure_class'] = $classification['delivery_failure_class'];
                    $meta['smtp_code'] = $classification['smtp_code'];
                    $meta['smtp_enhanced_status'] = $classification['smtp_enhanced_status'];
                    $meta['retry_recommended'] = $classification['retry_recommended'];

                    $log->forceFill([
                        'status' => 'failed',
                        'error_message' => $sanitized,
                        'meta' => $meta,
                    ])->save();
                }
            }

            if ($classification['delivery_failure_class'] === SmtpDeliveryFailureClassifier::PERMANENT_RECIPIENT_REJECT) {
                return;
            }

            throw $e;
        }
    }

    private function mailable(): BookingUniversalNotification|OtaOperationalNotificationMail
    {
        if ($this->universalPayload !== null) {
            return new BookingUniversalNotification($this->universalPayload);
        }

        return new OtaOperationalNotificationMail($this->htmlBody, $this->subject, $this->plainBody, $this->attachments);
    }
}
