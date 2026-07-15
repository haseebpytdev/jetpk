<?php

namespace App\Services\TravelData;

use App\Models\Airline;
use Illuminate\Support\Str;

/**
 * Canonical JetPK airline identity resolution (override map, aliases, DB fallback).
 */
final class AirlineCanonicalResolver
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $overrides = null;

    /** @var array<string, string>|null */
    private ?array $supplierAliases = null;

    /**
     * @return array<string, mixed>|null
     */
    public function overrideForIata(?string $code): ?array
    {
        $iata = $this->normalizeIata($code);
        if ($iata === null) {
            return null;
        }

        return $this->overrides()[$iata] ?? null;
    }

    public function resolveToCanonicalIata(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $normalized = Str::upper(trim($code));
        if (isset($this->overrides()[$normalized])) {
            return $normalized;
        }

        if (isset($this->supplierAliases()[$normalized])) {
            return $this->supplierAliases()[$normalized];
        }

        if (preg_match('/^[A-Z0-9]{2,3}$/', $normalized)) {
            return $normalized;
        }

        return null;
    }

    public function canonicalDisplayName(?string $code): ?string
    {
        $iata = $this->resolveToCanonicalIata($code);
        if ($iata === null) {
            return null;
        }

        $override = $this->overrideForIata($iata);
        if ($override !== null) {
            return (string) ($override['name'] ?? '');
        }

        $airline = $this->findDatabaseAirline($iata);

        return $airline?->name;
    }

    public function logoCode(?string $code): ?string
    {
        $iata = $this->resolveToCanonicalIata($code);
        if ($iata === null) {
            return null;
        }

        $override = $this->overrideForIata($iata);
        if ($override !== null) {
            $logo = trim((string) ($override['logo_code'] ?? ''));

            return $logo !== '' ? Str::upper($logo) : $iata;
        }

        return $iata;
    }

    public function isCanonicalOverride(string $iata): bool
    {
        return isset($this->overrides()[Str::upper(trim($iata))]);
    }

    /**
     * @return list<string>
     */
    public function requiredJetpkCodes(): array
    {
        return array_values(config('airline_canonical_overrides.required_jetpk_codes', []));
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public function payloadFromOverride(array $override): array
    {
        $iata = Str::upper(trim((string) ($override['iata'] ?? '')));
        $icao = isset($override['icao']) ? Str::upper(trim((string) $override['icao'])) : null;
        $name = trim((string) ($override['name'] ?? ''));
        $country = trim((string) ($override['country'] ?? ''));
        $aliases = is_array($override['aliases'] ?? null) ? $override['aliases'] : [];

        return [
            'iata_code' => $iata !== '' ? $iata : null,
            'icao_code' => $icao !== '' ? $icao : null,
            'name' => $name,
            'country' => $country !== '' ? $country : null,
            'is_active' => (bool) ($override['is_active'] ?? true),
            'logo_path' => $override['logo_path'] ?? null,
            'search_keywords' => $this->buildKeywords([$iata, $icao, $name, $country, ...$aliases]),
            'meta' => [
                'source' => 'jetpk-canonical-override',
                'canonical_override' => true,
                'logo_code' => $this->logoCode($iata),
                'aliases' => $aliases,
                'supplier_aliases' => $override['supplier_aliases'] ?? [],
            ],
        ];
    }

    /**
     * Pick the best source CSV row for a duplicate IATA group.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{row: ?array<string, mixed>, reason: string}
     */
    public function pickSourceRow(string $iata, array $rows): array
    {
        if ($rows === []) {
            return ['row' => null, 'reason' => 'empty_group'];
        }

        $override = $this->overrideForIata($iata);
        if ($override !== null) {
            $match = $this->matchRowToOverride($rows, $override);
            if ($match !== null) {
                return ['row' => $match, 'reason' => 'override_alias_match'];
            }

            return ['row' => $this->preferActiveRow($rows), 'reason' => 'override_forced_active_fallback'];
        }

        $active = array_values(array_filter($rows, static fn (array $r): bool => ($r['_active'] ?? false) === true));
        if (count($active) === 1) {
            return ['row' => $active[0], 'reason' => 'single_active'];
        }
        if (count($active) > 1) {
            $byCountry = $this->disambiguateByCountry($active);
            if ($byCountry !== null) {
                return ['row' => $byCountry, 'reason' => 'active_country_unique'];
            }

            return ['row' => null, 'reason' => 'ambiguous_active'];
        }

        if (count($rows) === 1) {
            return ['row' => $rows[0], 'reason' => 'single_row'];
        }

        return ['row' => null, 'reason' => 'ambiguous_inactive'];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $override
     */
    private function matchRowToOverride(array $rows, array $override): ?array
    {
        $needles = collect([
            $override['name'] ?? null,
            $override['icao'] ?? null,
            ...($override['aliases'] ?? []),
        ])->filter()->map(static fn ($v): string => mb_strtolower(trim((string) $v)))->unique()->values();

        foreach ($rows as $row) {
            $hay = mb_strtolower(trim((string) ($row['Name'] ?? '')));
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($hay, $needle)) {
                    return $row;
                }
            }
            $icao = Str::upper(trim((string) ($row['ICAO'] ?? '')));
            if ($icao !== '' && $icao === Str::upper(trim((string) ($override['icao'] ?? '')))) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function preferActiveRow(array $rows): array
    {
        foreach ($rows as $row) {
            if (($row['_active'] ?? false) === true) {
                return $row;
            }
        }

        return $rows[0];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function disambiguateByCountry(array $rows): ?array
    {
        $countries = collect($rows)->pluck('Country')->map(static fn ($c): string => trim((string) $c))->unique();
        if ($countries->count() === 1) {
            return $rows[0];
        }

        return null;
    }

    public function findDatabaseAirline(string $iata): ?Airline
    {
        return Airline::query()
            ->whereRaw('UPPER(COALESCE(iata_code, "")) = ?', [Str::upper(trim($iata))])
            ->first();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function overrides(): array
    {
        if ($this->overrides === null) {
            $raw = config('airline_canonical_overrides.overrides', []);
            $this->overrides = is_array($raw) ? $raw : [];
        }

        return $this->overrides;
    }

    /**
     * @return array<string, string>
     */
    public function supplierAliases(): array
    {
        if ($this->supplierAliases === null) {
            $map = [];
            foreach (config('airline_canonical_overrides.supplier_aliases', []) as $alias => $iata) {
                $map[Str::upper(trim((string) $alias))] = Str::upper(trim((string) $iata));
            }
            foreach ($this->overrides() as $iata => $override) {
                foreach ((array) ($override['supplier_aliases'] ?? []) as $alias) {
                    $map[Str::upper(trim((string) $alias))] = Str::upper(trim((string) $iata));
                }
            }
            $this->supplierAliases = $map;
        }

        return $this->supplierAliases;
    }

  private function normalizeIata(?string $code): ?string
    {
        $resolved = $this->resolveToCanonicalIata($code);

        return $resolved !== null && preg_match('/^[A-Z0-9]{2,3}$/', $resolved) ? $resolved : null;
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
}
