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
    ) {}

    public static function destinationAssetKey(string $itemId): string
    {
        $slug = Str::slug($itemId, '_');

        return 'destination_'.($slug !== '' ? $slug : 'item');
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

        $this->publicationService->publishPublicDiskRelativePath($relativePath);

        return $asset->fresh() ?? $asset;
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
