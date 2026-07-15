<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Branding\RemoveBrandLogoBackground;
use App\Models\Agency;
use App\Models\BrandingAssetProcess;
use App\Services\Media\BackgroundRemovalService;
use App\Services\Media\BackgroundRemovalSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class BrandingLogoBackgroundController extends Controller
{
    public function __construct(
        private readonly BackgroundRemovalService $backgroundRemovalService,
        private readonly BackgroundRemovalSettingsService $settingsService,
    ) {}

    public function stage(Request $request): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);

        $request->validate([
            'logo' => ['required', 'file', 'max:5120'],
        ]);

        $process = $this->backgroundRemovalService->stageLogoUpload(
            $agency,
            $request->user(),
            $request->file('logo'),
        );

        return response()->json($this->serializeProcess($process));
    }

    public function run(Request $request, BrandingAssetProcess $process): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);
        $this->assertProcessAgency($process, $agency);

        if ($process->status === \App\Enums\BrandingAssetProcessStatus::Processing) {
            return response()->json($this->serializeProcess($process));
        }

        try {
            RemoveBrandLogoBackground::dispatchSync($process->id);
        } catch (\Throwable $e) {
            report($e);
            $process = $process->fresh();
            if ($process !== null && $process->status === \App\Enums\BrandingAssetProcessStatus::Processing) {
                $process->forceFill([
                    'status' => \App\Enums\BrandingAssetProcessStatus::Failed,
                    'error_code' => 'provider_error',
                    'error_message_safe' => 'Background removal failed. Please try again or keep the original logo.',
                ])->save();
            }
        }

        return response()->json($this->serializeProcess($process->fresh()));
    }

    public function show(Request $request, BrandingAssetProcess $process): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('view', $agency);
        $this->assertProcessAgency($process, $agency);

        return response()->json($this->serializeProcess($process));
    }

    public function accept(Request $request, BrandingAssetProcess $process): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);
        $this->assertProcessAgency($process, $agency);

        $process = $this->backgroundRemovalService->acceptProcessedLogo($process, $request->user());

        return response()->json($this->serializeProcess($process));
    }

    public function discard(Request $request, BrandingAssetProcess $process): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);
        $this->assertProcessAgency($process, $agency);

        $process = $this->backgroundRemovalService->discard($process, $request->user());

        return response()->json($this->serializeProcess($process));
    }

    public function preview(Request $request, BrandingAssetProcess $process, string $variant): Response
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('view', $agency);
        $this->assertProcessAgency($process, $agency);

        $path = match ($variant) {
            'original' => $process->source_path,
            'processed' => $process->result_path,
            default => null,
        };

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $mime = $variant === 'processed' ? 'image/png' : (string) $process->source_mime;

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProcess(BrandingAssetProcess $process): array
    {
        $settings = $this->settingsService->getForAgency($process->agency);
        $providerConfigured = $this->settingsService->resolveProvider($settings)->isConfigured();

        return [
            'uuid' => $process->uuid,
            'status' => $process->status->value,
            'warnings' => $process->warnings ?? [],
            'error_code' => $process->error_code,
            'error_message' => $process->error_message_safe,
            'transparent_ratio' => $process->transparent_ratio,
            'width' => $process->width,
            'height' => $process->height,
            'provider' => $process->provider,
            'provider_configured' => $providerConfigured,
            'privacy_notice' => $providerConfigured
                ? 'This image will be sent to the configured background-removal provider for processing.'
                : null,
            'preview_urls' => [
                'original' => route('admin.settings.branding.logo-background.preview', ['process' => $process->uuid, 'variant' => 'original']),
                'processed' => $process->result_path
                    ? route('admin.settings.branding.logo-background.preview', ['process' => $process->uuid, 'variant' => 'processed'])
                    : null,
            ],
        ];
    }

    private function assertProcessAgency(BrandingAssetProcess $process, Agency $agency): void
    {
        if ((int) $process->agency_id !== (int) $agency->id) {
            abort(404);
        }
    }

    protected function resolveAgency(Request $request): Agency
    {
        $user = $request->user();
        if ($user->isPlatformAdmin() && $request->filled('agency_id')) {
            return Agency::query()->findOrFail($request->integer('agency_id'));
        }

        return Agency::query()->findOrFail($user->current_agency_id);
    }
}
