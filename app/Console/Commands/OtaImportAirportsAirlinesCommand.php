<?php

namespace App\Console\Commands;

use App\Models\Airline;
use App\Models\Airport;
use App\Services\TravelData\AirlineCsvImportService;
use App\Services\TravelData\AirportImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OtaImportAirportsAirlinesCommand extends Command
{
    protected $signature = 'ota:import-airports-airlines {--path=} {--logos} {--airlines-only : Import airlines (and optional logos) without merging Kaggle airports.csv} {--dry-run : Preview airline import counts without DB writes}';

    protected $description = 'Import global airports/airlines dataset and optional airline logos into local DB';

    /** @var array<string, int> */
    protected array $priorityBoosts = [
        'LHE' => 250,
        'KHI' => 250,
        'ISB' => 250,
        'PEW' => 220,
        'SKT' => 220,
        'DXB' => 260,
        'SHJ' => 220,
        'AUH' => 220,
        'JED' => 240,
        'RUH' => 240,
        'MED' => 220,
        'DOH' => 220,
        'KWI' => 210,
        'BAH' => 210,
        'IST' => 210,
        'LHR' => 210,
        'MAN' => 180,
        'BHX' => 180,
        'MEL' => 180,
        'SYD' => 180,
        'YYZ' => 180,
        'YUL' => 180,
        'JFK' => 190,
        'ORD' => 180,
        'KUL' => 180,
        'BKK' => 180,
        'SIN' => 200,
    ];

    public function handle(): int
    {
        $basePath = (string) ($this->option('path') ?: storage_path('app/imports/kaggle/airports-global'));
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $logosEnabled = (bool) $this->option('logos');
        $logosPath = $basePath;

        $airportsCsv = $basePath.DIRECTORY_SEPARATOR.'airports.csv';
        $airlinesCsv = $basePath.DIRECTORY_SEPARATOR.'airlines.csv';
        $routesCsv = $basePath.DIRECTORY_SEPARATOR.'routes.csv';

        $csvAvailable = File::exists($airportsCsv) && File::exists($airlinesCsv);
        $airlinesOnly = (bool) $this->option('airlines-only');
        $dryRun = (bool) $this->option('dry-run');
        if (! File::exists($airlinesCsv) && ! $logosEnabled) {
            $this->error('Missing airlines.csv in: '.$basePath);

            return self::FAILURE;
        }
        if (! $airlinesOnly && ! $csvAvailable && ! $logosEnabled) {
            $this->error('Missing required files. Expected airports.csv and airlines.csv in: '.$basePath);

            return self::FAILURE;
        }

        $airportStats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'route_connected' => 0];
        $airlineStats = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        if ($csvAvailable && ! $airlinesOnly) {
            $routeFrequency = File::exists($routesCsv) ? $this->buildRouteFrequency($routesCsv) : [];
            $airportStats = $this->importAirports($airportsCsv, $routeFrequency);
        }
        if (File::exists($airlinesCsv)) {
            if ($dryRun) {
                $analysis = app(AirlineCsvImportService::class)->analyze($airlinesCsv);
                $this->newLine();
                $this->info('Airline import dry-run');
                $this->line('db_write_attempted=false');
                foreach (['source_metrics', 'target_metrics', 'database_accounting'] as $section) {
                    $this->line('['.$section.']');
                    $block = $analysis[$section] ?? [];
                foreach ($block as $key => $value) {
                    if (in_array($key, ['duplicate_target_to_db_collisions', 'one_target_matching_multiple_db_rows', 'insert_targets', 'reconciliation'], true)) {
                        continue;
                    }
                    $this->line('  '.$key.'='.(is_array($value) ? json_encode($value) : $value));
                }
            }
            $tm = $analysis['target_metrics'] ?? [];
            $this->line('[target_metrics_skipped]');
            $this->line('  skipped_ambiguous='.($tm['skipped_ambiguous'] ?? 0));
            $this->line('  skipped_obsolete='.($tm['skipped_obsolete'] ?? 0));
            $this->line('  conflicts='.($tm['conflicts'] ?? 0));
                $collisions = $analysis['database_accounting']['duplicate_target_to_db_collisions'] ?? [];
                if ($collisions !== []) {
                    $this->line('[duplicate_target_to_db_collisions]');
                    foreach ($collisions as $row) {
                        $this->line('  '.json_encode($row));
                    }
                }

                return self::SUCCESS;
            }
            $airlineStats = app(AirlineCsvImportService::class)->import($airlinesCsv, false);
        } elseif ($logosEnabled) {
            $this->warn('CSV files not found. Running in logo-only mode.');
        }

        $logoStats = [
            'found' => 0,
            'copied' => 0,
            'matched' => 0,
            'skipped' => 0,
        ];
        if ($logosEnabled) {
            $logoStats = $this->importLogos($logosPath);
        }

        $this->newLine();
        $this->info('Import summary');
        $this->line('Airports imported: '.$airportStats['imported']);
        $this->line('Airports updated: '.$airportStats['updated']);
        $this->line('Airports skipped: '.$airportStats['skipped']);
        $this->line('Route-connected airports: '.$airportStats['route_connected']);
        $this->line('Airlines imported: '.($airlineStats['imported'] ?? 0));
        $this->line('Airlines updated: '.($airlineStats['updated'] ?? 0));
        $this->line('Airlines unchanged: '.($airlineStats['unchanged'] ?? 0));
        $this->line('Airlines skipped: '.($airlineStats['skipped'] ?? 0));
        $this->line('Logos found: '.$logoStats['found']);
        $this->line('Logos copied: '.$logoStats['copied']);
        $this->line('Logos matched: '.$logoStats['matched']);
        $this->line('Logos skipped: '.$logoStats['skipped']);

        return self::SUCCESS;
    }

    /**
     * @return array{imported:int,updated:int,skipped:int,route_connected:int}
     */
    protected function importAirports(string $csvPath, array $routeFrequency): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'route_connected' => 0];
        foreach ($this->readCsv($csvPath) as $row) {
            $name = $this->cleanValue($row['Name'] ?? null);
            if ($name === null) {
                $stats['skipped']++;

                continue;
            }

            $iata = $this->normalizeIata($row['IATA'] ?? null);
            $icao = $this->normalizeIcao($row['ICAO'] ?? null);
            $city = $this->cleanValue($row['City'] ?? null);
            $country = $this->cleanValue($row['Country'] ?? null);
            $timezone = $this->cleanValue($row['Timezone'] ?? null);
            $latitude = $this->toFloat($row['Latitude'] ?? null);
            $longitude = $this->toFloat($row['Longitude'] ?? null);

            $routeCount = $iata !== null ? (int) ($routeFrequency[$iata] ?? 0) : 0;
            $hasRoutes = $routeCount > 0;
            $priority = ($iata !== null ? ($this->priorityBoosts[$iata] ?? 0) : 0) + $routeCount;
            $isCommercial = $hasRoutes || ($iata !== null && isset($this->priorityBoosts[$iata]));
            $keywords = $this->buildKeywords([$iata, $icao, $name, $city, $country]);

            $airport = $this->findAirport($iata, $icao, $name, $city, $country);
            $isNew = ! $airport->exists;

            $airport->fill([
                'iata_code' => $iata,
                'icao_code' => $icao,
                'name' => $name,
                'city' => $city,
                'country' => $country,
                'country_code' => null,
                'airport_type' => null,
                'timezone' => $timezone,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'priority_score' => (int) $priority,
                'has_routes' => $hasRoutes,
                'route_count' => $routeCount,
                'is_commercial' => $isCommercial,
                'is_active' => true,
                'search_keywords' => $keywords,
                'meta' => [
                    'source' => 'kaggle-airports-global',
                ],
            ]);
            $airport->save();

            if ($isNew) {
                $stats['imported']++;
            } else {
                $stats['updated']++;
            }
            if ($hasRoutes) {
                $stats['route_connected']++;
            }
        }

        $this->ensurePriorityAirportsCommercial();

        return $stats;
    }

    /**
     * @return array{imported:int,updated:int,skipped:int}
     */
    protected function importAirlines(string $csvPath): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        foreach ($this->readCsv($csvPath) as $row) {
            $name = $this->cleanValue($row['Name'] ?? null);
            if ($name === null) {
                $stats['skipped']++;

                continue;
            }

            $iata = $this->normalizeCode($row['IATA'] ?? null);
            $icao = $this->normalizeCode($row['ICAO'] ?? null);
            $country = $this->cleanValue($row['Country'] ?? null);
            $active = strtoupper((string) ($row['Active'] ?? 'Y')) === 'Y';
            $keywords = $this->buildKeywords([$iata, $icao, $name, $country]);

            $airline = $this->findAirline($iata, $icao, $name);
            $isNew = ! $airline->exists;

            $airline->fill([
                'iata_code' => $iata,
                'icao_code' => $icao,
                'name' => $name,
                'country' => $country,
                'is_active' => $active,
                'search_keywords' => $keywords,
                'meta' => [
                    'source' => 'kaggle-airports-global',
                    'callsign' => $this->cleanValue($row['Callsign'] ?? null),
                ],
            ]);
            $airline->save();

            if ($isNew) {
                $stats['imported']++;
            } else {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    /**
     * @return array{found:int,copied:int,matched:int,skipped:int}
     */
    protected function importLogos(string $logosPath): array
    {
        $stats = ['found' => 0, 'copied' => 0, 'matched' => 0, 'skipped' => 0];
        if (! File::isDirectory($logosPath)) {
            $this->warn('Logo directory not found: '.$logosPath);

            return $stats;
        }

        $imageFiles = collect(File::allFiles($logosPath))
            ->filter(fn (\SplFileInfo $f): bool => in_array(strtolower($f->getExtension()), ['png', 'jpg', 'jpeg', 'svg', 'webp'], true))
            ->values();
        $stats['found'] = $imageFiles->count();
        if ($imageFiles->isEmpty()) {
            $this->warn('No logo image files found. Skipping logo copy/match.');

            return $stats;
        }

        foreach ($imageFiles as $file) {
            $basename = $file->getBasename();
            $stemRaw = pathinfo($basename, PATHINFO_FILENAME);
            $stemUpper = Str::upper($stemRaw);
            $stemSlug = $this->normalizedSlug($stemRaw);

            $airline = $this->findAirlineByLogoFilename($stemUpper, $stemSlug);

            if ($airline === null) {
                $stats['skipped']++;

                continue;
            }

            $targetRel = 'travel-assets/airlines/logos/'.$basename;
            Storage::disk('public')->put($targetRel, File::get($file->getPathname()));
            $airline->forceFill(['logo_path' => $targetRel])->save();

            $stats['copied']++;
            $stats['matched']++;
        }

        return $stats;
    }

    protected function findAirlineByLogoFilename(string $stemUpper, string $stemSlug): ?Airline
    {
        // 1) Exact ICAO match first
        $byIcao = Airline::query()
            ->whereRaw('UPPER(COALESCE(icao_code, "")) = ?', [$stemUpper])
            ->first();
        if ($byIcao !== null) {
            return $byIcao;
        }

        // 2) Exact IATA match
        $byIata = Airline::query()
            ->whereRaw('UPPER(COALESCE(iata_code, "")) = ?', [$stemUpper])
            ->first();
        if ($byIata !== null) {
            return $byIata;
        }

        // 3) Normalized airline name slug contains/equals
        if ($stemSlug === '') {
            return null;
        }

        /** @var Airline|null $byName */
        $byName = Airline::query()
            ->whereRaw(
                'LOWER(REPLACE(REPLACE(REPLACE(COALESCE(name, ""), "-", ""), "_", ""), " ", "")) = ?',
                [$stemSlug]
            )
            ->first();
        if ($byName !== null) {
            return $byName;
        }

        foreach (Airline::query()->select(['id', 'name'])->cursor() as $candidate) {
            $candidateSlug = $this->normalizedSlug((string) $candidate->name);
            if ($candidateSlug === '') {
                continue;
            }
            if (str_contains($candidateSlug, $stemSlug) || str_contains($stemSlug, $candidateSlug)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    protected function buildRouteFrequency(string $routesCsv): array
    {
        $freq = [];
        foreach ($this->readCsv($routesCsv) as $row) {
            $source = $this->normalizeIata($row['Source Airport'] ?? null);
            $destination = $this->normalizeIata($row['Destination Airport'] ?? null);
            if ($source !== null) {
                $freq[$source] = ($freq[$source] ?? 0) + 1;
            }
            if ($destination !== null) {
                $freq[$destination] = ($freq[$destination] ?? 0) + 1;
            }
        }

        return $freq;
    }

    protected function findAirport(?string $iata, ?string $icao, string $name, ?string $city, ?string $country): Airport
    {
        if ($iata !== null) {
            $byIata = Airport::query()->where('iata_code', $iata)->first();
            if ($byIata !== null) {
                return $byIata;
            }
        }
        if ($icao !== null) {
            $byIcao = Airport::query()->where('icao_code', $icao)->first();
            if ($byIcao !== null) {
                return $byIcao;
            }
        }

        $byName = Airport::query()
            ->where('name', $name)
            ->where('city', $city)
            ->where('country', $country)
            ->first();

        return $byName ?? new Airport;
    }

    protected function findAirline(?string $iata, ?string $icao, string $name): Airline
    {
        if ($iata !== null) {
            $byIata = Airline::query()->where('iata_code', $iata)->first();
            if ($byIata !== null) {
                return $byIata;
            }
        }
        if ($icao !== null) {
            $byIcao = Airline::query()->where('icao_code', $icao)->first();
            if ($byIcao !== null) {
                return $byIcao;
            }
        }

        $byName = Airline::query()->where('name', $name)->first();

        return $byName ?? new Airline;
    }

    protected function normalizeCode(mixed $value): ?string
    {
        $value = $this->cleanValue($value);
        if ($value === null) {
            return null;
        }

        return Str::upper($value);
    }

    protected function normalizeIata(mixed $value): ?string
    {
        $code = $this->normalizeCode($value);
        if ($code === null) {
            return null;
        }

        if (in_array($code, AirportImportService::reservedInvalidIataCodes(), true)) {
            return null;
        }

        if (! preg_match('/^[A-Z0-9]{3}$/', $code)) {
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

    protected function cleanValue(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '\\N' || $v === '-' || $v === '---' || strcasecmp($v, 'null') === 0 || strcasecmp($v, 'n/a') === 0) {
            return null;
        }

        return $v;
    }

    protected function ensurePriorityAirportsCommercial(): void
    {
        $priorityCodes = array_keys($this->priorityBoosts);
        Airport::query()
            ->whereIn('iata_code', $priorityCodes)
            ->update(['is_commercial' => true]);
    }

    protected function toFloat(mixed $value): ?float
    {
        $raw = $this->cleanValue($value);
        if ($raw === null || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    protected function buildKeywords(array $parts): ?string
    {
        $tokens = collect($parts)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => Str::lower(trim($item)))
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return null;
        }

        return implode(' ', $tokens);
    }

    protected function normalizedSlug(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->trim()
            ->toString();
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
