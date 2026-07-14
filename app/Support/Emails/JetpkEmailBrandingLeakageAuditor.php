<?php

namespace App\Support\Emails;

/**
 * Distinguishes forbidden-brand denylist configuration from actual rendered leakage.
 *
 * Denylist entries in config files must never count as branding leakage. Only inspect
 * rendered output and active templates (excluding denylist config definitions).
 */
class JetpkEmailBrandingLeakageAuditor
{
    /** @var list<string> */
    private const EXCLUDED_FILE_SUFFIXES = [
        'config/jetpk_operational_email.php',
        'config/jetpk_email.php',
    ];

    /** @var list<string> */
    private const ACTIVE_TEMPLATE_ROOT = 'resources/views/emails/themes/jetpakistan';

    /**
     * @return list<string>
     */
    public function forbiddenFragments(): array
    {
        $operational = config('jetpk_operational_email.forbidden_brand_fragments', []);
        $email = config('jetpk_email.forbidden_brand_fragments', []);

        return array_values(array_unique(array_filter(array_merge(
            is_array($operational) ? $operational : [],
            is_array($email) ? $email : [],
        ), fn ($f): bool => is_string($f) && $f !== '')));
    }

    /**
     * Scan rendered HTML/plain-text/subject output for forbidden branding.
     *
     * @return list<array{fragment: string, context: string}>
     */
    public function scanRenderedContent(string $content, string $context = 'rendered'): array
    {
        $hits = [];
        foreach ($this->forbiddenFragments() as $fragment) {
            if (str_contains($content, $fragment)) {
                $hits[] = ['fragment' => $fragment, 'context' => $context];
            }
        }

        return $hits;
    }

    /**
     * Scan active JetPK email Blade templates (never config denylist files).
     *
     * @return list<array{fragment: string, file: string}>
     */
    public function scanActiveBladeTemplates(): array
    {
        $root = base_path(self::ACTIVE_TEMPLATE_ROOT);
        if (! is_dir($root)) {
            return [];
        }

        $hits = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = $this->relativePath($file->getPathname());
            if ($this->isExcludedConfigPath($relative)) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            foreach ($this->forbiddenFragments() as $fragment) {
                if ($fragment !== '' && str_contains($contents, $fragment)) {
                    $hits[] = ['fragment' => $fragment, 'file' => $relative];
                }
            }
        }

        return $hits;
    }

    /**
     * Scan denylist config files — expected to contain fragments; never a leakage fail.
     *
     * @return list<string>
     */
    public function denylistConfigFragments(): array
    {
        $found = [];
        foreach (self::EXCLUDED_FILE_SUFFIXES as $configPath) {
            $full = base_path($configPath);
            if (! is_file($full)) {
                continue;
            }
            $contents = (string) file_get_contents($full);
            foreach ($this->forbiddenFragments() as $fragment) {
                if ($fragment !== '' && str_contains($contents, $fragment)) {
                    $found[] = $fragment;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Active application code must not use the misspelled applicant context key.
     *
     * @return list<array{file: string, line: int}>
     */
    public function scanMisspelledApplicantEmailKey(): array
    {
        $hits = [];
        $roots = [
            base_path('app'),
            base_path('routes'),
        ];

        $excluded = [
            'app/Support/Emails/JetpkEmailBrandingLeakageAuditor.php',
            'app/Console/Commands/JetpkEmailCoverageAuditCommand.php',
        ];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = $this->relativePath($file->getPathname());
                if (in_array($relative, $excluded, true)) {
                    continue;
                }

                $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
                if (! is_array($lines)) {
                    continue;
                }

                foreach ($lines as $index => $line) {
                    if (preg_match('/[\'"]applican_email[\'"]\s*=>|[\[\(][\'"]applican_email[\'"][\]\)]/', $line) === 1) {
                        $hits[] = [
                            'file' => $relative,
                            'line' => $index + 1,
                        ];
                    }
                }
            }
        }

        return $hits;
    }

    public function isExcludedConfigPath(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        foreach (self::EXCLUDED_FILE_SUFFIXES as $excluded) {
            if ($normalized === $excluded || str_ends_with($normalized, '/'.$excluded)) {
                return true;
            }
        }

        return false;
    }

    protected function relativePath(string $absolutePath): string
    {
        $base = str_replace('\\', '/', base_path());
        $path = str_replace('\\', '/', $absolutePath);

        return ltrim(str_replace($base.'/', '', $path), '/');
    }
}
