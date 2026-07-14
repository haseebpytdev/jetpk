<?php

namespace App\Services\TravelData;

use Illuminate\Support\Facades\File;

/**
 * Build and compare airline logo asset manifests from a filesystem root.
 */
final class AirlineAssetManifestService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

    /** @var list<string> */
    private const ALLOWED_ROOTS = [
        'airline-logos',
        'travel-assets/airlines/logos',
        'images',
    ];

    public function __construct(
        private readonly AirlineImageContentValidator $contentValidator,
    ) {}

    /**
     * @return array{
     *     generated_at: string,
     *     root: string,
     *     entry_count: int,
     *     valid: bool,
     *     validation_fail_count: int,
     *     entries: list<array<string, mixed>>
     * }
     */
    public function buildFromRoot(string $absoluteRoot, bool $includeGeneric = true): array
    {
        $absoluteRoot = rtrim($absoluteRoot, DIRECTORY_SEPARATOR);
        $entries = [];

        foreach (self::ALLOWED_ROOTS as $relativeRoot) {
            if ($relativeRoot === 'images') {
                if ($includeGeneric) {
                    $generic = $absoluteRoot.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'airline-generic.svg';
                    if (is_file($generic)) {
                        $entries[] = $this->entry('images/airline-generic.svg', $generic);
                    }
                }

                continue;
            }

            $scanRoot = $absoluteRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeRoot);
            if (! is_dir($scanRoot)) {
                continue;
            }

            foreach (File::allFiles($scanRoot) as $file) {
                $ext = strtolower($file->getExtension());
                if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    continue;
                }
                $relative = $relativeRoot.'/'.$file->getFilename();
                $entries[] = $this->entry($relative, $file->getPathname());
            }
        }

        usort($entries, static fn (array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']));

        return $this->finalizeManifest($absoluteRoot, $entries);
    }

    /**
     * @return array{
     *     generated_at: string,
     *     root: string,
     *     entry_count: int,
     *     valid: bool,
     *     validation_fail_count: int,
     *     entries: list<array<string, mixed>>
     * }
     */
    public function buildFromArchive(string $archivePath): array
    {
        $archivePath = rtrim($archivePath, DIRECTORY_SEPARATOR);
        if (! is_file($archivePath)) {
            return $this->finalizeManifest($archivePath, []);
        }

        $staging = storage_path('app/audits/manifest-build-'.uniqid('', true));
        File::ensureDirectoryExists($staging);

        try {
            $code = 0;
            $output = [];
            exec('tar -xzf '.escapeshellarg($archivePath).' -C '.escapeshellarg($staging).' 2>&1', $output, $code);
            if ($code !== 0) {
                return $this->finalizeManifest('archive:'.$archivePath, []);
            }

            $manifest = $this->buildFromRoot($staging, false);
            $manifest['root'] = 'archive:'.$archivePath;

            return $manifest;
        } finally {
            File::deleteDirectory($staging);
        }
    }

    public function manifestIsValid(array $manifest): bool
    {
        if (($manifest['valid'] ?? false) === false) {
            return false;
        }

        foreach ($manifest['entries'] ?? [] as $entry) {
            if (($entry['valid_content'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{pass: bool, fail_count: int, mismatches: list<array<string, mixed>>}
     */
    public function compareManifests(array $expected, array $actual): array
    {
        $mismatches = [];

        if (! $this->manifestIsValid($expected)) {
            foreach ($expected['entries'] ?? [] as $entry) {
                if (($entry['valid_content'] ?? false) !== true) {
                    $mismatches[] = [
                        'path' => $entry['path'] ?? '*',
                        'issue' => 'invalid_canonical_manifest_entry',
                        'errors' => $entry['validation_errors'] ?? [],
                    ];
                }
            }
            if ($mismatches === []) {
                $mismatches[] = ['path' => '*', 'issue' => 'invalid_canonical_manifest'];
            }

            return [
                'pass' => false,
                'fail_count' => count($mismatches),
                'mismatches' => $mismatches,
            ];
        }

        if (! $this->manifestIsValid($actual)) {
            foreach ($actual['entries'] ?? [] as $entry) {
                if (($entry['valid_content'] ?? false) !== true) {
                    $mismatches[] = [
                        'path' => $entry['path'] ?? '*',
                        'issue' => 'invalid_actual_manifest_entry',
                        'errors' => $entry['validation_errors'] ?? [],
                    ];
                }
            }

            return [
                'pass' => false,
                'fail_count' => count($mismatches),
                'mismatches' => $mismatches,
            ];
        }

        $expectedMap = $this->indexByPath($expected['entries'] ?? []);
        $actualMap = $this->indexByPath($actual['entries'] ?? []);

        foreach ($expectedMap as $path => $exp) {
            $act = $actualMap[$path] ?? null;
            if ($act === null) {
                $mismatches[] = ['path' => $path, 'issue' => 'missing_in_actual'];

                continue;
            }
            foreach (['size', 'sha256', 'detected_mime', 'valid_content'] as $field) {
                if (($exp[$field] ?? null) !== ($act[$field] ?? null)) {
                    $mismatches[] = [
                        'path' => $path,
                        'issue' => 'field_mismatch',
                        'field' => $field,
                        'expected' => $exp[$field] ?? null,
                        'actual' => $act[$field] ?? null,
                    ];
                }
            }
        }

        foreach ($actualMap as $path => $_) {
            if (! isset($expectedMap[$path])) {
                $mismatches[] = ['path' => $path, 'issue' => 'extra_in_actual'];
            }
        }

        if (($expected['entry_count'] ?? null) !== ($actual['entry_count'] ?? null)) {
            $mismatches[] = [
                'path' => '*',
                'issue' => 'entry_count_mismatch',
                'expected' => $expected['entry_count'] ?? null,
                'actual' => $actual['entry_count'] ?? null,
            ];
        }

        return [
            'pass' => $mismatches === [],
            'fail_count' => count($mismatches),
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array{
     *     generated_at: string,
     *     root: string,
     *     entry_count: int,
     *     valid: bool,
     *     validation_fail_count: int,
     *     entries: list<array<string, mixed>>
     * }
     */
    private function finalizeManifest(string $root, array $entries): array
    {
        $validationFailCount = 0;
        foreach ($entries as $entry) {
            if (($entry['valid_content'] ?? false) !== true) {
                $validationFailCount++;
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'root' => $root,
            'entry_count' => count($entries),
            'valid' => $validationFailCount === 0,
            'validation_fail_count' => $validationFailCount,
            'entries' => $entries,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, array<string, mixed>>
     */
    private function indexByPath(array $entries): array
    {
        $map = [];
        foreach ($entries as $entry) {
            $path = (string) ($entry['path'] ?? $entry['relative_path'] ?? '');
            if ($path !== '') {
                $map[$path] = $entry;
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $relativePath, string $absolutePath): array
    {
        $validated = $this->contentValidator->validateFile($absolutePath, $relativePath);

        return array_merge($validated, [
            'relative_path' => $relativePath,
            'mime' => $validated['detected_mime'],
        ]);
    }
}
