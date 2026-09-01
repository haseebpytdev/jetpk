<?php

namespace App\Services\Ai\Hybrid;

/**
 * Application-owned airport/city resolution. Never invents IATA codes.
 */
final class LocationResolver
{
    /** Cities that must clarify rather than auto-pick a hub airport. */
    private const AMBIGUOUS = [
        'london' => [
            ['label' => 'London Heathrow (LHR)', 'value' => 'LHR'],
            ['label' => 'London Gatwick (LGW)', 'value' => 'LGW'],
        ],
        'new york' => [
            ['label' => 'New York JFK (JFK)', 'value' => 'JFK'],
            ['label' => 'Newark (EWR)', 'value' => 'EWR'],
            ['label' => 'LaGuardia (LGA)', 'value' => 'LGA'],
        ],
        'nyc' => [
            ['label' => 'New York JFK (JFK)', 'value' => 'JFK'],
            ['label' => 'Newark (EWR)', 'value' => 'EWR'],
            ['label' => 'LaGuardia (LGA)', 'value' => 'LGA'],
        ],
    ];

    /** @var array<string, string> */
    private const CITY_TO_IATA = [
        'lahore' => 'LHE', 'lhe' => 'LHE', 'لاہور' => 'LHE',
        'islamabad' => 'ISB', 'isb' => 'ISB', 'isl' => 'ISB', 'اسلام آباد' => 'ISB', 'اسلاماباد' => 'ISB',
        'karachi' => 'KHI', 'khi' => 'KHI', 'کراچی' => 'KHI',
        'peshawar' => 'PEW', 'pew' => 'PEW', 'پشاور' => 'PEW',
        'multan' => 'MUX', 'mux' => 'MUX', 'ملتان' => 'MUX',
        'faisalabad' => 'LYP', 'lyp' => 'LYP', 'فیصل آباد' => 'LYP',
        'dubai' => 'DXB', 'dxb' => 'DXB', 'dwc' => 'DXB', 'دبئی' => 'DXB', 'دبي' => 'DXB',
        'jeddah' => 'JED', 'jed' => 'JED', 'جدہ' => 'JED', 'جده' => 'JED',
        'riyadh' => 'RUH', 'ruh' => 'RUH', 'ریاض' => 'RUH',
        'madinah' => 'MED', 'medina' => 'MED', 'med' => 'MED', 'مدینہ' => 'MED',
        'doha' => 'DOH', 'doh' => 'DOH', 'دوحہ' => 'DOH', 'دوحه' => 'DOH',
        'istanbul' => 'IST', 'ist' => 'IST', 'استنبول' => 'IST',
        'heathrow' => 'LHR', 'lhr' => 'LHR', 'gatwick' => 'LGW', 'lgw' => 'LGW',
        'manchester' => 'MAN', 'man' => 'MAN',
        'toronto' => 'YYZ', 'yyz' => 'YYZ',
        'sharjah' => 'SHJ', 'shj' => 'SHJ', 'شارجہ' => 'SHJ',
        'abu dhabi' => 'AUH', 'auh' => 'AUH',
        'muscat' => 'MCT', 'mct' => 'MCT',
        'bangkok' => 'BKK', 'bkk' => 'BKK',
        'kuala lumpur' => 'KUL', 'kul' => 'KUL',
        'jfk' => 'JFK', 'ewr' => 'EWR', 'lga' => 'LGA',
    ];

    private const KNOWN_IATA = [
        'LHE', 'ISB', 'KHI', 'PEW', 'MUX', 'LYP', 'DXB', 'JED', 'RUH', 'MED', 'DOH', 'IST',
        'LHR', 'LGW', 'MAN', 'YYZ', 'SHJ', 'AUH', 'MCT', 'BKK', 'KUL', 'JFK', 'EWR', 'LGA',
    ];

    /**
     * @return array{code: ?string, ambiguous: bool, options: list<array{label: string, value: string}>, provenance: ?string}
     */
    public function resolve(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return ['code' => null, 'ambiguous' => false, 'options' => [], 'provenance' => null];
        }
        $raw = trim($text);
        if (preg_match('/^IATA[:\s-]*([A-Za-z]{3})$/i', $raw, $m) === 1) {
            $raw = $m[1];
        }
        $upper = strtoupper($raw);
        if (preg_match('/^[A-Z]{3}$/', $upper) === 1) {
            if (in_array($upper, self::KNOWN_IATA, true)) {
                return ['code' => $upper, 'ambiguous' => false, 'options' => [], 'provenance' => 'EXPLICIT_USER'];
            }

            return ['code' => null, 'ambiguous' => false, 'options' => [], 'provenance' => null];
        }

        $key = mb_strtolower($raw);
        // Disambiguators first
        if (str_contains($key, 'heathrow') || $key === 'lhr') {
            return ['code' => 'LHR', 'ambiguous' => false, 'options' => [], 'provenance' => 'RESOLVED_MASTER_DATA'];
        }
        if (str_contains($key, 'gatwick') || $key === 'lgw') {
            return ['code' => 'LGW', 'ambiguous' => false, 'options' => [], 'provenance' => 'RESOLVED_MASTER_DATA'];
        }

        foreach (self::AMBIGUOUS as $city => $options) {
            if ($key === $city || str_contains($key, $city)) {
                // Exact airport code in text wins
                foreach ($options as $opt) {
                    if (str_contains($key, strtolower($opt['value']))) {
                        return ['code' => $opt['value'], 'ambiguous' => false, 'options' => [], 'provenance' => 'EXPLICIT_USER'];
                    }
                }

                return ['code' => null, 'ambiguous' => true, 'options' => $options, 'provenance' => null];
            }
        }

        if (isset(self::CITY_TO_IATA[$key])) {
            return ['code' => self::CITY_TO_IATA[$key], 'ambiguous' => false, 'options' => [], 'provenance' => 'RESOLVED_MASTER_DATA'];
        }
        if (isset(self::CITY_TO_IATA[$raw])) {
            return ['code' => self::CITY_TO_IATA[$raw], 'ambiguous' => false, 'options' => [], 'provenance' => 'RESOLVED_MASTER_DATA'];
        }
        foreach (self::CITY_TO_IATA as $alias => $code) {
            if ($alias !== '' && str_contains($key, $alias)) {
                return ['code' => $code, 'ambiguous' => false, 'options' => [], 'provenance' => 'RESOLVED_MASTER_DATA'];
            }
        }

        return ['code' => null, 'ambiguous' => false, 'options' => [], 'provenance' => null];
    }

    /**
     * @return array{0: ?string, 1: ?string, origin_ambiguous: bool, dest_ambiguous: bool, origin_options: list<array{label: string, value: string}>, dest_options: list<array{label: string, value: string}>}
     */
    public function extractRoute(string $normalized, string $original): array
    {
        $originText = null;
        $destText = null;
        $hay = mb_strtolower($normalized.' '.$original);

        // "Jeddah flights from LHE" / "Dubai flights from Lahore"
        if (preg_match('/([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+flights?\s+from\s+([a-z]{3}|[a-z\p{Arabic} ]{3,24})/u', $hay, $m) === 1) {
            $destText = trim($m[1]);
            $originText = trim($m[2]);
            $o = $this->resolve($originText);
            $d = $this->resolve($destText);
            if ($o['code'] || $d['code'] || $o['ambiguous'] || $d['ambiguous']) {
                return [$o['code'], $d['code'], $o['ambiguous'], $d['ambiguous'], $o['options'], $d['options']];
            }
        }

        // Mixed script: "ISB to دبئی" / "Lahore se دبئی"
        if (preg_match('/\b([a-z]{3}|[a-z ]{3,20}?)\s*(?:to|→|->|se)\s*([\p{Arabic}]{2,24})/u', $original, $m) === 1
            || preg_match('/\b([a-z]{3}|[a-z ]{3,20}?)\s*(?:to|→|->|se)\s*([\p{Arabic}]{2,24})/u', $normalized, $m) === 1) {
            $originText = trim($m[1]);
            $destText = trim($m[2]);
            $o = $this->resolve($originText);
            $d = $this->resolve($destText);
            if ($o['code'] || $d['code'] || $o['ambiguous'] || $d['ambiguous']) {
                return [$o['code'], $d['code'], $o['ambiguous'], $d['ambiguous'], $o['options'], $d['options']];
            }
        }

        $clean = preg_replace('/\b(flights?|please)\b/u', ' ', $normalized) ?? $normalized;
        $clean = preg_replace('/\s+/u', ' ', trim($clean)) ?? $clean;

        if (preg_match('/\b([a-z]{3})\s*(?:to|→|->|se)\s*([a-z]{3})\b/u', $clean, $m) === 1) {
            $originText = $m[1];
            $destText = $m[2];
        } elseif (preg_match('/\bfrom\s+([a-z]{3}|[a-z ]{3,20}?)\s+to\s+([a-z]{3}|[a-z ]{3,20}?)(?:\s|$|,|\.)/u', $clean, $m) === 1) {
            $originText = trim($m[1]);
            $destText = trim($m[2]);
        } elseif (preg_match('/([a-z ]{2,24}?)\s+(?:to|→|->|se)\s+([a-z ]{2,24}?)(?:\s|$|,|\.|for|on|under|direct|cheapest|tomorrow|today)/u', $clean, $m) === 1) {
            $originText = trim($m[1]);
            $destText = trim($m[2]);
        } elseif (preg_match('/([\p{Arabic}][\p{Arabic}\s]{1,30}?)\s*سے\s*([\p{Arabic}][\p{Arabic}\s]{1,30}?)(?:\s|$|براہ)/u', $original, $m) === 1) {
            $originText = trim($m[1]);
            $destText = trim($m[2]);
        } elseif (preg_match('/\b([a-z]{3})\s+([a-z]{3})\b/u', $clean, $m) === 1) {
            $o = $this->resolve($m[1]);
            $d = $this->resolve($m[2]);
            if ($o['code'] && $d['code']) {
                return [$o['code'], $d['code'], false, false, [], []];
            }
            // Skip non-airport leading tokens such as "kal LHE DXB".
            if (preg_match_all('/\b([a-z]{3})\b/u', $clean, $all) && count($all[1]) >= 2) {
                $codes = [];
                foreach ($all[1] as $tok) {
                    $r = $this->resolve($tok);
                    if ($r['code'] && ! in_array($r['code'], $codes, true)) {
                        $codes[] = $r['code'];
                    }
                    if (count($codes) >= 2) {
                        return [$codes[0], $codes[1], false, false, [], []];
                    }
                }
            }
        } else {
            // Connector-free left-to-right by message position
            $ordered = self::CITY_TO_IATA;
            uksort($ordered, static fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));
            $scan = $clean.' '.$original;
            $hits = [];
            foreach ($ordered as $alias => $code) {
                if ($alias === '' || mb_strlen((string) $alias) < 3) {
                    continue;
                }
                if (preg_match('/(?:^|[\s,])('.preg_quote((string) $alias, '/').')(?:[\s,]|$)/ui', $scan, $mm, PREG_OFFSET_CAPTURE) === 1) {
                    $pos = $mm[1][1];
                    $hits[] = ['pos' => $pos, 'code' => $code];
                }
            }
            usort($hits, static fn ($a, $b) => $a['pos'] <=> $b['pos']);
            $found = [];
            foreach ($hits as $hit) {
                if (! in_array($hit['code'], $found, true)) {
                    $found[] = $hit['code'];
                }
                if (count($found) >= 2) {
                    break;
                }
            }
            if (count($found) >= 2) {
                return [$found[0], $found[1], false, false, [], []];
            }
            foreach (array_keys(self::AMBIGUOUS) as $city) {
                if (str_contains($clean, $city)) {
                    $r = $this->resolve($city);

                    return [null, null, $r['ambiguous'], false, $r['options'], []];
                }
            }
        }

        $o = $this->resolve($originText);
        $d = $this->resolve($destText);

        return [
            $o['code'],
            $d['code'],
            $o['ambiguous'],
            $d['ambiguous'],
            $o['options'],
            $d['options'],
        ];
    }
}
