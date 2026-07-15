<?php

namespace App\Services\TravelData;

use App\Models\Airline;
use Illuminate\Support\Str;

/**
 * Canonical-aware Kaggle airlines.csv analysis and idempotent import.
 */
final class AirlineCsvImportService
{
    public function __construct(
        private readonly AirlineCanonicalResolver $canonical,
        private readonly AirlineImportAccountingService $accounting,
    ) {}

    /**
     * @return \Generator<int, array<string, string>>
     */
    public function readCsv(string $path): \Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        $headers = null;
        try {
            while (($data = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers = array_map(static fn ($h): string => trim((string) $h), $data);

                    continue;
                }
                if ($headers === []) {
                    continue;
                }
                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = $data[$index] ?? '';
                }
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $csvPath): array
    {
        $plan = $this->buildImportPlan($csvPath);
        $targets = $plan['targets'];

        return [
            'source_path' => $csvPath,
            'db_write_attempted' => false,
            'source_metrics' => $plan['source_metrics'],
            'target_metrics' => $plan['target_metrics'],
            'database_accounting' => $plan['database_accounting'],
            'duplicate_iata_groups' => $plan['duplicate_iata_groups'],
        ];
    }

    /**
     * @return array{imported:int,updated:int,skipped:int,unchanged:int,db_write_attempted:bool}
     */
    public function import(string $csvPath, bool $dryRun = false): array
    {
        $plan = $this->buildImportPlan($csvPath);
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'unchanged' => 0,
            'db_write_attempted' => false,
        ];

        foreach ($plan['targets'] as $target) {
            $payload = $target['payload'];
            if (($payload['iata_code'] ?? null) === null && ($payload['icao_code'] ?? null) === null) {
                $stats['skipped']++;

                continue;
            }

            $existing = $this->findExistingForPayload($payload);
            if ($existing === null) {
                $stats['imported']++;
                if (! $dryRun) {
                    $stats['db_write_attempted'] = true;
                    Airline::query()->create($payload);
                }

                continue;
            }

            if ($this->isCanonicalProtected($existing) && ! ($target['override_resolved'] ?? false)) {
                $stats['skipped']++;

                continue;
            }

            if ($this->needsUpdate($existing, $payload)) {
                $stats['updated']++;
                if (! $dryRun) {
                    $stats['db_write_attempted'] = true;
                    $existing->fill($payload);
                    $existing->save();
                }
            } else {
                $stats['unchanged']++;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildImportPlan(string $csvPath): array
    {
        $physicalRows = 0;
        $parsedRows = 0;
        $blankNames = 0;
        $malformedRows = 0;
        $noIdentityRows = 0;
        $inactiveRows = 0;
        $iataGroups = [];
        $icaoGroups = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $physicalRows++;
            $name = $this->cleanValue($row['Name'] ?? null);
            if ($name === null) {
                $blankNames++;

                continue;
            }

            $parsedRows++;
            $iata = $this->normalizeCode($row['IATA'] ?? null);
            $icao = $this->normalizeCode($row['ICAO'] ?? null);
            $active = strtoupper((string) ($row['Active'] ?? 'Y')) === 'Y';
            if (! $active) {
                $inactiveRows++;
            }

            if ($iata !== null && ! preg_match('/^[A-Z0-9]{2,3}$/', $iata)) {
                $malformedRows++;
            }
            if ($icao !== null && ! preg_match('/^[A-Z0-9]{3,4}$/', $icao)) {
                $malformedRows++;
            }

            if ($iata === null && $icao === null) {
                $noIdentityRows++;

                continue;
            }

            $normalizedRow = $row;
            $normalizedRow['_active'] = $active;
            $normalizedRow['_iata'] = $iata;
            $normalizedRow['_icao'] = $icao;

            if ($iata !== null) {
                $iataGroups[$iata] ??= [];
                $iataGroups[$iata][] = $normalizedRow;
            } elseif ($icao !== null) {
                $icaoGroups[$icao] ??= [];
                $icaoGroups[$icao][] = $normalizedRow;
            }
        }

        $duplicateIataGroups = [];
        foreach ($iataGroups as $iata => $rows) {
            if (count($rows) > 1) {
                $duplicateIataGroups[$iata] = array_map(fn (array $r): array => $this->candidateSummary($r), $rows);
            }
        }

        $targets = [];
        $metrics = [
            'inserts' => 0,
            'updates' => 0,
            'unchanged' => 0,
            'skipped_ambiguous' => 0,
            'skipped_obsolete' => 0,
            'override_resolved' => 0,
            'conflicts' => 0,
        ];

        foreach ($iataGroups as $iata => $rows) {
            $override = $this->canonical->overrideForIata($iata);
            $pick = $this->canonical->pickSourceRow($iata, $rows);
            if ($pick['row'] === null) {
                $metrics['skipped_ambiguous']++;

                continue;
            }

            $metrics['skipped_obsolete'] += max(0, count($rows) - 1);
            $payload = $override !== null
                ? $this->canonical->payloadFromOverride($override)
                : $this->payloadFromSourceRow($pick['row']);

            if ($override !== null) {
                $metrics['override_resolved']++;
            }

            $targetKey = 'iata:'.$iata;
            $targets[$targetKey] = [
                'key' => $targetKey,
                'payload' => $payload,
                'override_resolved' => $override !== null,
                'resolution_reason' => $pick['reason'],
            ];
        }

        foreach ($icaoGroups as $icao => $rows) {
            if (count($rows) > 1) {
                $metrics['skipped_ambiguous']++;

                continue;
            }
            $row = $rows[0];
            $payload = $this->payloadFromSourceRow($row);
            $iata = $payload['iata_code'] ?? null;
            $key = $iata !== null ? 'iata:'.$iata : 'icao:'.$icao;
            if (isset($targets[$key])) {
                $metrics['conflicts']++;

                continue;
            }
            $targets[$key] = [
                'key' => $key,
                'payload' => $payload,
                'override_resolved' => false,
                'resolution_reason' => 'icao_only',
            ];
        }

        foreach ($targets as $target) {
            $payload = $target['payload'];
            $existing = $this->findExistingForPayload($payload);
            if ($existing === null) {
                $metrics['inserts']++;
            } elseif ($this->needsUpdate($existing, $payload)) {
                $metrics['updates']++;
            } else {
                $metrics['unchanged']++;
            }
        }

        $databaseAccounting = $this->accounting->account($targets);

        return [
            'source_metrics' => [
                'physical_rows' => $physicalRows,
                'parsed_rows' => $parsedRows,
                'duplicate_iata_groups' => count($duplicateIataGroups),
                'duplicate_icao_groups' => count(array_filter($icaoGroups, static fn (array $g): bool => count($g) > 1)),
                'malformed_rows' => $malformedRows,
                'historical_inactive_rows' => $inactiveRows,
                'rows_with_no_usable_identity' => $noIdentityRows,
                'blank_names' => $blankNames,
            ],
            'target_metrics' => array_merge($metrics, [
                'unique_canonical_targets' => count($targets),
                'skipped_ambiguous' => $metrics['skipped_ambiguous'],
                'skipped_obsolete' => $metrics['skipped_obsolete'],
                'override_resolved' => $metrics['override_resolved'],
                'conflicts' => $metrics['conflicts'],
            ]),
            'database_accounting' => $databaseAccounting,
            'duplicate_iata_groups' => $duplicateIataGroups,
            'targets' => $targets,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function payloadFromSourceRow(array $row): array
    {
        $iata = $this->normalizeCode($row['IATA'] ?? null);
        $icao = $this->normalizeCode($row['ICAO'] ?? null);
        $name = (string) $this->cleanValue($row['Name'] ?? null);
        $country = $this->cleanValue($row['Country'] ?? null);
        $active = strtoupper((string) ($row['Active'] ?? 'Y')) === 'Y';

        return [
            'iata_code' => $iata,
            'icao_code' => $icao,
            'name' => $name,
            'country' => $country,
            'is_active' => $active,
            'search_keywords' => $this->buildKeywords([$iata, $icao, $name, $country]),
            'meta' => [
                'source' => 'kaggle-airports-global',
                'callsign' => $this->cleanValue($row['Callsign'] ?? null),
                'canonical_override' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function candidateSummary(array $row): array
    {
        return [
            'name' => $this->cleanValue($row['Name'] ?? null),
            'iata' => $this->normalizeCode($row['IATA'] ?? null),
            'icao' => $this->normalizeCode($row['ICAO'] ?? null),
            'country' => $this->cleanValue($row['Country'] ?? null),
            'active' => ($row['_active'] ?? false) === true,
        ];
    }

    private function isCanonicalProtected(Airline $airline): bool
    {
        if ((bool) data_get($airline->meta, 'canonical_override', false)) {
            return true;
        }

        $iata = Str::upper(trim((string) ($airline->iata_code ?? '')));

        return $iata !== '' && $this->canonical->isCanonicalOverride($iata);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findExistingForPayload(array $payload): ?Airline
    {
        $iata = $payload['iata_code'] ?? null;
        if ($iata !== null && trim((string) $iata) !== '') {
            return Airline::query()->whereRaw('UPPER(COALESCE(iata_code, "")) = ?', [Str::upper((string) $iata)])->first();
        }

        $icao = $payload['icao_code'] ?? null;
        if ($icao !== null && trim((string) $icao) !== '') {
            return Airline::query()->whereRaw('UPPER(COALESCE(icao_code, "")) = ?', [Str::upper((string) $icao)])->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function needsUpdate(Airline $existing, array $payload): bool
    {
        foreach (['iata_code', 'icao_code', 'name', 'country', 'is_active', 'search_keywords'] as $field) {
            $left = $existing->{$field};
            $right = $payload[$field] ?? null;
            if (is_bool($left)) {
                if ((bool) $left !== (bool) $right) {
                    return true;
                }
            } elseif ((string) $left !== (string) $right) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function buildKeywords(array $parts): string
    {
        return collect($parts)
            ->filter(static fn ($part): bool => $part !== null && trim((string) $part) !== '')
            ->map(static fn ($part): string => mb_strtolower(trim((string) $part)))
            ->unique()
            ->implode(' ');
    }

    private function normalizeCode(mixed $value): ?string
    {
        $value = $this->cleanValue($value);
        if ($value === null) {
            return null;
        }

        return Str::upper($value);
    }

    private function cleanValue(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '\\N' || $v === '-' || strcasecmp($v, 'null') === 0) {
            return null;
        }

        return $v;
    }
}
