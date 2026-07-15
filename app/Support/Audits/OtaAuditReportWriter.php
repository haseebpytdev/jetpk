<?php

namespace App\Support\Audits;

use Illuminate\Support\Facades\File;

/**
 * Shared markdown report writer for OTA audit commands.
 */
final class OtaAuditReportWriter
{
    /**
     * @param  list<string>  $lines
     */
    public static function write(string $path, array $lines): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode("\n", $lines)."\n");
    }

    /**
     * @param  list<array{file: string, line: int, pattern: string, classification: string, note: string}>  $findings
     * @return list<string>
     */
    public static function findingsTable(array $findings): array
    {
        $lines = [
            '| File | Line | Pattern | Classification | Note |',
            '|------|-----:|---------|----------------|------|',
        ];

        foreach ($findings as $finding) {
            $lines[] = sprintf(
                '| `%s` | %d | %s | %s | %s |',
                $finding['file'],
                $finding['line'],
                $finding['pattern'],
                $finding['classification'],
                $finding['note'],
            );
        }

        return $lines;
    }

    /**
     * @param  list<string>  $roots
     * @return list<array{file: string, line: int, content: string}>
     */
    public static function scanPattern(array $roots, string $pattern): array
    {
        $matches = [];
        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                if (! preg_match('/\.(php|blade\.php)$/', $path)) {
                    continue;
                }
                if (str_contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $lines = file($path, FILE_IGNORE_NEW_LINES);
                if ($lines === false) {
                    continue;
                }
                foreach ($lines as $i => $line) {
                    if (preg_match($pattern, $line)) {
                        $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                        $rel = str_replace('\\', '/', $rel);
                        $matches[] = [
                            'file' => $rel,
                            'line' => $i + 1,
                            'content' => trim($line),
                        ];
                    }
                }
            }
        }

        return $matches;
    }
}
