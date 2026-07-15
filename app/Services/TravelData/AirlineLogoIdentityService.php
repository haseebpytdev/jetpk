<?php

namespace App\Services\TravelData;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Prove canonical airline logo identity for required JetPK carrier codes.
 */
final class AirlineLogoIdentityService
{
    public function __construct(
        private readonly AirlineCanonicalResolver $canonical,
        private readonly AirlineBrandingService $branding,
    ) {}

    /**
     * @param  list<string>  $codes
     * @return array{pass: bool, fail_count: int, carriers: list<array<string, mixed>>}
     */
    public function audit(array $codes, ?string $publicRoot = null): array
    {
        $carriers = [];
        $failCount = 0;
        $staging = $publicRoot !== null;

        foreach ($codes as $code) {
            $iata = Str::upper(trim($code));
            $override = $this->canonical->overrideForIata($iata);
            $canonicalName = (string) ($override['name'] ?? '');
            $icao = (string) ($override['icao'] ?? '');
            $logoCode = $this->canonical->logoCode($iata) ?? $iata;

            $source = $this->resolveSourceAsset($logoCode, $publicRoot);
            if ($staging) {
                $publicUrl = $source !== null ? '/storage/'.($source['relative_path'] ?? '') : null;
                $fallback = false;
                $assetPass = $source !== null;
            } else {
                $publicUrl = $this->branding->getLogoForCode($iata);
                $fallback = str_contains((string) $publicUrl, 'airline-generic');
                $assetPass = $source !== null && ! $fallback;
            }

            $namePass = $canonicalName !== '' && $this->canonical->canonicalDisplayName($iata) === $canonicalName;
            if (! $namePass || ! $assetPass) {
                $failCount++;
            }

            $carriers[] = [
                'canonical_name' => $canonicalName,
                'iata' => $iata,
                'icao' => $icao !== '' ? $icao : null,
                'logo_code' => $logoCode,
                'source_asset_identity' => $source['identity'] ?? null,
                'source_relative_path' => $source['relative_path'] ?? null,
                'destination_relative_path' => $source['relative_path'] ?? null,
                'sha256' => $source['sha256'] ?? null,
                'mime' => $source['mime'] ?? null,
                'width' => $source['width'] ?? null,
                'height' => $source['height'] ?? null,
                'public_url' => $publicUrl,
                'fallback' => $fallback,
                'pass' => $namePass && $assetPass,
            ];
        }

        return [
            'pass' => $failCount === 0,
            'fail_count' => $failCount,
            'carriers' => $carriers,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSourceAsset(string $logoCode, ?string $publicRoot = null): ?array
    {
        $candidates = [
            'airline-logos/'.$logoCode.'.png',
            'airline-logos/'.$logoCode.'.webp',
            'travel-assets/airlines/logos/'.$logoCode.'.png',
            'travel-assets/airlines/logos/'.$logoCode.'.webp',
        ];

        foreach ($candidates as $relative) {
            if ($publicRoot !== null) {
                $absolute = rtrim($publicRoot, DIRECTORY_SEPARATOR)
                    .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (! is_file($absolute)) {
                    continue;
                }
            } elseif (! Storage::disk('public')->exists($relative)) {
                continue;
            } else {
                $absolute = storage_path('app/public/'.$relative);
            }

            $mime = (string) (mime_content_type($absolute) ?: '');
            $dims = @getimagesize($absolute);

            return [
                'identity' => $logoCode.'@'.$relative,
                'relative_path' => $relative,
                'sha256' => hash_file('sha256', $absolute) ?: null,
                'mime' => $mime,
                'width' => is_array($dims) ? ($dims[0] ?? null) : null,
                'height' => is_array($dims) ? ($dims[1] ?? null) : null,
            ];
        }

        return null;
    }
}
