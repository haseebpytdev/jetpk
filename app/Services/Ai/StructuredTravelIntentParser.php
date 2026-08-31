<?php

namespace App\Services\Ai;

use App\Data\Ai\TravelIntent;
use Carbon\Carbon;

/**
 * Deterministic no-LLM TravelIntent parser for common travel commands.
 */
final class StructuredTravelIntentParser
{
    /** @var array<string, string> */
    private const CITY_TO_IATA = [
        'lahore' => 'LHE',
        'lhe' => 'LHE',
        'islamabad' => 'ISB',
        'isb' => 'ISB',
        'karachi' => 'KHI',
        'khi' => 'KHI',
        'peshawar' => 'PEW',
        'pew' => 'PEW',
        'multan' => 'MUX',
        'mux' => 'MUX',
        'faisalabad' => 'LYP',
        'lyp' => 'LYP',
        'dubai' => 'DXB',
        'dxb' => 'DXB',
        'jeddah' => 'JED',
        'jed' => 'JED',
        'riyadh' => 'RUH',
        'ruh' => 'RUH',
        'madinah' => 'MED',
        'medina' => 'MED',
        'med' => 'MED',
        'doha' => 'DOH',
        'doh' => 'DOH',
        'istanbul' => 'IST',
        'ist' => 'IST',
        'london' => 'LHR',
        'lhr' => 'LHR',
        'manchester' => 'MAN',
        'man' => 'MAN',
        'toronto' => 'YYZ',
        'yyz' => 'YYZ',
        'new york' => 'JFK',
        'nyc' => 'JFK',
        'jfk' => 'JFK',
        'sharjah' => 'SHJ',
        'shj' => 'SHJ',
        'abu dhabi' => 'AUH',
        'auh' => 'AUH',
        'muscat' => 'MCT',
        'mct' => 'MCT',
        'bangkok' => 'BKK',
        'bkk' => 'BKK',
        'kuala lumpur' => 'KUL',
        'kul' => 'KUL',
        'لاہور' => 'LHE',
        'دبئی' => 'DXB',
        'دبي' => 'DXB',
        'اسلام آباد' => 'ISB',
        'اسلاماباد' => 'ISB',
        'کراچی' => 'KHI',
        'جدہ' => 'JED',
        'جده' => 'JED',
        'ریاض' => 'RUH',
        'دوحہ' => 'DOH',
        'دوحه' => 'DOH',
        'استنبول' => 'IST',
        'شارجہ' => 'SHJ',
        'ملتان' => 'MUX',
        'پشاور' => 'PEW',
    ];

    /** @var array<string, string> */
    private const AIRLINE_ALIASES = [
        'emirates' => 'EK',
        'ek' => 'EK',
        'pia' => 'PK',
        'pakistan international' => 'PK',
        'pk' => 'PK',
        'qatar' => 'QR',
        'qatar airways' => 'QR',
        'qr' => 'QR',
        'etihad' => 'EY',
        'ey' => 'EY',
        'flydubai' => 'FZ',
        'fz' => 'FZ',
        'airblue' => 'PA',
        'serene' => 'ER',
        'saudia' => 'SV',
        'sv' => 'SV',
        'turkish' => 'TK',
        'tk' => 'TK',
    ];

    /**
     * @param  array<string, mixed>|null  $prior
     */
    public function parse(string $message, ?array $prior = null): TravelIntent
    {
        $raw = trim($message);
        $lower = mb_strtolower($raw);

        if ($this->wantsHandoff($lower)) {
            return TravelIntent::fromArray(['intent' => 'handoff'], 'STRUCTURED_FALLBACK');
        }

        if ($this->wantsKnowledge($lower)) {
            return TravelIntent::fromArray(['intent' => 'knowledge'], 'STRUCTURED_FALLBACK');
        }

        $merged = is_array($prior) ? $prior : [];
        $merged['intent'] = $merged['intent'] ?? 'flight_search';

        if (preg_match('/\bgroup(s)?\b|group fare|group deal|\bگروپ\b/u', $lower) === 1
            || preg_match('/\bگروپ\b/u', $raw) === 1) {
            $merged['intent'] = 'group_search';
        }

        [$origin, $destination] = $this->extractRoute($lower, $raw);
        if ($origin !== null) {
            $merged['origin'] = $origin;
        }
        if ($destination !== null) {
            $merged['destination'] = $destination;
        }

        if (preg_match('/\bone day later\b|\bagle din\b|\bایک دن بعد\b/u', $lower) === 1
            && ! empty($merged['depart_date'])) {
            $merged['depart_date'] = Carbon::parse((string) $merged['depart_date'])->addDay()->toDateString();
        } else {
            $depart = $this->extractDepartDate($lower);
            if ($depart !== null) {
                $merged['depart_date'] = $depart;
            }
        }

        $return = $this->extractReturnDate($lower);
        if ($return !== null) {
            $merged['return_date'] = $return;
        }

        if (preg_match('/(\d+)\s*adults?/i', $raw, $m) === 1) {
            $merged['adults'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*(child|children|kids?)/i', $raw, $m) === 1) {
            $merged['children'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*infants?/i', $raw, $m) === 1) {
            $merged['infants'] = (int) $m[1];
        }

        if (preg_match('/\bdirect\b|\bnon[-\s]?stop\b|\bseedha\b|\bسیدھا\b/u', $lower) === 1) {
            $merged['max_stops'] = 0;
        }

        if (preg_match('/(\d+)\s*k\b|\bunder\s+(\d+)\s*k\b|(\d{4,7})\s*(pkr|rs)?\s*(se neeche|under|below)/i', $raw, $m) === 1) {
            $num = (int) ($m[1] ?: ($m[2] ?: $m[3]));
            $merged['budget'] = $num < 1000 ? $num * 1000 : $num;
        }

        foreach (self::AIRLINE_ALIASES as $alias => $code) {
            if (str_contains($lower, $alias)) {
                $merged['airline'] = $code;
                break;
            }
        }

        if (preg_match('/\bsubah\b|\bmorning\b|\bصبح\b/u', $lower) === 1) {
            $merged['time_preference'] = 'morning';
        } elseif (preg_match('/\bshaam\b|\bevening\b|\bشام\b/u', $lower) === 1) {
            $merged['time_preference'] = 'evening';
        }

        if (empty($merged['origin']) || empty($merged['destination'])) {
            if (($merged['intent'] ?? '') !== 'knowledge' && ($merged['intent'] ?? '') !== 'handoff') {
                // Keep follow-up prior if present; else unknown until route known
                if (empty($merged['origin']) && empty($prior['origin'] ?? null)) {
                    $merged['intent'] = $merged['intent'] ?? 'unknown';
                }
            }
        }

        return TravelIntent::fromArray($merged, 'STRUCTURED_FALLBACK');
    }

    private function wantsHandoff(string $lower): bool
    {
        return (bool) preg_match(
            '/talk to (a )?person|human support|agent please|live agent|speak to (support|agent)|real person|human please|staff please|talk to support|connect (me )?to (a )?(human|agent|support)|handoff/u',
            $lower
        );
    }

    private function wantsKnowledge(string $lower): bool
    {
        return (bool) preg_match(
            '/how (does |do )?booking|payment (deadline|process|help)|cancellation|refund policy|saved travelers?|support hours|faq|group(s)? (kaise|how)/u',
            $lower
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractRoute(string $lower, ?string $original = null): array
    {
        if (preg_match('/\b([a-z]{3})\s*(?:to|→|->|se|سے)\s*([a-z]{3})\b/u', $lower, $m) === 1) {
            return [
                $this->toIata($m[1]),
                $this->toIata($m[2]),
            ];
        }

        if (preg_match('/([a-z ]{3,20}?)\s+(?:to|→|->|se|سے)\s+([a-z ]{3,20}?)(?:\s|$|,|\.)/u', $lower, $m) === 1) {
            return [
                $this->toIata(trim($m[1])),
                $this->toIata(trim($m[2])),
            ];
        }

        $scriptSource = $original ?? $lower;
        // Urdu / Arabic script city pairs: "لاہور سے دبئی"
        if (preg_match('/([\p{Arabic}]{2,20})\s*سے\s*([\p{Arabic}]{2,20})/u', $scriptSource, $m) === 1) {
            return [
                $this->toIata(trim($m[1])),
                $this->toIata(trim($m[2])),
            ];
        }

        return [null, null];
    }

    private function toIata(string $token): ?string
    {
        $t = trim(mb_strtolower($token));
        if (isset(self::CITY_TO_IATA[$t])) {
            return self::CITY_TO_IATA[$t];
        }
        // Preserve original script lookup (Urdu keys are not lowercased the same way)
        $raw = trim($token);
        if (isset(self::CITY_TO_IATA[$raw])) {
            return self::CITY_TO_IATA[$raw];
        }
        if (preg_match('/^[a-z]{3}$/', $t) === 1) {
            return strtoupper($t);
        }

        return null;
    }

    private function extractDepartDate(string $lower): ?string
    {
        if (str_contains($lower, 'tomorrow') || str_contains($lower, 'kal')) {
            return Carbon::tomorrow()->toDateString();
        }
        if (str_contains($lower, 'today') || str_contains($lower, 'aaj')) {
            return Carbon::today()->toDateString();
        }
        if (preg_match('/next\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/', $lower, $m) === 1) {
            return Carbon::parse('next '.$m[1])->toDateString();
        }
        if (preg_match('/\b(\d{1,2})\s+(january|february|march|april|may|june|july|august|september|october|november|december)\b/i', $lower, $m) === 1) {
            $year = (int) date('Y');
            $dt = Carbon::parse(sprintf('%d %s %d', (int) $m[1], $m[2], $year));
            if ($dt->isPast()) {
                $dt->addYear();
            }

            return $dt->toDateString();
        }
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $lower, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractReturnDate(string $lower): ?string
    {
        if (preg_match('/return(?:ing)?\s+(\d{1,2}\s+\w+|\d{4}-\d{2}-\d{2})/i', $lower, $m) === 1) {
            try {
                $dt = Carbon::parse($m[1]);
                if ($dt->year === 1970 || $dt->isPast()) {
                    // relative day-month without year
                    $dt = Carbon::parse($m[1].' '.date('Y'));
                    if ($dt->isPast()) {
                        $dt->addYear();
                    }
                }

                return $dt->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
