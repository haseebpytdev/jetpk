<?php

namespace App\Support\Audits;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageAssetService;
use App\Services\Homepage\JetpkHomepageRouteFareRefreshService;
use App\Support\Client\ClientPageKeys;
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

        $checks = array_merge($checks, $this->leakageChecks($content));

        $failCount = count(array_filter($checks, static fn (array $c) => ($c['status'] ?? '') === 'fail'));

        return [
            'fail_count' => $failCount,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{fail_count: int, checks: list<array<string, mixed>>}
     */
    public function auditMedia(?ClientProfile $profile = null): array
    {
        if ($profile === null) {
            return ['fail_count' => 1, 'checks' => [$this->fail('profile_missing', 'Client profile not resolved.')]];
        }

        $checks = [];
        $assets = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->get();

        foreach ($assets as $asset) {
            if ($asset->path === '') {
                $checks[] = $this->fail('asset_empty_path', "Asset {$asset->asset_key} has empty path");

                continue;
            }

            if (! Storage::disk('public')->exists($asset->path)) {
                $checks[] = $this->fail('asset_missing_file', "Asset {$asset->asset_key} file missing at {$asset->path}");
            }

            if (str_contains(strtolower($asset->path), '..')) {
                $checks[] = $this->fail('asset_path_traversal', "Asset {$asset->asset_key} path traversal risk");
            }
        }

        $failCount = count(array_filter($checks, static fn (array $c) => ($c['status'] ?? '') === 'fail'));

        return ['fail_count' => $failCount, 'checks' => $checks];
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
        $assetKey = trim((string) ($item['image_asset_key'] ?? ''));
        if ($assetKey !== '') {
            return $this->assetService->urlFor($profile, ClientPageKeys::HOME, $assetKey);
        }

        $legacyKey = 'destination_'.($index + 1);

        return $this->assetService->urlFor($profile, ClientPageKeys::HOME, $legacyKey);
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
