<?php

namespace App\Support\Emails;

/**
 * Scans email template sources for truncated placeholder / variable identifier typos.
 *
 * Read-only. No mail send, no DB writes.
 */
final class EmailVariableIdentifierAuditor
{
    /** @var list<string> */
    public const SCAN_ROOTS = [
        'app/Support/Emails',
        'config',
        'resources/views',
    ];

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function malformedDefinitions(): array
    {
        return [
            ['agency_'.'nam', 'e'],
            ['brand_'.'nam', 'e'],
            ['company_'.'nam', 'e'],
            ['support_'.'emai', 'l'],
            ['support_'.'phon', 'e'],
        ];
    }

    public static function malformedPattern(): string
    {
        $pieces = [];
        foreach (self::malformedDefinitions() as [$fragment, $expectedNextChar]) {
            $pieces[] = preg_quote($fragment, '/').'(?!'.$expectedNextChar.'\\b)';
        }

        return '/'.implode('|', $pieces).'/';
    }

    /**
     * @return list<string>
     */
    public static function malformedFragments(): array
    {
        return array_map(fn (array $definition): string => $definition[0], self::malformedDefinitions());
    }

    /**
     * @return array{pass: bool, hit_count: int, hits: list<array{file: string, fragment: string, line: int}>}
     */
    public function scan(?array $roots = null): array
    {
        $hits = [];
        $pattern = self::malformedPattern();
        $selfPath = str_replace('\\', '/', __FILE__);

        foreach ($roots ?? self::SCAN_ROOTS as $root) {
            $absoluteRoot = base_path($root);
            if (! is_dir($absoluteRoot)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absoluteRoot, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                if ($path === $selfPath) {
                    continue;
                }

                if (! preg_match('/\.(php|blade\.php|json)$/i', $path)) {
                    continue;
                }

                $contents = file_get_contents($path);
                if ($contents === false || ! preg_match($pattern, $contents)) {
                    continue;
                }

                $relative = ltrim(str_replace(str_replace('\\', '/', base_path()), '', $path), '/');
                $lineNumber = 1;
                foreach (preg_split('/\R/', $contents) ?: [] as $line) {
                    if (preg_match($pattern, $line, $match)) {
                        $hits[] = [
                            'file' => $relative,
                            'fragment' => $match[0],
                            'line' => $lineNumber,
                        ];
                    }
                    $lineNumber++;
                }
            }
        }

        return [
            'pass' => $hits === [],
            'hit_count' => count($hits),
            'hits' => $hits,
        ];
    }
}
