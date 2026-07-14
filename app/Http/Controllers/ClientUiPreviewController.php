<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET-only sticky session preview controls for client UI v2 lane (V2-MC-0).
 */
class ClientUiPreviewController extends Controller
{
    public function activateV1(Request $request): RedirectResponse
    {
        $this->setSessionVersion($request, 'v1');

        return redirect()->to($this->redirectTarget($request));
    }

    public function activateV2(Request $request): RedirectResponse
    {
        $this->setSessionVersion($request, 'v2');

        return redirect()->to($this->redirectTarget($request));
    }

    public function reset(Request $request): RedirectResponse
    {
        $sessionKey = (string) config('client_ui.preview_session_key', 'client_ui_preview_version');
        $grantKey = (string) config('client_ui.preview_session_grant_key', 'client_ui_preview_granted');

        $request->session()->forget([$sessionKey, $grantKey]);

        return redirect()->to($this->redirectTarget($request, stripNamespace: true));
    }

    protected function setSessionVersion(Request $request, string $version): void
    {
        $allowed = config('client_ui.allowed_versions', ['v1', 'v2']);
        if (! is_array($allowed) || ! in_array($version, $allowed, true)) {
            return;
        }

        $sessionKey = (string) config('client_ui.preview_session_key', 'client_ui_preview_version');
        $request->session()->put($sessionKey, $version);
    }

    protected function redirectTarget(Request $request, bool $stripNamespace = false): string
    {
        $referer = $request->headers->get('referer');
        $target = is_string($referer) && $referer !== '' ? $referer : url('/');

        $parsed = parse_url($target);
        $path = $parsed['path'] ?? '/';

        if ($stripNamespace) {
            $path = ui_strip_preview_namespace_from_path($path);
        }

        $query = [];
        if (isset($parsed['query']) && is_string($parsed['query']) && $parsed['query'] !== '') {
            parse_str($parsed['query'], $query);
        }
        unset($query['preview_key'], $query['ui']);

        $queryString = http_build_query($query);

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }
}
