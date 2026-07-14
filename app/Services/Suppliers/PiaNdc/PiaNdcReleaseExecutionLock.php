<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use Illuminate\Support\Facades\File;

/**
 * Commit execution locks for PIA NDC option PNR release (R12F).
 * Blocks repeat release after successful commit; allows retry after failed pre-commit attempts when stale.
 */
class PiaNdcReleaseExecutionLock
{
    public const TTL_MINUTES = 30;

    public function __construct(
        private readonly ?string $diagnosticsRoot = null,
    ) {}

    public function lockPath(int $connectionId, string $orderId, string $ownerCode): string
    {
        $hash = hash('sha256', implode('|', [(string) $connectionId, $orderId, $ownerCode]));

        return $this->root().'/release-option-pnr-locks/'.$hash.'.lock';
    }

    public function isCommitted(int $connectionId, string $orderId, string $ownerCode): bool
    {
        $payload = $this->readPayload($this->lockPath($connectionId, $orderId, $ownerCode));

        return is_array($payload) && ($payload['committed'] ?? false) === true;
    }

    public function acquire(int $connectionId, string $orderId, string $ownerCode, string $correlationId): string
    {
        $lockPath = $this->lockPath($connectionId, $orderId, $ownerCode);
        File::ensureDirectoryExists(dirname($lockPath));

        if (is_file($lockPath)) {
            $payload = $this->readPayload($lockPath);
            if (is_array($payload) && ($payload['committed'] ?? false) === true) {
                throw new PiaNdcValidationException(
                    'release_already_committed',
                    422,
                    'Option PNR release was already committed for this order.',
                );
            }
            if (! $this->isStale($lockPath)) {
                throw new PiaNdcValidationException(
                    'duplicate_execution_guard',
                    422,
                    'A recent option PNR release attempt exists for this order.',
                );
            }
            @unlink($lockPath);
        }

        file_put_contents($lockPath, json_encode([
            'kind' => 'release_option_pnr_commit',
            'connection_id' => $connectionId,
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'correlation_id' => $correlationId,
            'committed' => false,
            'commit_success' => false,
            'locked_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES));

        return $lockPath;
    }

    public function markCommitted(string $lockPath, bool $commitSuccess, string $correlationId): void
    {
        if (! is_file($lockPath)) {
            return;
        }

        $payload = $this->readPayload($lockPath) ?? [];
        $payload['committed'] = $commitSuccess;
        $payload['commit_success'] = $commitSuccess;
        $payload['correlation_id'] = $correlationId;
        $payload['committed_at'] = now()->toIso8601String();

        file_put_contents($lockPath, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    public function isStale(string $lockPath): bool
    {
        if (! is_file($lockPath)) {
            return true;
        }

        $ageMinutes = (time() - (int) filemtime($lockPath)) / 60;

        return $ageMinutes >= self::TTL_MINUTES;
    }

    /**
     * @return list<string>
     */
    public function clearStaleLocks(?int $connectionId = null): array
    {
        $removed = [];
        $directory = $this->root().'/release-option-pnr-locks';
        if (! is_dir($directory)) {
            return $removed;
        }

        foreach (glob($directory.'/*.lock') ?: [] as $lockPath) {
            if (! is_string($lockPath) || ! $this->isStale($lockPath)) {
                continue;
            }
            $payload = $this->readPayload($lockPath);
            if (is_array($payload) && ($payload['committed'] ?? false) === true) {
                continue;
            }
            if ($connectionId !== null
                && is_array($payload)
                && isset($payload['connection_id'])
                && (int) $payload['connection_id'] !== $connectionId) {
                continue;
            }
            if (@unlink($lockPath)) {
                $removed[] = $lockPath;
            }
        }

        return $removed;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function readPayload(string $lockPath): ?array
    {
        if (! is_file($lockPath)) {
            return null;
        }

        $contents = file_get_contents($lockPath);
        $payload = is_string($contents) ? json_decode($contents, true) : null;

        return is_array($payload) ? $payload : null;
    }

    private function root(): string
    {
        return $this->diagnosticsRoot ?? storage_path('app/diagnostics/pia-ndc');
    }
}
