<?php

namespace App\Support\Audits;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Support\Client\ClientManagedPageCatalog;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPageSectionSchema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Read-only public-page CMS coverage audit for JetPK managed pages.
 */
final class JetpkPublicPageCmsCoverageAuditService
{
    private const OUTPUT_DIR = 'app/audits/jetpk-cms';

    /**
     * @return array<string, mixed>
     */
    public function run(string $profileSlug = 'jetpk', ?string $baseUrl = null): array
    {
        $baseUrl = rtrim($baseUrl ?? (string) config('app.url'), '/');
        $profile = $this->resolveProfile($profileSlug);
        $pages = [];
        $fail = 0;

        foreach (ClientManagedPageCatalog::pages() as $definition) {
            $pageKey = (string) $definition['page_key'];
            $page = $this->auditPage($definition, $profile, $baseUrl);
            if (! in_array($page['status'], ['FULL_CMS_OWNERSHIP', 'GLOBAL_COMPONENT'], true)
                && ! ($pageKey === ClientPageKeys::HOME && $page['status'] === 'PARTIAL_CMS_OWNERSHIP')) {
                if ($page['status'] !== 'ROUTE_MISSING' || $pageKey === ClientPageKeys::FAQ) {
                    $fail++;
                }
            }
            if ($page['server_error']) {
                $fail++;
            }
            $pages[] = $page;
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'profile' => $profileSlug,
            'db_write_attempted' => false,
            'cms_mutation_attempted' => false,
            'publish_attempted' => false,
            'supplier_call_attempted' => false,
            'fail' => $fail,
            'pages' => $pages,
        ];

        $dir = storage_path(self::OUTPUT_DIR);
        File::ensureDirectoryExists($dir);
        $jsonPath = $dir.'/PUBLIC-PAGE-CMS-COVERAGE.json';
        $mdPath = $dir.'/PUBLIC-PAGE-CMS-COVERAGE.md';
        File::put($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($mdPath, $this->markdown($payload));

        return array_merge($payload, ['path' => $jsonPath, 'md_path' => $mdPath]);
    }

  /**
   * @param  array<string, mixed>  $definition
   * @return array<string, mixed>
   */
    private function auditPage(array $definition, ?ClientProfile $profile, string $baseUrl): array
    {
        $pageKey = (string) $definition['page_key'];
        $path = (string) $definition['public_path'];
        $routeName = (string) $definition['public_route'];
        $bladePath = resource_path('views/'.ltrim((string) $definition['blade'], '/'));
        if (! str_starts_with((string) $definition['blade'], 'resources/')) {
            $bladePath = resource_path('views/'.(string) $definition['blade']);
        }

        $routeExists = Route::has($routeName);
        $httpStatus = null;
        $serverError = false;

        if ($routeExists && $path !== '/') {
            try {
                $response = Http::timeout(8)->get($baseUrl.$path);
                $httpStatus = $response->status();
                $serverError = $httpStatus >= 500;
            } catch (\Throwable) {
                $httpStatus = 0;
            }
        } elseif ($routeExists && $path === '/') {
            try {
                $response = Http::timeout(8)->get($baseUrl.'/');
                $httpStatus = $response->status();
                $serverError = $httpStatus >= 500;
            } catch (\Throwable) {
                $httpStatus = 0;
            }
        }

        $draftExists = false;
        $publishedExists = false;
        if ($profile !== null && Schema::hasTable('client_page_settings')) {
            $draftExists = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->where('status', ClientPageSettingStatus::Draft)
                ->exists();
            $publishedExists = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->where('status', ClientPageSettingStatus::Published)
                ->exists();
        }

        $bladeContent = File::exists($bladePath) ? (string) File::get($bladePath) : '';
        $visibleTextNodes = $this->estimateTextNodes($bladeContent);
        $hardcodedTextNodes = $this->countHardcodedClientLiterals($bladeContent);
        $cmsBackedTextNodes = max(0, $visibleTextNodes - $hardcodedTextNodes);
        $mediaSlots = count(ClientPageSectionSchema::requiredAssetKeys($pageKey));
        $hardcodedMediaSlots = $this->countHardcodedMedia($bladeContent);
        $cmsBackedMediaSlots = max(0, $mediaSlots - $hardcodedMediaSlots);

        $metadataCoverage = $this->metadataCoverage($bladeContent, $pageKey);
        $headerCoverage = $pageKey === ClientPageKeys::GLOBAL
            || str_contains($bladeContent, 'client_page_content')
            || str_contains($bladeContent, 'client_page_field');
        $footerCoverage = $pageKey === ClientPageKeys::FOOTER
            || str_contains($bladeContent, 'client_page_content')
            || str_contains($bladeContent, 'client_page_field');

        $status = $this->classifyStatus(
            $pageKey,
            $definition,
            $routeExists,
            $httpStatus,
            $hardcodedTextNodes,
            $cmsBackedTextNodes,
            $publishedExists,
        );

        return [
            'page_key' => $pageKey,
            'public_route' => $path,
            'public_route_name' => $routeName,
            'http_status' => $httpStatus,
            'ownership_type' => $definition['ownership_type'],
            'visible_text_nodes' => $visibleTextNodes,
            'cms_backed_text_nodes' => $cmsBackedTextNodes,
            'hardcoded_text_nodes' => $hardcodedTextNodes,
            'media_slots' => $mediaSlots,
            'cms_backed_media_slots' => $cmsBackedMediaSlots,
            'hardcoded_media_slots' => $hardcodedMediaSlots,
            'draft_exists' => $draftExists,
            'published_exists' => $publishedExists,
            'preview_available' => (bool) ($definition['preview_available'] ?? true),
            'publish_available' => (bool) ($definition['publish_available'] ?? true),
            'metadata_coverage' => $metadataCoverage,
            'header_coverage' => $headerCoverage,
            'footer_coverage' => $footerCoverage,
            'server_error' => $serverError,
            'runtime_classification' => $definition['runtime_classification'],
            'status' => $status,
        ];
    }

    private function resolveProfile(string $slug): ?ClientProfile
    {
        if (! Schema::hasTable('client_profiles')) {
            return null;
        }

        return ClientProfile::query()
            ->where('slug', $slug)
            ->where('is_master_profile', false)
            ->first();
    }

    private function estimateTextNodes(string $content): int
    {
        preg_match_all('/>([^<>{}\n@$][^<>{}\n@$]{2,})</', $content, $matches);

        return count($matches[1] ?? []);
    }

    private function countHardcodedClientLiterals(string $content): int
    {
        $count = 0;
        foreach (\App\Support\Client\ClientManagedPageHardcodeAllowlist::forbiddenContactPatterns() as $pattern) {
            if (str_contains($content, $pattern)) {
                $count++;
            }
        }
        if (preg_match_all("/client_page_content\\([^,]+,[^,]+,\\s*'[^']{8,}'/", $content, $matches)) {
            $count += count($matches[0]);
        }

        return $count;
    }

    private function countHardcodedMedia(string $content): int
    {
        preg_match_all('/(?:src|href)=["\'][^"\']*(?:jetpakistan|storage\/jetpk|facebook\\.com|instagram\\.com)[^"\']*["\']/', $content, $matches);

        return count($matches[0] ?? []);
    }

    private function metadataCoverage(string $content, string $pageKey): bool
    {
        if (str_contains($content, 'client_page_seo') || str_contains($content, 'seo_title')) {
            return true;
        }

        return in_array($pageKey, [ClientPageKeys::HOME, ClientPageKeys::TERMS, ClientPageKeys::PRIVACY, ClientPageKeys::FAQ], true)
            && str_contains($content, '@section(\'title\'');
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function classifyStatus(
        string $pageKey,
        array $definition,
        bool $routeExists,
        ?int $httpStatus,
        int $hardcodedTextNodes,
        int $cmsBackedTextNodes,
        bool $publishedExists,
    ): string {
        if (! $routeExists) {
            return 'ROUTE_MISSING';
        }
        if ($httpStatus !== null && $httpStatus >= 500) {
            return 'SERVER_ERROR';
        }
        if ($definition['ownership_type'] === ClientManagedPageCatalog::OWNERSHIP_GLOBAL) {
            return $hardcodedTextNodes > 2 ? 'PARTIAL_CMS_OWNERSHIP' : 'GLOBAL_COMPONENT';
        }
        if ($hardcodedTextNodes === 0 && $publishedExists) {
            return 'FULL_CMS_OWNERSHIP';
        }
        if ($hardcodedTextNodes > 0 && $cmsBackedTextNodes > 0) {
            return 'PARTIAL_CMS_OWNERSHIP';
        }
        if ($hardcodedTextNodes > 0) {
            return 'HARDCODED_CONTENT_REMAINS';
        }
        if (! $publishedExists) {
            return 'PARTIAL_CMS_OWNERSHIP';
        }

        return 'FULL_CMS_OWNERSHIP';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markdown(array $payload): string
    {
        $lines = [
            '# JetPK Public Page CMS Coverage Audit',
            '',
            'Generated: '.($payload['generated_at'] ?? ''),
            '',
            '| page_key | http_status | ownership | status | hardcoded_text | cms_backed_text | published |',
            '| --- | --- | --- | --- | --- | --- | --- |',
        ];
        foreach ($payload['pages'] ?? [] as $page) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %d | %d | %s |',
                $page['page_key'],
                (string) ($page['http_status'] ?? 'n/a'),
                $page['ownership_type'],
                $page['status'],
                $page['hardcoded_text_nodes'],
                $page['cms_backed_text_nodes'],
                $page['published_exists'] ? 'yes' : 'no',
            );
        }
        $lines[] = '';
        $lines[] = 'fail='.($payload['fail'] ?? 0);

        return implode("\n", $lines)."\n";
    }
}
