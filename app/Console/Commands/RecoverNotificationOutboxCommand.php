<?php

namespace App\Console\Commands;

use App\Models\NotificationOutbox;
use App\Services\Notifications\NotificationOutboxService;
use App\Services\Notifications\NotificationPipeline;
use Illuminate\Console\Command;

class RecoverNotificationOutboxCommand extends Command
{
    protected $signature = 'notifications:recover-outbox';

    protected $description = 'Release stale notification outbox locks and re-dispatch pending rows';

    public function handle(NotificationOutboxService $outbox, NotificationPipeline $pipeline): int
    {
        $recovered = $outbox->recoverStale();
        $dispatched = 0;

        $pending = NotificationOutbox::query()
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($pending as $row) {
            $pipeline->dispatchOutbox($row);
            $dispatched++;
        }

        $this->line('stale_recovered='.$recovered);
        $this->line('pending_dispatched='.$dispatched);

        return self::SUCCESS;
    }
}
