<?php

namespace App\Http\Middleware;

use App\Support\Ui\UiVersionResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds UI version context for the current request and shares it with all views.
 */
class ResolveUiVersionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $resolver = app(UiVersionResolver::class);
        $resolver->resolve();

        View::share([
            'uiChannel' => $resolver->channel(),
            'uiVersion' => $resolver->effectiveVersion(),
            'uiPreviewActive' => $resolver->isPreviewActive(),
        ]);

        return $next($request);
    }
}
