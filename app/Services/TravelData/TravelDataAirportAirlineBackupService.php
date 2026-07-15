<?php

namespace App\Services\TravelData;

use App\Models\Airline;
use App\Models\Airport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full-fidelity airports/airlines JSON backup and authoritative restore.
 */
final class TravelDataAirportAirlineBackupService
{
    /**
     * @return array<string, mixed>
     */
    public function export(string $path): array
    {
        $airportColumns = Schema::getColumnListing('airports');
        $airlineColumns = Schema::getColumnListing('airlines');

        $airports = Airport::query()->orderBy('id')->get();
        $airlines = Airline::query()->orderBy('id')->get();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'schema' => [
                'airports' => $airportColumns,
                'airlines' => $airlineColumns,
            ],
            'airport_ids' => $airports->pluck('id')->values()->all(),
            'airline_ids' => $airlines->pluck('id')->values()->all(),
            'airports' => $airports->toArray(),
            'airlines' => $airlines->toArray(),
            'counts' => [
                'airports' => $airports->count(),
                'airlines' => $airlines->count(),
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode travel-data backup JSON.');
        }

        $payload['sha256'] = hash('sha256', $json);
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode travel-data backup JSON with checksum.');
        }

        file_put_contents($path, $encoded);

        return [
            'path' => $path,
            'sha256' => $payload['sha256'],
            'exported_at' => $payload['exported_at'],
            'airport_rows' => $payload['counts']['airports'],
            'airline_rows' => $payload['counts']['airlines'],
            'airport_ids' => $payload['airport_ids'],
            'airline_ids' => $payload['airline_ids'],
            'schema' => $payload['schema'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(string $path, bool $dryRun = false, bool $authoritative = false): array
    {
        $payload = $this->readPayload($path);

        $stats = [
            'dry_run' => $dryRun,
            'authoritative' => $authoritative,
            'db_write_attempted' => ! $dryRun,
            'airports' => ['insert' => 0, 'update' => 0, 'delete' => 0, 'skip' => 0, 'conflict' => 0],
            'airlines' => ['insert' => 0, 'update' => 0, 'delete' => 0, 'skip' => 0, 'conflict' => 0],
            'backup_sha256' => $payload['sha256'] ?? null,
            'exported_at' => $payload['exported_at'] ?? null,
        ];

        $airportRows = is_array($payload['airports'] ?? null) ? $payload['airports'] : [];
        $airlineRows = is_array($payload['airlines'] ?? null) ? $payload['airlines'] : [];

        $this->reconcileTable(Airport::class, $airportRows, $dryRun, $authoritative, $stats['airports']);
        $this->reconcileTable(Airline::class, $airlineRows, $dryRun, $authoritative, $stats['airlines']);

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function readPayload(string $path): array
    {
        if (! is_file($path)) {
            throw new \InvalidArgumentException('Backup not found: '.$path);
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Invalid backup JSON.');
        }

        return $payload;
    }

    /**
     * @param  class-string<Airport|Airline>  $modelClass
     * @param  list<array<string, mixed>>  $rows
     * @param  array{insert:int,update:int,delete:int,skip:int,conflict:int}  $bucket
     */
    private function reconcileTable(string $modelClass, array $rows, bool $dryRun, bool $authoritative, array &$bucket): void
    {
        $backupIds = [];
        $existingById = $modelClass::query()->get()->keyBy('id');

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                $bucket['skip']++;

                continue;
            }

            $id = (int) $row['id'];
            $backupIds[] = $id;
            /** @var Airport|Airline|null $current */
            $current = $existingById->get($id);

            if ($current === null) {
                $bucket['insert']++;
                if (! $dryRun) {
                    $this->persistRow($modelClass, $row);
                }

                continue;
            }

            if ($this->rowsConflict($current->toArray(), $row)) {
                $bucket['conflict']++;
            }

            if ($this->rowNeedsUpdate($current->toArray(), $row)) {
                $bucket['update']++;
                if (! $dryRun) {
                    $current->fill($this->restorableAttributes($row));
                    $current->save();
                }
            } else {
                $bucket['skip']++;
            }
        }

        if (! $authoritative) {
            return;
        }

        $deleteIds = $existingById->keys()
            ->map(static fn ($id): int => (int) $id)
            ->diff($backupIds)
            ->values()
            ->all();

        $bucket['delete'] = count($deleteIds);
        if (! $dryRun && $deleteIds !== []) {
            $modelClass::query()->whereIn('id', $deleteIds)->delete();
        }
    }

    /**
     * @param  class-string<Airport|Airline>  $modelClass
     * @param  array<string, mixed>  $row
     */
    private function persistRow(string $modelClass, array $row): void
    {
        /** @var Model $model */
        $model = new $modelClass;
        $attributes = $this->restorableAttributes($row);
        $model->forceFill($attributes);
        if (isset($attributes['id'])) {
            $model->setAttribute($model->getKeyName(), $attributes['id']);
        }
        $model->save();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function restorableAttributes(array $row): array
    {
        unset($row['created_at'], $row['updated_at']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $backup
     */
    private function rowNeedsUpdate(array $existing, array $backup): bool
    {
        foreach ($this->restorableAttributes($backup) as $key => $value) {
            $left = $existing[$key] ?? null;
            $right = $value;
            if (is_array($left)) {
                $left = json_encode($left);
            }
            if (is_array($right)) {
                $right = json_encode($right);
            }
            if ((string) $left !== (string) $right) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $backup
     */
    private function rowsConflict(array $existing, array $backup): bool
    {
        foreach (['iata_code', 'icao_code'] as $codeField) {
            if (! array_key_exists($codeField, $backup) || ! array_key_exists($codeField, $existing)) {
                continue;
            }
            $backupCode = trim((string) ($backup[$codeField] ?? ''));
            $existingCode = trim((string) ($existing[$codeField] ?? ''));
            if ($backupCode !== '' && $existingCode !== '' && strtoupper($backupCode) !== strtoupper($existingCode)) {
                return true;
            }
        }

        return false;
    }
}
