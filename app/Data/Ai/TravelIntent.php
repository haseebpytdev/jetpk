<?php

namespace App\Data\Ai;

/**
 * Narrow validated shopping intent. Application chooses tools — model never names them.
 *
 * @phpstan-type TravelIntentArray array{
 *   intent: string,
 *   origin: ?string,
 *   destination: ?string,
 *   depart_date: ?string,
 *   return_date: ?string,
 *   adults: int,
 *   children: int,
 *   infants: int,
 *   cabin: ?string,
 *   airline: ?string,
 *   max_stops: ?int,
 *   budget: ?float,
 *   time_preference: ?string,
 *   currency: string,
 *   mode: string
 * }
 */
final class TravelIntent
{
    public function __construct(
        public readonly string $intent,
        public readonly ?string $origin = null,
        public readonly ?string $destination = null,
        public readonly ?string $departDate = null,
        public readonly ?string $returnDate = null,
        public readonly int $adults = 1,
        public readonly int $children = 0,
        public readonly int $infants = 0,
        public readonly ?string $cabin = null,
        public readonly ?string $airline = null,
        public readonly ?int $maxStops = null,
        public readonly ?float $budget = null,
        public readonly ?string $timePreference = null,
        public readonly string $currency = 'PKR',
        public readonly string $mode = 'STRUCTURED_FALLBACK',
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw, string $mode = 'STRUCTURED_FALLBACK'): self
    {
        $intent = strtolower(trim((string) ($raw['intent'] ?? 'unknown')));
        $intentAliases = [
            'travel' => 'flight_search',
            'flight' => 'flight_search',
            'flights' => 'flight_search',
            'search_flights' => 'flight_search',
            'group' => 'group_search',
            'groups' => 'group_search',
            'faq' => 'knowledge',
            'help' => 'knowledge',
            'support' => 'handoff',
            'human' => 'handoff',
            'agent' => 'handoff',
            'chatbot' => 'unknown',
        ];
        if (isset($intentAliases[$intent])) {
            $intent = $intentAliases[$intent];
        }
        $allowed = ['flight_search', 'group_search', 'knowledge', 'handoff', 'unknown'];
        if (! in_array($intent, $allowed, true)) {
            $intent = 'unknown';
        }

        $adults = max(1, min(9, (int) ($raw['adults'] ?? 1)));
        $children = max(0, min(9, (int) ($raw['children'] ?? 0)));
        $infants = max(0, min(9, (int) ($raw['infants'] ?? 0)));

        return new self(
            intent: $intent,
            origin: self::normalizeAirport($raw['origin'] ?? $raw['origin_text'] ?? null),
            destination: self::normalizeAirport($raw['destination'] ?? $raw['destination_text'] ?? null),
            departDate: self::normalizeDate($raw['depart_date'] ?? $raw['departDate'] ?? $raw['depart_date_text'] ?? null),
            returnDate: self::normalizeDate($raw['return_date'] ?? $raw['returnDate'] ?? $raw['return_date_text'] ?? null),
            adults: $adults,
            children: $children,
            infants: $infants,
            cabin: self::normalizeCabin($raw['cabin'] ?? null),
            airline: self::normalizeAirline($raw['airline'] ?? $raw['airline_name'] ?? null),
            maxStops: isset($raw['max_stops']) || isset($raw['maxStops'])
                ? max(0, min(3, (int) ($raw['max_stops'] ?? $raw['maxStops'])))
                : null,
            budget: self::normalizeBudget($raw['budget'] ?? $raw['budget_text'] ?? null),
            timePreference: self::normalizeTimePref($raw['time_preference'] ?? $raw['timePreference'] ?? null),
            currency: strtoupper((string) ($raw['currency'] ?? 'PKR')) ?: 'PKR',
            mode: $mode,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'depart_date' => $this->departDate,
            'return_date' => $this->returnDate,
            'adults' => $this->adults,
            'children' => $this->children,
            'infants' => $this->infants,
            'cabin' => $this->cabin,
            'airline' => $this->airline,
            'max_stops' => $this->maxStops,
            'budget' => $this->budget,
            'time_preference' => $this->timePreference,
            'currency' => $this->currency,
            'mode' => $this->mode,
        ];
    }

    public function isSearchable(): bool
    {
        return in_array($this->intent, ['flight_search', 'group_search'], true)
            && $this->origin !== null
            && $this->destination !== null;
    }

    /** @var array<string, string> */
    private const CITY_TO_IATA = [
        'lahore' => 'LHE', 'lhe' => 'LHE', 'islamabad' => 'ISB', 'isb' => 'ISB',
        'karachi' => 'KHI', 'khi' => 'KHI', 'peshawar' => 'PEW', 'multan' => 'MUX',
        'faisalabad' => 'LYP', 'dubai' => 'DXB', 'dxb' => 'DXB', 'jeddah' => 'JED',
        'jed' => 'JED', 'riyadh' => 'RUH', 'madinah' => 'MED', 'medina' => 'MED',
        'doha' => 'DOH', 'istanbul' => 'IST', 'london' => 'LHR', 'manchester' => 'MAN',
        'toronto' => 'YYZ', 'sharjah' => 'SHJ', 'abu dhabi' => 'AUH', 'muscat' => 'MCT',
        'bangkok' => 'BKK', 'kuala lumpur' => 'KUL',
    ];

    /** @var list<string> */
    private const KNOWN_IATA = [
        'LHE', 'ISB', 'KHI', 'PEW', 'MUX', 'LYP', 'DXB', 'JED', 'RUH', 'MED', 'DOH', 'IST',
        'LHR', 'MAN', 'YYZ', 'SHJ', 'AUH', 'MCT', 'BKK', 'KUL', 'JFK',
    ];

    private static function normalizeAirport(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $raw = trim((string) $value);
        if (preg_match('/^IATA[:\s-]*([A-Za-z]{3})$/i', $raw, $m) === 1) {
            $raw = $m[1];
        }
        $v = strtoupper($raw);
        // Accept only known IATA — reject invented codes (e.g. LHR for "Lahore").
        if (preg_match('/^[A-Z]{3}$/', $v) === 1) {
            return in_array($v, self::KNOWN_IATA, true) ? $v : null;
        }
        $key = strtolower($raw);
        if (isset(self::CITY_TO_IATA[$key])) {
            return self::CITY_TO_IATA[$key];
        }
        foreach (self::CITY_TO_IATA as $alias => $code) {
            if ($alias !== '' && str_contains($key, $alias)) {
                return $code;
            }
        }

        return null;
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $v = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1) {
            return $v;
        }

        return null;
    }

    private static function normalizeCabin(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = strtolower(trim($value));
        $map = [
            'economy' => 'economy',
            'eco' => 'economy',
            'premium_economy' => 'premium_economy',
            'business' => 'business',
            'first' => 'first',
        ];

        return $map[$v] ?? null;
    }

    /** @var array<string, string> */
    private const AIRLINE_ALIASES = [
        'emirates' => 'EK', 'ek' => 'EK',
        'pia' => 'PK', 'pakistan international' => 'PK', 'pk' => 'PK',
        'qatar' => 'QR', 'qatar airways' => 'QR', 'qr' => 'QR',
        'etihad' => 'EY', 'ey' => 'EY',
        'flydubai' => 'FZ', 'fz' => 'FZ',
        'saudia' => 'SV', 'sv' => 'SV', 'saudi' => 'SV',
        'flynas' => 'XY', 'xy' => 'XY',
        'gulf air' => 'GF', 'gf' => 'GF',
        'air arabia' => 'G9', 'g9' => 'G9',
        'flyjinnah' => '9P', 'fly jinnah' => '9P',
        'airblue' => 'PA', 'turkish' => 'TK', 'tk' => 'TK',
    ];

    private static function normalizeAirline(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $raw = trim($value);
        if ($raw === '' || preg_match('/jet\s*pakistan/i', $raw) === 1) {
            return null;
        }
        $v = strtoupper($raw);
        if (preg_match('/^[A-Z0-9]{2}$/', $v) === 1) {
            return $v;
        }
        $key = strtolower($raw);
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

    private static function normalizeBudget(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $n = (float) $value;

            return $n < 1000 ? $n * 1000.0 : $n;
        }
        if (! is_string($value)) {
            return null;
        }
        $s = strtolower(trim(str_replace([',', 'pkr', 'rs'], ['', '', ''], $value)));
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(k|hazar|lac|lakh)?$/u', $s, $m) === 1) {
            $n = (float) $m[1];
            $unit = $m[2] ?? '';
            if ($unit === 'k' || $unit === 'hazar') {
                return $n * 1000.0;
            }
            if ($unit === 'lac' || $unit === 'lakh') {
                return $n * 100000.0;
            }

            return $n < 1000 ? $n * 1000.0 : $n;
        }

        return null;
    }

    private static function normalizeTimePref(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = strtolower(trim($value));
        $allowed = ['morning', 'afternoon', 'evening', 'night', 'any'];

        return in_array($v, $allowed, true) ? $v : null;
    }
}
