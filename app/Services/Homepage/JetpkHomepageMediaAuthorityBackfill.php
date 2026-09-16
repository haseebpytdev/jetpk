<?php

namespace App\Services\Homepage;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\JetpkHomepageMediaAuthorityCatalog;
use App\Support\Client\JetpkHomepageSectionData;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent homepage media binding backfill for published JetPK homepage CMS rows.
 */
final class JetpkHomepageMediaAuthorityBackfill
{
    public function __construct(
        private readonly JetpkHomepageSectionData $homepage,
    ) {}

    /**
     * @return array{routes_bound: int, deals_bound: int, assets_upserted: int}
     */
    public function runForProfile(ClientProfile $profile): array
    {
        if (! Schema::hasTable('client_page_settings') || ! Schema::hasTable('client_page_assets')) {
            return ['routes_bound' => 0, 'deals_bound' => 0, 'assets_upserted' => 0];
        }

        $routesBound = 0;
        $dealsBound = 0;
        $assetsUpserted = 0;

        foreach ([ClientPageSettingStatus::Draft, ClientPageSettingStatus::Published] as $status) {
            $row = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', ClientPageKeys::HOME)
                ->where('status', $status)
                ->first();

            if ($row === null) {
                continue;
            }

            $content = is_array($row->content_json) ? $row->content_json : [];
            $changed = false;

            $content = $this->bindRoutes($profile, $content, $routesBound, $assetsUpserted, $changed);
            $content = $this->bindFeaturedDeals($profile, $content, $dealsBound, $assetsUpserted, $changed);

            if ($changed) {
                $row->update(['content_json' => $content]);
            }
        }

        return [
            'routes_bound' => $routesBound,
            'deals_bound' => $dealsBound,
            'assets_upserted' => $assetsUpserted,
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function bindRoutes(
        ClientProfile $profile,
        array $content,
        int &$routesBound,
        int &$assetsUpserted,
        bool &$changed,
    ): array {
        $items = $content['routes']['items'] ?? null;
        if (! is_array($items)) {
            return $content;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemId = trim((string) ($item['id'] ?? ''));
            $binding = JetpkHomepageMediaAuthorityCatalog::routeBindings()[$itemId] ?? null;
            if ($binding === null) {
                continue;
            }

            $currentKey = trim((string) ($item['image_asset_key'] ?? ''));
            if ($currentKey !== '' && $this->homepage->assetUrl($currentKey) !== null) {
                continue;
            }

            $this->upsertAsset(
                $profile,
                $binding['image_asset_key'],
                $binding['public_url'],
                $binding['path'] ?? '',
                $assetsUpserted,
            );

            if ($currentKey !== $binding['image_asset_key']) {
                $items[$index]['image_asset_key'] = $binding['image_asset_key'];
                $changed = true;
                $routesBound++;
            }
        }

        $content['routes']['items'] = $items;

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function bindFeaturedDeals(
        ClientProfile $profile,
        array $content,
        int &$dealsBound,
        int &$assetsUpserted,
        bool &$changed,
    ): array {
        $items = $content['featured_deals']['items'] ?? null;
        if (! is_array($items)) {
            return $content;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemId = trim((string) ($item['id'] ?? ''));
            $binding = JetpkHomepageMediaAuthorityCatalog::featuredDealBindings()[$itemId] ?? null;
            if ($binding === null) {
                continue;
            }

            $currentKey = trim((string) ($item['image_asset_key'] ?? ''));
            if ($currentKey !== '' && $this->homepage->assetUrl($currentKey) !== null) {
                continue;
            }

            $this->upsertAsset(
                $profile,
                $binding['image_asset_key'],
                $binding['public_url'],
                '',
                $assetsUpserted,
            );

            if ($currentKey !== $binding['image_asset_key']) {
                $items[$index]['image_asset_key'] = $binding['image_asset_key'];
                $changed = true;
                $dealsBound++;
            }
        }

        $content['featured_deals']['items'] = $items;

        return $content;
    }

    private function upsertAsset(
        ClientProfile $profile,
        string $assetKey,
        string $publicUrl,
        string $path,
        int &$assetsUpserted,
    ): void {
        $existing = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('asset_key', $assetKey)
            ->first();

        if ($existing !== null && trim((string) $existing->public_url) !== '') {
            return;
        }

        ClientPageAsset::query()->updateOrCreate(
            [
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => $assetKey,
            ],
            [
                'disk' => 'public',
                'path' => $path !== '' ? $path : 'client-assets/'.$profile->slug.'/pages/home/'.$assetKey,
                'public_url' => $publicUrl,
            ],
        );

        $assetsUpserted++;
    }
}
