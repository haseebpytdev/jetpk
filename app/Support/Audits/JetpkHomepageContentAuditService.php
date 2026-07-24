<?php

namespace App\Support\Audits;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageAssetService;
use App\Services\Homepage\JetpkHomepageAssetService;
use App\Services\Homepage\JetpkHomepageRouteFareRefreshService;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPageMediaSchema;
use App\Support\Client\ClientPublicWebrootPath;
use App\Support\Client\Homepage\JetpkHomepageHeroSizing;
use App\Support\Client\JetpkHomepageFareDisplay;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only audit for JetPK homepage trending routes, destinations, and support CTA.
 */
final class JetpkHomepageContentAuditService
{
    public function __construct(
        private readonly ClientPageAssetService $assetService,
    ) {}

    /**
     * @return array{fail_count: int, checks: list<array<string, mixed>>}
     */
    public function auditProfile(?ClientProfile $profile = null): array
    {
        if ($profile === null) {
            return [
                'fail_count' => 1,
                'checks' => [[
                    'code' => 'profile_missing',
                    'status' => 'fail',
                    'message' => 'Client profile not resolved.',
                ]],
            ];
        }

        $checks = [];
        $content = $this->publishedContent($profile);
        $fareCache = is_array($content['_fare_cache']['routes'] ?? null) ? $content['_fare_cache']['routes'] : [];

        $routeItems = $this->activeItems($content['routes']['items'] ?? []);
        $destItems = $this->activeItems($content['destinations']['items'] ?? []);

        $checks[] = $this->countCheck(
            'active_routes_min',
            count($routeItems),
            (int) config('jetpk_homepage.min_active_routes', 4),
            'Active trending routes',
        );
        $checks[] = $this->countCheck(
            'active_destinations_min',
            count($destItems),
            (int) config('jetpk_homepage.min_active_destinations', 4),
            'Active popular destinations',
        );

        $routeSignatures = [];
        foreach ($routeItems as $index => $item) {
            $from = strtoupper((string) ($item['from'] ?? ''));
            $to = strtoupper((string) ($item['to'] ?? ''));
            $trip = (string) ($item['trip_type'] ?? 'one_way');
            $signature = $from.'|'.$to.'|'.$trip;

            if ($from === '' || $to === '' || ! JetpkHomepageRouteFareRefreshService::isCanonicalAirport($from)) {
                $checks[] = $this->fail('route_iata_origin', "Route #{$index}: invalid origin {$from}");
            }
            if ($to === '' || ! JetpkHomepageRouteFareRefreshService::isCanonicalAirport($to)) {
                $checks[] = $this->fail('route_iata_destination', "Route #{$index}: invalid destination {$to}");
            }
            if (isset($routeSignatures[$signature])) {
                $checks[] = $this->fail('route_duplicate', "Duplicate route {$from}→{$to} ({$trip})");
            }
            $routeSignatures[$signature] = true;

            $display = JetpkHomepageFareDisplay::resolve($item, $fareCache[$item['id'] ?? ''] ?? null);
            if ($display === null && $this->isTruthy($item['dynamic_fare_enabled'] ?? '0')) {
                $checks[] = $this->warn('route_no_display_fare', "Route {$from}→{$to}: no displayable fare yet");
            } elseif ($display !== null && (int) round($display['amount']) <= 0) {
                $checks[] = $this->fail('route_pkr_zero', "Route {$from}→{$to}: PKR 0 would render");
            }
        }

        $destCodes = [];
        foreach ($destItems as $index => $item) {
            $code = strtoupper((string) ($item['code'] ?? ''));
            if ($code !== '' && isset($destCodes[$code])) {
                $checks[] = $this->fail('destination_duplicate', "Duplicate destination {$code}");
            }
            if ($code !== '') {
                $destCodes[$code] = true;
            }

            $price = JetpkHomepageFareDisplay::resolve($item, null);
            if ($price !== null && (int) round($price['amount']) <= 0) {
                $checks[] = $this->fail('destination_pkr_zero', "Destination {$code}: PKR 0 would render");
            }

            $image = $this->destinationImageUrl($profile, $item, $index);
            if ($image === null) {
                $fallback = (string) config('jetpk_homepage.destination_fallback_image', '');
                if ($fallback === '' || ! is_file(public_path($fallback))) {
                    $checks[] = $this->warn('destination_image_fallback', "Destination {$code}: using gradient fallback only");
                }
            } elseif (! $this->isLocalAssetUrl($image)) {
                $checks[] = $this->fail('destination_external_asset', "Destination {$code}: external image URL blocked");
            }
        }

        $support = is_array($content['support_cta'] ?? null) ? $content['support_cta'] : [];
        if ($this->isTruthy($support['enabled'] ?? '1')) {
            if ($this->isTruthy($support['call_enabled'] ?? '1') && trim((string) ($support['phone_value'] ?? '')) === '' && trim((string) ($support['call_url'] ?? '')) === '') {
                $checks[] = $this->warn('support_call_unconfigured', 'Support call button enabled without phone/URL');
            }
            if ($this->isTruthy($support['chat_enabled'] ?? '1') && trim((string) ($support['cta_link'] ?? '')) === '' && trim((string) ($support['chat_url'] ?? '')) === '') {
                $checks[] = $this->warn('support_chat_unconfigured', 'Live chat enabled without URL');
            }

            foreach (['support_cta_background', 'support_cta_background_mobile'] as $assetKey) {
                $url = $this->assetService->urlFor($profile, ClientPageKeys::HOME, $assetKey);
                if ($url !== null && ! $this->isLocalAssetUrl($url)) {
                    $checks[] = $this->fail('support_external_asset', "Support CTA {$assetKey} is external");
                }
                if ($url !== null) {
                    $asset = ClientPageAsset::query()
                        ->where('client_profile_id', $profile->id)
                        ->where('page_key', ClientPageKeys::HOME)
                        ->where('asset_key', $assetKey)
                        ->first();
                    if ($asset !== null && ! Storage::disk('public')->exists($asset->path)) {
                        $checks[] = $this->fail('support_broken_path', "Support CTA {$assetKey} storage path missing");
                    }
                }
            }
        }

        $checks = array_merge($checks, $this->auditHeroSizing($content));
        $checks = array_merge($checks, $this->leakageChecks($content));

        $failCount = count(array_filter($checks, static fn (array $c) => ($c['status'] ?? '') === 'fail'));

        return [
            'fail_count' => $failCount,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{
     *     fail_count: int,
     *     checks: list<array<string, mixed>>,
     *     slots: list<array<string, mixed>>,
     *     db_write_attempted: bool,
     *     cms_mutation_attempted: bool,
     *     publish_attempted: bool
     * }
     */
    public function auditMedia(?ClientProfile $profile = null): array
    {
        $readOnlyFlags = [
            'db_write_attempted' => false,
            'cms_mutation_attempted' => false,
            'publish_attempted' => false,
        ];

        if ($profile === null) {
            return array_merge($readOnlyFlags, [
                'fail_count' => 1,
                'checks' => [$this->fail('profile_missing', 'Client profile not resolved.')],
                'slots' => [],
            ]);
        }

        $checks = [];
        $slots = [];
        $published = $this->publishedContent($profile);
        $draft = $this->draftContent($profile);
        $canonicalSlots = $this->canonicalHomepageMediaSlots($published);

        foreach ($canonicalSlots as $slot) {
            $slotReport = $this->auditMediaSlot($profile, $slot, $published, $draft);
            $slots[] = $slotReport;

            if (($slotReport['status'] ?? '') === 'fail') {
                $checks[] = $this->fail(
                    'media_slot_'.$slotReport['slot'],
                    $this->formatMediaSlotMessage($slotReport),
                );
            } else {
                $checks[] = [
                    'code' => 'media_slot_'.$slotReport['slot'],
                    'status' => 'pass',
                    'message' => $this->formatMediaSlotMessage($slotReport),
                ];
            }
        }

        $failCount = count(array_filter($checks, static fn (array $c) => ($c['status'] ?? '') === 'fail'));

        return array_merge($readOnlyFlags, [
            'fail_count' => $failCount,
            'checks' => $checks,
            'slots' => $slots,
        ]);
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array<string, mixed>  $published
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function auditMediaSlot(ClientProfile $profile, array $slot, array $published, array $draft): array
    {
        $assetKey = (string) $slot['slot'];
        $asset = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('asset_key', $assetKey)
            ->first();

        $resolvedUrl = $this->assetService->urlFor($profile, ClientPageKeys::HOME, $assetKey);
        $usingFallback = $resolvedUrl === null;
        $storageExists = $asset !== null && $asset->path !== '' && Storage::disk('public')->exists($asset->path);
        $publicHttpExpected = $asset !== null && $asset->path !== ''
            ? $this->publicMirrorExists((string) $asset->path)
            : false;

        $draftPublishedMatch = 'not_applicable';
        if ($asset !== null) {
            $draftPublishedMatch = 'shared_asset_record';
        }

        $staleCacheRisk = $asset !== null
            && $resolvedUrl !== null
            && ! str_contains($resolvedUrl, 'v=');

        $renderContractOk = $this->supportCtaRenderContractOk(
            $published,
            $assetKey,
            $resolvedUrl,
            $storageExists,
            $publicHttpExpected,
        );

        $status = 'pass';
        if ($assetKey === 'support_cta_background' && ! $renderContractOk) {
            $status = 'fail';
        } elseif ($asset === null) {
            $status = 'pass';
        } elseif ($asset->path === '') {
            $status = 'fail';
        } elseif (! $storageExists) {
            $status = 'fail';
        } elseif (! $publicHttpExpected) {
            $status = 'fail';
        }

        return [
            'slot' => $assetKey,
            'section' => (string) $slot['section'],
            'published_asset_found' => $asset !== null,
            'published_asset_id_present' => $asset?->id !== null,
            'resolved_url_present' => $resolvedUrl !== null,
            'using_fallback' => $usingFallback,
            'file_exists' => $storageExists,
            'public_http_expected' => $publicHttpExpected,
            'draft_published_match_status' => $draftPublishedMatch,
            'stale_cache_risk' => $staleCacheRisk,
            'draft_only' => false,
            'render_contract_ok' => $renderContractOk,
            'status' => $status,
        ];
    }

    /**
     * @param  array<string, mixed>  $published
     */
    private function supportCtaRenderContractOk(
        array $published,
        string $assetKey,
        ?string $resolvedUrl,
        bool $storageExists,
        bool $publicHttpExpected,
    ): bool {
        if ($assetKey !== 'support_cta_background') {
            return true;
        }

        if (! $this->isTruthy(data_get($published, 'support_cta.enabled', '1'))) {
            return true;
        }

        $mode = (string) data_get($published, 'support_cta.background_mode', 'gradient');
        $expectsUploadedMedia = in_array($mode, ['uploaded', 'uploaded_overlay'], true);

        if ($expectsUploadedMedia) {
            return $resolvedUrl !== null && $storageExists && $publicHttpExpected;
        }

        return $resolvedUrl === null;
    }

    /**
     * @param  array<string, mixed>  $published
     * @return list<array{slot: string, section: string}>
     */
    private function canonicalHomepageMediaSlots(array $published): array
    {
        $slots = [];
        foreach (ClientPageMediaSchema::fieldsFor(ClientPageKeys::HOME) as $field) {
            $slots[] = [
                'slot' => (string) $field['key'],
                'section' => (string) $field['section'],
            ];
        }

        $items = is_array($published['destinations']['items'] ?? null) ? $published['destinations']['items'] : [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $assetKey = trim((string) ($item['image_asset_key'] ?? ''));
            if ($assetKey === '') {
                continue;
            }
            $slots[] = ['slot' => $assetKey, 'section' => 'destinations'];
        }

        $seen = [];
        $unique = [];
        foreach ($slots as $slot) {
            if (isset($seen[$slot['slot']])) {
                continue;
            }
            $seen[$slot['slot']] = true;
            $unique[] = $slot;
        }

        return $unique;
    }

    private function publicMirrorExists(string $relativePath): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relative === '') {
            return false;
        }

        if (is_file(public_path('storage/'.$relative))) {
            return true;
        }

        if (ClientPublicWebrootPath::usingConfiguredPath()) {
            return ClientPublicWebrootPath::isFile('storage/'.$relative);
        }

        return Storage::disk('public')->exists($relative);
    }

    /**
     * @param  array<string, mixed>  $slotReport
     */
    private function formatMediaSlotMessage(array $slotReport): string
    {
        $parts = [
            'slot='.$slotReport['slot'],
            'section='.$slotReport['section'],
            'published_asset_found='.(($slotReport['published_asset_found'] ?? false) ? 'true' : 'false'),
            'published_asset_id_present='.(($slotReport['published_asset_id_present'] ?? false) ? 'true' : 'false'),
            'resolved_url_present='.(($slotReport['resolved_url_present'] ?? false) ? 'true' : 'false'),
            'using_fallback='.(($slotReport['using_fallback'] ?? false) ? 'true' : 'false'),
            'file_exists='.(($slotReport['file_exists'] ?? false) ? 'true' : 'false'),
            'public_http_expected='.(($slotReport['public_http_expected'] ?? false) ? 'true' : 'false'),
            'draft_published_match_status='.($slotReport['draft_published_match_status'] ?? 'unknown'),
            'stale_cache_risk='.(($slotReport['stale_cache_risk'] ?? false) ? 'true' : 'false'),
            'draft_only='.(($slotReport['draft_only'] ?? false) ? 'true' : 'false'),
            'render_contract_ok='.(($slotReport['render_contract_ok'] ?? true) ? 'true' : 'false'),
        ];

        return implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function draftContent(ClientProfile $profile): array
    {
        $row = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        return is_array($row?->content_json) ? $row->content_json : [];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    private function leakageChecks(array $content): array
    {
        $encoded = json_encode($content) ?: '';
        $checks = [];
        $needles = ['parwaaz', 'haseeb-master', 'master-client', 'yourdomain', 'yd-travel'];
        foreach ($needles as $needle) {
            if (stripos($encoded, $needle) !== false) {
                $checks[] = $this->fail('content_leakage', "Blocked reference detected: {$needle}");
            }
        }

        if (preg_match('/https?:\/\/(?!.*jetpakistan)[^\s"\']+/i', $encoded)) {
            $checks[] = $this->warn('external_url_in_content', 'External URL found in homepage JSON — verify intent.');
        }

        return $checks;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function destinationImageUrl(ClientProfile $profile, array $item, int $index): ?string
    {
        $candidates = [];

        $assetKey = trim((string) ($item['image_asset_key'] ?? ''));
        if ($assetKey !== '') {
            $candidates[] = $assetKey;
        }

        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId !== '') {
            $candidates[] = JetpkHomepageAssetService::destinationAssetKey($itemId);
            $candidates[] = 'destination_'.$itemId;
        }

        $candidates[] = 'destination_'.($index + 1);

        foreach (array_unique($candidates) as $key) {
            $url = $this->assetService->urlFor($profile, ClientPageKeys::HOME, $key);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    private function auditHeroSizing(array $content): array
    {
        $checks = [];
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];

        foreach (JetpkHomepageHeroSizing::heroTextFieldKeys() as $field) {
            if (! array_key_exists($field, $hero)) {
                continue;
            }
            $normalized = JetpkHomepageHeroSizing::normalizeHeroTextPercent($hero[$field]);
            if ((string) $hero[$field] !== (string) $normalized && $hero[$field] !== '') {
                $checks[] = $this->warn('hero_size_clamped', "hero.{$field} clamped to {$normalized}%");
            }
        }

        if (array_key_exists('search_ui_scale', $hero)) {
            $normalized = JetpkHomepageHeroSizing::normalizeSearchUiPercent($hero['search_ui_scale']);
            if ((string) $hero['search_ui_scale'] !== (string) $normalized && $hero['search_ui_scale'] !== '') {
                $checks[] = $this->warn('search_ui_scale_clamped', "hero.search_ui_scale clamped to {$normalized}%");
            }
        }

        return $checks;
    }

    /**
     * @return array<string, mixed>
     */
    private function publishedContent(ClientProfile $profile): array
    {
        $row = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        return is_array($row?->content_json) ? $row->content_json : [];
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function activeItems(array $items): array
    {
        return array_values(array_filter($items, function ($item) {
            return is_array($item) && ($item['enabled'] ?? '1') !== '0'
                && trim((string) ($item['from'] ?? $item['code'] ?? $item['title'] ?? '')) !== '';
        }));
    }

    private function countCheck(string $code, int $actual, int $minimum, string $label): array
    {
        if ($actual < $minimum) {
            return $this->fail($code, "{$label}: {$actual}/{$minimum} active");
        }

        return ['code' => $code, 'status' => 'pass', 'message' => "{$label}: {$actual} active"];
    }

    /**
     * @return array{code: string, status: string, message: string}
     */
    private function fail(string $code, string $message): array
    {
        return ['code' => $code, 'status' => 'fail', 'message' => $message];
    }

    /**
     * @return array{code: string, status: string, message: string}
     */
    private function warn(string $code, string $message): array
    {
        return ['code' => $code, 'status' => 'warn', 'message' => $message];
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    private function isLocalAssetUrl(string $url): bool
    {
        if (str_starts_with($url, '/storage/') || str_starts_with($url, '/themes/')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === '') {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost) && strcasecmp($host, $appHost) === 0;
    }
}
