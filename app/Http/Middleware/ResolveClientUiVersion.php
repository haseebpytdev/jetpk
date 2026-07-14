<?php

namespace App\Http\Middleware;

use App\Support\Ui\UiVersionResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds client UI version context (v1 default, protected v2 preview lane) for all views.
 */
class ResolveClientUiVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->persistQueryPreviewToSession($request);

        $resolver = app(UiVersionResolver::class);
        $resolver->resolve();

        $version = $resolver->effectiveVersion();
        $isNamespace = $resolver->isPreviewNamespace();

        View::share([
            'uiChannel' => $resolver->channel(),
            'uiVersion' => $version,
            'uiPreviewActive' => $resolver->isPreviewActive(),
            'currentUiVersion' => $version,
            'isV2Ui' => $version === 'v2',
            'isV1Ui' => $version === 'v1',
            'isUiPreviewNamespace' => $isNamespace,
            'uiPreviewNamespace' => $isNamespace
                ? (string) config('client_ui.preview_namespace', 'v2')
                : null,
        ]);

        return $next($request);
    }

    protected function persistQueryPreviewToSession(Request $request): void
    {
        if (! (bool) config('client_ui.preview_enabled', true)) {
            return;
        }

        $param = (string) config('client_ui.preview_query_key', 'ui');
        if (! $request->query->has($param)) {
            return;
        }

        $value = $request->query($param);
        if (! is_string($value) || $value === '') {
            return;
        }

        $normalized = strtolower(trim($value));
        $allowed = config('client_ui.allowed_versions', ['v1', 'v2']);
        if (! is_array($allowed) || ! in_array($normalized, $allowed, true)) {
            return;
        }

        $sessionKey = (string) config('client_ui.preview_session_key', 'client_ui_preview_version');
        $request->session()->put($sessionKey, $normalized);
    }
}
