<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Services\Client\ClientRedirectResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the Next.js back-office dashboard for Admin and Staff portals.
 *
 * Prefers static HTML export from storage when present; otherwise proxies to the
 * local Next.js server (next start) after Laravel auth middleware passes.
 */
class BackOfficeDashboardController extends Controller
{
    public function admin(Request $request, ?string $path = null): Response|BinaryFileResponse|RedirectResponse
    {
        return $this->servePortal('admin', $path);
    }

    public function staff(Request $request, ?string $path = null): Response|BinaryFileResponse|RedirectResponse
    {
        return $this->servePortal('staff', $path);
    }

    public function testdashRedirect(Request $request, ClientRedirectResolver $resolver): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->guest(client_route('login'));
        }

        return redirect()->to($resolver->dashboardPathForUser($user));
    }

    private function servePortal(string $portal, ?string $path): Response|BinaryFileResponse|RedirectResponse
    {
        $normalized = trim($path ?? '', '/');
        $staticBase = storage_path("app/back-office-dashboard/{$portal}/dashboard");

        $static = $this->resolveStaticHtml($staticBase, $normalized);
        if ($static !== null) {
            return response()->file($static, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        if (config('dashboard.next_proxy_enabled', true)) {
            $proxied = $this->proxyToNextServer($portal, $normalized);
            if ($proxied !== null) {
                return $proxied;
            }
        }

        abort(503, 'The back-office dashboard is not deployed yet. Contact your platform administrator.');
    }

    private function resolveStaticHtml(string $basePath, string $normalized): ?string
    {
        $candidates = $normalized === ''
            ? ["{$basePath}/index.html"]
            : [
                "{$basePath}/{$normalized}/index.html",
                "{$basePath}/{$normalized}.html",
            ];

        foreach ($candidates as $file) {
            if (File::isFile($file)) {
                return $file;
            }
        }

        $fallback = "{$basePath}/index.html";

        return File::isFile($fallback) ? $fallback : null;
    }

    private function proxyToNextServer(string $portal, string $normalized): ?Response
    {
        $base = rtrim((string) config('dashboard.next_server_url'), '/');
        if ($base === '') {
            return null;
        }

        $target = $normalized === ''
            ? "{$base}/{$portal}/dashboard"
            : "{$base}/{$portal}/dashboard/{$normalized}";

        try {
            $forwardHeaders = [
                'Accept' => 'text/html,application/xhtml+xml',
                'X-Dashboard-Portal' => $portal,
            ];

            $cookie = request()->headers->get('Cookie');
            if (is_string($cookie) && $cookie !== '') {
                $forwardHeaders['Cookie'] = $cookie;
            }

            $upstream = Http::timeout(30)
                ->withHeaders($forwardHeaders)
                ->get($target, request()->query());

            if (! $upstream->successful()) {
                return null;
            }

            return response($upstream->body(), $upstream->status())
                ->withHeaders([
                    'Content-Type' => $upstream->header('Content-Type') ?? 'text/html; charset=UTF-8',
                    'Cache-Control' => 'no-store, private',
                ]);
        } catch (ConnectionException) {
            return null;
        }
    }
}
