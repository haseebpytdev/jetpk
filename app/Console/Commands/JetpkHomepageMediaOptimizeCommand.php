<?php

namespace App\Console\Commands;

use App\Models\ClientPageAsset;
use App\Models\ClientProfile;
use App\Services\Homepage\JetpkHomepageAssetService;
use App\Services\Homepage\JetpkResponsiveImageOptimizer;
use App\Support\Client\ClientPageKeys;
use Illuminate\Console\Command;

/**
 * Idempotent JetPakistan-homepage-scoped responsive media backfill.
 */
class JetpkHomepageMediaOptimizeCommand extends Command
{
    protected $signature = 'jetpk:homepage-media-optimize
        {--dry-run : Report planned derivatives without writing}
        {--profile=jetpk : Client profile slug (JetPakistan only by default)}
        {--asset= : Optional single asset_key filter}';

    protected $description = 'Generate responsive homepage CMS media derivatives for JetPakistan only';

    public function handle(JetpkHomepageAssetService $homepageAssets): int
    {
        $profileSlug = trim((string) $this->option('profile')) ?: 'jetpk';
        $dryRun = (bool) $this->option('dry-run');
        $assetFilter = trim((string) $this->option('asset'));

        $profile = ClientProfile::query()->where('slug', $profileSlug)->first();
        if ($profile === null) {
            $this->error('Client profile not found: '.$profileSlug);

            return self::FAILURE;
        }

        // Hard scope: refuse non-JetPakistan profiles unless explicitly jetpk*.
        if (! str_starts_with(strtolower($profileSlug), 'jetpk')) {
            $this->error('Refusing cross-client optimize. Profile must be JetPakistan-scoped (jetpk*).');

            return self::FAILURE;
        }

        $query = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->orderBy('asset_key');

        if ($assetFilter !== '') {
            $query->where('asset_key', $assetFilter);
        }

        $assets = $query->get();
        $processed = 0;
        $skipped = 0;

        foreach ($assets as $asset) {
            $mediaProfile = $homepageAssets->mediaProfileForAssetKey((string) $asset->asset_key);
            if ($mediaProfile === null) {
                $skipped++;
                continue;
            }

            // Hero remains owned by ClientPageAssetService / jetpk:hero-lcp-assets,
            // but backfill may still optimize hero through the shared profile when asked.
            if ($mediaProfile === JetpkResponsiveImageOptimizer::PROFILE_HERO && $assetFilter === '') {
                // Include hero in full homepage backfill.
            }

            $result = $homepageAssets->optimizeExistingAsset($asset, $profile, $dryRun);
            $processed++;

            $this->line(sprintf(
                '%s key=%s format=%s %dx%d bytes=%d widths=%s formats=%s fingerprint=%s%s',
                $dryRun ? 'DRY' : 'RUN',
                $result['asset_key'] ?? $asset->asset_key,
                $result['original_format'] ?? '-',
                (int) ($result['original_dimensions']['width'] ?? 0),
                (int) ($result['original_dimensions']['height'] ?? 0),
                (int) ($result['original_bytes'] ?? 0),
                implode(',', $result['planned_widths'] ?? []),
                implode(',', $result['planned_formats'] ?? []),
                $result['existing_fingerprint'] !== '' ? $result['existing_fingerprint'] : ($result['fingerprint'] ?? '-'),
                isset($result['activated']) ? (' activated='.($result['activated'] ? 'yes' : 'no')) : '',
            ));
        }

        $this->info(sprintf(
            'Done. profile=%s dry_run=%s processed=%d skipped=%d writes=%s',
            $profileSlug,
            $dryRun ? 'yes' : 'no',
            $processed,
            $skipped,
            $dryRun ? 'no' : 'yes',
        ));

        return self::SUCCESS;
    }
}
