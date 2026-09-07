<?php

namespace App\Jobs\Notifications;

use App\Models\NotificationOutbox;
use App\Services\Notifications\NotificationPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchNotificationOutboxEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $outboxId, ?string $queueName = null)
    {
        $queue = $queueName
            ?: ((bool) config('notifications.pipeline.async', false)
                ? (string) config('notifications.queues.transactional', 'notifications-transactional')
                : (string) config('notifications.pipeline.compat_queue', 'default'));
        $this->onQueue($queue);
    }

    public function handle(NotificationPipeline $pipeline): void
    {
        $row = NotificationOutbox::query()->find($this->outboxId);
        if ($row === null) {
            return;
        }

        $pipeline->processOutbox($row);
    }
}
