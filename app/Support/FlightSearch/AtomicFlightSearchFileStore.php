<?php

namespace App\Support\FlightSearch;

use Illuminate\Support\Facades\Log;

/**
 * Concurrent-safe progressive search payload store.
 *
 * Laravel file cache uses exclusive flock during put(), which blocks peer poll
 * gets for the full serialize+write of large Return payloads (REG-03 measured
 * POLL_RESPONSE_SERVER_P95 ≈ 2.6s). This store writes via temp+rename and reads
 * without exclusive locks so writers do not stall readers.
 *
 * No PII beyond search_id UUIDs in paths.
 */
final class AtomicFlightSearchFileStore
{
    private const DIR_RELATIVE = 'framework/cache/flight-search';

    /** @var array<string, float|int|null> */
    private static array $lastOp = [];

    public function pathFor(string $cacheKey): string
    {
        $safe = hash('sha256', $cacheKey);

        return storage_path(self::DIR_RELATIVE.'/'.$safe.'.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $cacheKey): ?array
    {
        $t0 = microtime(true);
        $path = $this->pathFor($cacheKey);
        self::$lastOp = [
            'op' => 'get',
            'lock_wait_ms' => 0.0,
            'read_ms' => null,
            'deserialize_ms' => null,
            'bytes' => null,
            'hit' => false,
        ];

        if (! is_file($path)) {
            self::$lastOp['read_ms'] = round((microtime(true) - $t0) * 1000, 3);

            return null;
        }

        $readStart = microtime(true);
        // No flock: atomic rename writers publish complete files; readers may see
        // previous generation or new generation, never a torn write.
        $raw = @file_get_contents($path);
        $readMs = (microtime(true) - $readStart) * 1000;
        self::$lastOp['read_ms'] = round($readMs, 3);

        if ($raw === false || $raw === '') {
            return null;
        }

        self::$lastOp['bytes'] = strlen($raw);
        $deserStart = microtime(true);
        $decoded = json_decode($raw, true);
        self::$lastOp['deserialize_ms'] = round((microtime(true) - $deserStart) * 1000, 3);
        self::$lastOp['total_ms'] = round((microtime(true) - $t0) * 1000, 3);

        if (! is_array($decoded)) {
            return null;
        }

        $expiresAt = (int) ($decoded['_expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            @unlink($path);

            return null;
        }

        $payload = $decoded['payload'] ?? null;
        if (! is_array($payload)) {
            return null;
        }

        self::$lastOp['hit'] = true;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(string $cacheKey, array $payload, int $ttlSeconds): void
    {
        $t0 = microtime(true);
        $path = $this->pathFor($cacheKey);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $envelope = [
            '_expires_at' => time() + max(1, $ttlSeconds),
            'payload' => $payload,
        ];

        $serStart = microtime(true);
        $json = json_encode($envelope, JSON_UNESCAPED_SLASHES);
        $serMs = (microtime(true) - $serStart) * 1000;
        if ($json === false) {
            Log::warning('flight_search.atomic_store.encode_failed', [
                'cache_key_fp' => substr(hash('sha256', $cacheKey), 0, 12),
            ]);

            return;
        }

        $tmp = $path.'.'.getmypid().'.'.bin2hex(random_bytes(4)).'.tmp';
        $writeStart = microtime(true);
        $ok = @file_put_contents($tmp, $json);
        if ($ok === false) {
            @unlink($tmp);

            return;
        }
        // Atomic publish on same filesystem — readers never observe partial JSON.
        if (! @rename($tmp, $path)) {
            @unlink($tmp);

            return;
        }
        // Drop PHP stat cache so peer poll workers cannot briefly miss the new inode.
        clearstatcache(true, $path);
        $writeMs = (microtime(true) - $writeStart) * 1000;

        self::$lastOp = [
            'op' => 'put',
            'lock_wait_ms' => 0.0,
            'serialize_ms' => round($serMs, 3),
            'write_ms' => round($writeMs, 3),
            'bytes' => strlen($json),
            'total_ms' => round((microtime(true) - $t0) * 1000, 3),
        ];
    }

    public function forget(string $cacheKey): void
    {
        $path = $this->pathFor($cacheKey);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return array<string, float|int|bool|string|null>
     */
    public static function lastOperationMetrics(): array
    {
        return self::$lastOp;
    }
}
