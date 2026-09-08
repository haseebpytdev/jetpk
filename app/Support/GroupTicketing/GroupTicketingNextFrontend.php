<?php

namespace App\Support\GroupTicketing;

use App\Models\GroupInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serves the current Next Group Ticketing UI when OLS still lands HTML on Laravel.
 */
final class GroupTicketingNextFrontend
{
    public const HEADER = 'X-JP-Groups-Frontend';

    public static function wantsJson(Request $request): bool
    {
        return $request->wantsJson() || $request->query('format') === 'json';
    }

    public static function detailPath(GroupInventory|string|int $inventory): string
    {
        if ($inventory instanceof GroupInventory) {
            $id = trim((string) ($inventory->public_id ?: $inventory->id));
        } else {
            $id = trim((string) $inventory);
        }

        return '/groups/'.$id;
    }

    public static function redirectToDetail(GroupInventory $inventory, Request $request): RedirectResponse
    {
        $target = self::detailPath($inventory);
        $query = $request->query();
        unset($query['format']);

        if ($query !== []) {
            $target .= '?'.http_build_query($query);
        }

        return redirect()->to($target);
    }

    public static function proxy(Request $request, string $nextPath): Response
    {
        try {
            $query = $request->getQueryString();
            $url = 'http://127.0.0.1:3010'.$nextPath.($query ? '?'.$query : '');
            $headers = [
                'X-Forwarded-Host' => $request->getHost(),
                'X-Forwarded-Proto' => $request->isSecure() ? 'https' : 'http',
                'User-Agent' => (string) $request->userAgent(),
            ];

            $cookieHeader = collect($request->cookies->all())
                ->map(static fn ($value, $name): string => $name.'='.$value)
                ->implode('; ');
            if ($cookieHeader !== '') {
                $headers['Cookie'] = $cookieHeader;
            }

            foreach ([
                'RSC',
                'Next-Router-State-Tree',
                'Next-Router-Prefetch',
                'Next-Router-Segment-Prefetch',
                'Accept',
            ] as $headerName) {
                $value = $request->headers->get($headerName);
                if (is_string($value) && $value !== '') {
                    $headers[$headerName] = $value;
                }
            }
            if (! isset($headers['Accept'])) {
                $headers['Accept'] = 'text/html,application/xhtml+xml';
            }

            $response = Http::timeout(8)
                ->withHeaders($headers)
                ->get($url);

            if ($response->successful() && is_string($response->body()) && $response->body() !== '') {
                $contentType = $response->header('Content-Type') ?: 'text/html; charset=utf-8';

                return response($response->body(), 200)
                    ->header('Content-Type', $contentType)
                    ->header(self::HEADER, 'next-proxy');
            }
        } catch (\Throwable $exception) {
            Log::warning('groups_next_frontend_proxy_failed', [
                'path' => $nextPath,
                'message' => $exception->getMessage(),
            ]);
        }

        return self::fallback();
    }

    public static function fallback(): Response
    {
        return response(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>JetPakistan Groups</title></head>'
            .'<body style="font-family:Inter,system-ui,sans-serif;padding:2rem">'
            .'<p>JetPakistan Groups</p>'
            .'<p>The current group ticketing experience is temporarily unavailable.</p>'
            .'</body></html>',
            200,
            [
                'Content-Type' => 'text/html; charset=utf-8',
                self::HEADER => 'fallback',
            ],
        );
    }
}
