<?php

namespace App\Services\Client;

use App\Support\Client\ClientPublicWebrootPath;
use Illuminate\Support\Facades\File;

/**
 * Mirrors storage/public disk assets into Laravel public/storage and the configured live webroot.
 */
final class ClientPageAssetPublicationService
{
    /**
     * @return list<string> absolute paths written
     */
    public function publishPublicDiskRelativePath(string $relativePath): array
    {
        $relative = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relative === '') {
            return [];
        }

        $source = storage_path('app/public/'.$relative);
        if (! is_file($source)) {
            return [];
        }

        $written = [];

        $laravelMirror = public_path('storage/'.$relative);
        File::ensureDirectoryExists(dirname($laravelMirror));
        if ($this->copyIfChanged($source, $laravelMirror)) {
            $written[] = $laravelMirror;
        }

        if (ClientPublicWebrootPath::usingConfiguredPath()) {
            $webrootMirror = ClientPublicWebrootPath::path('storage/'.$relative);
            File::ensureDirectoryExists(dirname($webrootMirror));
            if ($this->copyIfChanged($source, $webrootMirror)) {
                $written[] = $webrootMirror;
            }
        }

        return $written;
    }

    /**
     * @param  list<string>  $relativePaths
     * @return list<string>
     */
    public function publishManyPublicDiskRelativePaths(array $relativePaths): array
    {
        $written = [];
        foreach ($relativePaths as $relativePath) {
            if (! is_string($relativePath) || trim($relativePath) === '') {
                continue;
            }

            $written = array_merge($written, $this->publishPublicDiskRelativePath($relativePath));
        }

        return array_values(array_unique($written));
    }

    public function deletePublishedRelativePath(string $relativePath): void
    {
        $relative = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relative === '') {
            return;
        }

        $paths = [
            public_path('storage/'.$relative),
        ];

        if (ClientPublicWebrootPath::usingConfiguredPath()) {
            $paths[] = ClientPublicWebrootPath::path('storage/'.$relative);
        }

        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function copyIfChanged(string $source, string $target): bool
    {
        if (is_file($target) && hash_file('sha256', $source) === hash_file('sha256', $target)) {
            return false;
        }

        return copy($source, $target);
    }
}
