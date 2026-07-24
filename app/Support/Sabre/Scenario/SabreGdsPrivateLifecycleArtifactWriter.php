<?php

namespace App\Support\Sabre\Scenario;

use Illuminate\Support\Facades\Storage;

/**
 * Atomic private lifecycle JSON artifacts (mode 0600 at creation).
 */
final class SabreGdsPrivateLifecycleArtifactWriter
{
    public const MODE_EXPECTED = 0600;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{relative_path: string, absolute_path: string, mode_expected: int, mode_actual: int|null}
     */
    public function write(string $relativePath, array $payload): array
    {
        $disk = Storage::disk('local');
        $absolutePath = $disk->path($relativePath);
        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        @chmod($directory, 0700);

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $tempPath = $absolutePath.'.tmp.'.bin2hex(random_bytes(8));
        file_put_contents($tempPath, $encoded, LOCK_EX);
        @chmod($tempPath, self::MODE_EXPECTED);
        rename($tempPath, $absolutePath);
        @chmod($absolutePath, self::MODE_EXPECTED);

        $perms = @fileperms($absolutePath);
        $modeActual = $perms !== false ? ($perms & 0777) : null;

        return [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'mode_expected' => self::MODE_EXPECTED,
            'mode_actual' => $modeActual,
        ];
    }
}
