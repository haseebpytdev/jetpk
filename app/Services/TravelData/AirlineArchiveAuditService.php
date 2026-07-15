<?php

namespace App\Services\TravelData;

use Illuminate\Support\Facades\File;

/**
 * Validate airline logo tar archives before extraction (read-only).
 */
final class AirlineArchiveAuditService
{
    /** @var list<string> */
    private const ALLOWED_PREFIXES = [
        'airline-logos/',
        'travel-assets/airlines/logos/',
    ];

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

    public function __construct(
        private readonly AirlineImageContentValidator $contentValidator,
    ) {}

    /**
     * @return array{pass: bool, fail_count: int, issues: list<array<string, mixed>>, entry_count: int}
     */
    public function audit(string $archivePath): array
    {
        if (! is_file($archivePath)) {
            return [
                'pass' => false,
                'fail_count' => 1,
                'issues' => [['type' => 'missing_archive', 'path' => $archivePath]],
                'entry_count' => 0,
            ];
        }

        $entries = $this->listTarEntries($archivePath);
        if ($entries === null) {
            return [
                'pass' => false,
                'fail_count' => 1,
                'issues' => [['type' => 'unreadable_archive', 'path' => $archivePath]],
                'entry_count' => 0,
            ];
        }

        $validated = $this->validateEntries($entries);
        $contentIssues = $validated['issues'] === []
            ? $this->validateArchiveContent($archivePath)
            : [];

        $issues = array_merge($validated['issues'], $contentIssues);

        return [
            'pass' => $issues === [],
            'fail_count' => count($issues),
            'issues' => $issues,
            'entry_count' => $validated['entry_count'],
        ];
    }

    /**
     * @param  list<array{name: string, type: string, size: int}>  $entries
     * @return array{issues: list<array<string, mixed>>, entry_count: int}
     */
    public function validateEntries(array $entries): array
    {
        $issues = [];
        $fileCount = 0;

        foreach ($entries as $entry) {
            $name = $this->normalizeArchivePath($entry['name']);
            $type = $entry['type'];

            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }
            $fileCount++;

            if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
                $issues[] = ['type' => 'absolute_path', 'path' => $name];
            }
            if (str_contains($name, '../') || str_starts_with($name, '..')) {
                $issues[] = ['type' => 'traversal', 'path' => $name];
            }
            if (in_array($type, ['symlink', 'link'], true)) {
                $issues[] = ['type' => 'symlink_or_hardlink', 'path' => $name, 'tar_type' => $type];
            }
            if (in_array($type, ['block', 'char', 'fifo'], true)) {
                $issues[] = ['type' => 'device_or_fifo', 'path' => $name, 'tar_type' => $type];
            }

            $allowed = false;
            foreach (self::ALLOWED_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                $issues[] = ['type' => 'outside_allowed_roots', 'path' => $name];
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                $issues[] = ['type' => 'unexpected_extension', 'path' => $name, 'extension' => $ext];
            }

            if ($entry['size'] === 0) {
                $issues[] = ['type' => 'zero_byte', 'path' => $name];
            }
        }

        return [
            'issues' => $issues,
            'entry_count' => $fileCount,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validateArchiveContent(string $archivePath): array
    {
        $staging = storage_path('app/audits/archive-audit-'.uniqid('', true));
        File::ensureDirectoryExists($staging);

        try {
            $code = 0;
            $output = [];
            exec('tar -xzf '.escapeshellarg($archivePath).' -C '.escapeshellarg($staging).' 2>&1', $output, $code);
            if ($code !== 0) {
                return [['type' => 'extract_failed', 'path' => $archivePath, 'output' => implode("\n", $output)]];
            }

            $issues = [];
            foreach (['airline-logos', 'travel-assets/airlines/logos'] as $relativeRoot) {
                $scanRoot = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeRoot);
                if (! is_dir($scanRoot)) {
                    continue;
                }

                foreach (File::allFiles($scanRoot) as $file) {
                    $relative = $relativeRoot.'/'.$file->getFilename();
                    $validated = $this->contentValidator->validateFile($file->getPathname(), $relative);
                    if (! $validated['valid_content']) {
                        $issues[] = [
                            'type' => 'invalid_image_content',
                            'path' => $relative,
                            'errors' => $validated['validation_errors'],
                        ];
                    }
                }
            }

            return $issues;
        } finally {
            File::deleteDirectory($staging);
        }
    }

    /**
     * @return list<array{name: string, type: string, size: int}>|null
     */
    private function listTarEntries(string $archivePath): ?array
    {
        $verbose = $this->listTarEntriesViaTar($archivePath, true);
        if ($verbose !== null) {
            return $verbose;
        }

        $plain = $this->listTarEntriesViaTar($archivePath, false);
        if ($plain !== null) {
            return $plain;
        }

        if (! class_exists(\PharData::class)) {
            return null;
        }

        try {
            $phar = new \PharData($archivePath);
            $entries = [];
            foreach ($phar as $name => $file) {
                if ($file->isDir()) {
                    continue;
                }
                $entries[] = [
                    'name' => $this->normalizeArchivePath(str_replace('\\', '/', (string) $name)),
                    'type' => 'file',
                    'size' => (int) $file->getSize(),
                ];
            }

            return $entries !== [] ? $entries : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{name: string, type: string, size: int}>|null
     */
    private function listTarEntriesViaTar(string $archivePath, bool $verbose): ?array
    {
        $output = [];
        $code = 0;
        $flag = $verbose ? '-tvzf' : '-tzf';
        exec('tar '.$flag.' '.escapeshellarg($archivePath).' 2>&1', $output, $code);
        if ($code !== 0 || $output === []) {
            return null;
        }

        $entries = [];
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($verbose) {
                if (str_starts_with($line, 'd')) {
                    continue;
                }
                if (! preg_match('/\s(\d+)\s+\w{3}\s+\d+\s+\d{2}:\d{2}\s+(.+)$/', $line, $matches)) {
                    continue;
                }
                $entries[] = [
                    'name' => $this->normalizeArchivePath($matches[2]),
                    'type' => 'file',
                    'size' => (int) $matches[1],
                ];

                continue;
            }

            if (str_ends_with($line, '/')) {
                continue;
            }

            $entries[] = [
                'name' => $this->normalizeArchivePath($line),
                'type' => 'file',
                'size' => 1,
            ];
        }

        return $entries !== [] ? $entries : null;
    }

    private function normalizeArchivePath(string $name): string
    {
        $name = str_replace('\\', '/', trim($name));
        while (str_starts_with($name, './')) {
            $name = substr($name, 2);
        }

        return $name;
    }
}
