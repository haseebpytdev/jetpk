<?php

namespace App\Services\TravelData;

use Illuminate\Support\Facades\File;

/**
 * Build jetpk-airline-logos.tgz from approved public-disk logo directories.
 */
final class AirlineAssetArchiveBuildService
{
    /** @var list<string> */
    private const RELATIVE_DIRS = [
        'airline-logos',
        'travel-assets/airlines/logos',
    ];

    public function __construct(
        private readonly AirlineArchiveAuditService $audit,
    ) {}

    /**
     * @return array{pass: bool, archive: string, sha256: string, entry_count: int, audit: array<string, mixed>}
     */
    public function build(string $outputPath, ?string $publicRoot = null): array
    {
        $publicRoot = rtrim($publicRoot ?? storage_path('app/public'), DIRECTORY_SEPARATOR);
        $outputPath = rtrim($outputPath, DIRECTORY_SEPARATOR);

        $staging = storage_path('app/audits/archive-build-'.uniqid('', true));
        File::ensureDirectoryExists($staging);

        try {
            $fileCount = 0;
            foreach (self::RELATIVE_DIRS as $relative) {
                $sourceDir = $publicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (! is_dir($sourceDir)) {
                    continue;
                }

                foreach (File::allFiles($sourceDir) as $file) {
                    $target = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative)
                        .DIRECTORY_SEPARATOR.$file->getFilename();
                    File::ensureDirectoryExists(dirname($target));
                    File::copy($file->getPathname(), $target);
                    $fileCount++;
                }
            }

            if ($fileCount === 0) {
                return [
                    'pass' => false,
                    'archive' => $outputPath,
                    'sha256' => '',
                    'entry_count' => 0,
                    'audit' => [
                        'pass' => false,
                        'fail_count' => 1,
                        'issues' => [['type' => 'no_source_files', 'path' => $publicRoot]],
                        'entry_count' => 0,
                    ],
                ];
            }

            if (is_file($outputPath)) {
                unlink($outputPath);
            }

            $code = 0;
            $output = [];
            $members = implode(' ', array_map(
                static fn (string $relative): string => escapeshellarg($relative),
                self::RELATIVE_DIRS,
            ));
            $stagingArg = escapeshellarg($staging);
            $archiveArg = escapeshellarg($outputPath);
            exec("tar -czf {$archiveArg} -C {$stagingArg} {$members} 2>&1", $output, $code);
            if ($code !== 0) {
                return [
                    'pass' => false,
                    'archive' => $outputPath,
                    'sha256' => '',
                    'entry_count' => $fileCount,
                    'audit' => [
                        'pass' => false,
                        'fail_count' => 1,
                        'issues' => [['type' => 'tar_failed', 'output' => implode("\n", $output)]],
                        'entry_count' => 0,
                    ],
                ];
            }

            $audit = $this->audit->audit($outputPath);
            $sha256 = hash_file('sha256', $outputPath) ?: '';

            return [
                'pass' => (bool) ($audit['pass'] ?? false),
                'archive' => $outputPath,
                'sha256' => $sha256,
                'entry_count' => (int) ($audit['entry_count'] ?? 0),
                'audit' => $audit,
            ];
        } finally {
            File::deleteDirectory($staging);
        }
    }
}
