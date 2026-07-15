<?php

namespace App\Support\Ui;

use App\Support\Client\ReservedClientPreviewSlugs;
use Illuminate\Support\Facades\Route;

/**
 * Read-only status snapshot for V2-MC-0 client UI preview lane.
 */
class ClientUiVersionStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $themeAssets = config('client_ui.theme_assets', []);
        $cssMap = is_array($themeAssets['css'] ?? null) ? $themeAssets['css'] : [];
        $jsMap = is_array($themeAssets['js'] ?? null) ? $themeAssets['js'] : [];

        $missingV2 = [];
        foreach (array_merge($cssMap, $jsMap) as $v1 => $v2) {
            if (! is_file(public_path($v2))) {
                $missingV2[] = $v2;
            }
        }

        $previewRoutes = collect(Route::getRoutes())->filter(function ($route): bool {
            $uri = $route->uri();

            return str_starts_with($uri, 'ui/')
                || $uri === 'v2'
                || str_starts_with($uri, 'v2/');
        })->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())->values()->all();

        $mutationV2Routes = collect(Route::getRoutes())->filter(function ($route): bool {
            $uri = $route->uri();
            if ($uri !== 'v2' && ! str_starts_with($uri, 'v2/')) {
                return false;
            }

            return ! in_array('GET', $route->methods(), true) && ! in_array('HEAD', $route->methods(), true);
        })->count();

        return [
            'versioning_enabled' => (bool) config('client_ui.enabled', true),
            'default_version' => (string) config('client_ui.default_version', 'v1'),
            'force_v1_default' => (bool) config('client_ui.force_v1_default_until_verified', true),
            'preview_enabled' => (bool) config('client_ui.preview_enabled', true),
            'allowed_versions' => config('client_ui.allowed_versions', ['v1', 'v2']),
            'namespace_enabled' => (bool) config('client_ui.preview_namespace_enabled', true),
            'namespace' => (string) config('client_ui.preview_namespace', 'v2'),
            'protection_enabled' => (bool) config('client_ui.preview_protection_enabled', true),
            'preview_key_configured' => is_string(config('client_ui.preview_key')) && config('client_ui.preview_key') !== '',
            'session_sticky_enabled' => (bool) config('client_ui.preview_enabled', true),
            'reserved_v2' => ReservedClientPreviewSlugs::isReserved('v2'),
            'reserved_ui' => ReservedClientPreviewSlugs::isReserved('ui'),
            'middleware_namespace_dispatch' => true,
            'mutation_v2_routes' => $mutationV2Routes,
            'v1_css' => array_keys($cssMap),
            'v1_js' => array_keys($jsMap),
            'v2_clones' => array_values(array_merge($cssMap, $jsMap)),
            'missing_v2_clones' => $missingV2,
            'layouts_updated' => 5,
            'preview_routes' => $previewRoutes,
            'helpers_present' => function_exists('ui_preserve_url') && function_exists('ui_preserve_route'),
            'warnings' => $this->warnings(),
        ];
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];

        if ((string) config('client_ui.default_version', 'v1') !== 'v1') {
            $warnings[] = 'default_version is not v1';
        }

        if (! (bool) config('client_ui.force_v1_default_until_verified', true)) {
            $warnings[] = 'force_v1_default_until_verified is disabled';
        }

        if ((bool) config('client_ui.preview_protection_enabled', true)
            && (! is_string(config('client_ui.preview_key')) || config('client_ui.preview_key') === '')) {
            $warnings[] = 'preview protection enabled but CLIENT_UI_PREVIEW_KEY is empty (admin/dev-cp/session grant only)';
        }

        return $warnings;
    }
}
