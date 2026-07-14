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

class BackgroundRemovalSettingsController extends Controller
{
    public function __construct(
        private readonly BackgroundRemovalSettingsService $settingsService,
    ) {}

    public function edit(Request $request)
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);
        $setting = $this->settingsService->getForAgency($agency);

        return view(client_view('settings.background-removal', 'admin'), [
            'agency' => $agency,
            'setting' => $setting,
            'maskedApiKey' => $setting->maskedApiKey(),
        ]);
    }

    public function update(Request $request)
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:disabled,remove_bg'],
            'api_endpoint' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'max_source_bytes' => ['required', 'integer', 'min:102400', 'max:10485760'],
            'is_enabled' => ['nullable', 'boolean'],
            'default_for_logos' => ['nullable', 'boolean'],
        ]);

        $validated['is_enabled'] = $request->boolean('is_enabled');
        $validated['default_for_logos'] = $request->boolean('default_for_logos');

        $this->settingsService->update($agency, $request->user(), $validated);

        return back()->with('status', 'background-removal-updated');
    }

    public function test(Request $request): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);
        $setting = $this->settingsService->getForAgency($agency);
        $provider = $this->settingsService->resolveProvider($setting);
        $health = $provider->healthCheck();

        return response()->json([
            'ok' => $health->healthy,
            'message' => $health->message,
            'code' => $health->errorCode,
        ]);
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
