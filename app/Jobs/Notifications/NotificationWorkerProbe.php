<?php

namespace App\Jobs\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationWorkerProbe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public string $token, string $queueName)
    {
        $this->onQueue($queueName);
    }

    public function handle(): void
    {
        $payload = [
            'token' => $this->token,
            'queue' => $this->queue,
            'consumed_at' => now()->toIso8601String(),
        ];
        Cache::put(self::cacheKey($this->token), $payload, 3600);
        Log::info('notification.worker.probe', $payload);
    }

    public static function cacheKey(string $token): string
    {
        return 'notification.worker.probe.'.$token;
    }
}
