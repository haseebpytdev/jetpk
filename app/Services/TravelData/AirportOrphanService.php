<?php

namespace App\Services\TravelData;

use App\Models\Airport;

/**
 * Read-only orphan detection for airports without a valid stored IATA code.
 */
final class AirportOrphanService
{
    /**
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $orphans = $this->orphanQuery()->orderBy('id')->get();

        return [
            'orphan_count' => $orphans->count(),
            'orphans' => $orphans->map(static fn (Airport $airport): array => [
                'id' => $airport->id,
                'iata_code' => $airport->iata_code,
                'icao_code' => $airport->icao_code,
                'name' => $airport->name,
                'city' => $airport->city,
                'country' => $airport->country,
                'is_active' => $airport->is_active,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{db_write_attempted: bool, deleted: int, candidates: list<array<string, mixed>>}
     */
    public function cleanup(bool $dryRun = false): array
    {
        $orphans = $this->orphanQuery()->orderBy('id')->get();
        $candidates = $orphans->map(static fn (Airport $airport): array => [
            'id' => $airport->id,
            'iata_code' => $airport->iata_code,
            'icao_code' => $airport->icao_code,
            'name' => $airport->name,
        ])->values()->all();

        $deleted = 0;
        if (! $dryRun && $orphans->isNotEmpty()) {
            $deleted = $this->orphanQuery()->delete();
        }

        return [
            'db_write_attempted' => ! $dryRun,
            'deleted' => $dryRun ? 0 : $deleted,
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
        ];
    }

    private function orphanQuery()
    {
        return Airport::query()->where(function ($q): void {
            $q->whereNull('iata_code')
                ->orWhereRaw('LENGTH(TRIM(COALESCE(iata_code, ""))) < 3')
                ->orWhereRaw('UPPER(TRIM(iata_code)) IN ("-", "---", "N/A", "NUL", "NULL", "\\N")');
        });
    }
}
