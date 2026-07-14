<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Airport extends Model
{
    protected $fillable = [
        'iata_code',
        'icao_code',
        'name',
        'city',
        'country',
        'country_code',
        'airport_type',
        'timezone',
        'latitude',
        'longitude',
        'priority_score',
        'has_routes',
        'route_count',
        'is_commercial',
        'is_active',
        'search_keywords',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'bool',
            'priority_score' => 'int',
            'has_routes' => 'bool',
            'route_count' => 'int',
            'is_commercial' => 'bool',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $needle = mb_strtolower($term);

        return $query->where(function (Builder $q) use ($needle): void {
            $q->whereRaw('LOWER(TRIM(COALESCE(iata_code, ""))) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(TRIM(COALESCE(icao_code, ""))) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(TRIM(COALESCE(name, ""))) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(TRIM(COALESCE(city, ""))) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(TRIM(COALESCE(country, ""))) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(TRIM(COALESCE(search_keywords, ""))) LIKE ?', ["%{$needle}%"]);
        });
    }

    /**
     * 2–3 letter “token” queries: IATA/ICAO prefix or exact, plus city/name/country **prefix** only.
     * Avoids junk matches like "lhe" → Vilhelmina / Wilhelmshaven via substring city names.
     */
    public function scopeSearchCompactToken(Builder $query, string $termLower): Builder
    {
        $termLower = mb_strtolower(trim($termLower));
        if ($termLower === '') {
            return $query;
        }

        $upper = mb_strtoupper($termLower);

        return $query->where(function (Builder $q) use ($termLower, $upper): void {
            $q->whereRaw('UPPER(TRIM(COALESCE(iata_code, ""))) = ?', [$upper])
                ->orWhereRaw('UPPER(TRIM(COALESCE(iata_code, ""))) LIKE ?', [$upper.'%'])
                ->orWhereRaw('UPPER(TRIM(COALESCE(icao_code, ""))) LIKE ?', [$upper.'%'])
                ->orWhereRaw('LOWER(TRIM(COALESCE(city, ""))) LIKE ?', [$termLower.'%'])
                ->orWhereRaw('LOWER(TRIM(COALESCE(name, ""))) LIKE ?', [$termLower.'%'])
                ->orWhereRaw('LOWER(TRIM(COALESCE(country, ""))) LIKE ?', [$termLower.'%']);
        });
    }

    public static function queryTermIsCompactAirportToken(string $normalizedLower): bool
    {
        $t = trim($normalizedLower);

        return $t !== '' && mb_strlen($t) >= 2 && mb_strlen($t) <= 3 && (bool) preg_match('/^[a-z]+$/u', $t);
    }

    public function scopeWithValidIata(Builder $query): Builder
    {
        return $query
            ->whereNotNull('iata_code')
            ->whereRaw('LENGTH(TRIM(iata_code)) >= 3')
            ->whereRaw('UPPER(TRIM(iata_code)) NOT IN ("-", "---", "N/A", "NULL")');
    }

    /**
     * Repair Kaggle / bulk imports: trim codes, ensure catalog airports stay selectable in autocomplete.
     *
     * @return array{trimmed_sql: bool, nulled_invalid_iata: int}
     */
    public static function normalizeCatalogForSearch(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $trimmedSql = false;
        $nulled = 0;

        if (in_array($driver, ['sqlite', 'mysql', 'mariadb', 'pgsql'], true)) {
            $connection->statement('UPDATE airports SET iata_code = UPPER(TRIM(iata_code)) WHERE iata_code IS NOT NULL');
            $connection->statement('UPDATE airports SET icao_code = UPPER(TRIM(icao_code)) WHERE icao_code IS NOT NULL');
            $nulled = $connection->update(
                "UPDATE airports SET iata_code = NULL WHERE iata_code IS NOT NULL AND (
                    LENGTH(TRIM(iata_code)) < 3
                    OR UPPER(TRIM(iata_code)) IN ('-', '---', 'N/A', 'NUL', 'NULL', '\\N')
                )"
            );
            $trimmedSql = true;
        }

        return [
            'trimmed_sql' => $trimmedSql,
            'nulled_invalid_iata' => (int) $nulled,
        ];
    }

    /**
     * Passenger / OTA-relevant airports: scheduled route data, hub boosts, or explicit commercial flag.
     */
    public function scopeCommerciallySearchable(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('has_routes', true)
                ->orWhere('priority_score', '>', 0)
                ->orWhere('is_commercial', true);
        });
    }
}
