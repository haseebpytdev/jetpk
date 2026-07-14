<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use Illuminate\Support\Facades\File;

/**
 * TTL-based preview vs commit execution locks for PIA NDC cancel diagnostics.
 */
class PiaNdcCancelExecutionLock
{
    public const TTL_MINUTES = 30;

    public function __construct(
        private readonly ?string $diagnosticsRoot = null,
    ) {}

    public function lockKindForOperation(string $operationKey): string
    {
        return $operationKey === 'cancel_preview' ? 'preview' : 'commit';
    }

    public function acquire(string $kind, int $connectionId, string $orderId, string $ownerCode): string
    {
        $lockPath = $this->lockPath($kind, $connectionId, $orderId, $ownerCode);
        File::ensureDirectoryExists(dirname($lockPath));

        if (is_file($lockPath) && ! $this->isStale($lockPath)) {
            throw new PiaNdcValidationException(
                'duplicate_execution_guard',
                422,
                'A recent cancel diagnostic '.$kind.' execution exists for this order.',
            );
        }

        if (is_file($lockPath)) {
            @unlink($lockPath);
        }

        file_put_contents($lockPath, json_encode([
            'kind' => $kind,
            'connection_id' => $connectionId,
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'locked_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES));

        return $lockPath;
    }

    public function lockPath(string $kind, int $connectionId, string $orderId, string $ownerCode): string
    {
        $hash = hash('sha256', implode('|', [(string) $connectionId, $orderId, $ownerCode]));
        $subdir = $kind === 'preview' ? 'order-cancel-preview-locks' : 'order-cancel-commit-locks';

        return $this->root().'/'.$subdir.'/'.$hash.'.lock';
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
        foreach (['order-cancel-preview-locks', 'order-cancel-commit-locks', 'order-cancel-locks'] as $subdir) {
            $directory = $this->root().'/'.$subdir;
            if (! is_dir($directory)) {
                continue;
            }
            foreach (glob($directory.'/*.lock') ?: [] as $lockPath) {
                if (! is_string($lockPath) || ! $this->isStale($lockPath)) {
                    continue;
                }
                if ($connectionId !== null) {
                    $contents = file_get_contents($lockPath);
                    $payload = is_string($contents) ? json_decode($contents, true) : null;
                    if (is_array($payload) && isset($payload['connection_id']) && (int) $payload['connection_id'] !== $connectionId) {
                        continue;
                    }
                }
                if (@unlink($lockPath)) {
                    $removed[] = $lockPath;
                }
            }
        }

        return $removed;
    }

    private function root(): string
    {
        return $this->diagnosticsRoot ?? storage_path('app/diagnostics/pia-ndc');
    }
}
