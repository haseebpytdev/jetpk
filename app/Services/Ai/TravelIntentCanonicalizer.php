<?php

namespace App\Services\Ai;

use Carbon\Carbon;

/**
 * Application-owned TravelIntent canonicalization.
 * LLM may emit names/phrases; this class resolves codes/dates/budgets and confidence.
 */
final class TravelIntentCanonicalizer
{
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
        'doha' => 'DOH', 'doh' => 'DOH', 'دوحہ' => 'DOH',
        'istanbul' => 'IST', 'ist' => 'IST', 'استنبول' => 'IST',
        'london' => 'LHR', 'lhr' => 'LHR', 'heathrow' => 'LHR',
        'manchester' => 'MAN', 'man' => 'MAN',
        'toronto' => 'YYZ', 'yyz' => 'YYZ',
        'sharjah' => 'SHJ', 'shj' => 'SHJ', 'شارجہ' => 'SHJ',
        'abu dhabi' => 'AUH', 'auh' => 'AUH',
        'muscat' => 'MCT', 'mct' => 'MCT',
        'bangkok' => 'BKK', 'bkk' => 'BKK',
        'kuala lumpur' => 'KUL', 'kul' => 'KUL',
        'new york' => 'JFK', 'nyc' => 'JFK', 'jfk' => 'JFK',
    ];

    /** Known IATA allowlist used by JetPakistan shopping (reject invented codes). */
    private const KNOWN_IATA = [
        'LHE', 'ISB', 'KHI', 'PEW', 'MUX', 'LYP', 'DXB', 'JED', 'RUH', 'MED', 'DOH', 'IST',
        'LHR', 'MAN', 'YYZ', 'SHJ', 'AUH', 'MCT', 'BKK', 'KUL', 'JFK',
    ];

    /** @var array<string, string> */
    private const AIRLINE_ALIASES = [
        'emirates' => 'EK', 'ek' => 'EK', 'ایمارات' => 'EK',
        'pia' => 'PK', 'pakistan international' => 'PK', 'pk' => 'PK',
        'qatar' => 'QR', 'qatar airways' => 'QR', 'qr' => 'QR',
        'etihad' => 'EY', 'ey' => 'EY',
        'flydubai' => 'FZ', 'fz' => 'FZ',
        'saudia' => 'SV', 'sv' => 'SV', 'saudi' => 'SV',
        'flynas' => 'XY', 'xy' => 'XY',
        'gulf air' => 'GF', 'gf' => 'GF',
        'air arabia' => 'G9', 'g9' => 'G9',
        'flyjinnah' => '9P', 'fly jinnah' => '9P', '9p' => '9P',
        'airblue' => 'PA', 'turkish' => 'TK', 'tk' => 'TK', 'serene' => 'ER',
    ];

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>|null  $prior
     * @return array{intent: array<string, mixed>, clarification_required: bool, reasons: list<string>}
     */
    public function canonicalize(array $raw, ?array $prior = null, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $prior = is_array($prior) ? $prior : [];
        $reasons = [];

        $merged = $prior;
        foreach ($raw as $k => $v) {
            if ($v === null || $v === '' || $v === 'null') {
                continue;
            }
            $merged[$k] = $v;
        }

        // Follow-up patch: day delta
        if (isset($raw['depart_date_delta_days']) && is_numeric($raw['depart_date_delta_days'])) {
            $base = $merged['depart_date'] ?? $prior['depart_date'] ?? null;
            if (is_string($base) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $base) === 1) {
                $merged['depart_date'] = Carbon::parse($base)->addDays((int) $raw['depart_date_delta_days'])->toDateString();
            }
        }

        $originText = $this->firstString($raw, ['origin_text', 'origin'])
            ?? $this->firstString($prior, ['origin_text', 'origin']);
        $destText = $this->firstString($raw, ['destination_text', 'destination'])
            ?? $this->firstString($prior, ['destination_text', 'destination']);

        $origin = $this->resolveAirport($originText, $reasons, 'origin');
        $destination = $this->resolveAirport($destText, $reasons, 'destination');

        // Prefer prior resolved codes when follow-up omitted locations
        if ($origin === null && isset($prior['origin']) && is_string($prior['origin'])) {
            $origin = $this->resolveAirport($prior['origin'], $reasons, 'origin');
        }
        if ($destination === null && isset($prior['destination']) && is_string($prior['destination'])) {
            $destination = $this->resolveAirport($prior['destination'], $reasons, 'destination');
        }

        $departExpr = $this->firstString($raw, ['depart_date_text', 'depart_date', 'departDate']);
        $returnExpr = $this->firstString($raw, ['return_date_text', 'return_date', 'returnDate']);
        $depart = $this->resolveDate($departExpr, $now, $merged['depart_date'] ?? $prior['depart_date'] ?? null);
        $return = $this->resolveDate($returnExpr, $now, $merged['return_date'] ?? $prior['return_date'] ?? null);

        if ($depart === null && isset($merged['depart_date']) && is_string($merged['depart_date'])) {
            $depart = $this->resolveDate((string) $merged['depart_date'], $now, null);
        }

        $airline = $this->resolveAirline(
            $this->firstString($raw, ['airline_name', 'airline'])
                ?? $this->firstString($prior, ['airline_name', 'airline'])
        );

        $budget = $this->resolveBudget(
            $raw['budget_text'] ?? $raw['budget'] ?? $prior['budget_text'] ?? $prior['budget'] ?? null
        );

        $clarification = filter_var($raw['clarification_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($originText !== null && $origin === null) {
            $clarification = true;
            $reasons[] = 'origin_unresolved';
        }
        if ($destText !== null && $destination === null) {
            $clarification = true;
            $reasons[] = 'destination_unresolved';
        }

        // Reject invented JetPakistan airline brand
        if (isset($raw['airline_name']) && is_string($raw['airline_name'])
            && preg_match('/jet\s*pakistan/i', $raw['airline_name']) === 1) {
            $airline = null;
            $reasons[] = 'rejected_invented_airline';
        }

        $intent = strtolower(trim((string) ($merged['intent'] ?? $raw['intent'] ?? 'unknown')));
        if ($clarification && in_array($intent, ['flight_search', 'group_search'], true)
            && ($origin === null || $destination === null)) {
            $intent = 'unknown';
        }

        $out = [
            'intent' => $intent,
            'origin' => $origin,
            'destination' => $destination,
            'origin_text' => $originText,
            'destination_text' => $destText,
            'depart_date' => $depart,
            'return_date' => $return,
            'adults' => isset($merged['adults']) ? (int) $merged['adults'] : (isset($prior['adults']) ? (int) $prior['adults'] : 1),
            'children' => isset($merged['children']) ? (int) $merged['children'] : (isset($prior['children']) ? (int) $prior['children'] : 0),
            'infants' => isset($merged['infants']) ? (int) $merged['infants'] : (isset($prior['infants']) ? (int) $prior['infants'] : 0),
            'cabin' => $merged['cabin'] ?? $prior['cabin'] ?? null,
            'airline' => $airline,
            'max_stops' => array_key_exists('max_stops', $raw) || array_key_exists('max_stops', $merged)
                ? (isset($merged['max_stops']) ? (int) $merged['max_stops'] : null)
                : ($prior['max_stops'] ?? null),
            'budget' => $budget,
            'time_preference' => $merged['time_preference'] ?? $prior['time_preference'] ?? null,
            'currency' => $merged['currency'] ?? $prior['currency'] ?? 'PKR',
            'clarification_required' => $clarification,
        ];

        // Confidence gate: do not allow searchable intent without resolved O/D
        if (in_array($out['intent'], ['flight_search', 'group_search'], true)
            && ($out['origin'] === null || $out['destination'] === null)) {
            $out['clarification_required'] = true;
            $out['intent'] = 'unknown';
            $reasons[] = 'blocked_unresolved_route';
        }

        return [
            'intent' => $out,
            'clarification_required' => (bool) $out['clarification_required'],
            'reasons' => $reasons,
        ];
    }

    public function resolveAirport(?string $text, array &$reasons = [], string $field = 'airport'): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $raw = trim($text);
        // Strip bogus prefixes like IATA:XXX
        if (preg_match('/^IATA[:\s-]*([A-Za-z]{3})$/i', $raw, $m) === 1) {
            $raw = $m[1];
        }

        $upper = strtoupper($raw);
        if (preg_match('/^[A-Z]{3}$/', $upper) === 1) {
            if (in_array($upper, self::KNOWN_IATA, true)) {
                return $upper;
            }
            $reasons[] = $field.'_unknown_iata_'.$upper;

            return null;
        }

        $key = mb_strtolower($raw);
        if (isset(self::CITY_TO_IATA[$key])) {
            return self::CITY_TO_IATA[$key];
        }
        // Partial contains for "Lahore Pakistan"
        foreach (self::CITY_TO_IATA as $alias => $code) {
            if (str_contains($key, $alias)) {
                return $code;
            }
        }
        $reasons[] = $field.'_unresolved';

        return null;
    }

    public function resolveAirline(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $raw = trim($text);
        if (preg_match('/jet\s*pakistan/i', $raw) === 1) {
            return null;
        }
        $upper = strtoupper($raw);
        if (preg_match('/^[A-Z0-9]{2}$/', $upper) === 1) {
            return $upper;
        }
        $key = mb_strtolower($raw);
        if (isset(self::AIRLINE_ALIASES[$key])) {
            return self::AIRLINE_ALIASES[$key];
        }
        foreach (self::AIRLINE_ALIASES as $alias => $code) {
            if (str_contains($key, $alias)) {
                return $code;
            }
        }

        return null;
    }

    public function resolveBudget(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $n = (float) $value;

            return $n < 1000 ? $n * 1000 : $n;
        }
        if (! is_string($value)) {
            return null;
        }
        $s = strtolower(trim($value));
        $s = str_replace([',', 'pkr', 'rs', 'rupees'], ['', '', '', ''], $s);
        $s = trim($s);
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(k|hazar|lac|lakh)?$/u', $s, $m) === 1) {
            $n = (float) $m[1];
            $unit = $m[2] ?? '';
            if ($unit === 'k' || $unit === 'hazar') {
                return $n * 1000;
            }
            if ($unit === 'lac' || $unit === 'lakh') {
                return $n * 100000;
            }

            return $n < 1000 ? $n * 1000 : $n;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(hazar|lac|lakh|k)\b/u', $s, $m) === 1) {
            $n = (float) $m[1];
            $unit = $m[2];
            if ($unit === 'k' || $unit === 'hazar') {
                return $n * 1000;
            }

            return $n * 100000;
        }
        if (preg_match('/(\d{4,7})/', $s, $m) === 1) {
            return (float) $m[1];
        }

        return null;
    }

    public function resolveDate(?string $expr, Carbon $now, mixed $fallback = null): ?string
    {
        if ($expr === null || trim($expr) === '') {
            if (is_string($fallback) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fallback) === 1) {
                return $fallback;
            }

            return null;
        }
        $e = trim($expr);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $e) === 1) {
            return $e;
        }
        $lower = mb_strtolower($e);
        if (in_array($lower, ['today', 'aaj'], true)) {
            return $now->toDateString();
        }
        if (in_array($lower, ['tomorrow', 'kal'], true)) {
            return $now->copy()->addDay()->toDateString();
        }
        if (in_array($lower, ['parso', 'day after tomorrow'], true)) {
            return $now->copy()->addDays(2)->toDateString();
        }
        if (preg_match('/next\s+friday|aglay\s+jumay|agle\s+jumma|aglay\s+jumma/u', $lower) === 1) {
            return $now->copy()->next(Carbon::FRIDAY)->toDateString();
        }
        if (preg_match('/next\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/', $lower, $m) === 1) {
            return $now->copy()->next($m[1])->toDateString();
        }
        if (preg_match('/\b(\d{1,2})\s+(jan|january|feb|february|mar|march|apr|april|may|jun|june|jul|july|aug|august|sep|sept|september|oct|october|nov|november|dec|december)\b/i', $e, $m) === 1) {
            try {
                $dt = Carbon::parse(sprintf('%d %s %d', (int) $m[1], $m[2], $now->year));
                if ($dt->lt($now->copy()->startOfDay())) {
                    $dt->addYear();
                }

                return $dt->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $arr
     * @param  list<string>  $keys
     */
    private function firstString(array $arr, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (! array_key_exists($k, $arr) || $arr[$k] === null || $arr[$k] === '') {
                continue;
            }
            if (is_string($arr[$k]) || is_numeric($arr[$k])) {
                return trim((string) $arr[$k]);
            }
        }

        return null;
    }
}
