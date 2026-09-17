<?php

namespace App\Services\Ai\Hybrid;

final class AirlineResolver
{
    /** @var array<string, string> */
    private const ALIASES = [
        'emirates' => 'EK', 'ek' => 'EK', 'ایمارات' => 'EK',
        'pia' => 'PK', 'pakistan international' => 'PK', 'pakistan international airlines' => 'PK', 'pk' => 'PK',
        'qatar' => 'QR', 'qatar airways' => 'QR', 'qr' => 'QR',
        'etihad' => 'EY', 'ey' => 'EY',
        'flydubai' => 'FZ', 'fz' => 'FZ',
        'saudia' => 'SV', 'saudi airlines' => 'SV', 'saudi' => 'SV', 'sv' => 'SV',
        'flynas' => 'XY', 'xy' => 'XY',
        'gulf air' => 'GF', 'gf' => 'GF',
        'air arabia' => 'G9', 'g9' => 'G9',
        'flyjinnah' => '9P', 'fly jinnah' => '9P', '9p' => '9P',
        'airblue' => 'PA', 'turkish' => 'TK', 'tk' => 'TK', 'serene' => 'ER',
    ];

    /**
     * @return array{code: ?string, provenance: ?string}
     */
    public function resolveFromMessage(string $normalized, string $original): array
    {
        if (preg_match('/jet\s*pakistan/i', $original) === 1) {
            return ['code' => null, 'provenance' => null];
        }
        $hay = mb_strtolower($normalized.' '.$original);
        foreach (self::ALIASES as $alias => $code) {
            if (str_contains($hay, $alias)) {
                return ['code' => $code, 'provenance' => 'RESOLVED_MASTER_DATA'];
            }
        }

        return ['code' => null, 'provenance' => null];
    }

    public function resolve(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        if (preg_match('/jet\s*pakistan/i', $text) === 1) {
            return null;
        }
        $upper = strtoupper(trim($text));
        if (preg_match('/^[A-Z0-9]{2}$/', $upper) === 1) {
            return $upper;
        }
        $key = mb_strtolower(trim($text));
        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }
        foreach (self::ALIASES as $alias => $code) {
            if (str_contains($key, $alias)) {
                return $code;
            }
        }

        return null;
    }
}
