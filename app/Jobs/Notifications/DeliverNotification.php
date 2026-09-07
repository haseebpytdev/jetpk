<?php

namespace App\Jobs\Notifications;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Communication\OtaNotificationService;
use App\Services\Notifications\NotificationDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeliverNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 90;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $templateVariables
     * @param  array<string, mixed>  $recipientContext
     */
    public function __construct(
        public int $deliveryId,
        public int $agencyId,
        public string $eventKey,
        public array $payload,
        public ?int $bookingId,
        public ?int $actorId,
        public ?string $fallbackSubject,
        public string $fallbackBody,
        public array $templateVariables,
        public array $recipientContext,
        ?string $queueName = null,
    ) {
        $queue = $queueName
            ?: ((bool) config('notifications.pipeline.async', false)
                ? (string) config('notifications.queues.transactional', 'notifications-transactional')
                : (string) config('notifications.pipeline.compat_queue', 'default'));
        $this->onQueue($queue);
    }

    public function handle(
        OtaNotificationService $notifications,
        NotificationDeliveryService $deliveries,
    ): void {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);
        if ($delivery === null) {
            return;
        }
        if (in_array($delivery->status, ['sent', 'delivered'], true)) {
            return;
        }

        $agency = Agency::query()->find($this->agencyId);
        if ($agency === null) {
            $deliveries->markFailed($delivery, 'Agency missing.');

            return;
        }

        $booking = $this->bookingId !== null ? Booking::query()->find($this->bookingId) : null;
        $actor = $this->actorId !== null ? User::query()->find($this->actorId) : null;

        $context = $this->recipientContext;
        $context['_pipeline_delivery'] = true;
        $context['notify_buckets'] = [$delivery->audience];
        $context['logged_in_user_email'] = $context['logged_in_user_email'] ?? $delivery->recipient;

        if (! $deliveries->beginAttempt($delivery)) {
            return;
        }

        try {
            $notifications->send(
                agency: $agency,
                eventKey: $this->eventKey,
                payload: $this->payload,
                booking: $booking,
                actor: $actor,
                fallbackSubject: $this->fallbackSubject,
                fallbackBody: $this->fallbackBody,
                templateVariables: $this->templateVariables,
                attachments: [],
                recipientContext: $context,
            );
            $deliveries->markSent($delivery);
        } catch (Throwable $e) {
            $deliveries->markFailed($delivery, $e->getMessage());
            throw $e;
        }
    }
}
