<?php

namespace App\Services\TravelData;

use App\Models\Airport;
use App\Support\Geo\CountryList;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Imports passenger-relevant airports from an OurAirports-style CSV (IATA-filtered).
 *
 * CSV is uploaded manually to storage — no remote download. Official IATA data can
 * replace the file later without changing this service contract.
 */
class AirportImportService
{
    /**
     * IATA codes rejected from import/storage (SQL/Windows reserved or OurAirports placeholders).
     *
     * @return list<string>
     */
    public static function reservedInvalidIataCodes(): array
    {
        return ['-', '---', '\\N', 'N/A', 'NULL', 'NUL'];
    }

    /** @var array<string, int> */
    protected array $priorityBoosts;

    /** @var array<string, int> */
    protected array $typePriority;

    /** @var list<string> */
    protected array $routeRegionCodes;

    protected int $regionBoost;

    /** @var array<string, array<string, mixed>> */
    protected array $configOverrides = [];

    public function __construct()
    {
        $this->priorityBoosts = config('airports_import.priority_boosts', []);
        $this->typePriority = config('airports_import.type_priority', []);
        $this->routeRegionCodes = config('airports_import.route_region_country_codes', []);
        $this->regionBoost = (int) config('airports_import.region_priority_boost', 15);
        $this->configOverrides = config('airports_overrides', []);
    }

    /**
     * @return array{
     *     imported:int,
     *     updated:int,
     *     skipped:int,
     *     skipped_closed:int,
     *     skipped_no_iata:int,
     *     skipped_type:int,
     *     overrides_applied:int,
     *     dry_run:bool,
     *     truncated:bool,
     *     db_write_attempted:bool
     * }
     */
    public function import(string $sourcePath, bool $dryRun = false, bool $truncate = false, bool $pruneNotInSource = false): array
    {
        if (! File::isFile($sourcePath)) {
            throw new \InvalidArgumentException('Airport CSV not found: '.$sourcePath);
        }

        $stats = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'skipped_closed' => 0,
            'skipped_no_iata' => 0,
            'skipped_type' => 0,
            'overrides_applied' => 0,
            'dry_run' => $dryRun,
            'truncated' => false,
            'db_write_attempted' => false,
        ];

        $overrides = $this->loadOverrides();
        $sourceEligibleIatas = [];

        $sourceChecksum = hash_file('sha256', $sourcePath) ?: null;
        $importedAt = now()->toIso8601String();

        if ($truncate && ! $dryRun) {
            Airport::query()->delete();
            $stats['truncated'] = true;
            $stats['db_write_attempted'] = true;
        } elseif ($truncate) {
            $stats['truncated'] = true;
        }

        foreach ($this->readCsv($sourcePath) as $row) {
            if (! $this->shouldImportRow($row, $stats)) {
                continue;
            }

            $normalized = $this->normalizeRow($row);
            if ($normalized === null) {
                $stats['skipped']++;
                $stats['skipped_no_iata']++;

                continue;
            }

            $iata = $normalized['iata_code'];
            $sourceEligibleIatas[] = $iata;
            $normalized = $this->applyOverrides($normalized, $iata, $overrides, $stats);
            $normalized['meta'] = array_merge($normalized['meta'] ?? [], [
                'import_checksum' => $sourceChecksum,
                'imported_at' => $importedAt,
            ]);

            if ($dryRun) {
                $exists = Airport::query()->where('iata_code', $iata)->exists();
                if ($exists) {
                    $stats['updated']++;
                } else {
                    $stats['imported']++;
                }

                continue;
            }

            $stats['db_write_attempted'] = true;
            $airport = Airport::query()->firstOrNew(['iata_code' => $iata]);
            $isNew = ! $airport->exists;
            $airport->fill($normalized);
            $airport->save();

            if ($isNew) {
                $stats['imported']++;
            } else {
                $stats['updated']++;
            }
        }

        if ($pruneNotInSource) {
            $prune = $this->pruneNotInSource($sourceEligibleIatas, $dryRun);
            $stats['pruned_not_in_source'] = $prune['pruned_not_in_source'];
            $stats['prune_candidates'] = $prune['prune_candidates'];
            if ($prune['db_write_attempted']) {
                $stats['db_write_attempted'] = true;
            }
        }

        return $stats;
    }

    /**
     * Remove airports with IATA codes absent from a source eligibility set (explicit opt-in only).
     *
     * @param  list<string>  $sourceEligibleIatas
     * @return array{
     *     pruned_not_in_source:int,
     *     prune_candidates:list<array{id:int,iata_code:?string,name:?string,city:?string}>,
     *     db_write_attempted:bool
     * }
     */
    public function pruneNotInSource(array $sourceEligibleIatas, bool $dryRun = false): array
    {
        $eligible = collect($sourceEligibleIatas)->map(static fn (string $code): string => Str::upper(trim($code)))->unique()->values();

        $candidates = Airport::query()
            ->whereNotNull('iata_code')
            ->whereNotIn('iata_code', $eligible->all())
            ->orderBy('id')
            ->get(['id', 'iata_code', 'name', 'city']);

        $mapped = $candidates->map(static fn (Airport $airport): array => [
            'id' => (int) $airport->id,
            'iata_code' => $airport->iata_code,
            'name' => $airport->name,
            'city' => $airport->city,
        ])->values()->all();

        $deleted = 0;
        if (! $dryRun && $candidates->isNotEmpty()) {
            $deleted = Airport::query()->whereIn('id', $candidates->pluck('id')->all())->delete();
        }

        return [
            'pruned_not_in_source' => $dryRun ? $candidates->count() : $deleted,
            'prune_candidates' => $mapped,
            'db_write_attempted' => ! $dryRun && $candidates->isNotEmpty(),
        ];
    }

    /**
     * Read-only source analysis for parity audits (no database or cache writes).
     *
     * @return array<string, mixed>
     */
    public function analyzeSource(string $sourcePath): array
    {
        if (! File::isFile($sourcePath)) {
            throw new \InvalidArgumentException('Airport CSV not found: '.$sourcePath);
        }

        $stats = [
            'source_path' => $sourcePath,
            'source_sha256' => hash_file('sha256', $sourcePath) ?: null,
            'physical_line_count' => 0,
            'parsed_row_count' => 0,
            'eligible_rows_before_dedup' => 0,
            'unique_eligible_iata_count' => 0,
            'unique_eligible_icao_count' => 0,
            'duplicates_removed' => 0,
            'duplicate_iata' => [],
            'duplicate_icao' => [],
            'rejected_malformed_iata' => 0,
            'closed_airports' => 0,
            'rows_without_iata' => 0,
            'filtered_airport_types' => 0,
            'skipped_reserved_iata' => 0,
            'reserved_iata_codes_rejected' => [],
            'override_count' => count($this->loadOverrides()),
            'source_countries' => [],
            'eligible_iata_codes' => [],
        ];

        $eligibleByIata = [];
        $icaoCounts = [];
        $countryCodes = [];

        foreach ($this->readCsv($sourcePath) as $row) {
            $stats['parsed_row_count']++;

            $scratch = [
                'skipped' => 0,
                'skipped_closed' => 0,
                'skipped_no_iata' => 0,
                'skipped_type' => 0,
                'skipped_reserved_iata' => 0,
            ];

            if (! $this->shouldImportRow($row, $scratch)) {
                $stats['closed_airports'] += $scratch['skipped_closed'];
                $stats['rows_without_iata'] += $scratch['skipped_no_iata'];
                $stats['filtered_airport_types'] += $scratch['skipped_type'];
                $stats['skipped_reserved_iata'] += $scratch['skipped_reserved_iata'];

                $rawIata = $this->normalizeCode($row['iata_code'] ?? null);
                if ($rawIata !== null && $this->normalizeIata($row['iata_code'] ?? null) === null) {
                    if (in_array($rawIata, self::reservedInvalidIataCodes(), true)) {
                        if (! in_array($rawIata, $stats['reserved_iata_codes_rejected'], true)) {
                            $stats['reserved_iata_codes_rejected'][] = $rawIata;
                        }
                    } else {
                        $stats['rejected_malformed_iata']++;
                    }
                }

                continue;
            }

            $normalized = $this->normalizeRow($row);
            if ($normalized === null) {
                $stats['rows_without_iata']++;

                continue;
            }

            $stats['eligible_rows_before_dedup']++;
            $iata = (string) $normalized['iata_code'];
            if (isset($eligibleByIata[$iata])) {
                $stats['duplicates_removed']++;
                if (! in_array($iata, $stats['duplicate_iata'], true)) {
                    $stats['duplicate_iata'][] = $iata;
                }
            }
            $eligibleByIata[$iata] = $normalized;

            $icao = trim((string) ($normalized['icao_code'] ?? ''));
            if ($icao !== '') {
                $icaoCounts[$icao] = ($icaoCounts[$icao] ?? 0) + 1;
            }

            $cc = trim((string) ($normalized['country_code'] ?? ''));
            if ($cc !== '') {
                $countryCodes[$cc] = true;
            }
        }

        $stats['physical_line_count'] = $this->countPhysicalLines($sourcePath);
        $stats['unique_eligible_iata_count'] = count($eligibleByIata);
        $stats['eligible_iata_codes'] = array_keys($eligibleByIata);
        sort($stats['eligible_iata_codes']);

        $stats['unique_eligible_icao_count'] = count(array_filter(
            $icaoCounts,
            static fn (int $count): bool => $count >= 1,
        ));
        $stats['duplicate_icao'] = array_keys(array_filter(
            $icaoCounts,
            static fn (int $count): bool => $count > 1,
        ));
        sort($stats['duplicate_icao']);
        $stats['source_countries'] = array_keys($countryCodes);
        sort($stats['source_countries']);

        return $stats;
    }

    /**
     * Compare source eligibility with the current database (read-only).
     *
     * @param  array<string, mixed>  $sourceAnalysis
     * @return array<string, mixed>
     */
    public function analyzeDatabaseAgainstSource(array $sourceAnalysis): array
    {
        $sourceIatas = collect($sourceAnalysis['eligible_iata_codes'] ?? [])
            ->filter()
            ->map(static fn (string $code): string => Str::upper(trim($code)))
            ->unique()
            ->values();

        $dbAirports = Airport::query()->get();
        $dbIatas = $dbAirports
            ->pluck('iata_code')
            ->filter()
            ->map(static fn (?string $code): string => Str::upper(trim((string) $code)))
            ->filter()
            ->values();

        $dbIataCounts = $dbIatas->countBy()->all();
        $orphanRowsWithoutIata = $dbAirports->filter(
            static fn (Airport $a): bool => trim((string) ($a->iata_code ?? '')) === '',
        )->count();
        $dbIcaoCounts = $dbAirports
            ->pluck('icao_code')
            ->filter()
            ->map(static fn (?string $code): string => Str::upper(trim((string) $code)))
            ->countBy()
            ->all();

        $missingInDb = $sourceIatas->diff($dbIatas)->values()->all();
        $extraInDb = $dbIatas->diff($sourceIatas)->values()->all();

        $blankName = $dbAirports->filter(static fn (Airport $a): bool => trim((string) $a->name) === '')->count();
        $blankCity = $dbAirports->filter(static fn (Airport $a): bool => trim((string) ($a->city ?? '')) === '')->count();
        $blankCountry = $dbAirports->filter(static fn (Airport $a): bool => trim((string) ($a->country ?? '')) === '')->count();
        $missingIcao = $dbAirports->filter(static fn (Airport $a): bool => trim((string) ($a->icao_code ?? '')) === '')->count();
        $missingKeywords = $dbAirports->filter(static fn (Airport $a): bool => trim((string) ($a->search_keywords ?? '')) === '')->count();

        $invalidCountries = [];
        $malformedCoords = [];
        foreach ($dbAirports as $airport) {
            $cc = trim((string) ($airport->country_code ?? ''));
            if ($cc !== '' && CountryList::normalizeAlpha2($cc) === null) {
                $invalidCountries[] = $airport->iata_code;
            }
            $lat = $airport->latitude;
            $lng = $airport->longitude;
            if (($lat !== null && (abs((float) $lat) > 90)) || ($lng !== null && (abs((float) $lng) > 180))) {
                $malformedCoords[] = $airport->iata_code;
            }
        }

        $expectedInsert = $sourceIatas->diff($dbIatas)->count();
        $expectedUpdate = $sourceIatas->intersect($dbIatas)->count();
        $expectedUnchanged = 0;
        $expectedTotal = $sourceIatas->count();

        return [
            'database_airport_count' => $dbAirports->count(),
            'active_airport_count' => $dbAirports->where('is_active', true)->count(),
            'inactive_airport_count' => $dbAirports->where('is_active', false)->count(),
            'database_unique_iata_count' => $dbIatas->unique()->count(),
            'database_unique_icao_count' => $dbAirports->pluck('icao_code')->filter()->unique()->count(),
            'orphan_rows_without_iata' => $orphanRowsWithoutIata,
            'missing_source_iata_in_db' => $missingInDb,
            'missing_source_iata_in_db_count' => count($missingInDb),
            'extra_db_rows_not_in_source' => $extraInDb,
            'extra_db_rows_not_in_source_count' => count($extraInDb),
            'rows_missing_icao' => $missingIcao,
            'rows_with_blank_name' => $blankName,
            'rows_with_blank_city' => $blankCity,
            'rows_with_blank_country' => $blankCountry,
            'invalid_iso_country_codes' => array_values(array_unique($invalidCountries)),
            'invalid_iso_country_codes_count' => count(array_unique($invalidCountries)),
            'malformed_latitude_longitude' => array_values(array_unique($malformedCoords)),
            'malformed_latitude_longitude_count' => count(array_unique($malformedCoords)),
            'missing_search_keywords' => $missingKeywords,
            'duplicate_db_iata' => array_keys(array_filter($dbIataCounts, static fn (int $c): bool => $c > 1)),
            'duplicate_db_icao' => array_keys(array_filter($dbIcaoCounts, static fn (int $c): bool => $c > 1)),
            'expected_post_import_insert_count' => $expectedInsert,
            'expected_post_import_update_count' => $expectedUpdate,
            'expected_post_import_unchanged_count' => $expectedUnchanged,
            'expected_post_import_total' => $expectedTotal,
        ];
    }

    protected function countPhysicalLines(string $path): int
    {
        $count = 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }
        try {
            while (fgets($handle) !== false) {
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int|bool>  $stats
     */
    protected function shouldImportRow(array $row, array &$stats): bool
    {
        $type = mb_strtolower(trim((string) ($row['type'] ?? '')));

        if ($type === 'closed') {
            $stats['skipped']++;
            $stats['skipped_closed']++;

            return false;
        }

        $iata = $this->normalizeIata($row['iata_code'] ?? null);
        if ($iata === null) {
            $stats['skipped']++;
            $raw = $this->normalizeCode($row['iata_code'] ?? null);
            if ($raw !== null && in_array($raw, self::reservedInvalidIataCodes(), true)) {
                $stats['skipped_reserved_iata'] = (int) ($stats['skipped_reserved_iata'] ?? 0) + 1;
            } else {
                $stats['skipped_no_iata']++;
            }

            return false;
        }

        if (in_array($type, ['large_airport', 'medium_airport'], true)) {
            return true;
        }

        if ($type === 'small_airport') {
            if ($this->isScheduledService($row)) {
                return true;
            }

            if (isset($this->priorityBoosts[$iata]) || isset($this->configOverrides[$iata])) {
                return true;
            }

            $stats['skipped']++;
            $stats['skipped_type']++;

            return false;
        }

        if ($this->isScheduledService($row) && $iata !== null) {
            return true;
        }

        $stats['skipped']++;
        $stats['skipped_type']++;

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function normalizeRow(array $row): ?array
    {
        $iata = $this->normalizeIata($row['iata_code'] ?? null);
        if ($iata === null) {
            return null;
        }

        $icao = $this->normalizeIcao($row['gps_code'] ?? $row['ident'] ?? null);
        $name = $this->cleanValue($row['name'] ?? null) ?? $iata;
        $city = $this->cleanValue($row['municipality'] ?? null);
        $isoCountry = CountryList::normalizeAlpha2($this->cleanValue($row['iso_country'] ?? null));
        $countryRow = $isoCountry !== null ? CountryList::findByAlpha2($isoCountry) : null;
        $country = $countryRow['name'] ?? $isoCountry;
        $type = mb_strtolower(trim((string) ($row['type'] ?? '')));
        $latitude = $this->toFloat($row['latitude_deg'] ?? null);
        $longitude = $this->toFloat($row['longitude_deg'] ?? null);
        $keywords = $this->buildKeywords([
            $iata,
            $icao,
            $name,
            $city,
            $country,
            $this->cleanValue($row['keywords'] ?? null),
        ]);

        $priority = $this->computePriorityScore($iata, $type, $isoCountry);

        return [
            'iata_code' => $iata,
            'icao_code' => $icao,
            'name' => $name,
            'city' => $city,
            'country' => $country,
            'country_code' => $isoCountry,
            'airport_type' => $type !== '' ? $type : null,
            'timezone' => $this->cleanValue($row['tz_database_timezone'] ?? $row['timezone'] ?? null),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'priority_score' => $priority,
            'has_routes' => false,
            'route_count' => 0,
            'is_commercial' => true,
            'is_active' => true,
            'search_keywords' => $keywords,
            'meta' => [
                'source' => 'ourairports-iata-dataset',
                'dataset_label' => (string) config('airports_import.dataset_label'),
                'ident' => $this->cleanValue($row['ident'] ?? null),
                'scheduled_service' => $this->cleanValue($row['scheduled_service'] ?? null),
                'iso_region' => $this->cleanValue($row['iso_region'] ?? null),
                'elevation_ft' => $this->toFloat($row['elevation_ft'] ?? null),
                'source_updated_at' => $this->cleanValue($row['last_updated'] ?? $row['lastedit'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $overrides
     * @param  array<string, int|bool>  $stats
     * @return array<string, mixed>
     */
    protected function applyOverrides(array $data, string $iata, array $overrides, array &$stats): array
    {
        $override = $overrides[$iata] ?? null;
        if ($override === null) {
            return $data;
        }

        $stats['overrides_applied']++;

        foreach (['name', 'city', 'country', 'country_code', 'timezone'] as $field) {
            if (isset($override[$field]) && $override[$field] !== '') {
                $data[$field] = $override[$field];
            }
        }

        if (array_key_exists('is_active', $override)) {
            $data['is_active'] = (bool) $override['is_active'];
        }

        if (isset($override['priority_score'])) {
            $data['priority_score'] = max((int) $data['priority_score'], (int) $override['priority_score']);
        }

        if (! empty($override['aliases'])) {
            $aliasText = is_array($override['aliases'])
                ? implode(' ', $override['aliases'])
                : (string) $override['aliases'];
            $data['search_keywords'] = $this->buildKeywords([
                $data['search_keywords'],
                $aliasText,
            ]);
        }

        $data['meta'] = array_merge($data['meta'] ?? [], [
            'override_applied' => true,
        ]);

        return $data;
    }

    protected function computePriorityScore(string $iata, string $type, ?string $isoCountry): int
    {
        $score = $this->priorityBoosts[$iata] ?? 0;
        $score += $this->typePriority[$type] ?? 0;

        if ($isoCountry !== null && in_array($isoCountry, $this->routeRegionCodes, true)) {
            $score += $this->regionBoost;
        }

        return $score;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function loadOverrides(): array
    {
        $merged = $this->configOverrides;

        $csvPath = (string) config('airports_import.overrides_csv');
        if (! File::isFile($csvPath)) {
            return $merged;
        }

        foreach ($this->readCsv($csvPath) as $row) {
            $iata = $this->normalizeIata($row['iata_code'] ?? $row['IATA'] ?? $row['iata'] ?? null);
            if ($iata === null) {
                continue;
            }

            $entry = [];
            foreach (['name', 'city', 'country', 'country_code', 'timezone', 'aliases'] as $field) {
                $value = $this->cleanValue($row[$field] ?? null);
                if ($value !== null) {
                    $entry[$field] = $value;
                }
            }

            if (isset($row['is_active']) && $row['is_active'] !== '') {
                $entry['is_active'] = in_array(mb_strtolower(trim((string) $row['is_active'])), ['1', 'true', 'yes', 'y'], true);
            }

            if (isset($row['priority_score']) && is_numeric($row['priority_score'])) {
                $entry['priority_score'] = (int) $row['priority_score'];
            }

            $merged[$iata] = array_merge($merged[$iata] ?? [], $entry);
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function isScheduledService(array $row): bool
    {
        return mb_strtolower(trim((string) ($row['scheduled_service'] ?? ''))) === 'yes';
    }

    protected function normalizeIata(mixed $value): ?string
    {
        $code = $this->normalizeCode($value);
        if ($code === null) {
            return null;
        }

        if (in_array($code, self::reservedInvalidIataCodes(), true)) {
            return null;
        }

        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            return null;
        }

        return $code;
    }

    protected function normalizeIcao(mixed $value): ?string
    {
        $code = $this->normalizeCode($value);
        if ($code === null) {
            return null;
        }

        if (! preg_match('/^[A-Z0-9]{3,4}$/', $code)) {
            return null;
        }

        return $code;
    }

    protected function normalizeCode(mixed $value): ?string
    {
        $value = $this->cleanValue($value);
        if ($value === null) {
            return null;
        }

        return Str::upper($value);
    }

    protected function cleanValue(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '\\N' || $v === '-' || $v === '---' || strcasecmp($v, 'null') === 0 || strcasecmp($v, 'n/a') === 0) {
            return null;
        }

        return $v;
    }

    protected function toFloat(mixed $value): ?float
    {
        $raw = $this->cleanValue($value);
        if ($raw === null || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    /**
     * @param  list<mixed>  $parts
     */
    protected function buildKeywords(array $parts): ?string
    {
        $tokens = collect($parts)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->flatMap(function (string $item): array {
                return preg_split('/\s*,\s*/', Str::lower(trim($item))) ?: [];
            })
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return null;
        }

        return implode(' ', $tokens);
    }

    /**
     * @return \Generator<int, array<string, string|null>>
     */
    protected function readCsv(string $path): \Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                return;
            }

            $headers = array_map(static fn ($h): string => trim((string) $h), $headers);

            while (($data = fgetcsv($handle)) !== false) {
                if ($data === [null] || $data === []) {
                    continue;
                }

                /** @var array<string, string|null> $row */
                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = $data[$index] ?? null;
                }

                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }
}
