<?php

use App\Http\Middleware\PersistClientPreviewContext;
use App\Models\ClientProfile;
use App\Services\Client\ClientAssetResolver;
use App\Services\Client\ClientBrandingResolver;
use App\Services\Client\ClientThemeResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

if (! function_exists('client_branding')) {
    function client_branding(): ClientBrandingResolver
    {
        return app(ClientBrandingResolver::class);
    }
}

if (! function_exists('jetpk_company_branding')) {
    function jetpk_company_branding(): \App\Support\Branding\JetpkCompanyBrandingResolver
    {
        return app(\App\Support\Branding\JetpkCompanyBrandingResolver::class);
    }
}

if (! function_exists('uses_jetpk_company_branding')) {
    function uses_jetpk_company_branding(): bool
    {
        return jetpk_company_branding()->isJetpkDeployment();
    }
}

if (! function_exists('client_theme')) {
    function client_theme(): ClientThemeResolver
    {
        return app(ClientThemeResolver::class);
    }
}

if (! function_exists('client_assets')) {
    function client_assets(): ClientAssetResolver
    {
        return app(ClientAssetResolver::class);
    }
}

if (! function_exists('client_page_content')) {
    /**
     * @param  mixed  $default
     */
    function client_page_content(string $pageKey, string $sectionKey, mixed $default = '', bool $allowEmpty = true): mixed
    {
        return app(\App\Services\Client\ClientPageContentResolver::class)
            ->section($pageKey, $sectionKey, $default, $allowEmpty);
    }
}

if (! function_exists('client_page_field')) {
    /**
     * Resolve a page field using canonical defaults when the key is absent.
     *
     * @param  mixed  $defaultWhenAbsent
     */
    function client_page_field(string $pageKey, string $sectionKey, mixed $defaultWhenAbsent = ''): mixed
    {
        return client_page_content($pageKey, $sectionKey, $defaultWhenAbsent, true);
    }
}

if (! function_exists('client_page_asset')) {
    function client_page_asset(string $pageKey, string $assetKey, ?string $default = null): ?string
    {
        return app(\App\Services\Client\ClientPageContentResolver::class)
            ->assetUrl($pageKey, $assetKey, $default);
    }
}

if (! function_exists('client_view')) {
    function client_view(string $name, string $area = 'frontend'): string
    {
        return app(RuntimeViewResolver::class)->view($name, $area);
    }
}

if (! function_exists('client_view_exists')) {
    function client_view_exists(string $name, string $area = 'frontend'): bool
    {
        return app(RuntimeViewResolver::class)->exists($name, $area);
    }
}

if (! function_exists('client_layout')) {
    function client_layout(string $name = 'app', string $area = 'frontend'): string
    {
        if (is_v2_ui() && $area === 'frontend') {
            if ($name === 'auth' && View::exists('ui.site.v2.layouts.auth')) {
                return 'ui.site.v2.layouts.auth';
            }

            if (in_array($name, ['app', 'frontend'], true) && View::exists('ui.site.v2.layouts.frontend')) {
                return 'ui.site.v2.layouts.frontend';
            }
        }

        return app(RuntimeViewResolver::class)->layout($name, $area);
    }
}

if (! function_exists('client_layout_exists')) {
    function client_layout_exists(string $name = 'app', string $area = 'frontend'): bool
    {
        return app(RuntimeViewResolver::class)->layoutExists($name, $area);
    }
}

if (! function_exists('is_client_preview')) {
    function is_client_preview(): bool
    {
        return app(CurrentClientContext::class)->isPreview();
    }
}

if (! function_exists('ota_single_client_root_slug')) {
    function ota_single_client_root_slug(): ?string
    {
        if (! filter_var(config('ota_client.single_client_mode', false), FILTER_VALIDATE_BOOL)) {
            return null;
        }

        if (! filter_var(config('ota_client.single_client_root', false), FILTER_VALIDATE_BOOL)) {
            return null;
        }

        $slug = trim((string) config('ota_client.slug', ''));

        return $slug !== '' ? $slug : null;
    }
}

if (! function_exists('current_client_slug')) {
    function current_client_slug(): ?string
    {
        $rootSlug = ota_single_client_root_slug();
        if ($rootSlug !== null) {
            return $rootSlug;
        }

        $request = request();

        if ($request instanceof Request) {
            $routeSlug = $request->route('clientSlug');
            if (is_string($routeSlug) && trim($routeSlug) !== '') {
                return trim($routeSlug);
            }

            $attrSlug = $request->attributes->get(PersistClientPreviewContext::REQUEST_ATTR_SLUG);
            if (is_string($attrSlug) && trim($attrSlug) !== '') {
                return trim($attrSlug);
            }
        }

        if (is_client_preview()) {
            return app(CurrentClientContext::class)->slug();
        }

        return null;
    }
}

if (! function_exists('current_client_profile')) {
    function current_client_profile(): ?ClientProfile
    {
        return app(CurrentClientContext::class)->get();
    }
}

if (! function_exists('client_parity_route_name')) {
    function client_parity_route_name(string $routeName): ?string
    {
        $candidates = [
            'client.parity.'.$routeName,
            'client.parity.'.$routeName.'.alias',
            'client.preview.'.$routeName,
        ];

        foreach ($candidates as $candidate) {
            if (Route::has($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (! function_exists('client_route')) {
    /**
     * @param  array<string, mixed>|object|int|string  $parameters
     */
    function client_route(string $routeName, array|object|int|string $parameters = [], ?string $clientSlug = null): string
    {
        if (! is_array($parameters)) {
            $route = Route::getRoutes()->getByName($routeName);
            $paramNames = $route !== null ? $route->parameterNames() : [];
            if (count($paramNames) === 1) {
                $parameters = [$paramNames[0] => $parameters];
            } else {
                $parameters = [];
            }
        }

        $slug = $clientSlug ?? current_client_slug();

        if ($slug !== null && config('client_route_parity.enabled', true)) {
            $parityName = client_parity_route_name($routeName);
            if ($parityName !== null) {
                $parameters['clientSlug'] = $slug;

                return ui_preserve_url(route($parityName, $parameters, false));
            }
        }

        return ui_preserve_url(route($routeName, $parameters, false));
    }
}

if (! function_exists('client_home_flight_search_url')) {
    function client_home_flight_search_url(?string $clientSlug = null): string
    {
        $slug = $clientSlug ?? current_client_slug();
        $anchor = $slug === 'jetpk' ? '#jp-flight-search' : '#ota-flight-search';

        return client_route('home', [], $slug).$anchor;
    }
}

if (! function_exists('client_url')) {
    function client_url(string $path = '', ?string $clientSlug = null): string
    {
        $slug = $clientSlug ?? current_client_slug();

        [$pathPart, $queryPart] = client_url_split_path_query($path);
        $normalized = '/'.ltrim($pathPart, '/');

        if ($normalized === '/') {
            $normalized = '';
        }

        if ($slug !== null && ota_single_client_root_slug() === null) {
            $prefixed = '/'.$slug.$normalized;

            return ui_preserve_url($queryPart !== '' ? $prefixed.'?'.$queryPart : $prefixed);
        }

        $base = url($normalized !== '' ? $normalized : '/');

        if (is_ui_preview_namespace()) {
            $parsed = parse_url($base);
            $path = ($parsed['path'] ?? '/').(isset($parsed['query']) && $parsed['query'] !== '' ? '?'.$parsed['query'] : '');

            return url(ui_preserve_url($path));
        }

        return $queryPart !== '' ? $base.(str_contains($base, '?') ? '&' : '?').$queryPart : $base;
    }
}

if (! function_exists('client_url_split_path_query')) {
    /**
     * @return array{0: string, 1: string}
     */
    function client_url_split_path_query(string $path): array
    {
        $questionPos = strpos($path, '?');

        if ($questionPos === false) {
            return [$path, ''];
        }

        return [
            substr($path, 0, $questionPos),
            substr($path, $questionPos + 1),
        ];
    }
}

if (! function_exists('client_relative_path')) {
    function client_relative_path(?Request $request = null): string
    {
        $request ??= request();

        if (! $request instanceof Request) {
            return '';
        }

        $path = trim($request->path(), '/');
        $slug = current_client_slug();

        if ($slug !== null && $slug !== '' && (str_starts_with($path, $slug.'/') || $path === $slug)) {
            $path = ltrim(substr($path, strlen($slug)), '/');
        }

        return $path;
    }
}

if (! function_exists('request_client_slug_for_errors')) {
    function request_client_slug_for_errors(?Request $request = null): ?string
    {
        $rootSlug = ota_single_client_root_slug();
        if ($rootSlug !== null) {
            return $rootSlug;
        }

        $request ??= request();

        if (! $request instanceof Request) {
            return null;
        }

        $slug = current_client_slug();
        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        if ($request->hasSession()) {
            $sessionSlug = $request->session()->get(PersistClientPreviewContext::SESSION_KEY);
            if (is_string($sessionSlug) && trim($sessionSlug) !== '') {
                return trim($sessionSlug);
            }
        }

        $path = trim($request->path(), '/');
        if ($path === 'jetpk' || str_starts_with($path, 'jetpk/')) {
            return 'jetpk';
        }

        return null;
    }
}

if (! function_exists('client_error_view')) {
    function client_error_view(string $code): string
    {
        $slug = request_client_slug_for_errors();
        if ($slug === 'jetpk') {
            $themeView = 'themes.frontend.jetpakistan.errors.'.$code;
            $shellView = 'themes.frontend.jetpakistan.errors.partials.shell';
            if (View::exists($themeView) && View::exists($shellView)) {
                return $themeView;
            }
        }

        return 'errors.'.$code;
    }
}

if (! function_exists('client_error_response')) {
    /**
     * @param  array<string, mixed>  $data
     */
    function client_error_response(string $code, array $data = [], ?int $status = null): \Illuminate\Http\Response
    {
        return app(\App\Support\Client\ClientErrorResponseResolver::class)->response($code, $data, $status);
    }
}

if (! function_exists('client_route_slug')) {
    /**
     * @deprecated Use current_client_slug() instead.
     */
    function client_route_slug(): ?string
    {
        return current_client_slug();
    }
}

if (! function_exists('client_safe_route')) {
    /**
     * Client-aware route URL that cannot silently fall back to Master/root public paths.
     *
     * @param  array<string, mixed>  $parameters
     */
    function client_safe_route(string $routeName, array $parameters = [], ?string $clientSlug = null): string
    {
        return app(\App\Support\Client\ClientNoFallbackGuard::class)
            ->safeRoute($routeName, $parameters, $clientSlug);
    }
}

if (! function_exists('client_safe_url')) {
    /**
     * Client-aware path URL that cannot silently fall back to Master/root public paths.
     *
     * @param  array<string, mixed>|string|null  $query
     */
    function client_safe_url(string $path, mixed $query = [], ?string $clientSlug = null): string
    {
        return app(\App\Support\Client\ClientNoFallbackGuard::class)
            ->safeUrl($path, $query, $clientSlug);
    }
}
