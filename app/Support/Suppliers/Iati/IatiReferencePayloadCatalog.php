<?php

namespace App\Support\Suppliers\Iati;

/**
 * Successful IATI /search payloads extracted from Binham iati.pk reference debug logs.
 * No credentials — route/date/payload shape only.
 */
class IatiReferencePayloadCatalog
{
    /**
     * Embedded fixtures from Binham/Iati_new/uploads/iati_search_debug.txt (HTTP 200, flights > 0).
     *
     * @return list<array{
     *     id: string,
     *     route: string,
     *     origin: string,
     *     destination: string,
     *     departure_date: string,
     *     environment: string,
     *     flight_base: string,
     *     reference_timestamp: string,
     *     reference_http_code: int,
     *     reference_departure_flights: int,
     *     reference_request_ip: string,
     *     payload: array<string, mixed>
     * }>
     */
    public static function embeddedFixtures(): array
    {
        return [
            [
                'id' => 'lhe-dxb-2026-05-30',
                'route' => 'LHE-DXB',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_date' => '2026-05-30',
                'environment' => 'production',
                'flight_base' => 'https://api.iati.com/rest/flight/v2',
                'reference_timestamp' => '2026-05-16 14:36:33',
                'reference_http_code' => 200,
                'reference_departure_flights' => 61,
                'reference_request_ip' => '145.223.77.132',
                'payload' => [
                    'from_destination' => ['code' => 'LHE', 'city' => false],
                    'to_destination' => ['code' => 'DXB', 'city' => false],
                    'departure_date' => '2026-05-30',
                    'pax_list' => [['type' => 'ADULT', 'count' => 1]],
                    'accept_pending' => true,
                    'cabin_type' => 'ECONOMY',
                ],
            ],
            [
                'id' => 'isb-jed-2026-05-22',
                'route' => 'ISB-JED',
                'origin' => 'ISB',
                'destination' => 'JED',
                'departure_date' => '2026-05-22',
                'environment' => 'production',
                'flight_base' => 'https://api.iati.com/rest/flight/v2',
                'reference_timestamp' => '2026-05-16 16:38:13',
                'reference_http_code' => 200,
                'reference_departure_flights' => 96,
                'reference_request_ip' => '145.223.77.132',
                'payload' => [
                    'from_destination' => ['code' => 'ISB', 'city' => false],
                    'to_destination' => ['code' => 'JED', 'city' => false],
                    'departure_date' => '2026-05-22',
                    'pax_list' => [['type' => 'ADULT', 'count' => 1]],
                    'accept_pending' => true,
                    'cabin_type' => 'ECONOMY',
                ],
            ],
            [
                'id' => 'isb-jed-2026-06-06',
                'route' => 'ISB-JED',
                'origin' => 'ISB',
                'destination' => 'JED',
                'departure_date' => '2026-06-06',
                'environment' => 'production',
                'flight_base' => 'https://api.iati.com/rest/flight/v2',
                'reference_timestamp' => '2026-05-16 18:40:54',
                'reference_http_code' => 200,
                'reference_departure_flights' => 208,
                'reference_request_ip' => '145.223.77.132',
                'payload' => [
                    'from_destination' => ['code' => 'ISB', 'city' => false],
                    'to_destination' => ['code' => 'JED', 'city' => false],
                    'departure_date' => '2026-06-06',
                    'pax_list' => [['type' => 'ADULT', 'count' => 1]],
                    'accept_pending' => true,
                    'cabin_type' => 'ECONOMY',
                ],
            ],
            [
                'id' => 'dxb-lhr-2026-05-16',
                'route' => 'DXB-LHR',
                'origin' => 'DXB',
                'destination' => 'LHR',
                'departure_date' => '2026-05-16',
                'environment' => 'production',
                'flight_base' => 'https://api.iati.com/rest/flight/v2',
                'reference_timestamp' => '2026-05-16 18:12:21',
                'reference_http_code' => 200,
                'reference_departure_flights' => 31,
                'reference_request_ip' => '145.223.77.132',
                'payload' => [
                    'from_destination' => ['code' => 'DXB', 'city' => false],
                    'to_destination' => ['code' => 'LHR', 'city' => false],
                    'departure_date' => '2026-05-16',
                    'pax_list' => [['type' => 'ADULT', 'count' => 1]],
                    'accept_pending' => true,
                    'cabin_type' => 'ECONOMY',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function referenceDebugPaths(): array
    {
        $candidates = [
            base_path('Binham/Iati_new/uploads/iati_search_debug.txt'),
            base_path('Binham/public_html/uploads/iati_search_debug.txt'),
        ];

        return array_values(array_filter($candidates, is_file(...)));
    }

    /**
     * @return list<array{
     *     id: string,
     *     route: string,
     *     origin: string,
     *     destination: string,
     *     departure_date: string,
     *     environment: string,
     *     flight_base: string,
     *     reference_timestamp: ?string,
     *     reference_http_code: int,
     *     reference_departure_flights: int,
     *     reference_request_ip: ?string,
     *     payload: array<string, mixed>,
     *     source_file: string
     * }>
     */
    public static function parseDebugFile(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $samples = [];
        $pendingStart = null;
        $lineNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $trimmed = trim($line);

            if ($trimmed === '' || $trimmed[0] !== '{') {
                continue;
            }

            $decoded = json_decode($trimmed, true);
            if (! is_array($decoded)) {
                continue;
            }

            if (isset($decoded['iati_payload']) && is_array($decoded['iati_payload'])) {
                $request = is_array($decoded['request'] ?? null) ? $decoded['request'] : [];
                $origin = strtoupper(trim((string) ($request['origin'] ?? $decoded['iati_payload']['from_destination']['code'] ?? '')));
                $destination = strtoupper(trim((string) ($request['destination'] ?? $decoded['iati_payload']['to_destination']['code'] ?? '')));
                $departureDate = trim((string) ($decoded['iati_payload']['departure_date'] ?? ''));

                $pendingStart = [
                    'route' => $origin.'-'.$destination,
                    'origin' => $origin,
                    'destination' => $destination,
                    'departure_date' => $departureDate,
                    'environment' => (string) ($decoded['environment'] ?? 'production'),
                    'flight_base' => (string) ($decoded['flight_base'] ?? 'https://api.iati.com/rest/flight/v2'),
                    'payload' => $decoded['iati_payload'],
                    'source_file' => $path,
                    'source_line' => $lineNumber,
                ];

                continue;
            }

            if ($pendingStart === null) {
                continue;
            }

            if (! isset($decoded['http_code'])) {
                continue;
            }

            $httpCode = (int) $decoded['http_code'];
            $departureCount = 0;
            $requestIp = null;
            $timestamp = null;

            if (isset($decoded['departure_flights'])) {
                $departureCount = (int) $decoded['departure_flights'];
            } elseif (preg_match('/"departure_flights"\s*:\s*\[/', (string) ($decoded['raw_preview'] ?? ''))) {
                $departureCount = 1;
            }

            if (preg_match('/"request_ip"\s*:\s*"([^"]+)"/', (string) ($decoded['raw_preview'] ?? ''), $ipMatch)) {
                $requestIp = $ipMatch[1];
            }

            if (preg_match('/"timestamp"\s*:\s*"([^"]+)"/', (string) ($decoded['raw_preview'] ?? ''), $tsMatch)) {
                $timestamp = $tsMatch[1];
            }

            if ($httpCode === 200 && $departureCount > 0) {
                $id = strtolower($pendingStart['origin'].'-'.$pendingStart['destination'].'-'.$pendingStart['departure_date']);
                $samples[] = array_merge($pendingStart, [
                    'id' => $id,
                    'reference_timestamp' => $timestamp,
                    'reference_http_code' => $httpCode,
                    'reference_departure_flights' => $departureCount,
                    'reference_request_ip' => $requestIp,
                ]);
            }

            $pendingStart = null;
        }

        fclose($handle);

        return $samples;
    }

    /**
     * Resolve a reference sample by id or route. Departure date is not part of matching —
     * use {@see applyDepartureDateOverride()} after selection when --date is passed.
     *
     * @return array<string, mixed>|null
     */
    public static function resolveSample(string $reference, ?string $route): ?array
    {
        $fromFiles = [];
        foreach (self::referenceDebugPaths() as $path) {
            $fromFiles = array_merge($fromFiles, self::parseDebugFile($path));
        }

        $pool = $fromFiles !== [] ? $fromFiles : self::embeddedFixtures();

        if ($reference !== 'latest') {
            foreach ($pool as $sample) {
                if (($sample['id'] ?? '') === $reference) {
                    return $sample;
                }
            }
        }

        if ($route !== null && trim($route) !== '') {
            $normalizedRoute = strtoupper(str_replace(['→', ' ', '_'], ['-', '-', '-'], trim($route)));
            $pool = array_values(array_filter(
                $pool,
                static fn (array $sample): bool => strtoupper((string) $sample['route']) === $normalizedRoute,
            ));
        }

        if ($pool === []) {
            return null;
        }

        if ($reference === 'latest') {
            usort($pool, static function (array $a, array $b): int {
                $aTs = strtotime((string) ($a['reference_timestamp'] ?? '1970-01-01')) ?: 0;
                $bTs = strtotime((string) ($b['reference_timestamp'] ?? '1970-01-01')) ?: 0;

                return $bTs <=> $aTs;
            });

            return $pool[0];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    public static function applyDepartureDateOverride(array $sample, string $date): array
    {
        $normalized = trim($date);
        if ($normalized === '') {
            return $sample;
        }

        $payload = is_array($sample['payload'] ?? null) ? $sample['payload'] : [];
        $payload['departure_date'] = $normalized;
        $sample['payload'] = $payload;

        return $sample;
    }

    /**
     * Replay variants: raw Http probes and IatiClient direct path.
     *
     * @return array<string, array{transport: string, organization_id?: bool, correlation_id?: bool, note: string}>
     */
    public static function headerVariants(): array
    {
        return [
            'reference_exact' => [
                'transport' => 'raw',
                'organization_id' => false,
                'correlation_id' => false,
                'note' => 'Binham IATI_API_REQUEST: Bearer JWT, Accept, Content-Type only',
            ],
            'reference_plus_org_header' => [
                'transport' => 'raw',
                'organization_id' => true,
                'correlation_id' => false,
                'note' => 'Reference payload + OTA Organization-Id header (reference omits this)',
            ],
            'ota_client_headers' => [
                'transport' => 'raw',
                'organization_id' => true,
                'correlation_id' => true,
                'note' => 'Raw Http with OTA IatiClient header set: Organization-Id + X-Correlation-ID',
            ],
            'ota_no_org_header' => [
                'transport' => 'raw',
                'organization_id' => false,
                'correlation_id' => true,
                'note' => 'OTA correlation only — isolate Organization-Id impact',
            ],
            'iati_client_direct' => [
                'transport' => 'iati_client',
                'note' => 'Existing IatiClient::post(/search) — same path as iati:test-search',
            ],
        ];
    }

    public static function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
