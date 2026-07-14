<?php

namespace App\Services\TravelData;

use App\Models\Airline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Targeted sync of configured JetPK canonical airline overrides into the database.
 */
final class AirlineCanonicalSyncService
{
    public function __construct(
        private readonly AirlineCanonicalResolver $canonical,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(): array
    {
        $entries = [];
        $updateCount = 0;
        $insertCount = 0;
        $unchangedCount = 0;
        $conflictCount = 0;

        foreach ($this->configuredOverrides() as $iata => $override) {
            $entry = $this->planEntry($iata, $override);
            $entries[] = $entry;

            match ($entry['action']) {
                'update' => $updateCount++,
                'insert' => $insertCount++,
                'unchanged' => $unchangedCount++,
                'conflict' => $conflictCount++,
                default => null,
            };
        }

        return [
            'db_write_attempted' => false,
            'entries' => $entries,
            'update_count' => $updateCount,
            'insert_count' => $insertCount,
            'unchanged_count' => $unchangedCount,
            'conflict_count' => $conflictCount,
            'has_conflicts' => $conflictCount > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(): array
    {
        $plan = $this->plan();
        if ($plan['has_conflicts']) {
            return array_merge($plan, [
                'applied' => false,
                'reason' => 'duplicate_iata_conflict',
                'db_write_attempted' => false,
            ]);
        }

        if ($plan['update_count'] === 0 && $plan['insert_count'] === 0) {
            return array_merge($plan, [
                'applied' => true,
                'db_write_attempted' => false,
            ]);
        }

        DB::transaction(function () use ($plan): void {
            foreach ($plan['entries'] as $entry) {
                if ($entry['action'] === 'conflict') {
                    throw new \RuntimeException('Canonical sync blocked by duplicate IATA rows.');
                }

                $payload = $entry['desired_payload'];
                if ($entry['action'] === 'insert') {
                    Airline::query()->create($payload);

                    continue;
                }

                if ($entry['action'] === 'update') {
                    $airline = Airline::query()->find($entry['current_db_id']);
                    if ($airline === null) {
                        throw new \RuntimeException('Canonical sync target row missing: '.$entry['iata']);
                    }
                    $this->applyPayload($airline, $payload);
                }
            }
        });

        return array_merge($plan, [
            'applied' => true,
            'db_write_attempted' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function planEntry(string $iata, array $override): array
    {
        $payload = $this->canonical->payloadFromOverride($override);
        $matches = $this->findRowsForIata($iata);

        $current = $matches->first();
        $action = 'unchanged';
        $conflictDbIds = [];

        if ($matches->count() > 1) {
            $action = 'conflict';
            $conflictDbIds = $matches->pluck('id')->values()->all();
        } elseif ($current === null) {
            $action = 'insert';
        } elseif ($this->needsSync($current, $payload)) {
            $action = 'update';
        }

        return [
            'iata' => $iata,
            'current_db_id' => $current?->id,
            'current_name' => $current?->name,
            'desired_name' => $payload['name'] ?? null,
            'current_icao' => $current?->icao_code,
            'desired_icao' => $payload['icao_code'] ?? null,
            'current_country' => $current?->country,
            'desired_country' => $payload['country'] ?? null,
            'current_is_active' => $current?->is_active,
            'desired_is_active' => $payload['is_active'] ?? null,
            'action' => $action,
            'conflict_db_ids' => $conflictDbIds,
            'desired_payload' => $payload,
        ];
    }

    /**
     * @return Collection<int, Airline>
     */
    private function findRowsForIata(string $iata): Collection
    {
        return Airline::query()
            ->whereRaw('UPPER(COALESCE(iata_code, "")) = ?', [Str::upper(trim($iata))])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function needsSync(Airline $existing, array $payload): bool
    {
        foreach (['iata_code', 'icao_code', 'name', 'country', 'is_active', 'search_keywords'] as $field) {
            $left = $existing->{$field};
            $right = $payload[$field] ?? null;
            if (is_bool($left) || is_bool($right)) {
                if ((bool) $left !== (bool) $right) {
                    return true;
                }
            } elseif ((string) $left !== (string) $right) {
                return true;
            }
        }

        $existingMeta = is_array($existing->meta) ? $existing->meta : [];
        $desiredMeta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        if ((bool) ($existingMeta['canonical_override'] ?? false) !== (bool) ($desiredMeta['canonical_override'] ?? false)) {
            return true;
        }
        if ((string) ($existingMeta['source'] ?? '') !== (string) ($desiredMeta['source'] ?? '')) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyPayload(Airline $airline, array $payload): void
    {
        $sync = [
            'iata_code' => $payload['iata_code'] ?? null,
            'icao_code' => $payload['icao_code'] ?? null,
            'name' => $payload['name'] ?? null,
            'country' => $payload['country'] ?? null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'search_keywords' => $payload['search_keywords'] ?? null,
            'meta' => $payload['meta'] ?? null,
        ];

        if (($payload['logo_path'] ?? null) !== null) {
            $sync['logo_path'] = $payload['logo_path'];
        }

        $airline->fill($sync);
        $airline->save();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function configuredOverrides(): array
    {
        $overrides = config('airline_canonical_overrides.overrides', []);

        return is_array($overrides) ? $overrides : [];
    }
}
