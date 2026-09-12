<?php

namespace App\Services\Next;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Secured on-demand Next.js ISR revalidation for homepage CMS and public config tags.
 */
class JetpkNextCacheRevalidationService
{
    /**
     * @return array{ok: bool, status?: int, body?: mixed, skipped?: string}
     */
    public function revalidateHomepageAndPublicConfig(): array
    {
        $secret = trim((string) config('jetpk_next.revalidate_secret', ''));
        if ($secret === '') {
            return ['ok' => false, 'skipped' => 'missing_secret'];
        }

        $baseUrl = rtrim((string) config('jetpk_next.public_base_url', ''), '/');
        $path = (string) config('jetpk_next.revalidate_homepage_path', '/api/internal/revalidate/homepage');
        if ($baseUrl === '') {
            return ['ok' => false, 'skipped' => 'missing_base_url'];
        }

        $timeout = max(2, (int) config('jetpk_next.revalidate_timeout_seconds', 8));

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['X-Jetpk-Revalidate-Secret' => $secret])
                ->post($baseUrl.$path);

            if (! $response->successful()) {
                Log::warning('jetpk.next_revalidate_failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ];
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'body' => $response->json(),
            ];
        } catch (\Throwable $exception) {
            Log::warning('jetpk.next_revalidate_exception', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'skipped' => 'exception',
                'body' => $exception->getMessage(),
            ];
        }
    }
}
