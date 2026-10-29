<?php

namespace App\Services\Homepage;

use App\Models\ClientPageAsset;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageAssetPublicationService;
use App\Services\Client\ClientPageAssetService;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\JetpkHomepageFareDisplay;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * JetPK homepage media uploads under jetpk/homepage/* while reusing client_page_assets.
 */
final class JetpkHomepageAssetService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly ClientPageAssetService $assetService,
        private readonly ClientPageAssetPublicationService $publicationService,
        private readonly JetpkResponsiveImageOptimizer $responsiveOptimizer,
    ) {}

    public static function destinationAssetKey(string $itemId): string
    {
        $slug = Str::slug($itemId, '_');

        return 'destination_'.($slug !== '' ? $slug : 'item');
    }

    public static function routeAssetKey(string $itemId): string
    {
        $slug = Str::slug($itemId, '_');

        return 'route_'.($slug !== '' ? $slug : 'item');
    }

    public static function featuredDealAssetKey(string $itemId): string
    {
        $slug = Str::slug($itemId, '_');

        return 'featured_deal_'.($slug !== '' ? $slug : 'item');
    }

    public function storeRouteImage(
        ClientProfile $profile,
        string $itemId,
        UploadedFile $file,
        ?int $userId = null,
        ?string $altText = null,
    ): ClientPageAsset {
        return $this->storeScopedImage(
            $profile,
            self::routeAssetKey($itemId),
            $file,
            (string) config('jetpk_homepage.route_storage_prefix', 'jetpk/homepage/trending-routes'),
            $userId,
            $altText,
        );
    }

    public function storeFeaturedDealImage(
        ClientProfile $profile,
        string $itemId,
        UploadedFile $file,
        ?int $userId = null,
        ?string $altText = null,
    ): ClientPageAsset {
        return $this->storeScopedImage(
            $profile,
            self::featuredDealAssetKey($itemId),
            $file,
            (string) config('jetpk_homepage.featured_deal_storage_prefix', 'jetpk/homepage/featured-deals'),
            $userId,
            $altText,
        );
    }

    public function storeDestinationImage(
        ClientProfile $profile,
        string $itemId,
        UploadedFile $file,
        ?int $userId = null,
        ?string $altText = null,
    ): ClientPageAsset {
        return $this->storeScopedImage(
            $profile,
            self::destinationAssetKey($itemId),
            $file,
            (string) config('jetpk_homepage.destination_storage_prefix', 'jetpk/homepage/popular-destinations'),
            $userId,
            $altText,
        );
    }

    public function storeSupportCtaImage(
        ClientProfile $profile,
        string $variant,
        UploadedFile $file,
        ?int $userId = null,
        ?string $altText = null,
    ): ClientPageAsset {
        $assetKey = $variant === 'mobile' ? 'support_cta_background_mobile' : 'support_cta_background';

        return $this->storeScopedImage(
            $profile,
            $assetKey,
            $file,
            (string) config('jetpk_homepage.support_cta_storage_prefix', 'jetpk/homepage/support-cta'),
            $userId,
            $altText,
        );
    }

    public function urlForAssetKey(ClientProfile $profile, string $assetKey): ?string
    {
        return $this->assetService->urlFor($profile, ClientPageKeys::HOME, $assetKey);
    }

    public function destroyAsset(ClientPageAsset $asset): void
    {
        $this->assetService->destroy($asset);
    }

    private function storeScopedImage(
        ClientProfile $profile,
        string $assetKey,
        UploadedFile $file,
        string $storagePrefix,
        ?int $userId,
        ?string $altText,
    ): ClientPageAsset {
        $this->assertSafeImage($file);

        $profileSlug = trim((string) $profile->asset_profile) !== ''
            ? trim((string) $profile->asset_profile)
            : trim((string) $profile->slug);

        $extension = $this->resolveExtension($file);
        $filename = Str::slug($assetKey, '_').'-'.now()->format('YmdHis').'-'.substr(str_replace('.', '', uniqid('', true)), -10).'.'.$extension;
        $directory = rtrim($storagePrefix, '/').'/'.$profileSlug;
        $relativePath = $directory.'/'.$filename;

        $disk = Storage::disk('public');
        $stored = $disk->putFileAs($directory, $file, $filename);
        if ($stored === false || ! $disk->exists($relativePath)) {
            throw ValidationException::withMessages(['file' => 'The upload could not be saved.']);
        }

        $existing = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('asset_key', $assetKey)
            ->first();

        if ($existing !== null && $existing->path !== '' && $existing->path !== $relativePath) {
            $this->assetService->deleteStoredFile($existing->path, (string) ($existing->disk ?: 'public'));
        }

        $previousFingerprint = is_array($existing?->meta_json['responsive_media'] ?? null)
            ? (string) ($existing->meta_json['responsive_media']['fingerprint'] ?? '')
            : '';
        $previousProfile = is_array($existing?->meta_json['responsive_media'] ?? null)
            ? (string) ($existing->meta_json['responsive_media']['profile'] ?? '')
            : '';

        $asset = ClientPageAsset::query()->updateOrCreate(
            [
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => $assetKey,
            ],
            [
                'disk' => 'public',
                'path' => $relativePath,
                'public_url' => $disk->url($relativePath),
                'alt_text' => $altText,
                'meta_json' => [
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'original_name' => $file->getClientOriginalName(),
                    'extension' => $extension,
                ],
                'created_by' => $userId,
            ],
        );

        return $this->finalizeResponsiveOptimization(
            $asset,
            $profile,
            $assetKey,
            $previousFingerprint,
            $previousProfile,
        );
    }

    /**
     * Optimize an existing homepage scoped asset (used by uploads and backfill).
     */
    public function optimizeExistingAsset(ClientPageAsset $asset, ClientProfile $profile, bool $dryRun = false): array
    {
        $mediaProfile = $this->mediaProfileForAssetKey((string) $asset->asset_key);
        if ($mediaProfile === null) {
            return [
                'skipped' => true,
                'reason' => 'unsupported asset key',
                'asset_key' => $asset->asset_key,
            ];
        }

        $absolute = $this->assetService->absolutePathFor($asset);
        if ($absolute === null) {
            return [
                'skipped' => true,
                'reason' => 'missing source file',
                'asset_key' => $asset->asset_key,
            ];
        }

        $profileSlug = trim((string) $profile->asset_profile) !== ''
            ? trim((string) $profile->asset_profile)
            : trim((string) $profile->slug);

        $sourceSize = @getimagesize($absolute);
        $plan = [
            'asset_key' => $asset->asset_key,
            'original_format' => strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION)),
            'original_dimensions' => [
                'width' => (int) ($sourceSize[0] ?? 0),
                'height' => (int) ($sourceSize[1] ?? 0),
            ],
            'original_bytes' => is_file($absolute) ? (int) filesize($absolute) : 0,
            'planned_widths' => array_values($this->responsiveOptimizer->widthsFor($mediaProfile)),
            'planned_formats' => $this->plannedFormats(),
            'media_profile' => $mediaProfile,
            'existing_fingerprint' => $this->existingFingerprintFor($asset, $mediaProfile),
        ];

        if ($dryRun) {
            return array_merge($plan, ['dry_run' => true, 'writes' => false]);
        }

        $previousFingerprint = $plan['existing_fingerprint'];
        $sourceFingerprint = substr(hash_file('sha256', $absolute), 0, 16);
        $resolvedProfileSlug = $profileSlug !== '' ? $profileSlug : 'jetpk-assets';

        // Idempotent skip: same source fingerprint with active meta already published.
        if (
            $previousFingerprint !== ''
            && $previousFingerprint === $sourceFingerprint
            && $this->activeManifestMatchesSource($asset, $mediaProfile, (string) $asset->path)
        ) {
            return array_merge($plan, [
                'dry_run' => false,
                'activated' => true,
                'fingerprint' => $previousFingerprint,
                'unchanged' => true,
                'writes' => false,
                'warning' => null,
            ]);
        }

        $result = $this->responsiveOptimizer->optimize(
            $absolute,
            $resolvedProfileSlug,
            $mediaProfile,
            ClientPageKeys::HOME,
            (string) $asset->path,
        );

        $meta = is_array($asset->meta_json) ? $asset->meta_json : [];
        if ($result['activated'] && is_array($result['manifest'])) {
            if ($previousFingerprint !== '' && $previousFingerprint !== $result['fingerprint']) {
                $this->responsiveOptimizer->deleteVariantDirectory(
                    $resolvedProfileSlug,
                    ClientPageKeys::HOME,
                    $mediaProfile,
                    $previousFingerprint,
                );
            }
            if ($mediaProfile === JetpkResponsiveImageOptimizer::PROFILE_HERO) {
                $meta['hero_lcp'] = $result['manifest'];
                unset($meta['hero_lcp_warning']);
            } else {
                $meta['responsive_media'] = $result['manifest'];
                unset($meta['responsive_media_warning']);
            }
        } else {
            if ($mediaProfile === JetpkResponsiveImageOptimizer::PROFILE_HERO) {
                $meta['hero_lcp_warning'] = $result['warning'] ?? 'Hero optimization did not activate.';
                unset($meta['hero_lcp']);
            } else {
                $meta['responsive_media_warning'] = $result['warning'] ?? 'Responsive optimization did not activate.';
                // Do not keep stale derivatives active after a replacement source.
                unset($meta['responsive_media']);
            }
        }

        $asset->update(['meta_json' => $meta]);
        $this->publicationService->publishPublicDiskRelativePath((string) $asset->path);
        $this->publicationService->publishManyPublicDiskRelativePaths($result['published_paths']);

        return array_merge($plan, [
            'dry_run' => false,
            'activated' => $result['activated'],
            'fingerprint' => $result['fingerprint'],
            'unchanged' => false,
            'writes' => true,
            'warning' => $result['warning'],
        ]);
    }

    private function activeManifestMatchesSource(ClientPageAsset $asset, string $mediaProfile, string $sourcePath): bool
    {
        $meta = is_array($asset->meta_json) ? $asset->meta_json : [];
        $manifest = $mediaProfile === JetpkResponsiveImageOptimizer::PROFILE_HERO
            ? ($meta['hero_lcp'] ?? null)
            : ($meta['responsive_media'] ?? null);

        if (! is_array($manifest) || ! is_array($manifest['variants'] ?? null)) {
            return false;
        }

        $manifestSource = trim((string) ($manifest['source_path'] ?? ''));
        if ($manifestSource !== '' && $manifestSource !== trim($sourcePath)) {
            return false;
        }

        foreach ($manifest['variants'] as $formats) {
            if (! is_array($formats) || ! is_array($formats['jpg'] ?? null)) {
                continue;
            }
            $path = trim((string) ($formats['jpg']['path'] ?? ''));
            if ($path !== '' && Storage::disk('public')->exists($path)) {
                return true;
            }
        }

        return false;
    }

    private function finalizeResponsiveOptimization(
        ClientPageAsset $asset,
        ClientProfile $profile,
        string $assetKey,
        string $previousFingerprint,
        string $previousProfile,
    ): ClientPageAsset {
        $mediaProfile = $this->mediaProfileForAssetKey($assetKey);
        if ($mediaProfile === null) {
            $this->publicationService->publishPublicDiskRelativePath((string) $asset->path);

            return $asset;
        }

        $absolute = $this->assetService->absolutePathFor($asset);
        if ($absolute === null) {
            $this->publicationService->publishPublicDiskRelativePath((string) $asset->path);

            return $asset;
        }

        $profileSlug = trim((string) $profile->asset_profile) !== ''
            ? trim((string) $profile->asset_profile)
            : trim((string) $profile->slug);

        $result = $this->responsiveOptimizer->optimize(
            $absolute,
            $profileSlug !== '' ? $profileSlug : 'jetpk-assets',
            $mediaProfile,
            ClientPageKeys::HOME,
            (string) $asset->path,
        );

        $meta = is_array($asset->meta_json) ? $asset->meta_json : [];
        if ($result['activated'] && is_array($result['manifest'])) {
            if ($previousFingerprint !== ''
                && $previousFingerprint !== $result['fingerprint']
                && $previousProfile !== ''
            ) {
                $this->responsiveOptimizer->deleteVariantDirectory(
                    $profileSlug !== '' ? $profileSlug : 'jetpk-assets',
                    ClientPageKeys::HOME,
                    $previousProfile,
                    $previousFingerprint,
                );
            }
            $meta['responsive_media'] = $result['manifest'];
            unset($meta['responsive_media_warning']);
        } else {
            $meta['responsive_media_warning'] = $result['warning'] ?? 'Responsive optimization did not activate.';
            unset($meta['responsive_media']);
        }

        $asset->update(['meta_json' => $meta]);
        $this->publicationService->publishPublicDiskRelativePath((string) $asset->path);
        $this->publicationService->publishManyPublicDiskRelativePaths($result['published_paths']);

        return $asset->fresh() ?? $asset;
    }

    public function mediaProfileForAssetKey(string $assetKey): ?string
    {
        if (str_starts_with($assetKey, 'destination_')
            || str_starts_with($assetKey, 'route_')
            || str_starts_with($assetKey, 'featured_deal_')
        ) {
            return JetpkResponsiveImageOptimizer::PROFILE_CARD;
        }

        if (str_starts_with($assetKey, 'support_cta_background')) {
            return JetpkResponsiveImageOptimizer::PROFILE_SUPPORT_BACKGROUND;
        }

        if ($assetKey === 'hero_background') {
            return JetpkResponsiveImageOptimizer::PROFILE_HERO;
        }

        return null;
    }

    private function existingFingerprintFor(ClientPageAsset $asset, string $mediaProfile): string
    {
        $meta = is_array($asset->meta_json) ? $asset->meta_json : [];
        if ($mediaProfile === JetpkResponsiveImageOptimizer::PROFILE_HERO) {
            return is_array($meta['hero_lcp'] ?? null)
                ? (string) ($meta['hero_lcp']['fingerprint'] ?? '')
                : '';
        }

        return is_array($meta['responsive_media'] ?? null)
            ? (string) ($meta['responsive_media']['fingerprint'] ?? '')
            : '';
    }

    /**
     * @return list<string>
     */
    private function plannedFormats(): array
    {
        $formats = ['jpg'];
        if (function_exists('imagewebp')) {
            $formats[] = 'webp';
        }
        if (function_exists('imageavif')) {
            $formats[] = 'avif';
        }

        return $formats;
    }

    private function assertSafeImage(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'The upload is invalid or incomplete.']);
        }

        $extension = strtolower((string) $file->extension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'Only JPEG, PNG, and WebP images are allowed.']);
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['file' => 'Invalid image MIME type.']);
        }

        if ($file->getSize() > 5120 * 1024) {
            throw ValidationException::withMessages(['file' => 'Image must be 5 MB or smaller.']);
        }
    }

    private function resolveExtension(UploadedFile $file): string
    {
        $detected = strtolower((string) $file->extension());
        if (in_array($detected, self::ALLOWED_EXTENSIONS, true)) {
            return $detected === 'jpeg' ? 'jpg' : $detected;
        }

        return match ((string) $file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function safeItemKey(string $itemId): string
    {
        $slug = Str::slug($itemId, '_');

        return $slug !== '' ? $slug : 'item';
    }
}
