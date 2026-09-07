<?php

namespace App\Console\Commands;

use App\Models\NotificationDelivery;
use App\Models\NotificationOutbox;
use Illuminate\Console\Command;

class NotificationPipelineStatusCommand extends Command
{
    protected $signature = 'notifications:status';

    protected $description = 'Read-only notification outbox and delivery backlog counts';

    public function handle(): int
    {
        $pending = NotificationOutbox::query()->where('status', 'pending')->count();
        $failedOutbox = NotificationOutbox::query()->where('status', 'failed')->count();
        $oldest = NotificationOutbox::query()->where('status', 'pending')->orderBy('id')->value('created_at');
        $failedDeliveries = NotificationDelivery::query()->where('status', 'failed')->count();

        $this->line('outbox_pending='.$pending);
        $this->line('outbox_processing='.NotificationOutbox::query()->where('status', 'processing')->count());
        $this->line('outbox_failed='.$failedOutbox);
        $this->line('oldest_pending='.($oldest ?? 'none'));
        $this->line('deliveries_failed='.$failedDeliveries);
        $this->line('async='.(config('notifications.pipeline.async') ? 'true' : 'false'));
        $this->line('queue_connection='.(string) config('queue.default'));
        $this->line('pipeline_enabled='.(config('notifications.pipeline.enabled') ? 'true' : 'false'));
        $failedJobs = 0;
        try {
            $failedJobs = (int) \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            $failedJobs = -1;
        }
        $this->line('failed_jobs='.$failedJobs);

        return self::SUCCESS;
    }
}
