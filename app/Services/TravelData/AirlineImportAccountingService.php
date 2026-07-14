<?php

namespace App\Services\TravelData;

use App\Models\Airline;
use Illuminate\Support\Str;

/**
 * Database-aware accounting for canonical airline import dry-runs.
 */
final class AirlineImportAccountingService
{
    public function __construct(
        private readonly AirlineCanonicalResolver $canonical,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $targets
     * @return array<string, mixed>
     */
    public function account(array $targets): array
    {
        $currentDbRows = Airline::query()->count();

        $insertTargets = [];
        $updateDbIds = [];
        $unchangedDbIds = [];
        $duplicateTargetToDb = [];
        $oneTargetMultipleDb = [];
        $dbIdHitCount = [];

        foreach ($targets as $targetKey => $target) {
            $payload = $target['payload'] ?? [];
            $matches = $this->findAllMatchingRows($payload);

            if ($matches->isEmpty()) {
                $insertTargets[] = [
                    'target_key' => $targetKey,
                    'iata' => $payload['iata_code'] ?? null,
                    'icao' => $payload['icao_code'] ?? null,
                    'name' => $payload['name'] ?? null,
                ];

                continue;
            }

            if (count($matches) > 1) {
                $oneTargetMultipleDb[] = [
                    'target_key' => $targetKey,
                    'matched_db_ids' => $matches->pluck('id')->values()->all(),
                    'matched_iata' => $matches->pluck('iata_code')->values()->all(),
                    'matched_icao' => $matches->pluck('icao_code')->values()->all(),
                ];
            }

            $primary = $matches->first();
            $dbId = (int) $primary->id;
            $dbIdHitCount[$dbId] = ($dbIdHitCount[$dbId] ?? 0) + 1;

            if ($dbIdHitCount[$dbId] > 1) {
                $duplicateTargetToDb[] = [
                    'target_key' => $targetKey,
                    'db_id' => $dbId,
                    'db_iata' => $primary->iata_code,
                    'db_icao' => $primary->icao_code,
                    'db_name' => $primary->name,
                    'hit_count_for_db_id' => $dbIdHitCount[$dbId],
                ];
            }

            if ($this->needsUpdate($primary, $payload)) {
                $updateDbIds[$dbId] = true;
            } else {
                $unchangedDbIds[$dbId] = true;
            }
        }

        foreach ($updateDbIds as $dbId => $_) {
            unset($unchangedDbIds[$dbId]);
        }

        $inserts = count($insertTargets);
        $updates = count($updateDbIds);
        $unchanged = count($unchangedDbIds);
        $uniqueMatchedDbIds = $updates + $unchanged;
        $uniqueTargets = count($targets);
        $expectedPostImport = $currentDbRows + $inserts;

        $targetOnlyNotInDb = $uniqueTargets - $uniqueMatchedDbIds - $inserts;
        // Targets that match an existing DB row already claimed by another target.
        $sharedDbCollisionTargets = count($duplicateTargetToDb);

        return [
            'current_database_rows' => $currentDbRows,
            'unique_normalized_source_targets' => $uniqueTargets,
            'unique_matched_db_row_ids' => $uniqueMatchedDbIds,
            'duplicate_target_to_db_matches' => count($duplicateTargetToDb),
            'duplicate_target_to_db_collisions' => $duplicateTargetToDb,
            'one_target_matching_multiple_db_rows' => $oneTargetMultipleDb,
            'inserts' => $inserts,
            'updates' => $updates,
            'unchanged' => $unchanged,
            'insert_targets' => $insertTargets,
            'expected_post_import_database_rows' => $expectedPostImport,
            'reconciliation' => [
                'unique_matched_db_ids_equals_updates_plus_unchanged' => $uniqueMatchedDbIds === ($updates + $unchanged),
                'expected_post_import_formula' => $currentDbRows.' + '.$inserts.' = '.$expectedPostImport,
                'targets_vs_db_rows_delta' => $uniqueTargets - $currentDbRows,
                'shared_db_collision_targets' => $sharedDbCollisionTargets,
                'insert_targets' => $inserts,
                'explanation' => $this->buildDeltaExplanation(
                    $uniqueTargets,
                    $currentDbRows,
                    $inserts,
                    $sharedDbCollisionTargets,
                    $uniqueMatchedDbIds,
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Support\Collection<int, Airline>
     */
    private function findAllMatchingRows(array $payload): \Illuminate\Support\Collection
    {
        $ids = [];
        $matches = collect();

        $iata = isset($payload['iata_code']) ? Str::upper(trim((string) $payload['iata_code'])) : '';
        if ($iata !== '') {
            foreach (Airline::query()->whereRaw('UPPER(COALESCE(iata_code, "")) = ?', [$iata])->get() as $row) {
                $ids[$row->id] = $row;
            }
        }

        $icao = isset($payload['icao_code']) ? Str::upper(trim((string) $payload['icao_code'])) : '';
        if ($icao !== '') {
            foreach (Airline::query()->whereRaw('UPPER(COALESCE(icao_code, "")) = ?', [$icao])->get() as $row) {
                $ids[$row->id] = $row;
            }
        }

        return collect(array_values($ids));
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

    private function buildDeltaExplanation(
        int $uniqueTargets,
        int $currentDbRows,
        int $inserts,
        int $sharedCollisions,
        int $uniqueMatchedDbIds,
    ): string {
        $delta = $uniqueTargets - $currentDbRows;

        return 'unique_normalized_source_targets ('.$uniqueTargets.') minus current_database_rows ('.$currentDbRows.') = '
            .$delta.'. Of those '.$delta.' excess targets: '.$inserts.' are insert_targets (no DB row yet), and '
            .$sharedCollisions.' additional targets resolve to a DB row ID already matched by another target (duplicate target-to-DB collision). '
            .'unique_matched_db_row_ids ('.$uniqueMatchedDbIds.') counts each DB row at most once; '
            .'expected_post_import_database_rows = current_database_rows + inserts = '.($currentDbRows + $inserts).'.';
    }
}
