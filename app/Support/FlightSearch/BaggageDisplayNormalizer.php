<?php

namespace App\Support\FlightSearch;

/**
 * Customer-safe baggage label normalization (IATI/Sabre raw units → readable copy).
 */
class BaggageDisplayNormalizer
{
    public const NOT_PROVIDED = 'Not provided by supplier';

    /**
     * Customer-safe display string; never null (missing → {@see NOT_PROVIDED}).
     */
    public static function forDisplay(?string $raw): string
    {
        $normalized = self::normalizeLabel($raw);

        return ($normalized !== null && $normalized !== '') ? $normalized : self::NOT_PROVIDED;
    }

    public static function normalizeLabel(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = trim(str_replace(["\u{00A0}", "\xc2\xa0"], ' ', (string) $raw));
        if ($text === '') {
            return null;
        }

        $lower = strtolower($text);
        if (in_array($lower, ['n/a', 'na', 'nil', 'none', '--', '-', 'not available', 'unavailable'], true)) {
            return null;
        }

        if (str_contains($lower, 'not provided') || str_contains($lower, 'not included')) {
            return 'Not included';
        }

        if (! str_contains($text, ',')) {
            return self::normalizeToken($text);
        }

        $parts = array_values(array_filter(array_map(
            static fn (string $part): ?string => self::normalizeToken(trim($part)),
            preg_split('/\s*,\s*/', $text) ?: [],
        ), static fn (?string $part): bool => $part !== null && $part !== ''));

        $parts = array_values(array_unique($parts));
        if ($parts === []) {
            return null;
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return implode(', ', $parts);
    }

    public static function normalizeToken(string $token): ?string
    {
        $token = trim(preg_replace('/\s+/u', ' ', $token) ?? '');
        if ($token === '') {
            return null;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(piece|pieces|pc|pcs)$/i', $token, $m)) {
            $count = (float) $m[1];
            $label = abs($count - 1.0) < 0.001 ? 'piece' : 'pieces';

            return ((string) (int) round($count)).' '.$label;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(kilo|kilos|kilogram|kilograms|kg)$/i', $token, $m)) {
            return ((string) (int) round((float) $m[1])).' kg';
        }

        if (preg_match('/^(\d+(?:\.\d+)?)(piece|pieces|pc|pcs)$/i', $token, $m)) {
            $count = (float) $m[1];
            $label = abs($count - 1.0) < 0.001 ? 'piece' : 'pieces';

            return ((string) (int) round($count)).' '.$label;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)(kilo|kilos|kilogram|kilograms|kg)$/i', $token, $m)) {
            return ((string) (int) round((float) $m[1])).' kg';
        }

        $token = preg_replace('/\bKILO\b/i', 'kg', $token) ?? $token;
        $token = preg_replace('/\bKILOS\b/i', 'kg', $token) ?? $token;
        $token = preg_replace('/\bPIECE\b/i', 'piece', $token) ?? $token;
        $token = preg_replace('/\bPIECES\b/i', 'pieces', $token) ?? $token;
        $token = preg_replace('/\b(\d+)\s*KG\b/i', '$1 kg', $token) ?? $token;

        return trim($token);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function labelsFromSupplierItems(array $items): ?string
    {
        $parts = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $amount = trim((string) ($item['amount'] ?? $item['weight'] ?? ''));
            $unit = trim((string) ($item['unit'] ?? $item['type'] ?? 'kg'));
            if ($amount === '') {
                continue;
            }
            $label = self::normalizeToken($amount.' '.$unit);
            if ($label !== null && $label !== '') {
                $parts[] = $label;
            }
        }

        $parts = array_values(array_unique($parts));
        if ($parts === []) {
            return null;
        }

        return self::forDisplay(implode(', ', $parts));
    }

    /**
     * @return array{checked: ?string, cabin: ?string, summary: ?string}
     */
    public static function formatAllowance(?string $checked, ?string $cabin): array
    {
        $checkedNorm = self::normalizeLabel($checked);
        $cabinNorm = self::normalizeLabel($cabin);
        $summary = trim(implode(' / ', array_values(array_filter([$checkedNorm, $cabinNorm]))), ' /');

        return [
            'checked' => $checkedNorm,
            'cabin' => $cabinNorm,
            'summary' => $summary !== '' ? $summary : null,
        ];
    }

    /**
     * Split combined supplier summaries (e.g. "20 kg / 7 kg", "CHECKED UP TO 25 KG · CABIN UP TO 7 KG").
     *
     * @return array{checked: ?string, cabin: ?string}
     */
    public static function splitCombinedSummary(?string $summary): array
    {
        $text = trim((string) $summary);
        if ($text === '') {
            return ['checked' => null, 'cabin' => null];
        }

        $checked = null;
        $cabin = null;
        $parts = preg_split('/\s*(?:\/|·|;|,)\s*/', $text) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $lower = strtolower($part);
            $fragment = self::extractAllowanceFragment($part) ?? self::normalizeLabel($part);
            if ($fragment === null || $fragment === '') {
                continue;
            }
            if (str_contains($lower, 'cabin') || str_contains($lower, 'carry') || str_contains($lower, 'hand')) {
                $cabin ??= $fragment;

                continue;
            }
            if (str_contains($lower, 'check') || str_contains($lower, 'hold')) {
                $checked ??= $fragment;

                continue;
            }
            if ($checked === null) {
                $checked = $fragment;
            } elseif ($cabin === null && $fragment !== $checked) {
                $cabin = $fragment;
            }
        }

        return ['checked' => $checked, 'cabin' => $cabin];
    }

    public static function extractAllowanceFragment(string $text): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*(kg|kilo|kilos|kilogram|kilograms)\b/i', $text, $matches)) {
            return self::normalizeToken($matches[1].' '.$matches[2]);
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(pc|pcs|piece|pieces)\b/i', $text, $matches)) {
            return self::normalizeToken($matches[1].' '.$matches[2]);
        }
        if (preg_match('/(\d+(?:\.\d+)?)(kg|kilo|kilos|pc|pcs|piece|pieces)\b/i', $text, $matches)) {
            return self::normalizeToken($matches[1].$matches[2]);
        }

        return null;
    }
}
