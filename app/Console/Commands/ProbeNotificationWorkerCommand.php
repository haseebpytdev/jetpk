<?php

namespace App\Console\Commands;

use App\Jobs\Notifications\NotificationWorkerProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProbeNotificationWorkerCommand extends Command
{
    protected $signature = 'notifications:probe-worker
        {--queue= : Single queue name}
        {--wait=90 : Seconds to wait for consumption}';

    protected $description = 'Dispatch a no-email probe job onto notification queues and wait for the worker';

    public function handle(): int
    {
        $wait = max(5, (int) $this->option('wait'));
        $single = trim((string) $this->option('queue'));
        $queues = $single !== ''
            ? [$single]
            : [
                (string) config('notifications.queues.critical'),
                (string) config('notifications.queues.transactional'),
                (string) config('notifications.queues.ops'),
                (string) config('notifications.queues.bulk'),
            ];

        $started = microtime(true);
        $tokens = [];
        foreach ($queues as $queue) {
            $token = (string) Str::uuid();
            $tokens[$queue] = $token;
            dispatch(new NotificationWorkerProbe($token, $queue));
            $this->line('PROBE_DISPATCHED queue='.$queue.' token='.$token);
        }

        $deadline = time() + $wait;
        $consumed = [];
        while (time() <= $deadline && count($consumed) < count($tokens)) {
            foreach ($tokens as $queue => $token) {
                if (isset($consumed[$queue])) {
                    continue;
                }
                $hit = Cache::get(NotificationWorkerProbe::cacheKey($token));
                if (is_array($hit) && ($hit['token'] ?? null) === $token) {
                    $consumed[$queue] = $hit;
                    $latency = (int) round((microtime(true) - $started) * 1000);
                    $this->line('PROBE_CONSUMED queue='.$queue.' token='.$token.' consumed_at='.($hit['consumed_at'] ?? '').' latency_ms='.$latency);
                }
            }
            if (count($consumed) < count($tokens)) {
                usleep(500000);
            }
        }

        $allPass = count($consumed) === count($tokens);
        foreach ($tokens as $queue => $token) {
            if (! isset($consumed[$queue])) {
                $this->line('PROBE_TIMEOUT queue='.$queue.' token='.$token);
            }
        }
        $this->line('PROBE_ALL_QUEUES='.($allPass ? 'PASS' : 'FAIL'));
        $this->line('MAX_OBSERVED_LATENCY_MS='.(int) round((microtime(true) - $started) * 1000));

        return $allPass ? self::SUCCESS : self::FAILURE;
    }
}
