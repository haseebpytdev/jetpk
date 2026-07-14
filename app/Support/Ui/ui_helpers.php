<?php

use App\Support\Ui\UiLayerResolver;
use App\Support\Ui\UiVersionResolver;
use Illuminate\Contracts\View\View;

if (! function_exists('ui_resolver')) {
    function ui_resolver(): UiVersionResolver
    {
        return app(UiVersionResolver::class);
    }
}

if (! function_exists('ui_channel')) {
    function ui_channel(): ?string
    {
        return ui_resolver()->channel();
    }
}

if (! function_exists('ui_version')) {
    function ui_version(): string
    {
        $resolver = ui_resolver();
        $resolver->resolve();

        return $resolver->effectiveVersion();
    }
}

if (! function_exists('current_ui_version')) {
    function current_ui_version(): string
    {
        return ui_version();
    }
}

if (! function_exists('is_v2_ui')) {
    function is_v2_ui(): bool
    {
        return current_ui_version() === 'v2';
    }
}

if (! function_exists('is_v1_ui')) {
    function is_v1_ui(): bool
    {
        return current_ui_version() === 'v1';
    }
}

if (! function_exists('is_ui_preview_namespace')) {
    function is_ui_preview_namespace(): bool
    {
        $resolver = ui_resolver();
        $resolver->resolve();

        return $resolver->isPreviewNamespace();
    }
}

if (! function_exists('ui_preview_namespace')) {
    function ui_preview_namespace(): ?string
    {
        if (! is_ui_preview_namespace()) {
            return null;
        }

        return (string) config('client_ui.preview_namespace', 'v2');
    }
}

if (! function_exists('ui_view')) {
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $mergeData
     */
    function ui_view(string $path, array $data = [], array $mergeData = []): View
    {
        $resolver = ui_resolver();
        $resolver->resolve();

        return view($resolver->resolveViewName($path), $data, $mergeData);
    }
}

if (! function_exists('ui_asset')) {
    function ui_asset(string $path): string
    {
        $resolver = ui_resolver();
        $resolver->resolve();

        $resolved = $resolver->resolveAssetPath($path);
        $url = asset($resolved);

        if (is_file(public_path($resolved))) {
            return $url.'?v='.filemtime(public_path($resolved));
        }

        return $url;
    }
}

if (! function_exists('ui_asset_is_static_path')) {
    function ui_asset_is_static_path(string $path): bool
    {
        $normalized = '/'.ltrim($path, '/');
        $prefixes = [
            '/css/', '/js/', '/images/', '/storage/', '/assets/',
            '/build/', '/vendor/', '/client-assets/', '/themes/',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('ui_strip_preview_namespace_from_path')) {
    function ui_strip_preview_namespace_from_path(string $path): string
    {
        $namespace = strtolower((string) config('client_ui.preview_namespace', 'v2'));
        $trimmed = trim($path, '/');

        if ($trimmed === $namespace) {
            return '/';
        }

        if (str_starts_with($trimmed, $namespace.'/')) {
            $remainder = substr($trimmed, strlen($namespace) + 1);

            return $remainder === '' ? '/' : '/'.$remainder;
        }

        return $path === '' ? '/' : (str_starts_with($path, '/') ? $path : '/'.$path);
    }
}

if (! function_exists('ui_preserve_url')) {
    function ui_preserve_url(?string $path = null): string
    {
        if ($path === null) {
            $path = '/'.ltrim(request()->path(), '/');
            if ($path === '/'.'') {
                $path = '/';
            }
        }

        if (ui_asset_is_static_path($path)) {
            return $path;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        [$pathPart, $queryPart] = client_url_split_path_query($path);
        $normalized = '/'.ltrim($pathPart, '/');
        if ($normalized === '/') {
            $normalized = '';
        }

        if ((bool) config('client_ui.preserve_preview_namespace_links', true)
            && is_ui_preview_namespace()) {
            $namespace = (string) config('client_ui.preview_namespace', 'v2');
            $prefixed = '/'.$namespace.$normalized;

            return $queryPart !== '' ? $prefixed.'?'.$queryPart : $prefixed;
        }

        return $queryPart !== '' ? $normalized.'?'.$queryPart : ($normalized !== '' ? $normalized : '/');
    }
}

if (! function_exists('ui_layer_resolver')) {
    function ui_layer_resolver(): UiLayerResolver
    {
        return app(UiLayerResolver::class);
    }
}

if (! function_exists('ui_layer_contexts')) {
    /**
     * @return list<string>
     */
    function ui_layer_contexts(): array
    {
        return ui_layer_resolver()->contextsForRequest();
    }
}

if (! function_exists('ui_layer_asset')) {
    function ui_layer_asset(string $path): string
    {
        $normalized = ltrim($path, '/');
        $url = asset($normalized);
        $fullPath = public_path($normalized);

        if (is_file($fullPath)) {
            return $url.'?v='.filemtime($fullPath);
        }

        return $url;
    }
}

if (! function_exists('ui_preserve_route')) {
    /**
     * @param  array<string, mixed>  $parameters
     */
    function ui_preserve_route(string $routeName, array $parameters = [], bool $absolute = true): string
    {
        $url = route($routeName, $parameters, $absolute);

        if ($absolute) {
            $parsed = parse_url($url);
            $path = $parsed['path'] ?? '/';
            $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

            return ui_preserve_url($path.$query);
        }

        return ui_preserve_url($url);
    }
}
