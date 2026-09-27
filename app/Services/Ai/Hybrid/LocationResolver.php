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
        'abu dhabi' => 'AUH', 'auh' => 'AUH', 'ابوظہبی' => 'AUH',
        'muscat' => 'MCT', 'mct' => 'MCT', 'مسقط' => 'MCT',
        'bangkok' => 'BKK', 'bkk' => 'BKK', 'بینکاک' => 'BKK',
        'kuala lumpur' => 'KUL', 'kul' => 'KUL',
        'jfk' => 'JFK', 'ewr' => 'EWR', 'lga' => 'LGA',
        'peshawar' => 'PEW', 'pew' => 'PEW', 'پشاور' => 'PEW', 'peshawr' => 'PEW',
        'sharjah' => 'SHJ', 'shj' => 'SHJ', 'شارجہ' => 'SHJ',
        'istanbul' => 'IST', 'ist' => 'IST', 'استنبول' => 'IST',
        'ankara' => 'ESB', 'esb' => 'ESB',
        'toronto' => 'YYZ', 'yyz' => 'YYZ',
        'manchester' => 'MAN', 'man' => 'MAN',
        'heathrow' => 'LHR', 'lhr' => 'LHR', 'gatwick' => 'LGW', 'lgw' => 'LGW',
        'doha' => 'DOH', 'doh' => 'DOH', 'دوحہ' => 'DOH', 'دوحه' => 'DOH',
        'madinah' => 'MED', 'medina' => 'MED', 'med' => 'MED', 'مدینہ' => 'MED',
        'riyadh' => 'RUH', 'ruh' => 'RUH', 'ریاض' => 'RUH',
        'jeddah' => 'JED', 'jed' => 'JED', 'جدہ' => 'JED', 'جده' => 'JED',
        'dubai' => 'DXB', 'dubay' => 'DXB', 'dubayee' => 'DXB', 'dubayi' => 'DXB',
        'dxb' => 'DXB', 'dwc' => 'DXB', 'دبئی' => 'DXB', 'دبي' => 'DXB',
        'lahore' => 'LHE', 'lahor' => 'LHE', 'lhe' => 'LHE', 'لاہور' => 'LHE',
        'islamabad' => 'ISB', 'isb' => 'ISB', 'isl' => 'ISB', 'اسلام آباد' => 'ISB', 'اسلاماباد' => 'ISB',
        'karachi' => 'KHI', 'khi' => 'KHI', 'کراچی' => 'KHI',
        'multan' => 'MUX', 'mux' => 'MUX', 'ملتان' => 'MUX',
        'faisalabad' => 'LYP', 'lyp' => 'LYP', 'فیصل آباد' => 'LYP',
    ];

    private const KNOWN_IATA = [
        'LHE', 'ISB', 'KHI', 'PEW', 'MUX', 'LYP', 'DXB', 'JED', 'RUH', 'MED', 'DOH', 'IST', 'ESB',
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
            if ($alias === '' || mb_strlen((string) $alias) < 4) {
                continue;
            }
            if (preg_match('/(?:^|[\s,])'.preg_quote((string) $alias, '/').'(?:[\s,]|$)/ui', $key) === 1
                || preg_match('/(?:^|[\s,])'.preg_quote((string) $alias, '/').'(?:[\s,]|$)/ui', $raw) === 1) {
                return ['code' => $code, 'ambiguous' => false, 'options' => [], 'provenance' => 'RESOLVED_MASTER_DATA'];
            }
        }

        return ['code' => null, 'ambiguous' => false, 'options' => [], 'provenance' => null];
    }

    /**
     * Detect multi-leg / open-jaw itineraries (never collapse to a single O/D pair).
     *
     * @return list<array{origin: string, destination: string}>|null
     */
    public function extractOpenJawLegs(string $normalized, string $original): ?array
    {
        // Single scan string — never concatenate normalized+original (synthetic self-pair risk).
        $hay = $this->scanHaystack($normalized, $original);
        $legs = [];

        // Prefer explicit "A to B … come back from C to D" / "and then from C to D".
        if (preg_match(
            '/\bfrom\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,20}?)\s+to\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,20}?)\s+(?:and\s+then|then|,?\s+and)\s+(?:come\s+back\s+|return\s+)?from\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,20}?)\s+to\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,20}?)\b/u',
            $hay,
            $m
        ) === 1) {
            $o1 = $this->resolve(trim($m[1]));
            $d1 = $this->resolve(trim($m[2]));
            $o2 = $this->resolve(trim($m[3]));
            $d2 = $this->resolve(trim($m[4]));
            if ($o1['code'] && $d1['code'] && $o2['code'] && $d2['code']) {
                $legs = [
                    ['origin' => $o1['code'], 'destination' => $d1['code']],
                    ['origin' => $o2['code'], 'destination' => $d2['code']],
                ];
            }
        }

        // "Lahore to Jeddah then Medina to Lahore" / "LHE to JED then MED to LHE"
        // (no leading "from" — common live UAT phrasing).
        // Do not use a bare [a-z]{3} alternative first — it steals city prefixes ("Lah" from Lahore).
        if (count($legs) < 2 && preg_match(
            '/\b([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+to\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+(?:and\s+then|then)\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+to\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\b/u',
            $hay,
            $m
        ) === 1) {
            $o1 = $this->resolve(trim($m[1]));
            $d1 = $this->resolve(trim($m[2]));
            $o2 = $this->resolve(trim($m[3]));
            $d2 = $this->resolve(trim($m[4]));
            if ($o1['code'] && $d1['code'] && $o2['code'] && $d2['code']) {
                $legs = [
                    ['origin' => $o1['code'], 'destination' => $d1['code']],
                    ['origin' => $o2['code'], 'destination' => $d2['code']],
                ];
            }
        }

        if (count($legs) < 2 && preg_match_all(
            '/\b(?:from\s+)?([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+to\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\b/u',
            $hay,
            $matches,
            PREG_SET_ORDER
        ) >= 2) {
            $legs = [];
            foreach ($matches as $m) {
                $o = $this->resolve(trim($m[1]));
                $d = $this->resolve(trim($m[2]));
                if ($o['code'] && $d['code']) {
                    $legs[] = ['origin' => $o['code'], 'destination' => $d['code']];
                }
            }
        }

        if (count($legs) < 2) {
            return null;
        }

        // Deduplicate identical consecutive legs.
        $unique = [];
        foreach ($legs as $leg) {
            // Drop synthetic same-airport legs unless user explicitly typed them elsewhere.
            if ($leg['origin'] === $leg['destination']) {
                continue;
            }
            $key = $leg['origin'].'-'.$leg['destination'];
            if (! isset($unique[$key])) {
                $unique[$key] = $leg;
            }
        }
        $legs = array_values($unique);
        if (count($legs) < 2) {
            return null;
        }

        // Simple round-trip A→B then B→A is not open-jaw.
        if (
            count($legs) === 2
            && $legs[0]['origin'] === $legs[1]['destination']
            && $legs[0]['destination'] === $legs[1]['origin']
        ) {
            return null;
        }

        return $legs;
    }

    /**
     * Progressive role-aware O/D (CQ43-R1). Never invents O=D from a single city token.
     *
     * @return array{
     *   origin: ?string,
     *   destination: ?string,
     *   origin_explicit: bool,
     *   destination_explicit: bool,
     *   explicit_route: bool,
     *   origin_only: bool,
     *   destination_only: bool,
     *   origin_ambiguous: bool,
     *   dest_ambiguous: bool,
     *   origin_options: list<array{label: string, value: string}>,
     *   dest_options: list<array{label: string, value: string}>
     * }
     */
    public function extractProgressiveOd(string $normalized, string $original): array
    {
        [$o, $d, $oAmb, $dAmb, $oOpts, $dOpts] = $this->extractRoute($normalized, $original);
        $originExplicit = $o !== null;
        $destExplicit = $d !== null;
        $explicitRoute = $originExplicit && $destExplicit;
        $originOnly = $originExplicit && ! $destExplicit;
        $destinationOnly = $destExplicit && ! $originExplicit;

        return [
            'origin' => $o,
            'destination' => $d,
            'origin_explicit' => $originExplicit,
            'destination_explicit' => $destExplicit,
            'explicit_route' => $explicitRoute,
            'origin_only' => $originOnly,
            'destination_only' => $destinationOnly,
            'origin_ambiguous' => $oAmb,
            'dest_ambiguous' => $dAmb,
            'origin_options' => $oOpts,
            'dest_options' => $dOpts,
        ];
    }

    /**
     * Prefer one scan string. Concatenating normalized+original creates synthetic
     * "Lahore from Lahore" self-pairs (CQ43 LHE-LHE).
     */
    private function scanHaystack(string $normalized, string $original): string
    {
        $n = mb_strtolower(trim($normalized));
        $o = mb_strtolower(trim($original));
        if ($n === '' && $o === '') {
            return '';
        }
        if ($n === '' || $n === $o) {
            return $o !== '' ? $o : $n;
        }

        // Prefer normalized (language-normalized) for Latin/Roman-Urdu patterns.
        return $n !== '' ? $n : $o;
    }

    /**
     * @return array{0: ?string, 1: ?string, origin_ambiguous: bool, dest_ambiguous: bool, origin_options: list<array{label: string, value: string}>, dest_options: list<array{label: string, value: string}>}
     */
    public function extractRoute(string $normalized, string $original): array
    {
        $originText = null;
        $destText = null;
        $hay = $this->scanHaystack($normalized, $original);
        $origLower = mb_strtolower(trim($original));

        // Origin-only follow-ups BEFORE "DEST from ORIGIN" (CQ43-R1).
        // "from Lahore" / "Lahore se" must not self-pair into LHE→LHE.
        if (preg_match('/^(?:please\s+)?(?:flights?\s+)?from\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)(?:\s*[,.?!]*)?$/u', $hay, $m) === 1
            || preg_match('/^([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+se(?:\s*[,.?!]*)?$/u', $hay, $m) === 1) {
            $o = $this->resolve(trim($m[1]));
            if ($o['code'] || $o['ambiguous']) {
                return [$o['code'], null, $o['ambiguous'], false, $o['options'], []];
            }
        }

        // Destination-led starts: "I need Dubai", "flights to Dubai", "to Dubai", "Dubai jana hai".
        if (preg_match('/^(?:i\s+)?(?:need|want|looking\s+for)\s+(?:flights?\s+(?:to\s+)?)?([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)(?:\s*[,.?!]*)?$/u', $hay, $m) === 1
            || preg_match('/^(?:flights?\s+)?to\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)(?:\s*[,.?!]*)?$/u', $hay, $m) === 1
            || preg_match('/^([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+jana\s+hai(?:\s*[,.?!]*)?$/u', $hay, $m) === 1
            || preg_match('/^([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+jana\s+hai(?:\s*[,.?!]*)?$/u', $origLower, $m) === 1) {
            $token = trim($m[1]);
            if (! preg_match('/\b(help|assistance|support|ticket|fare|booking|human|agent)\b/u', $token)) {
                $d = $this->resolve($token);
                if ($d['code'] || $d['ambiguous']) {
                    return [null, $d['code'], false, $d['ambiguous'], [], $d['options']];
                }
            }
        }

        // Active-travel destination corrections: "Make it Doha", "actually Dubai again".
        if (preg_match('/\b(?:make\s+(?:it|that)|change\s+(?:it|that)\s+to|switch\s+(?:it\s+|that\s+)?to|use)\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)(?:\s+instead)?(?:\s*[,.?!]*)?$/u', $hay, $m) === 1
            || preg_match('/\bactually\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+again(?:\s*[,.?!]*)?$/u', $hay, $m) === 1) {
            $d = $this->resolve(trim($m[1]));
            if ($d['code'] || $d['ambiguous']) {
                return [null, $d['code'], false, $d['ambiguous'], [], $d['options']];
            }
        }

        // "Dubai se Lahore wapis/wapas" / "A to B return" — single explicit route; trailing
        // return cue must NOT invent a second reciprocal leg (CQ42-R2).
        if (preg_match(
            '/\b([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+(?:to|→|->|se)\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+(?:wapis|wapas|return|واپس)\b/u',
            $hay,
            $m
        ) === 1) {
            $o = $this->resolve(trim($m[1]));
            $d = $this->resolve(trim($m[2]));
            if ($o['code'] && $d['code']) {
                return [$o['code'], $d['code'], false, false, [], []];
            }
            if ($o['code'] || $d['code'] || $o['ambiguous'] || $d['ambiguous']) {
                return [$o['code'], $d['code'], $o['ambiguous'], $d['ambiguous'], $o['options'], $d['options']];
            }
        }

        // "to Doha from Lahore" / "ticket to Doha from Lahore"
        if (preg_match('/\bto\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+from\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{2,24}?)(?=\s|$|,|\.|\?|!)/u', $hay, $m) === 1) {
            $destText = trim($m[1]);
            $originText = trim($m[2]);
            $o = $this->resolve($originText);
            $d = $this->resolve($destText);
            if ($o['code'] && $d['code'] && $o['code'] !== $d['code']) {
                return [$o['code'], $d['code'], false, false, [], []];
            }
            if (($o['code'] || $d['code'] || $o['ambiguous'] || $d['ambiguous']) && $o['code'] !== $d['code']) {
                return [$o['code'], $d['code'], $o['ambiguous'], $d['ambiguous'], $o['options'], $d['options']];
            }
        }

        // "Jeddah flights from LHE" / "Dubai flights from Lahore"
        if (preg_match('/([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+flights?\s+from\s+([a-z]{3}|[a-z\p{Arabic} ]{3,24})/u', $hay, $m) === 1) {
            $destText = trim($m[1]);
            $originText = trim($m[2]);
            // Ignore polite prefixes like "please find flights from …"
            if (! preg_match('/\b(please|find|show|need|want|get)\b/u', $destText)) {
                $o = $this->resolve($originText);
                $d = $this->resolve($destText);
                if ($o['code'] && $d['code'] && $o['code'] !== $d['code']) {
                    return [$o['code'], $d['code'], false, false, [], []];
                }
                if (($o['code'] || $d['code'] || $o['ambiguous'] || $d['ambiguous']) && $o['code'] !== $d['code']) {
                    return [$o['code'], $d['code'], $o['ambiguous'], $d['ambiguous'], $o['options'], $d['options']];
                }
            }
        }

        // "Bangkok from Islamabad" / "Dubai from Lahore" — require distinct cities.
        if (preg_match('/([a-z\p{Arabic} ]{3,24}?)\s+from\s+([a-z]{3}|[a-z\p{Arabic} ]{3,24})(?:\s|$|,|\.|on|for|tomorrow|today)/u', $hay, $m) === 1) {
            $destText = trim($m[1]);
            $originText = trim($m[2]);
            if (! preg_match('/\b(please|find|show|need|want|get|flights?)\b/u', $destText)) {
                $o = $this->resolve($originText);
                $d = $this->resolve($destText);
                // Reject synthetic self-pairs (same token duplicated via scan artifacts).
                if ($o['code'] && $d['code'] && $o['code'] === $d['code']) {
                    // Origin-only salvage: treat as "from ORIGIN".
                    return [$o['code'], null, false, false, [], []];
                }
                if ($o['code'] && $d['code']) {
                    return [$o['code'], $d['code'], false, false, [], []];
                }
                if ($o['ambiguous'] || $d['ambiguous']) {
                    return [$o['code'], $d['code'], $o['ambiguous'], $d['ambiguous'], $o['options'], $d['options']];
                }
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

        $clean = preg_replace('/\b(flights?|please|find|show|need)\b/u', ' ', $normalized) ?? $normalized;
        $clean = preg_replace('/\s+/u', ' ', trim($clean)) ?? $clean;

        if (preg_match('/\b([a-z]{3})\s*(?:to|→|->|se)\s*([a-z]{3})\b/u', $clean, $m) === 1) {
            $o = $this->resolve($m[1]);
            $d = $this->resolve($m[2]);
            if ($o['code'] && $d['code']) {
                return [$o['code'], $d['code'], false, false, [], []];
            }
            // Fall through — avoid treating word tails like "war se shj" as IATA pairs.
        } elseif (preg_match('/\bfrom\s+([a-z]{3}|[a-z]+(?:\s+[a-z]+){0,3}?)\s+to\s+([a-z]{3}|[a-z]+(?:\s+[a-z]+){0,3}?)(?=\s+(?:on|for|under|direct|cheapest|sasti|tomorrow|today|\d)|$|,|\.|\?|!)/u', $clean, $m) === 1) {
            $originText = trim($m[1]);
            $destText = trim($m[2]);
        } elseif (preg_match('/([a-z]+(?:\s+[a-z]+){0,3}?)\s+(?:to|→|->|se)\s+([a-z]{3}|[a-z]+(?:\s+[a-z]+){0,3}?)(?=\s+(?:on|for|under|direct|cheapest|sasti|jaldi|emirates|saudia|tomorrow|today|wapis|wapas|return|\d)|$|,|\.|\?|!)/u', $clean, $m) === 1) {
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
            // Connector-free left-to-right by message position (single scan — no concat).
            $ordered = self::CITY_TO_IATA;
            uksort($ordered, static fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));
            $scan = $this->scanHaystack($clean, $original);
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
            // Single-city destination correction while shopping / pending confirm:
            // "Actually make that Doha instead", "change it to Doha", "use Doha instead".
            if (count($found) === 1) {
                if (
                    preg_match('/\b(make (it|that)|change (it|that) to|switch (it |that )?to|use)\b/u', $clean) === 1
                    || preg_match('/\binstead\b/u', $clean) === 1
                    || preg_match('/\bactually\b.*\bagain\b/u', $clean) === 1
                ) {
                    return [null, $found[0], false, false, [], []];
                }
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

        // Partial / synthetic same-city pair salvage: keep origin only unless user typed O=D explicitly.
        if ($o['code'] && $d['code'] && $o['code'] === $d['code']) {
            $explicitSameAirport = preg_match(
                '/\b'.preg_quote(mb_strtolower((string) ($originText ?? '')), '/').'\s*(?:to|→|->|se)\s*'.preg_quote(mb_strtolower((string) ($destText ?? '')), '/').'\b/u',
                $hay
            ) === 1
                || preg_match('/\b([a-z]{3})\s*(?:to|→|->|se)\s*\1\b/u', $hay) === 1;
            if (! $explicitSameAirport) {
                return [$o['code'], null, false, false, [], []];
            }
        }

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
