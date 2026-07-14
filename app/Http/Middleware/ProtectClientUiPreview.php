<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates protected /v2 preview namespace and /ui/v2 activation (V2-MC-0).
 */
class ProtectClientUiPreview
{
    public const REQUEST_ATTR_ORIGINAL_PATH = 'client_ui_original_path';

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('client_ui.preview_protection_enabled', true)) {
            return $next($request);
        }

        if (! $this->requiresProtection($request)) {
            return $next($request);
        }

        if ($this->isGranted($request)) {
            return $next($request);
        }

        abort(404);
    }

    protected function requiresProtection(Request $request): bool
    {
        if (! (bool) config('client_ui.preview_namespace_enabled', true)) {
            return false;
        }

        $namespace = (string) config('client_ui.preview_namespace', 'v2');
        $first = $this->firstPathSegment($request);

        if ($first === strtolower($namespace)) {
            return true;
        }

        if ($request->is('ui/v2') || $request->is('ui/v2/*')) {
            return true;
        }

        return false;
    }

    protected function isGranted(Request $request): bool
    {
        $grantKey = (string) config('client_ui.preview_session_grant_key', 'client_ui_preview_granted');

        $configuredKey = config('client_ui.preview_key');
        $providedKey = $request->query('preview_key');

        if (is_string($configuredKey) && $configuredKey !== ''
            && is_string($providedKey) && hash_equals($configuredKey, $providedKey)) {
            if ($request->hasSession()) {
                $request->session()->put($grantKey, true);
            }

            return true;
        }

        if (! $request->hasSession()) {
            return false;
        }

        if ($request->session()->get($grantKey) === true) {
            return true;
        }

        $user = $request->user() ?? Auth::guard('web')->user();
        if ($user !== null && $user->account_type === AccountType::PlatformAdmin) {
            $request->session()->put($grantKey, true);

            return true;
        }

        if ($request->session()->has('dev_cp_user_id')) {
            $request->session()->put($grantKey, true);

            return true;
        }

        return false;
    }

    protected function firstPathSegment(Request $request): string
    {
        $path = $request->attributes->get(self::REQUEST_ATTR_ORIGINAL_PATH);
        if (! is_string($path) || $path === '') {
            $path = $request->getPathInfo();
        }

        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return '';
        }

        $parts = explode('/', $trimmed);

        return strtolower($parts[0] ?? '');
    }
}
