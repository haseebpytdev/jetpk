<?php

namespace App\Services\Client;

use App\Models\ClientPageAsset;
use App\Models\ClientProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores client-scoped page assets on the public disk:
 * storage/app/public/client-assets/{profile}/pages/{pageKey}/ → /storage/client-assets/...
 */
final class ClientPageAssetService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

    public function store(
        ClientProfile $profile,
        string $pageKey,
        string $assetKey,
        UploadedFile $file,
        ?int $userId = null,
        ?string $altText = null,
    ): ClientPageAsset {
        $this->assertUploadReadable($file);

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $extension = $this->resolveSafeExtension($file, $mimeType);

        $profileSlug = trim((string) $profile->asset_profile) !== ''
            ? trim((string) $profile->asset_profile)
            : trim((string) $profile->slug);

        $safeKey = Str::slug($assetKey, '_');
        $filename = $safeKey.'-'.now()->format('YmdHis').'.'.$extension;
        $directory = 'client-assets/'.$profileSlug.'/pages/'.$pageKey;
        $relativePath = $directory.'/'.$filename;

        $disk = Storage::disk('public');
        $existing = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('asset_key', $assetKey)
            ->first();
        $previousPath = $existing?->path;

        try {
            $stored = $disk->putFileAs($directory, $file, $filename);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => 'The upload could not be saved. Please try again.',
            ]);
        }

        if ($stored === false || ! $disk->exists($relativePath) || ! is_readable($disk->path($relativePath))) {
            throw ValidationException::withMessages([
                'file' => 'The upload could not be saved. Please try again.',
            ]);
        }

        $publicUrl = $disk->url($relativePath);

        try {
            $asset = ClientPageAsset::query()->updateOrCreate(
                [
                    'client_profile_id' => $profile->id,
                    'page_key' => $pageKey,
                    'asset_key' => $assetKey,
                ],
                [
                    'disk' => 'public',
                    'path' => $relativePath,
                    'public_url' => $publicUrl,
                    'alt_text' => $altText,
                    'meta_json' => [
                        'mime' => $mimeType,
                        'size' => $size,
                        'original_name' => $originalName,
                        'extension' => $extension,
                    ],
                    'created_by' => $userId,
                ],
            );

            if ($previousPath !== null && $previousPath !== '' && $previousPath !== $relativePath) {
                $this->deleteStoredFile($previousPath, (string) ($existing?->disk ?: 'public'));
            }

            return $asset;
        } catch (\Throwable $e) {
            $disk->delete($relativePath);

            throw $e;
        }
    }

    public function destroy(ClientPageAsset $asset): void
    {
        if ($asset->path !== '') {
            $this->deleteStoredFile($asset->path, (string) ($asset->disk ?: 'public'));
        }

        $asset->delete();
    }

    public function urlFor(ClientProfile $profile, string $pageKey, string $assetKey): ?string
    {
        if (! Schema::hasTable('client_page_assets')) {
            return null;
        }

        $asset = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('asset_key', $assetKey)
            ->first();

        if ($asset === null || $asset->path === '') {
            return null;
        }

        $diskName = (string) ($asset->disk ?: 'public');

        if (Storage::disk($diskName)->exists($asset->path)) {
            return Storage::disk($diskName)->url($asset->path);
        }

        return $asset->public_url ?: null;
    }

    public function absolutePathFor(ClientPageAsset $asset): ?string
    {
        if ($asset->path === '') {
            return null;
        }

        $diskName = (string) ($asset->disk ?: 'public');
        $disk = Storage::disk($diskName);

        if ($disk->exists($asset->path)) {
            return $disk->path($asset->path);
        }

        $legacy = public_path($asset->path);

        return is_file($legacy) ? $legacy : null;
    }

    public function deleteStoredFile(string $path, string $diskName = 'public'): void
    {
        if ($path === '') {
            return;
        }

        $disk = Storage::disk($diskName);
        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        $legacy = public_path($path);
        if (is_file($legacy)) {
            @unlink($legacy);
        }
    }

    private function assertUploadReadable(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'The upload is invalid or incomplete.',
            ]);
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_readable($realPath)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read.',
            ]);
        }
    }

    private function resolveSafeExtension(UploadedFile $file, string $mimeType): string
    {
        $detected = strtolower((string) $file->extension());
        if (in_array($detected, self::ALLOWED_EXTENSIONS, true)) {
            return $detected;
        }

        $clientExtension = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($clientExtension, self::ALLOWED_EXTENSIONS, true)) {
            return $clientExtension;
        }

        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'bin',
        };
    }
}
