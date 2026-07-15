<?php

namespace App\Services\TravelData;

use App\Models\Airline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AirlineBrandingService
{
    protected AirlineLogoCacheService $logoCache;

    protected AirlineCanonicalResolver $canonical;

    public function __construct(?AirlineLogoCacheService $logoCache = null, ?AirlineCanonicalResolver $canonical = null)
    {
        $this->logoCache = $logoCache ?? app(AirlineLogoCacheService::class);
        $this->canonical = $canonical ?? app(AirlineCanonicalResolver::class);
    }

    protected function storagePublicUrl(string $path): string
    {
        $url = Storage::url($path);
        $pathOnly = parse_url($url, PHP_URL_PATH);

        if (is_string($pathOnly) && $pathOnly !== '') {
            return $pathOnly;
        }

        return str_starts_with($url, '/') ? $url : '/'.ltrim($url, '/');
    }

    /**
     * Resolved logo URL: uploaded file under storage/app/public first, then local cache, then generic icon.
     * Does not hotlink third-party CDNs in customer-facing HTML.
     */
    public function getLogoForCode(?string $code): ?string
    {
        $logoCode = $this->canonical->logoCode($code);
        if ($logoCode === null) {
            return $this->logoCache->genericFallbackPublicUrl();
        }

        $stored = $this->getStoredLogoUrl($logoCode);
        if ($stored !== null) {
            return $stored;
        }

        $canonicalIata = $this->canonical->resolveToCanonicalIata($code);
        if ($canonicalIata !== null && $canonicalIata !== $logoCode) {
            $storedCanonical = $this->getStoredLogoUrl($canonicalIata);
            if ($storedCanonical !== null) {
                return $storedCanonical;
            }
        }

        if (! config('ota.airline_logo_cache.enabled', true)) {
            return $this->logoCache->genericFallbackPublicUrl();
        }

        return $this->logoCache->resolvePublicUrl(
            $logoCode,
            (bool) config('ota.airline_logo_cache.download_on_miss', true),
        );
    }

    public function getDisplayNameForCode(?string $code): ?string
    {
        $name = $this->canonical->canonicalDisplayName($code);
        if ($name !== null && trim($name) !== '') {
            return $name;
        }

        $normalized = $this->canonical->resolveToCanonicalIata($code);
        if ($normalized === null) {
            return null;
        }

        return $this->findAirlineRecord($normalized)?->name;
    }

    /**
     * Uploaded logo only (no cache/CDN). Used when callers must avoid external URLs.
     */
    public function getStoredLogoUrl(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $logoCode = $this->canonical->logoCode($code) ?? Str::upper(trim($code));
        $canonicalIata = $this->canonical->resolveToCanonicalIata($code) ?? $logoCode;
        $airline = $this->findAirlineRecord($canonicalIata);

        if ($airline !== null && $airline->logo_path !== null && Storage::disk('public')->exists($airline->logo_path)) {
            return $this->storagePublicUrl($airline->logo_path);
        }

        $override = $this->canonical->overrideForIata($canonicalIata);
        $overridePath = trim((string) ($override['logo_path'] ?? ''));
        if ($overridePath !== '' && Storage::disk('public')->exists($overridePath)) {
            return $this->storagePublicUrl($overridePath);
        }

        return null;
    }

    protected function findAirlineRecord(string $normalized): ?Airline
    {
        return Airline::query()
            ->active()
            ->where(function ($q) use ($normalized): void {
                $q->whereRaw('UPPER(COALESCE(iata_code, "")) = ?', [$normalized])
                    ->orWhereRaw('UPPER(COALESCE(icao_code, "")) = ?', [$normalized]);
            })
            ->first();
    }

    /**
     * @deprecated Customer HTML must not hotlink CDNs; use getLogoForCode() instead.
     */
    public function cdnLogoUrlForCode(?string $code): ?string
    {
        if (! config('ota.airline_logo_cdn_enabled', true)) {
            return null;
        }

        $normalized = Str::upper(trim((string) $code));
        if (! preg_match('/^[A-Z0-9]{2}$/', $normalized)) {
            return null;
        }

        $template = (string) config(
            'ota.airline_logo_cdn_template',
            'https://images.kiwi.com/airlines/64x64/{CODE}.png'
        );

        return str_replace('{CODE}', $normalized, $template);
    }

    public function genericFallbackLogoUrl(): string
    {
        return $this->logoCache->genericFallbackPublicUrl();
    }

    /**
     * @param  array<int, array<string, mixed>>|Collection<int, array<string, mixed>>  $offers
     * @return array<string, string>
     */
    public function mapLogosForOffers(array|Collection $offers): array
    {
        $rows = $offers instanceof Collection ? $offers->all() : $offers;
        $codes = collect($rows)
            ->map(function (array $offer): ?string {
                $primary = trim((string) ($offer['airline_code'] ?? ''));
                if ($primary !== '') {
                    return $this->canonical->resolveToCanonicalIata($primary);
                }

                $fallback = trim((string) ($offer['carrier_code'] ?? ''));

                return $fallback !== '' ? $this->canonical->resolveToCanonicalIata($fallback) : null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return [];
        }

        $airlines = Airline::query()
            ->active()
            ->where(function ($q) use ($codes): void {
                $q->whereIn('iata_code', $codes->all())
                    ->orWhereIn('icao_code', $codes->all());
            })
            ->get(['iata_code', 'icao_code', 'logo_path']);

        $map = [];
        foreach ($airlines as $airline) {
            if ($airline->logo_path === null || ! Storage::disk('public')->exists($airline->logo_path)) {
                continue;
            }
            $url = $this->storagePublicUrl($airline->logo_path);
            if ($airline->iata_code !== null) {
                $map[Str::upper($airline->iata_code)] = $url;
            }
            if ($airline->icao_code !== null) {
                $map[Str::upper($airline->icao_code)] = $url;
            }
        }

        $downloadOnMiss = (bool) config('ota.airline_logo_cache.download_on_miss', true);

        foreach ($codes as $code) {
            $key = Str::upper((string) $code);
            if (isset($map[$key])) {
                continue;
            }

            $logoCode = $this->canonical->logoCode($key) ?? $key;
            $cached = $this->logoCache->resolvePublicUrl($logoCode, $downloadOnMiss);
            if ($cached !== null) {
                $map[$key] = $cached;
            }
        }

        return $map;
    }
}
