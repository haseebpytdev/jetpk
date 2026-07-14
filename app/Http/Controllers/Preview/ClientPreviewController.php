<?php

namespace App\Http\Controllers\Preview;

use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use App\Services\Client\ClientAssetResolver;
use App\Services\Client\ClientBrandingResolver;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\ClientThemeResolver;
use App\Services\Client\CurrentClientContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

/**
 * Safe placeholder pages for master-workspace client preview routing (MC-4, MC-5A, MC-6A).
 * Default deployment slug redirects to production URLs in ResolvePreviewClient (MC-5B).
 */
class ClientPreviewController extends Controller
{
    public function __construct(
        private readonly CurrentClientContext $clientContext,
        private readonly ClientProfileResolver $profileResolver,
        private readonly ClientAssetResolver $assetResolver,
        private readonly ClientBrandingResolver $brandingResolver,
        private readonly ClientThemeResolver $themeResolver,
    ) {}

    public function clientRoot(): RedirectResponse
    {
        $slug = $this->clientContext->slug();
        abort_unless(is_string($slug) && $slug !== '', 404);

        if (config('client_route_parity.enabled', true) && Route::has('client.parity.home.alias')) {
            return redirect()->to(route('client.parity.home.alias', ['clientSlug' => $slug], false));
        }

        return redirect()->route('client.preview.home', ['clientSlug' => $slug]);
    }

    public function home(): View
    {
        return $this->previewView('preview.client.home', 'Public home');
    }

    public function login(): View
    {
        return $this->previewView('preview.client.login', 'Login');
    }

    public function admin(): View
    {
        return $this->previewView('preview.client.admin', 'Admin');
    }

    public function staff(): View
    {
        return $this->previewView('preview.client.staff', 'Staff');
    }

    public function agent(): View
    {
        return $this->previewView('preview.client.agent', 'Agent');
    }

    private function previewView(string $view, string $portalLabel): View
    {
        $profile = $this->clientContext->get();
        abort_unless($profile instanceof ClientProfile, 404);

        return view($view, [
            'portalLabel' => $portalLabel,
            'profile' => $profile,
            'context' => $this->clientContext,
            'assetResolver' => $this->assetResolver,
            'brandingResolver' => $this->brandingResolver,
            'themeResolver' => $this->themeResolver,
            'runtimeConfig' => $this->profileResolver->toRuntimeConfig($profile),
        ]);
    }
}
