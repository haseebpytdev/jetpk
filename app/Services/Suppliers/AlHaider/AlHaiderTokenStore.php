<?php

namespace App\Services\Suppliers\AlHaider;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Encrypted durable Al-Haider bearer token store (server-side only).
 *
 * Uses storage/app/private — survives cache:clear and deploys when storage persists.
 * Writes are atomic (temp + rename) with restrictive directory/file permissions.
 */
final class AlHaiderTokenStore
{
    private const RELATIVE_PATH = 'private/suppliers/al-haider/auth-token.enc';

    private const DIRECTORY_MODE = 0750;

    private const FILE_MODE = 0600;

    public function load(): ?AlHaiderTokenRecord
    {
        $path = $this->absolutePath();
        if (! is_file($path)) {
            return null;
        }

        try {
            $encrypted = File::get($path);
            if (! is_string($encrypted) || trim($encrypted) === '') {
                return null;
            }

            $decoded = json_decode(Crypt::decryptString($encrypted), true);

            return is_array($decoded) ? AlHaiderTokenRecord::fromArray($decoded) : null;
        } catch (\Throwable $exception) {
            Log::warning('alhaider.auth.durable_load_failed', [
                'supplier' => 'alhaider',
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function save(AlHaiderTokenRecord $record): void
    {
        $path = $this->absolutePath();
        $directory = dirname($path);

        $this->ensureSecureDirectory($directory);

        $encrypted = Crypt::encryptString(json_encode($record->toArray(), JSON_THROW_ON_ERROR));
        $this->atomicReplace($path, $encrypted);

        Log::info('alhaider.auth.durable_saved', [
            'supplier' => 'alhaider',
            'expires_at' => $record->expiresAt,
            'source' => $record->source,
        ]);
    }

    public function markInvalidated(?AlHaiderTokenRecord $record): void
    {
        if ($record === null) {
            return;
        }

        $this->save($record->invalidated(time()));
    }

    public function hasValidToken(): bool
    {
        $record = $this->load();

        return $record !== null && $record->isValid(time(), $this->expiryMarginSeconds());
    }

    public function expiryMarginSeconds(): int
    {
        return max(60, (int) config('suppliers.al_haider.token_expiry_margin_seconds', 86_400));
    }

    public function absolutePath(): string
    {
        return storage_path('app/'.self::RELATIVE_PATH);
    }

    public function directoryMode(): int
    {
        return self::DIRECTORY_MODE;
    }

    public function fileMode(): int
    {
        return self::FILE_MODE;
    }

    private function ensureSecureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            if (! mkdir($directory, self::DIRECTORY_MODE, true) && ! is_dir($directory)) {
                throw new RuntimeException('Failed to create Al-Haider token directory.');
            }
        }

        $this->applyMode($directory, self::DIRECTORY_MODE);
    }

    /**
     * Atomically replace the authoritative token file without truncating it in place.
     *
     * A failed replacement leaves any existing encrypted record intact.
     */
    private function atomicReplace(string $path, string $encrypted): void
    {
        $this->verifyDecryptablePayload($encrypted);

        $directory = dirname($path);
        $tempPath = $directory.'/auth-token.'.bin2hex(random_bytes(8)).'.tmp';

        try {
            $written = file_put_contents($tempPath, $encrypted, LOCK_EX);
            if ($written === false || $written !== strlen($encrypted)) {
                throw new RuntimeException('Failed to write Al-Haider token temporary file.');
            }

            if (! is_file($tempPath) || filesize($tempPath) === 0) {
                throw new RuntimeException('Al-Haider token temporary file is empty.');
            }

            $this->applyMode($tempPath, self::FILE_MODE);

            if (! rename($tempPath, $path)) {
                throw new RuntimeException('Failed to atomically replace Al-Haider token file.');
            }

            $this->applyMode($path, self::FILE_MODE);
        } catch (\Throwable $exception) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            throw $exception;
        }
    }

    private function verifyDecryptablePayload(string $encrypted): void
    {
        $decoded = json_decode(Crypt::decryptString($encrypted), true);
        if (! is_array($decoded) || AlHaiderTokenRecord::fromArray($decoded) === null) {
            throw new RuntimeException('Al-Haider token payload failed validation before persistence.');
        }
    }

    private function applyMode(string $path, int $mode): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            return;
        }

        @chmod($path, $mode);
    }
}
