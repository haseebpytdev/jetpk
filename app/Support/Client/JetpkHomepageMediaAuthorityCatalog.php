<?php

namespace App\Support\Client;

use App\Services\Homepage\JetpkHomepageAssetService;

/**
 * Authoritative homepage route/deal media bindings recovered from production CMS
 * evidence (probe-homepage.json, golden prod snapshots, live asset probes).
 *
 * These map stable CMS item IDs to existing approved server assets. No new images
 * are generated; bindings reference assets already approved for JetPakistan.
 */
final class JetpkHomepageMediaAuthorityCatalog
{
    /**
     * @return array<string, array{image_asset_key: string, public_url: string, path?: string, evidence: string}>
     */
    public static function routeBindings(): array
    {
        return [
            'seed-khi-dxb' => [
                'image_asset_key' => JetpkHomepageAssetService::routeAssetKey('seed-khi-dxb'),
                'public_url' => '/images/home/destination-dubai.jpg',
                'evidence' => 'Golden route card photography; legacy route CMS upload missing on server (404)',
            ],
            'seed-lhe-jed' => [
                'image_asset_key' => JetpkHomepageAssetService::routeAssetKey('seed-lhe-jed'),
                'public_url' => '/images/home/destination-jeddah.jpg',
                'evidence' => 'Golden route card photography; legacy route CMS upload missing on server (404)',
            ],
            'seed-isb-lhr' => [
                'image_asset_key' => JetpkHomepageAssetService::routeAssetKey('seed-isb-lhr'),
                'public_url' => '/images/home/destination-london.jpg',
                'evidence' => 'Golden route card photography; route_seed_isb_lhr storage missing (404)',
            ],
            'seed-khi-ruh' => [
                'image_asset_key' => 'route_seed_khi_ruh',
                'public_url' => 'https://jetpakistan.pk/storage/client-assets/jetpk-assets/pages/home/route_seed_khi_ruh-20260913115311.png',
                'path' => 'client-assets/jetpk-assets/pages/home/route_seed_khi_ruh-20260913115311.png',
                'evidence' => 'Production CMS upload route_seed_khi_ruh-20260913115311.png (HTTP 200)',
            ],
        ];
    }

    /**
     * @return array<string, array{image_asset_key: string, public_url: string, evidence: string}>
     */
    public static function featuredDealBindings(): array
    {
        return [
            '2b3881220c1477fe53deab40b3d0170a' => [
                'image_asset_key' => JetpkHomepageAssetService::featuredDealAssetKey('2b3881220c1477fe53deab40b3d0170a'),
                'public_url' => '/images/home/offer-gcc.jpg',
                'evidence' => 'Approved featured-deal photography for Air Arabia ISB→DXB slot',
            ],
            '9ae5ad6bca0bf3481c79635c582d4531' => [
                'image_asset_key' => JetpkHomepageAssetService::featuredDealAssetKey('9ae5ad6bca0bf3481c79635c582d4531'),
                'public_url' => '/images/home/offer-uk.jpg',
                'evidence' => 'Approved featured-deal photography for AirBlue LHE→IST slot',
            ],
            'aa6546f91e6984f9ae3ad93929189a6b' => [
                'image_asset_key' => JetpkHomepageAssetService::featuredDealAssetKey('aa6546f91e6984f9ae3ad93929189a6b'),
                'public_url' => '/images/home/offer-domestic.jpg',
                'evidence' => 'Approved featured-deal photography for AirSial ISB→JED slot',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function assetKeyVariants(string $assetKey): array
    {
        $variants = [trim($assetKey)];
        $underscored = str_replace('-', '_', $assetKey);
        $hyphenated = str_replace('_', '-', $assetKey);

        if ($underscored !== $assetKey) {
            $variants[] = $underscored;
        }
        if ($hyphenated !== $assetKey) {
            $variants[] = $hyphenated;
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
