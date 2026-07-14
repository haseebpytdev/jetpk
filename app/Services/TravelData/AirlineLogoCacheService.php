<?php

namespace App\Services\TravelData;

use App\Models\Airline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local public-disk cache for airline logos (IATA/ICAO-safe filenames only).
 * Downloads from configured fallback template once; serves /storage/airline-logos/{CODE}.png.
 */
class AirlineLogoCacheService
{
    public function cacheDirectory(): string
    {
        return trim((string) config('ota.airline_logo_cache.directory', 'airline-logos'), '/');
    }

    public function genericFallbackPublicUrl(): string
    {
        $path = trim((string) config('ota.airline_logo_cache.generic_fallback', 'images/airline-generic.svg'), '/');

        return '/'.ltrim($path, '/');
    }

    /**
     * @return non-empty-string|null
     */
    public function normalizeSafeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = Str::upper(trim($code));
        if ($normalized === '' || ! preg_match('/^[A-Z0-9]{2,3}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    public function cacheRelativePath(string $code): ?string
    {
        $safe = $this->normalizeSafeCode($code);
        if ($safe === null) {
            return null;
        }

        return $this->cacheDirectory().'/'.$safe.'.png';
    }

    public function hasCachedLogo(?string $code): bool
    {
        $relative = $this->cacheRelativePath((string) $code);
        if ($relative === null) {
            return false;
        }

        return Storage::disk('public')->exists($relative);
    }

    /**
     * Public URL for a cached logo file, or null when not on disk.
     */
    public function publicUrlForCachedLogo(?string $code): ?string
    {
        $relative = $this->cacheRelativePath((string) $code);
        if ($relative === null || ! Storage::disk('public')->exists($relative)) {
            return null;
        }

        $url = Storage::disk('public')->url($relative);
        $pathOnly = parse_url($url, PHP_URL_PATH);

        if (is_string($pathOnly) && $pathOnly !== '') {
            return $pathOnly;
        }

        return str_starts_with($url, '/') ? $url : '/'.ltrim($url, '/');
    }

    /**
     * Resolve logo for display: cached local URL, optionally fetch once, else generic fallback.
     */
    public function resolvePublicUrl(?string $code, bool $attemptDownload = true): ?string
    {
        $cached = $this->publicUrlForCachedLogo($code);
        if ($cached !== null) {
            return $cached;
        }

        if ($attemptDownload && $this->normalizeSafeCode($code) !== null) {
            $this->cacheLogoFromFallback((string) $code);
            $cached = $this->publicUrlForCachedLogo($code);
            if ($cached !== null) {
                return $cached;
            }
        }

        return $this->genericFallbackPublicUrl();
    }

    /**
     * Download logo from trusted fallback template into public storage.
     */
    public function cacheLogoFromFallback(string $code): bool
    {
        $safe = $this->normalizeSafeCode($code);
        if ($safe === null) {
            Log::info('airline_logo_cache_skip_invalid_code', ['code' => $code]);

            return false;
        }

        $relative = $this->cacheRelativePath($safe);
        if ($relative === null) {
            return false;
        }

        if (Storage::disk('public')->exists($relative)) {
            return true;
        }

        $downloadUrl = $this->fallbackDownloadUrlForCode($safe);
        if ($downloadUrl === null) {
            Log::info('airline_logo_cache_no_fallback_url', ['code' => $safe]);

            return false;
        }

        try {
            $response = Http::timeout((int) config('ota.airline_logo_cache.download_timeout_seconds', 8))
                ->withHeaders(['User-Agent' => 'OTA-AirlineLogoCache/1.0'])
                ->get($downloadUrl);

            if (! $response->successful()) {
                Log::info('airline_logo_cache_download_failed', [
                    'code' => $safe,
                    'status' => $response->status(),
                ]);

                return false;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) < 32) {
                Log::info('airline_logo_cache_download_empty', ['code' => $safe]);

                return false;
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            if ($contentType !== '' && ! str_contains($contentType, 'image')) {
                Log::info('airline_logo_cache_download_not_image', [
                    'code' => $safe,
                    'content_type' => $contentType,
                ]);

                return false;
            }

            Storage::disk('public')->makeDirectory($this->cacheDirectory());
            Storage::disk('public')->put($relative, $body);

            Log::info('airline_logo_cache_stored', ['code' => $safe, 'path' => $relative]);

            return true;
        } catch (\Throwable $e) {
            Log::info('airline_logo_cache_download_error', [
                'code' => $safe,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{attempted: int, stored: int, skipped: int, failed: int}
     */
    public function cacheAllUsedFromDatabase(): array
    {
        $stats = ['attempted' => 0, 'stored' => 0, 'skipped' => 0, 'failed' => 0];

        $codes = Airline::query()
            ->active()
            ->whereNotNull('iata_code')
            ->pluck('iata_code')
            ->map(fn (?string $c) => $this->normalizeSafeCode($c))
            ->filter()
            ->unique()
            ->values();

        foreach ($codes as $code) {
            $stats['attempted']++;
            if ($this->hasCachedLogo($code)) {
                $stats['skipped']++;

                continue;
            }
            if ($this->cacheLogoFromFallback($code)) {
                $stats['stored']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    protected function fallbackDownloadUrlForCode(string $safeCode): ?string
    {
        if (! config('ota.airline_logo_cache.download_enabled', true)) {
            return null;
        }

        $template = (string) config(
            'ota.airline_logo_cache.download_template',
            config('ota.airline_logo_cdn_template', 'https://images.kiwi.com/airlines/64x64/{CODE}.png')
        );

        if ($template === '' || ! str_contains($template, '{CODE}')) {
            return null;
        }

        return str_replace('{CODE}', $safeCode, $template);
    }
}
