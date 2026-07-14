<?php

namespace App\Services\Media;

use App\Data\Media\BackgroundRemovalInput;
use App\Enums\BrandingAssetProcessStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\BrandingAssetProcess;
use App\Models\User;
use App\Services\Agencies\AgencyBrandingService;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates logo background-removal staging, provider calls, validation, and acceptance.
 */
final class BackgroundRemovalService
{
    private const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

    public function __construct(
        private readonly BackgroundRemovalSettingsService $settingsService,
        private readonly ImageTransparencyInspector $transparencyInspector,
        private readonly AgencyBrandingService $brandingService,
    ) {}

    public function stageLogoUpload(Agency $agency, User $actor, UploadedFile $file): BrandingAssetProcess
    {
        $this->assertRasterLogoUpload($file, $this->settingsService->getForAgency($agency));

        $uuid = (string) Str::uuid();
        $ext = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $relative = "branding-background-removal/{$agency->id}/{$uuid}/original.{$ext}";

        Storage::disk('local')->putFileAs(
            dirname($relative),
            $file,
            basename($relative),
        );

        $absolute = Storage::disk('local')->path($relative);
        $checksum = hash_file('sha256', $absolute) ?: null;
        $inspection = $this->transparencyInspector->inspect($absolute);

        $duplicate = BrandingAssetProcess::query()
            ->where('agency_id', $agency->id)
            ->where('asset_type', 'logo')
            ->where('source_checksum', $checksum)
            ->whereIn('status', [
                BrandingAssetProcessStatus::Pending,
                BrandingAssetProcessStatus::Processing,
            ])
            ->exists();

        if ($duplicate) {
            Storage::disk('local')->delete($relative);

            throw ValidationException::withMessages([
                'logo' => 'This logo is already being processed. Wait for the current job or discard it first.',
            ]);
        }

        $process = BrandingAssetProcess::query()->create([
            'uuid' => $uuid,
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'asset_type' => 'logo',
            'status' => BrandingAssetProcessStatus::Pending,
            'source_path' => $relative,
            'source_checksum' => $checksum,
            'source_mime' => (string) $file->getMimeType(),
            'source_size' => $file->getSize(),
            'warnings' => $inspection->warning ? [$inspection->warning] : (
                $inspection->hasTransparentPixels ? ['This image already contains transparency. Keeping the original is recommended.'] : []
            ),
            'expires_at' => now()->addHours((int) config('background-removal.staging_ttl_hours', 72)),
        ]);

        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => 'branding.logo_background_staged',
            'auditable_type' => BrandingAssetProcess::class,
            'auditable_id' => $process->id,
            'properties' => ['new_values' => SensitiveDataRedactor::redact(['uuid' => $uuid, 'asset_type' => 'logo'])],
        ]);

        return $process;
    }

    public function process(BrandingAssetProcess $process): BrandingAssetProcess
    {
        $this->assertAgencyProcess($process);
        if (! in_array($process->status, [BrandingAssetProcessStatus::Pending, BrandingAssetProcessStatus::Failed], true)) {
            return $process;
        }

        $agency = $process->agency;
        $settings = $this->settingsService->getForAgency($agency);
        $provider = $this->settingsService->resolveProvider($settings);

        if (! $provider->isConfigured()) {
            return $this->markFailed($process, 'provider_disabled', 'Background removal is not available. Keep the original logo or configure a provider.');
        }

        $process->forceFill([
            'status' => BrandingAssetProcessStatus::Processing,
            'provider' => $provider->providerName(),
        ])->save();

        $sourceAbsolute = Storage::disk('local')->path($process->source_path);
        $result = $provider->remove(new BackgroundRemovalInput(
            absoluteSourcePath: $sourceAbsolute,
            sourceMime: (string) $process->source_mime,
            timeoutSeconds: min(
                (int) ($settings->timeout_seconds ?? 30),
                (int) config('background-removal.max_timeout_seconds', 120),
            ),
            idempotencyKey: $process->source_checksum,
        ));

        if (! $result->success || $result->outputAbsolutePath === null) {
            return $this->markFailed(
                $process,
                $result->errorCode ?? 'provider_error',
                $result->errorMessageSafe ?? 'Background removal failed.',
                $result->processingMs,
            );
        }

        $validation = $this->validateProcessedPng($result->outputAbsolutePath, $settings);
        if ($validation !== null) {
            @unlink($result->outputAbsolutePath);

            return $this->markFailed($process, $validation['code'], $validation['message'], $result->processingMs);
        }

        $processedRelative = "branding-background-removal/{$agency->id}/{$process->uuid}/processed.png";
        Storage::disk('local')->put($processedRelative, file_get_contents($result->outputAbsolutePath) ?: '');
        @unlink($result->outputAbsolutePath);

        $processedAbsolute = Storage::disk('local')->path($processedRelative);
        $inspection = $this->transparencyInspector->inspect($processedAbsolute);
        [$width, $height] = @getimagesize($processedAbsolute) ?: [null, null];

        $warnings = array_values(array_filter(array_merge(
            $process->warnings ?? [],
            $result->warnings,
            $inspection->warning ? [$inspection->warning] : [],
        )));

        $process->forceFill([
            'status' => BrandingAssetProcessStatus::Completed,
            'result_path' => $processedRelative,
            'result_checksum' => hash_file('sha256', $processedAbsolute) ?: null,
            'result_mime' => 'image/png',
            'result_size' => filesize($processedAbsolute) ?: null,
            'width' => $width,
            'height' => $height,
            'transparent_ratio' => $inspection->transparentPixelRatio,
            'opaque_ratio' => $inspection->opaquePixelRatio,
            'provider_request_id' => $result->providerRequestId,
            'processing_ms' => $result->processingMs,
            'warnings' => $warnings,
            'error_code' => null,
            'error_message_safe' => null,
        ])->save();

        return $process->fresh();
    }

    public function acceptProcessedLogo(BrandingAssetProcess $process, User $actor): BrandingAssetProcess
    {
        $this->assertAgencyProcess($process);
        if ($process->status !== BrandingAssetProcessStatus::Completed || blank($process->result_path)) {
            throw ValidationException::withMessages(['process' => 'Processed logo is not ready to accept.']);
        }

        $agency = $process->agency;
        $processedAbsolute = Storage::disk('local')->path($process->result_path);
        $sanitized = $this->reencodePngWithoutMetadata($processedAbsolute);
        $hash = substr(hash('sha256', $sanitized), 0, 12);
        $publicRelative = "agencies/{$agency->id}/branding/logo-".now()->format('YmdHis')."-{$hash}.png";

        Storage::disk('public')->put($publicRelative, $sanitized);

        $settings = $this->brandingService->getSettingsForAgency($agency);
        $settings->logo_path = $publicRelative;
        $settings->save();

        $process->forceFill([
            'status' => BrandingAssetProcessStatus::Accepted,
            'accepted_at' => now(),
        ])->save();

        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => 'branding.logo_background_accepted',
            'auditable_type' => BrandingAssetProcess::class,
            'auditable_id' => $process->id,
            'properties' => ['new_values' => ['logo_path' => $publicRelative]],
        ]);

        return $process->fresh();
    }

    public function discard(BrandingAssetProcess $process, User $actor): BrandingAssetProcess
    {
        $this->assertAgencyProcess($process);
        $process->forceFill([
            'status' => BrandingAssetProcessStatus::Discarded,
            'discarded_at' => now(),
        ])->save();

        AuditLog::query()->create([
            'agency_id' => $process->agency_id,
            'user_id' => $actor->id,
            'action' => 'branding.logo_background_discarded',
            'auditable_type' => BrandingAssetProcess::class,
            'auditable_id' => $process->id,
            'properties' => ['new_values' => []],
        ]);

        return $process->fresh();
    }

    /**
     * @return array{code: string, message: string}|null
     */
    private function validateProcessedPng(string $absolutePath, \App\Models\BackgroundRemovalSetting $settings): ?array
    {
        if (! is_file($absolutePath)) {
            return ['code' => 'invalid_output', 'message' => 'Processed image is missing.'];
        }

        $size = filesize($absolutePath) ?: 0;
        if ($size < 100 || $size > ($settings->max_source_bytes ?? 5_242_880)) {
            return ['code' => 'invalid_output', 'message' => 'Processed image size is not acceptable.'];
        }

        $info = @getimagesize($absolutePath);
        if ($info === false || ($info['mime'] ?? '') !== 'image/png') {
            return ['code' => 'invalid_output', 'message' => 'Processed image must be a valid PNG.'];
        }

        $pixels = (int) $info[0] * (int) $info[1];
        if ($pixels <= 0 || $pixels > (int) ($settings->max_source_pixels ?? 16_777_216)) {
            return ['code' => 'invalid_output', 'message' => 'Processed image dimensions are not acceptable.'];
        }

        $inspection = $this->transparencyInspector->inspect($absolutePath);
        if ($inspection->known && ! $inspection->hasTransparentPixels) {
            return ['code' => 'no_transparency', 'message' => 'Processed image does not contain transparency.'];
        }

        if ($inspection->isFullyTransparent) {
            return ['code' => 'fully_transparent', 'message' => 'Processed image appears fully transparent.'];
        }

        $minOpaque = (float) config('background-removal.min_opaque_pixel_ratio', 0.01);
        if ($inspection->known && $inspection->opaquePixelRatio < $minOpaque) {
            return ['code' => 'no_foreground', 'message' => 'Processed image does not contain enough visible foreground.'];
        }

        return null;
    }

    private function markFailed(
        BrandingAssetProcess $process,
        string $code,
        string $message,
        int $processingMs = 0,
    ): BrandingAssetProcess {
        $process->forceFill([
            'status' => BrandingAssetProcessStatus::Failed,
            'error_code' => $code,
            'error_message_safe' => $message,
            'processing_ms' => $processingMs,
        ])->save();

        return $process->fresh();
    }

    private function assertRasterLogoUpload(UploadedFile $file, \App\Models\BackgroundRemovalSetting $settings): void
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['logo' => 'Only PNG, JPEG, or WebP logos can use background removal. SVG uses the sanitized SVG workflow.']);
        }

        if (strtolower((string) $file->getClientOriginalExtension()) === 'svg') {
            throw ValidationException::withMessages(['logo' => 'SVG logos cannot use AI background removal.']);
        }

        if ($file->getSize() > (int) ($settings->max_source_bytes ?? 5_242_880)) {
            throw ValidationException::withMessages(['logo' => 'Logo file is too large for background removal.']);
        }

        $info = @getimagesize($file->getRealPath() ?: $file->getPathname());
        if ($info === false) {
            throw ValidationException::withMessages(['logo' => 'Uploaded file is not a valid image.']);
        }

        $pixels = (int) $info[0] * (int) $info[1];
        if ($pixels <= 0 || $pixels > (int) ($settings->max_source_pixels ?? 16_777_216)) {
            throw ValidationException::withMessages(['logo' => 'Image dimensions are too large for processing.']);
        }
    }

    private function assertAgencyProcess(BrandingAssetProcess $process): void
    {
        if ($process->asset_type !== 'logo') {
            throw ValidationException::withMessages(['process' => 'Unsupported asset process type.']);
        }
    }

    private function reencodePngWithoutMetadata(string $absolutePath): string
    {
        if (! extension_loaded('gd')) {
            $contents = file_get_contents($absolutePath);

            return $contents !== false ? $contents : '';
        }

        $image = @imagecreatefrompng($absolutePath);
        if ($image === false) {
            throw ValidationException::withMessages(['process' => 'Processed logo file is not a valid PNG.']);
        }

        imagesavealpha($image, true);
        imagealphablending($image, false);

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
