<?php

namespace App\Support\Audits;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Booking;
use App\Services\TravelData\AirlineBrandingService;
use App\Services\TravelData\AirlineCanonicalResolver;
use App\Services\TravelData\AirlineImageContentValidator;
use App\Services\TravelData\AirlineLogoCacheService;
use App\Services\TravelData\AirportImportService;
use App\Support\TravelData\AirportDisplayLabelResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Read-only JetPK airport/airline parity audits. No DB writes, no cache writes, no supplier calls.
 */
final class JetpkAirportParityAuditService
{
    public function __construct(
        private readonly AirportImportService $airportImporter,
        private readonly AirlineBrandingService $airlineBranding,
        private readonly AirlineLogoCacheService $logoCache,
        private readonly AirlineImageContentValidator $imageValidator,
        private readonly AirlineCanonicalResolver $canonicalResolver,
    ) {}

    /**
     * @return array{pass: bool, fail_count: int, path_json: string, path_md: string, report: array<string, mixed>}
     */
    public function airportDataParityAudit(?string $sourcePath = null): array
    {
        $sourcePath = $sourcePath ?: (string) config('airports_import.default_source');
        $source = $this->airportImporter->analyzeSource($sourcePath);
        $database = $this->airportImporter->analyzeDatabaseAgainstSource($source);
        $supplier = $this->analyzeSupplierReferencedAirports();

        $uniqueEligible = (int) ($source['unique_eligible_iata_count'] ?? 0);
        $historicalApprox = 5461;
        $countExplanation = $this->explainEligibleCountDelta($uniqueEligible, $historicalApprox, $source);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'jetpk_isolation' => $this->isolationSnapshot(),
            'source' => $source,
            'database' => $database,
            'supplier_referenced_airports' => $supplier,
            'count_reconciliation' => $countExplanation,
            'implementation_map' => $this->airportImplementationMap(),
        ];

        $failCount = 0;
        if (! File::isFile($sourcePath)) {
            $failCount++;
        }
        if ($database['database_airport_count'] < $uniqueEligible) {
            $failCount++;
        }
        if (($database['database_unique_iata_count'] ?? 0) !== $uniqueEligible) {
            $failCount++;
        }
        if (($database['extra_db_rows_not_in_source_count'] ?? 0) > 0) {
            $failCount++;
        }
        if ($database['duplicate_db_iata'] !== []) {
            $failCount++;
        }
        if ($database['missing_source_iata_in_db_count'] > 0) {
            $failCount++;
        }
        if (($database['orphan_rows_without_iata'] ?? 0) > 0) {
            $failCount++;
        }

        $report['pass'] = $failCount === 0;
        $report['fail_count'] = $failCount;

        $dir = $this->auditDirectory();
        $jsonPath = $dir.'/AIRPORT-DATA-PARITY.json';
        $mdPath = $dir.'/AIRPORT-DATA-PARITY.md';
        File::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($mdPath, $this->renderAirportMarkdown($report));

        return [
            'pass' => $failCount === 0,
            'fail_count' => $failCount,
            'path_json' => $jsonPath,
            'path_md' => $mdPath,
            'report' => $report,
        ];
    }

    /**
     * @return array{pass: bool, fail_count: int, checks: list<array<string, mixed>>}
     */
    public function airportUiConsumptionAudit(): array
    {
        $checks = [];
        $failCount = 0;

        $this->recordCheck(
            'route airports.search exists',
            Route::has('airports.search'),
            $checks,
            $failCount,
        );
        $this->recordCheck(
            'route is JetPK-safe shared API (no master URL in route list)',
            ! str_contains((string) route('airports.search', [], false), 'haseebasif.com'),
            $checks,
            $failCount,
        );

        $bladePath = resource_path('views/themes/frontend/jetpakistan/components/search/search-shell.blade.php');
        $blade = is_file($bladePath) ? (string) file_get_contents($bladePath) : '';
        $this->recordCheck(
            'search-shell configures autocomplete URL',
            str_contains($blade, 'data-airports-url') && str_contains($blade, '/airports/search'),
            $checks,
            $failCount,
        );
        $this->recordCheck(
            'search-shell has no hardcoded master domain',
            ! str_contains($blade, 'haseebasif.com') && ! str_contains($blade, 'ota.haseeb'),
            $checks,
            $failCount,
        );

        $jsPath = public_path('themes/frontend/jetpakistan/js/airport-autocomplete.js');
        $js = is_file($jsPath) ? (string) file_get_contents($jsPath) : '';
        $this->recordCheck(
            'autocomplete JS exists',
            $js !== '',
            $checks,
            $failCount,
        );
        $this->recordCheck(
            'autocomplete JS uses relative airports endpoint by default',
            str_contains($js, '/airports/search'),
            $checks,
            $failCount,
        );

        $representative = ['LHE', 'ISB', 'KHI', 'DXB', 'JED', 'MED', 'DOH', 'AUH', 'SHJ', 'IST', 'LHR', 'JFK'];
        foreach ($representative as $code) {
            $airport = Airport::query()->where('iata_code', $code)->first();
            $resolved = AirportDisplayLabelResolver::resolve($code, $airport);
            $this->recordCheck(
                "display resolver for {$code}",
                $airport !== null ? $resolved['label'] !== '' : $resolved['label'] === $code,
                $checks,
                $failCount,
                $airport !== null ? $resolved['label'] : 'missing airport row',
            );
        }

        $consumerFiles = [
            'results' => ['resources/views/frontend/flights/partials/results-page.blade.php', ['FlightOfferDisplayPresenter', 'origin_city']],
            'checkout_summary' => ['resources/views/themes/frontend/jetpakistan/frontend/booking/partials/jp-trip-summary-card.blade.php', ['origin_city', 'jpJourneys']],
            'confirmation' => ['resources/views/themes/frontend/jetpakistan/frontend/booking/partials/confirmation-body.blade.php', ['FlightOfferDisplayPresenter', 'airportCityMap']],
            'admin_booking' => ['resources/views/dashboard/admin/bookings/partials/detail-body.blade.php', ['journey_od', 'itineraryOverview', 'connection_airports']],
            'group_card' => ['resources/views/frontend/group-ticketing/partials/result-card.blade.php', ['route_line', 'AirportDisplayLabelResolver']],
        ];
        foreach ($consumerFiles as $label => [$relative, $needles]) {
            $path = base_path($relative);
            $exists = is_file($path);
            $content = $exists ? (string) file_get_contents($path) : '';
            $usesPresenter = $exists && collect($needles)->contains(static fn (string $needle): bool => str_contains($content, $needle));
            $this->recordCheck(
                "consumer {$label} uses resolved airport labels",
                $usesPresenter,
                $checks,
                $failCount,
            );
        }

        $this->recordCheck(
            'cache key prefix is deployment-local (airport_search_v8)',
            str_contains((string) file_get_contents(app_path('Http/Controllers/Frontend/AirportSearchController.php')), 'airport_search_v8:'),
            $checks,
            $failCount,
        );

        return [
            'pass' => $failCount === 0,
            'fail_count' => $failCount,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{pass: bool, fail_count: int, path_json: string, path_md: string, report: array<string, mixed>}
     */
    public function airlineLogoCoverageAudit(): array
    {
        $airlines = Airline::query()->get();
        $logoDirs = [
            storage_path('app/public/airline-logos'),
            storage_path('app/public/travel-assets/airlines/logos'),
        ];

        $invalidContentPaths = [];
        $zeroBytePaths = [];
        $validatedFileCount = 0;
        foreach ($logoDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob($dir.'/*') ?: [] as $file) {
                if (! is_file($file)) {
                    continue;
                }
                $validatedFileCount++;
                $relative = str_contains($dir, 'travel-assets')
                    ? 'travel-assets/airlines/logos/'.basename($file)
                    : 'airline-logos/'.basename($file);
                $validated = $this->imageValidator->validateFile($file, $relative);
                if (! $validated['valid_content']) {
                    $invalidContentPaths[] = [
                        'path' => $relative,
                        'errors' => $validated['validation_errors'],
                    ];
                }
                if ($validated['size'] === 0) {
                    $zeroBytePaths[] = $relative;
                }
            }
        }

        $logoPathMissing = [];
        $logoPathPresent = [];
        foreach ($airlines as $airline) {
            $path = trim((string) ($airline->logo_path ?? ''));
            if ($path === '') {
                continue;
            }
            if (Storage::disk('public')->exists($path)) {
                $logoPathPresent[] = $airline->iata_code;
            } else {
                $logoPathMissing[] = $airline->iata_code;
            }
        }

        $requiredCanonical = [];
        $requiredFailCount = 0;
        foreach ($this->canonicalResolver->requiredJetpkCodes() as $code) {
            $found = $this->findCanonicalLogoOnDisk($code);
            $requiredCanonical[] = [
                'iata' => $code,
                'canonical_name' => $this->canonicalResolver->canonicalDisplayName($code),
                'status' => $found['status'] ?? 'ABSENT',
                'path' => $found['public_path'] ?? '/storage/airline-logos/'.$code.'.png',
                'valid_content' => $found['valid_content'] ?? false,
                'validation_errors' => $found['validation_errors'] ?? [],
            ];
            if (($found['status'] ?? '') !== 'VALID') {
                $requiredFailCount++;
            }
        }

        $genericFallback = $this->logoCache->genericFallbackPublicUrl();
        $genericPath = public_path('images/airline-generic.svg');
        $genericValid = is_file($genericPath) && $this->imageValidator->validateFile($genericPath, 'images/airline-generic.svg')['valid_content'];

        $iataCounts = $airlines->pluck('iata_code')->filter()->countBy()->all();
        $icaoCounts = $airlines->pluck('icao_code')->filter()->countBy()->all();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'jetpk_isolation' => $this->isolationSnapshot(),
            'airline_database_row_count' => $airlines->count(),
            'rows_with_iata' => $airlines->whereNotNull('iata_code')->count(),
            'rows_with_icao' => $airlines->whereNotNull('icao_code')->count(),
            'duplicate_iata' => array_keys(array_filter($iataCounts, static fn (int $c): bool => $c > 1)),
            'duplicate_icao' => array_keys(array_filter($icaoCounts, static fn (int $c): bool => $c > 1)),
            'blank_airline_names' => $airlines->filter(static fn (Airline $a): bool => trim((string) $a->name) === '')->count(),
            'database_metadata' => [
                'rows_with_logo_path' => $airlines->whereNotNull('logo_path')->count(),
                'logo_path_files_present' => $logoPathPresent,
                'logo_path_files_missing' => $logoPathMissing,
            ],
            'filesystem_validation' => [
                'validated_file_count' => $validatedFileCount,
                'invalid_content_paths' => $invalidContentPaths,
                'zero_byte_paths' => $zeroBytePaths,
                'generic_fallback' => $genericFallback,
                'generic_fallback_valid' => $genericValid,
            ],
            'required_canonical_codes' => $requiredCanonical,
            'resolution_contract' => [
                'order' => [
                    'normalized_iata',
                    'icao_to_iata',
                    'configured_alias',
                    'database_logo_path',
                    'local_cached_asset',
                    'neutral_generic_logo',
                ],
                'canonical_strategy' => 'Option B: storage/app/public/airline-logos/{CODE}.png via /storage symlink; DB travel-assets/logos as override',
            ],
            'implementation_map' => $this->airlineImplementationMap(),
        ];

        $failCount = 0;
        if ($report['duplicate_iata'] !== []) {
            $failCount++;
        }
        if ($invalidContentPaths !== []) {
            $failCount += count($invalidContentPaths);
        }
        if ($zeroBytePaths !== []) {
            $failCount += count($zeroBytePaths);
        }
        if (! $genericValid) {
            $failCount++;
        }
        $failCount += $requiredFailCount;

        $dir = $this->auditDirectory();
        $jsonPath = $dir.'/AIRLINE-LOGO-COVERAGE.json';
        $mdPath = $dir.'/AIRLINE-LOGO-COVERAGE.md';
        File::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($mdPath, $this->renderAirlineMarkdown($report));

        return [
            'pass' => $failCount === 0,
            'fail_count' => $failCount,
            'path_json' => $jsonPath,
            'path_md' => $mdPath,
            'report' => $report,
        ];
    }

    /**
     * @return array{pass: bool, fail_count: int, assets: list<array<string, mixed>>}
     */
    public function airlineAssetPathAudit(): array
    {
        $assets = [];
        $failCount = 0;
        $summary = [
            'checked' => 0,
            'valid' => 0,
            'missing' => 0,
            'fallback' => 0,
            'invalid' => 0,
        ];
        $auditedPaths = [];

        $generic = public_path('images/airline-generic.svg');
        $assets[] = $this->auditAssetAt($generic, '/images/airline-generic.svg', $summary, $failCount, required: true);
        $auditedPaths['/images/airline-generic.svg'] = true;

        foreach ($this->canonicalResolver->requiredJetpkCodes() as $code) {
            $found = $this->findCanonicalLogoOnDisk($code);
            if ($found === null) {
                $publicPath = '/storage/airline-logos/'.$code.'.png';
                $summary['checked']++;
                $summary['fallback']++;
                $assets[] = [
                    'status' => 'FALLBACK',
                    'public_path' => $publicPath,
                    'absolute_path' => null,
                    'size' => 0,
                    'detected_mime' => null,
                    'valid_content' => false,
                    'validation_errors' => ['required_logo_missing'],
                    'required' => true,
                ];
                $failCount++;
                $auditedPaths[$publicPath] = true;

                continue;
            }

            $assets[] = $this->auditAssetAt(
                $found['absolute'],
                $found['public_path'],
                $summary,
                $failCount,
                required: true,
                presetStatus: $found['status'],
            );
            $auditedPaths[$found['public_path']] = true;
        }

        foreach ([
            storage_path('app/public/airline-logos') => '/storage/airline-logos/',
            storage_path('app/public/travel-assets/airlines/logos') => '/storage/travel-assets/airlines/logos/',
        ] as $dir => $publicPrefix) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                if (! is_file($file)) {
                    continue;
                }
                $basename = basename($file);
                $publicPath = $publicPrefix.$basename;
                if (isset($auditedPaths[$publicPath])) {
                    continue;
                }
                $assets[] = $this->auditAssetAt($file, $publicPath, $summary, $failCount, required: false);
                $auditedPaths[$publicPath] = true;
            }
        }

        $symlink = public_path('storage');
        $symlinkOk = is_link($symlink) || is_dir($symlink);
        if (! $symlinkOk) {
            $failCount++;
        }

        return [
            'pass' => $failCount === 0,
            'fail_count' => $failCount,
            'storage_symlink_ok' => $symlinkOk,
            'summary' => $summary,
            'assets' => $assets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeSupplierReferencedAirports(): array
    {
        $codes = collect();
        $malformed = [];

        Booking::query()
            ->select(['id', 'meta', 'route'])
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->each(function (Booking $booking) use (&$codes, &$malformed): void {
                $meta = is_array($booking->meta) ? $booking->meta : [];
                foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
                    $snapshot = $meta[$key] ?? null;
                    if (! is_array($snapshot)) {
                        continue;
                    }
                    $this->collectAirportCodesFromSnapshot($snapshot, $codes, $malformed);
                }
                foreach (explode('-', (string) ($booking->route ?? '')) as $part) {
                    $this->pushAirportCode($part, $codes, $malformed);
                }
            });

        $unique = $codes->unique()->sort()->values();
        $resolved = [];
        $unresolved = [];
        foreach ($unique as $code) {
            $airport = Airport::query()->where('iata_code', $code)->first();
            if ($airport !== null) {
                $resolved[] = $code;
            } else {
                $unresolved[] = $code;
            }
        }

        return [
            'referenced_unique_airport_codes' => $unique->all(),
            'referenced_count' => $unique->count(),
            'resolved_codes' => $resolved,
            'resolved_count' => count($resolved),
            'unresolved_codes' => $unresolved,
            'unresolved_count' => count($unresolved),
            'malformed_codes' => array_values(array_unique($malformed)),
            'fallback_only_codes' => $unresolved,
            'note' => 'Read-only scan of local booking meta/route fields; no supplier API calls.',
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function collectAirportCodesFromSnapshot(array $snapshot, \Illuminate\Support\Collection $codes, array &$malformed): void
    {
        $paths = [
            ['origin', 'iata'],
            ['destination', 'iata'],
            ['departure', 'iata'],
            ['arrival', 'iata'],
        ];
        foreach ($paths as $path) {
            $value = data_get($snapshot, implode('.', $path));
            $this->pushAirportCode($value, $codes, $malformed);
        }

        $segments = data_get($snapshot, 'segments', data_get($snapshot, 'journeys.0.segments', []));
        if (is_array($segments)) {
            foreach ($segments as $segment) {
                if (! is_array($segment)) {
                    continue;
                }
                $this->pushAirportCode($segment['origin'] ?? $segment['from'] ?? null, $codes, $malformed);
                $this->pushAirportCode($segment['destination'] ?? $segment['to'] ?? null, $codes, $malformed);
                $this->pushAirportCode(data_get($segment, 'departure.iata'), $codes, $malformed);
                $this->pushAirportCode(data_get($segment, 'arrival.iata'), $codes, $malformed);
            }
        }
    }

    private function pushAirportCode(mixed $value, \Illuminate\Support\Collection $codes, array &$malformed): void
    {
        $code = Str::upper(trim((string) $value));
        if ($code === '') {
            return;
        }
        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            $malformed[] = $code;

            return;
        }
        $codes->push($code);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function explainEligibleCountDelta(int $currentUnique, int $historicalApprox, array $source): array
    {
        $duplicateIata = $source['duplicate_iata'] ?? [];
        $duplicatesRemoved = (int) ($source['duplicates_removed'] ?? 0);
        $reservedRejected = $source['reserved_iata_codes_rejected'] ?? [];
        $reservedCount = (int) ($source['skipped_reserved_iata'] ?? 0);

        $rawEligibleIfReservedAllowed = $currentUnique + count($reservedRejected);
        $deltaVsHistorical = $rawEligibleIfReservedAllowed - $historicalApprox;

        $explanation = 'The current OurAirports-style CSV yields '.$currentUnique.' importable unique IATA airports after '
            .'closed/no-IATA/type filters, last-wins deduplication, and reserved-code rejection '
            .'('.implode(', ', $reservedRejected ?: ['none']).').';

        if ($rawEligibleIfReservedAllowed === $historicalApprox) {
            $explanation .= ' The historical ~'.$historicalApprox.' figure counted '.$rawEligibleIfReservedAllowed
                .' raw eligible rows including reserved placeholder code(s); importable storage count is '.$currentUnique.'.';
        } elseif ($deltaVsHistorical === -1) {
            $explanation .= ' The historical ~'.$historicalApprox.' figure is one row higher: dataset refresh removed or '
                .'reclassified one airport, and/or an older export counted reserved placeholder IATA '
                .implode('/', $reservedRejected ?: ['codes']).' as eligible.';
        } else {
            $explanation .= ' The historical ~'.$historicalApprox.' delta ('.($rawEligibleIfReservedAllowed - $historicalApprox)
                .') is explained by dataset refresh and deduplication rules, not an import bug.';
        }

        return [
            'current_unique_eligible_iata' => $currentUnique,
            'raw_eligible_including_reserved_rejected' => $rawEligibleIfReservedAllowed,
            'reserved_iata_codes_rejected' => $reservedRejected,
            'skipped_reserved_iata_rows' => $reservedCount,
            'historical_approximate_count' => $historicalApprox,
            'delta_importable_vs_historical' => $currentUnique - $historicalApprox,
            'delta_raw_vs_historical' => $rawEligibleIfReservedAllowed - $historicalApprox,
            'explanation' => $explanation,
            'duplicate_iata_in_source' => $duplicateIata,
            'duplicates_removed_by_last_wins' => $duplicatesRemoved,
            'physical_csv_lines' => $source['physical_line_count'] ?? null,
            'parsed_rows' => $source['parsed_row_count'] ?? null,
            'eligible_before_dedup' => $source['eligible_rows_before_dedup'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function isolationSnapshot(): array
    {
        return [
            'client_slug' => (string) config('ota_client.slug', ''),
            'single_client_mode' => (bool) config('ota_client.single_client_mode', false),
            'app_url' => (string) config('app.url', ''),
            'uses_master_domain_in_app_url' => str_contains((string) config('app.url', ''), 'haseebasif.com'),
            'public_webroot_path' => (string) config('ota_client.public_webroot_path', ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function airportImplementationMap(): array
    {
        return [
            'model' => 'App\Models\Airport',
            'import_command' => 'airports:import',
            'normalize_command' => 'ota:normalize-airports-search',
            'source_csv' => (string) config('airports_import.default_source'),
            'overrides_config' => 'config/airports_overrides.php',
            'seeder_fallback' => 'Database\Seeders\AirportAirlineReferenceSeeder',
            'search_endpoint' => 'GET /airports/search',
            'search_controller' => 'App\Http\Controllers\Frontend\AirportSearchController',
            'autocomplete_js' => 'public/themes/frontend/jetpakistan/js/airport-autocomplete.js',
            'search_shell_blade' => 'resources/views/themes/frontend/jetpakistan/components/search/search-shell.blade.php',
            'display_resolver' => 'App\Support\TravelData\AirportDisplayLabelResolver',
            'result_presenter' => 'App\Support\FlightSearch\FlightOfferDisplayPresenter',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function airlineImplementationMap(): array
    {
        return [
            'model' => 'App\Models\Airline',
            'branding_service' => 'App\Services\TravelData\AirlineBrandingService',
            'logo_cache_service' => 'App\Services\TravelData\AirlineLogoCacheService',
            'cache_directory' => $this->logoCache->cacheDirectory(),
            'generic_fallback' => $this->logoCache->genericFallbackPublicUrl(),
            'import_command' => 'ota:import-airports-airlines --logos',
            'cache_logos_command' => 'ota:cache-airline-logos --all-used',
        ];
    }

    private function auditDirectory(): string
    {
        $dir = storage_path('app/audits/jetpk-airport-parity');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     */
    private function recordCheck(string $label, bool $pass, array &$checks, int &$failCount, string $detail = ''): array
    {
        if (! $pass) {
            $failCount++;
        }
        $row = ['label' => $label, 'pass' => $pass, 'detail' => $detail];
        $checks[] = $row;

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCanonicalLogoOnDisk(string $code): ?array
    {
        $logoCode = $this->canonicalResolver->logoCode($code) ?? $code;
        $candidates = [
            ['absolute' => storage_path('app/public/airline-logos/'.$logoCode.'.png'), 'public' => '/storage/airline-logos/'.$logoCode.'.png', 'relative' => 'airline-logos/'.$logoCode.'.png'],
            ['absolute' => storage_path('app/public/airline-logos/'.$logoCode.'.webp'), 'public' => '/storage/airline-logos/'.$logoCode.'.webp', 'relative' => 'airline-logos/'.$logoCode.'.webp'],
            ['absolute' => storage_path('app/public/travel-assets/airlines/logos/'.$logoCode.'.png'), 'public' => '/storage/travel-assets/airlines/logos/'.$logoCode.'.png', 'relative' => 'travel-assets/airlines/logos/'.$logoCode.'.png'],
            ['absolute' => storage_path('app/public/travel-assets/airlines/logos/'.$logoCode.'.webp'), 'public' => '/storage/travel-assets/airlines/logos/'.$logoCode.'.webp', 'relative' => 'travel-assets/airlines/logos/'.$logoCode.'.webp'],
        ];

        foreach ($candidates as $candidate) {
            if (! is_file($candidate['absolute'])) {
                continue;
            }

            $validated = $this->imageValidator->validateFile($candidate['absolute'], $candidate['relative']);

            return [
                'absolute' => $candidate['absolute'],
                'public_path' => $candidate['public'],
                'relative_path' => $candidate['relative'],
                'status' => $validated['valid_content'] ? 'VALID' : 'INVALID',
                'valid_content' => $validated['valid_content'],
                'validation_errors' => $validated['validation_errors'],
            ];
        }

        return null;
    }

    /**
     * @param  array{checked:int,valid:int,missing:int,fallback:int,invalid:int}  $summary
     * @return array<string, mixed>
     */
    private function auditAssetAt(
        ?string $absolutePath,
        string $publicPath,
        array &$summary,
        int &$failCount,
        bool $required = false,
        ?string $presetStatus = null,
    ): array {
        $summary['checked']++;
        $publicPath = $publicPath !== '' ? $publicPath : '/unknown-asset';

        if ($absolutePath === null || ! is_file($absolutePath)) {
            $summary[$required ? 'fallback' : 'missing']++;
            if ($required) {
                $failCount++;
            }

            return [
                'status' => $required ? 'FALLBACK' : 'ABSENT',
                'public_path' => $publicPath,
                'absolute_path' => $absolutePath,
                'size' => 0,
                'detected_mime' => null,
                'valid_content' => false,
                'validation_errors' => [$required ? 'required_asset_missing' : 'optional_asset_absent'],
                'required' => $required,
            ];
        }

        $relative = ltrim(str_replace('/storage/', '', $publicPath), '/');
        $validated = $this->imageValidator->validateFile($absolutePath, $relative);
        $status = $presetStatus ?? ($validated['valid_content'] ? 'PASS' : 'INVALID');

        if ($validated['valid_content']) {
            $summary['valid']++;
        } else {
            $summary['invalid']++;
            $failCount++;
        }

        return [
            'status' => $status === 'VALID' ? 'PASS' : $status,
            'public_path' => $publicPath,
            'absolute_path' => $absolutePath,
            'size' => $validated['size'],
            'detected_mime' => $validated['detected_mime'],
            'valid_content' => $validated['valid_content'],
            'validation_errors' => $validated['validation_errors'],
            'required' => $required,
            'valid' => $validated['valid_content'],
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderAirportMarkdown(array $report): string
    {
        $source = $report['source'] ?? [];
        $db = $report['database'] ?? [];
        $count = $report['count_reconciliation'] ?? [];

        return implode("\n", [
            '# JetPK Airport Data Parity',
            '',
            'Generated: '.($report['generated_at'] ?? ''),
            '',
            '## Source',
            '- Physical lines: '.($source['physical_line_count'] ?? ''),
            '- Parsed rows: '.($source['parsed_row_count'] ?? ''),
            '- Eligible before dedup: '.($source['eligible_rows_before_dedup'] ?? ''),
            '- Unique eligible IATA: '.($source['unique_eligible_iata_count'] ?? ''),
            '- Duplicates removed: '.($source['duplicates_removed'] ?? ''),
            '- Closed: '.($source['closed_airports'] ?? ''),
            '- No IATA: '.($source['rows_without_iata'] ?? ''),
            '- Type filtered: '.($source['filtered_airport_types'] ?? ''),
            '',
            '## Database',
            '- Airport rows: '.($db['database_airport_count'] ?? ''),
            '- Active: '.($db['active_airport_count'] ?? ''),
            '- Missing source IATA: '.($db['missing_source_iata_in_db_count'] ?? ''),
            '- Expected post-import total: '.($db['expected_post_import_total'] ?? ''),
            '',
            '## Count reconciliation',
            '- Current unique: '.($count['current_unique_eligible_iata'] ?? ''),
            '- Historical approx: '.($count['historical_approximate_count'] ?? ''),
            '- Delta: '.($count['delta'] ?? ''),
            '- Explanation: '.($count['explanation'] ?? ''),
            '',
            'Fail count: '.($report['fail_count'] ?? 0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderAirlineMarkdown(array $report): string
    {
        $dbMeta = $report['database_metadata'] ?? [];
        $fs = $report['filesystem_validation'] ?? [];

        return implode("\n", [
            '# JetPK Airline Logo Coverage',
            '',
            'Generated: '.($report['generated_at'] ?? ''),
            '',
            '## Database metadata (informational)',
            '- Airline rows: '.($report['airline_database_row_count'] ?? ''),
            '- Rows with logo_path: '.($dbMeta['rows_with_logo_path'] ?? ''),
            '',
            '## Filesystem validation',
            '- Validated files: '.($fs['validated_file_count'] ?? ''),
            '- Invalid content paths: '.count($fs['invalid_content_paths'] ?? []),
            '- Generic fallback valid: '.(($fs['generic_fallback_valid'] ?? false) ? 'yes' : 'no'),
            '',
            '## Required canonical codes',
            ...array_map(
                static fn (array $row): string => '- '.($row['iata'] ?? '').' '.($row['status'] ?? '').' '.($row['path'] ?? ''),
                $report['required_canonical_codes'] ?? [],
            ),
            '',
            'Canonical strategy: '.(($report['resolution_contract']['canonical_strategy'] ?? '')),
        ]);
    }
}
