<?php

namespace App\Services\Booking;

use App\Models\Airport;

/**
 * Determines whether a public itinerary crosses a country border using airport reference data.
 */
class InternationalRouteDetector
{
    public function isInternational(?string $originIata, ?string $destinationIata): bool
    {
        $o = strtoupper(trim((string) $originIata));
        $d = strtoupper(trim((string) $destinationIata));

        if ($o === '' || $d === '') {
            return false;
        }

        $originAirport = Airport::query()->where('iata_code', $o)->first();
        $destAirport = Airport::query()->where('iata_code', $d)->first();

        if ($originAirport === null || $destAirport === null) {
            return false;
        }

        $oc = $this->normalizeCountryKey($originAirport);
        $dc = $this->normalizeCountryKey($destAirport);

        if ($oc === '' || $dc === '') {
            return true;
        }

        return strcasecmp($oc, $dc) !== 0;
    }

    /**
     * True when both endpoints resolve to airports in Pakistan (domestic PK itinerary).
     * Used for travel-document rules (CNIC vs passport). Unknown or partial reference data → false (strict passport-only).
     */
    public function isPakistanDomesticForTravelDocuments(?string $originIata, ?string $destinationIata): bool
    {
        return $this->nationalIdTravelDocumentsAllowedForOffer(null, $originIata, $destinationIata);
    }

    /**
     * Pakistan-only domestic itineraries may collect CNIC / national ID. All other itineraries require passport.
     *
     * @param  array<string, mixed>|null  $offer
     */
    public function nationalIdTravelDocumentsAllowedForOffer(?array $offer, ?string $fallbackOriginIata, ?string $fallbackDestinationIata): bool
    {
        $buckets = $this->distinctCountryBucketsFromOffer($offer, $fallbackOriginIata, $fallbackDestinationIata);

        return count($buckets) === 1 && array_key_exists('PK', $buckets);
    }

    /**
     * @param  array<string, mixed>|null  $offer
     */
    public function requiresPassportOnlyTravelDocuments(?array $offer, ?string $fallbackOriginIata, ?string $fallbackDestinationIata): bool
    {
        $buckets = $this->distinctCountryBucketsFromOffer($offer, $fallbackOriginIata, $fallbackDestinationIata);
        if ($buckets === []) {
            return true;
        }
        if (count($buckets) > 1) {
            return true;
        }

        $only = array_key_first($buckets);

        return $only !== 'PK';
    }

    /**
     * @param  array<string, mixed>|null  $offer
     * @return array<string, true> canonical country bucket => true
     */
    public function distinctCountryBucketsFromOffer(?array $offer, ?string $fallbackOriginIata, ?string $fallbackDestinationIata): array
    {
        $iatas = [];
        if (is_array($offer)) {
            $segments = $offer['segments'] ?? [];
            if (is_array($segments)) {
                foreach ($segments as $seg) {
                    if (! is_array($seg)) {
                        continue;
                    }
                    foreach (['origin', 'destination', 'departure_airport', 'arrival_airport'] as $k) {
                        $c = strtoupper(trim((string) ($seg[$k] ?? '')));
                        if (strlen($c) === 3) {
                            $iatas[$c] = true;
                        }
                    }
                }
            }
            foreach (['origin', 'destination'] as $k) {
                $c = strtoupper(trim((string) ($offer[$k] ?? '')));
                if (strlen($c) === 3) {
                    $iatas[$c] = true;
                }
            }
        }
        $fo = strtoupper(trim((string) $fallbackOriginIata));
        $fd = strtoupper(trim((string) $fallbackDestinationIata));
        if (strlen($fo) === 3) {
            $iatas[$fo] = true;
        }
        if (strlen($fd) === 3) {
            $iatas[$fd] = true;
        }

        $buckets = [];
        foreach (array_keys($iatas) as $iata) {
            $ap = Airport::query()->where('iata_code', $iata)->first();
            if ($ap === null) {
                continue;
            }
            $b = $this->canonicalCountryBucketForAirport($ap);
            if ($b !== '') {
                $buckets[$b] = true;
            }
        }

        return $buckets;
    }

    protected function canonicalCountryBucketForAirport(Airport $airport): string
    {
        $code = strtoupper(trim((string) ($airport->country_code ?? '')));
        if ($code === 'PK') {
            return 'PK';
        }
        $country = strtolower(trim((string) ($airport->country ?? '')));
        if ($country === 'pakistan') {
            return 'PK';
        }
        if ($code !== '' && strlen($code) === 2) {
            return $code;
        }
        if ($country !== '') {
            return 'name:'.$country;
        }

        return '';
    }

    protected function isPakistanAirport(Airport $airport): bool
    {
        $code = strtoupper(trim((string) ($airport->country_code ?? '')));
        if ($code === 'PK') {
            return true;
        }

        return strcasecmp(trim((string) ($airport->country ?? '')), 'Pakistan') === 0;
    }

    protected function normalizeCountryKey(Airport $airport): string
    {
        $code = trim((string) ($airport->country_code ?? ''));
        if ($code !== '') {
            return strtoupper($code);
        }

        return strtolower(trim((string) ($airport->country ?? '')));
    }
}
