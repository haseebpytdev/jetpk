<?php

namespace App\Support\Audits;

use App\Support\Client\ClientManagedPageCatalog;
use App\Support\Client\ClientManagedPageHardcodeAllowlist;
use Illuminate\Support\Facades\File;

/**
 * Read-only scan of managed JetPK public views for client-specific runtime literals.
 */
final class JetpkManagedPageHardcodeAuditService
{
    private const OUTPUT_DIR = 'app/audits/jetpk-cms';

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $filesScanned = 0;
        $clientContentLiterals = [];
        $unapprovedRuntimeFallbacks = [];
        $hardcodedContactDetails = [];
        $hardcodedLegalCopy = [];
        $hardcodedNavigationLabels = [];
        $hardcodedMetadata = [];
        $allowedPlatformLiterals = ClientManagedPageHardcodeAllowlist::platformLiterals();

        $paths = array_merge(
            ClientManagedPageCatalog::managedFrontendViewPaths(),
            ClientManagedPageCatalog::managedServicePaths(),
        );

        foreach ($paths as $relativePath) {
            $absolute = $this->resolvePath($relativePath);
            if (! File::exists($absolute)) {
                continue;
            }
            $filesScanned++;
            $content = (string) File::get($absolute);

            foreach (ClientManagedPageHardcodeAllowlist::forbiddenContactPatterns() as $pattern) {
                if (preg_match('/["\'][^"\']*'.preg_quote($pattern, '/').'[^"\']*["\']/', $content)) {
                    $hardcodedContactDetails[] = ['file' => $relativePath, 'pattern' => $pattern];
                }
            }

            foreach (ClientManagedPageHardcodeAllowlist::forbiddenLegalPatterns() as $pattern) {
                if (stripos($content, $pattern) !== false) {
                    $hardcodedLegalCopy[] = ['file' => $relativePath, 'pattern' => $pattern];
                }
            }

            if (preg_match_all("/client_page_content\\([^,]+,\\s*'[^']+',\\s*'([^']{8,})'/", $content, $matches)) {
                foreach ($matches[1] as $literal) {
                    if (! $this->isAllowedLiteral($literal, $allowedPlatformLiterals)) {
                        $unapprovedRuntimeFallbacks[] = ['file' => $relativePath, 'literal' => $literal];
                    }
                }
            }

            if (preg_match_all("/\\?\\?\\s*'([^']{12,})'/", $content, $matches)) {
                foreach ($matches[1] as $literal) {
                    if (! $this->isAllowedLiteral($literal, $allowedPlatformLiterals)) {
                        $unapprovedRuntimeFallbacks[] = ['file' => $relativePath, 'literal' => $literal];
                    }
                }
            }

            if (str_contains($relativePath, 'header.blade.php') || str_contains($relativePath, 'drawer.blade.php')) {
                foreach (['Home', 'Booking', 'Support', 'About', '24/7 Support'] as $label) {
                    if (str_contains($content, '>'.$label.'<') || str_contains($content, "'".$label."'")) {
                        $hardcodedNavigationLabels[] = ['file' => $relativePath, 'label' => $label];
                    }
                }
            }

            if (preg_match_all("/@section\\('title',\\s*'([^']+)'/", $content, $matches)) {
                foreach ($matches[1] as $title) {
                    if (str_contains($title, 'JetPakistan') && ! str_contains($content, 'client_page_seo')) {
                        $hardcodedMetadata[] = ['file' => $relativePath, 'title' => $title];
                    }
                }
            }
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'managed_files_scanned' => $filesScanned,
            'client_content_literals_found' => count($clientContentLiterals),
            'allowed_platform_literals' => count($allowedPlatformLiterals),
            'unapproved_runtime_fallbacks' => count($unapprovedRuntimeFallbacks),
            'hardcoded_contact_details' => count($hardcodedContactDetails),
            'hardcoded_legal_copy' => count($hardcodedLegalCopy),
            'hardcoded_navigation_labels' => count($hardcodedNavigationLabels),
            'hardcoded_metadata' => count($hardcodedMetadata),
            'findings' => [
                'unapproved_runtime_fallbacks' => $unapprovedRuntimeFallbacks,
                'hardcoded_contact_details' => $hardcodedContactDetails,
                'hardcoded_legal_copy' => $hardcodedLegalCopy,
                'hardcoded_navigation_labels' => $hardcodedNavigationLabels,
                'hardcoded_metadata' => $hardcodedMetadata,
            ],
        ];

        $dir = storage_path(self::OUTPUT_DIR);
        File::ensureDirectoryExists($dir);
        $jsonPath = $dir.'/MANAGED-PAGE-HARDCODE-AUDIT.json';
        File::put($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return array_merge($payload, ['path' => $jsonPath]);
    }

    private function resolvePath(string $relativePath): string
    {
        if (str_starts_with($relativePath, 'app/')) {
            return base_path($relativePath);
        }

        return resource_path('views/'.$relativePath);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function isAllowedLiteral(string $literal, array $allowed): bool
    {
        foreach ($allowed as $token) {
            if (str_contains($literal, $token)) {
                return true;
            }
        }

        return trim($literal) === '';
    }
}
