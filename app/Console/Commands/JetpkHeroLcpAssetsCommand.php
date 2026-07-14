<?php

namespace App\Console\Commands;

use App\Models\ClientPageAsset;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageAssetPublicationService;
use App\Services\Client\ClientPageAssetService;
use App\Services\Homepage\JetpkHeroImageOptimizer;
use App\Support\Client\ClientPageKeys;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Regenerates validated responsive hero variants for the current CMS hero source.
 */
class JetpkHeroLcpAssetsCommand extends Command
{
    protected $signature = 'jetpk:hero-lcp-assets
        {--source= : Absolute path to an original hero upload (never an existing lcp derivative)}
        {--profile= : Client asset profile slug}
        {--dry-run : Inspect the resolved source and exit without writing variants}';

    protected $description = 'Generate validated responsive hero variants for the current CMS hero';

    public function handle(
        JetpkHeroImageOptimizer $optimizer,
        ClientPageAssetService $assetService,
        ClientPageAssetPublicationService $publicationService,
    ): int {
        $resolved = $this->resolveSource($assetService);
        if ($resolved === null) {
            $this->error('Could not resolve a CMS hero source. Upload hero_background or pass --source= to an original file.');

            return self::FAILURE;
        }

        ['path' => $source, 'profile_slug' => $profileSlug, 'asset' => $asset] = $resolved;

        if ($this->isDerivativePath($source)) {
            $this->error('Refusing to optimize an existing lcp derivative. Provide the original CMS upload path.');

            return self::FAILURE;
        }

        $fingerprint = substr(hash_file('sha256', $source), 0, 16);
        $this->line('Source: '.$source);
        $this->line('Fingerprint: '.$fingerprint);
        $this->line('Profile: '.$profileSlug);

        if ((bool) $this->option('dry-run')) {
            return self::SUCCESS;
        }

        $existingFingerprint = is_array($asset?->meta_json['hero_lcp'] ?? null)
            ? (string) ($asset->meta_json['hero_lcp']['fingerprint'] ?? '')
            : '';

        $result = $optimizer->optimize($source, $profileSlug, ClientPageKeys::HOME, $asset?->path);
        foreach ($result['validation'] as $row) {
            $status = ($row['valid'] ?? false) ? 'PASS' : 'FAIL';
            $this->line(sprintf(
                '%s %s/%s %s',
                $status,
                $row['breakpoint'] ?? '-',
                $row['format'] ?? '-',
                $row['reason'] ?? '',
            ));
        }

        if (! $result['activated'] || ! is_array($result['manifest'])) {
            $this->error($result['warning'] ?? 'Hero optimization failed.');

            return self::FAILURE;
        }

        if ($existingFingerprint !== '' && $existingFingerprint !== $result['fingerprint']) {
            $optimizer->deleteVariantDirectory($profileSlug, ClientPageKeys::HOME, $existingFingerprint);
        }

        if ($asset instanceof ClientPageAsset) {
            $meta = is_array($asset->meta_json) ? $asset->meta_json : [];
            $meta['hero_lcp'] = $result['manifest'];
            unset($meta['hero_lcp_warning']);
            $asset->update(['meta_json' => $meta]);
        }

        $publicationService->publishManyPublicDiskRelativePaths($result['published_paths']);
        $this->quarantineLegacyFlatVariants($profileSlug);
        $this->info('Validated hero variants generated and published.');

        return self::SUCCESS;
    }

    /**
     * @return array{path: string, profile_slug: string, asset: ?ClientPageAsset}|null
     */
    private function resolveSource(ClientPageAssetService $assetService): ?array
    {
        $explicit = trim((string) $this->option('source'));
        if ($explicit !== '') {
            $path = is_file($explicit) ? $explicit : public_path(ltrim($explicit, '/'));
            if (! is_file($path)) {
                return null;
            }

            return [
                'path' => $path,
                'profile_slug' => trim((string) $this->option('profile')) ?: 'jetpk-assets',
                'asset' => null,
            ];
        }

        $asset = ClientPageAsset::query()
            ->where('page_key', ClientPageKeys::HOME)
            ->where('asset_key', 'hero_background')
            ->latest('id')
            ->first();

        if ($asset === null) {
            return null;
        }

        $absolute = $assetService->absolutePathFor($asset);
        if ($absolute === null) {
            return null;
        }

        $profile = ClientProfile::query()->find($asset->client_profile_id);
        $profileSlug = trim((string) ($profile?->asset_profile ?: $profile?->slug ?: $this->option('profile')));

        return [
            'path' => $absolute,
            'profile_slug' => $profileSlug !== '' ? $profileSlug : 'jetpk-assets',
            'asset' => $asset,
        ];
    }

    private function isDerivativePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return str_contains($normalized, '/pages/home/lcp/');
    }

    private function quarantineLegacyFlatVariants(string $profileSlug): void
    {
        $legacyDir = public_path('client-assets/'.$profileSlug.'/pages/home/lcp');
        if (! is_dir($legacyDir)) {
            return;
        }

        foreach (glob($legacyDir.'/hero-*.{avif,webp,jpg,jpeg}', GLOB_BRACE) ?: [] as $legacyFile) {
            if (is_file($legacyFile)) {
                @unlink($legacyFile);
            }
        }
    }
}
