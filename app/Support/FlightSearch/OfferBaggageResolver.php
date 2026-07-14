<?php

namespace App\Support\FlightSearch;

/**
 * Best-effort carry-on / checked baggage resolution for offer and fare-option display.
 * Reads normalized offer fields and supplier-specific nested structures without mutating booking data.
 */
class OfferBaggageResolver
{
    /**
     * @param  array<string, mixed>  $offer
     * @return array{checked: ?string, cabin: ?string, summary: ?string}
     */
    public static function resolveFromOffer(array $offer): array
    {
        $checkedCandidates = [];
        $cabinCandidates = [];
        $summaryCandidates = [];

        self::collectFromBaggageArray($offer['baggage'] ?? null, $checkedCandidates, $cabinCandidates, $summaryCandidates);
        self::pushCandidate($checkedCandidates, $offer['baggage_checked_display'] ?? $offer['baggage_checked'] ?? null);
        self::pushCandidate($cabinCandidates, $offer['baggage_cabin_display'] ?? $offer['baggage_cabin'] ?? null);
        self::pushCandidate($summaryCandidates, $offer['baggage_summary_display'] ?? null);

        $customerFields = is_array($offer['customer_display_fields'] ?? null) ? $offer['customer_display_fields'] : [];
        foreach (is_array($customerFields['segment_baggage'] ?? null) ? $customerFields['segment_baggage'] : [] as $segmentRow) {
            if (! is_array($segmentRow)) {
                continue;
            }
            self::pushCandidate($checkedCandidates, $segmentRow['checked'] ?? null);
            self::pushCandidate($cabinCandidates, $segmentRow['cabin'] ?? null);
        }
        foreach (is_array($customerFields['baggage_lines'] ?? null) ? $customerFields['baggage_lines'] : [] as $line) {
            self::parseBaggageLine($line, $checkedCandidates, $cabinCandidates, $summaryCandidates);
        }
        foreach (is_array($customerFields['passenger_baggage'] ?? null) ? $customerFields['passenger_baggage'] : [] as $paxRow) {
            if (! is_array($paxRow)) {
                continue;
            }
            self::pushCandidate($checkedCandidates, $paxRow['checked'] ?? null);
            self::pushCandidate($cabinCandidates, $paxRow['cabin'] ?? null);
        }

        foreach (is_array($offer['segments'] ?? null) ? $offer['segments'] : [] as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            self::collectFromBaggageArray($segment['baggage'] ?? null, $checkedCandidates, $cabinCandidates, $summaryCandidates);
            self::pushCandidate($checkedCandidates, $segment['baggage_checked'] ?? null);
            self::pushCandidate($cabinCandidates, $segment['baggage_cabin'] ?? null);
        }

        $brandedFares = is_array($offer['branded_fares'] ?? null) ? $offer['branded_fares'] : [];
        if ($brandedFares !== []) {
            $first = is_array($brandedFares[0] ?? null) ? $brandedFares[0] : [];
            self::collectFromFareOptionRow($first, $checkedCandidates, $cabinCandidates, $summaryCandidates);
        }

        $fareSummary = is_array($offer['fare_summary_display'] ?? null) ? $offer['fare_summary_display'] : [];
        foreach (is_array($fareSummary['baggage_lines'] ?? null) ? $fareSummary['baggage_lines'] : [] as $line) {
            self::parseBaggageLine($line, $checkedCandidates, $cabinCandidates, $summaryCandidates);
        }

        $checked = self::bestAllowance($checkedCandidates);
        $cabin = self::bestAllowance($cabinCandidates);
        $summary = self::bestAllowance($summaryCandidates);

        if ($summary === null && ($checked !== null || $cabin !== null)) {
            $summary = BaggageDisplayNormalizer::formatAllowance($checked, $cabin)['summary'];
        }

        if ($checked === null || $cabin === null) {
            $split = BaggageDisplayNormalizer::splitCombinedSummary($summary);
            $checked ??= $split['checked'];
            $cabin ??= $split['cabin'];
        }

        return [
            'checked' => $checked,
            'cabin' => $cabin,
            'summary' => $summary,
        ];
    }

    /**
     * Normalize and fill missing carry-on / checked fields on a fare-option row.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public static function enrichFareOptionRow(array $row, array $offer = []): array
    {
        $checkedCandidates = [];
        $cabinCandidates = [];
        $summaryCandidates = [];

        self::collectFromFareOptionRow($row, $checkedCandidates, $cabinCandidates, $summaryCandidates);

        if ($checkedCandidates === [] && $cabinCandidates === [] && $summaryCandidates === []) {
            $offerResolved = self::resolveFromOffer($offer);
            self::pushCandidate($checkedCandidates, $offerResolved['checked']);
            self::pushCandidate($cabinCandidates, $offerResolved['cabin']);
            self::pushCandidate($summaryCandidates, $offerResolved['summary']);
        }

        $checked = self::bestAllowance($checkedCandidates);
        $cabin = self::bestAllowance($cabinCandidates);
        $summary = self::bestAllowance($summaryCandidates);

        if ($summary === null && ($checked !== null || $cabin !== null)) {
            $summary = BaggageDisplayNormalizer::formatAllowance($checked, $cabin)['summary'];
        }

        if ($checked === null || $cabin === null) {
            $split = BaggageDisplayNormalizer::splitCombinedSummary($summary);
            $checked ??= $split['checked'];
            $cabin ??= $split['cabin'];
        }

        if ($checked !== null) {
            $row['check_in_summary'] = $checked;
        }
        if ($cabin !== null) {
            $row['carry_on_summary'] = $cabin;
        }
        if ($summary !== null) {
            $row['baggage_summary'] = $summary;
        }

        return $row;
    }

    /**
     * @param  list<string>  $checkedCandidates
     * @param  list<string>  $cabinCandidates
     * @param  list<string>  $summaryCandidates
     */
    protected static function collectFromFareOptionRow(array $row, array &$checkedCandidates, array &$cabinCandidates, array &$summaryCandidates): void
    {
        self::pushCandidate($checkedCandidates, $row['check_in_summary'] ?? $row['checked_baggage'] ?? null);
        self::pushCandidate($cabinCandidates, $row['carry_on_summary'] ?? $row['carry_on'] ?? null);
        self::pushCandidate($summaryCandidates, $row['baggage_summary'] ?? $row['baggage'] ?? null);
        self::collectFromBaggageArray($row['baggage'] ?? null, $checkedCandidates, $cabinCandidates, $summaryCandidates);

        foreach (is_array($row['baggage_lines'] ?? null) ? $row['baggage_lines'] : [] as $line) {
            self::parseBaggageLine($line, $checkedCandidates, $cabinCandidates, $summaryCandidates);
        }
    }

    /**
     * @param  list<string>  $checkedCandidates
     * @param  list<string>  $cabinCandidates
     * @param  list<string>  $summaryCandidates
     */
    protected static function collectFromBaggageArray(mixed $baggage, array &$checkedCandidates, array &$cabinCandidates, array &$summaryCandidates): void
    {
        if (! is_array($baggage)) {
            if (is_string($baggage) && trim($baggage) !== '') {
                self::parseBaggageLine($baggage, $checkedCandidates, $cabinCandidates, $summaryCandidates);
            }

            return;
        }

        self::pushCandidate($checkedCandidates, $baggage['checked'] ?? null);
        self::pushCandidate($cabinCandidates, $baggage['cabin'] ?? null);
        self::pushCandidate($summaryCandidates, $baggage['summary'] ?? null);

        $split = BaggageDisplayNormalizer::splitCombinedSummary($baggage['summary'] ?? null);
        self::pushCandidate($checkedCandidates, $split['checked']);
        self::pushCandidate($cabinCandidates, $split['cabin']);
    }

    /**
     * @param  list<string>  $checkedCandidates
     * @param  list<string>  $cabinCandidates
     * @param  list<string>  $summaryCandidates
     */
    protected static function parseBaggageLine(mixed $line, array &$checkedCandidates, array &$cabinCandidates, array &$summaryCandidates): void
    {
        if (is_array($line)) {
            $label = trim((string) ($line['label'] ?? $line['type'] ?? ''));
            $value = trim((string) ($line['text'] ?? $line['value'] ?? $line['summary'] ?? ''));
            if ($value === '') {
                return;
            }
            $bucket = self::bucketForBaggageLabel($label !== '' ? $label : $value);
            if ($bucket === 'cabin') {
                self::pushCandidate($cabinCandidates, $value);
            } elseif ($bucket === 'checked') {
                self::pushCandidate($checkedCandidates, $value);
            } else {
                self::pushCandidate($summaryCandidates, $value);
            }

            return;
        }

        $text = trim((string) $line);
        if ($text === '') {
            return;
        }

        if (preg_match('/^(checked|check-in|check in|hold)\s*:\s*(.+)$/i', $text, $matches)) {
            self::pushCandidate($checkedCandidates, trim($matches[2]));

            return;
        }
        if (preg_match('/^(carry-on|carry on|cabin|hand)\s*:\s*(.+)$/i', $text, $matches)) {
            self::pushCandidate($cabinCandidates, trim($matches[2]));

            return;
        }

        $bucket = self::bucketForBaggageLabel($text);
        if ($bucket === 'cabin') {
            self::pushCandidate($cabinCandidates, $text);
        } elseif ($bucket === 'checked') {
            self::pushCandidate($checkedCandidates, $text);
        } else {
            self::pushCandidate($summaryCandidates, $text);
            $split = BaggageDisplayNormalizer::splitCombinedSummary($text);
            self::pushCandidate($checkedCandidates, $split['checked']);
            self::pushCandidate($cabinCandidates, $split['cabin']);
        }
    }

    protected static function bucketForBaggageLabel(string $label): ?string
    {
        $norm = strtolower(preg_replace('/[^a-z0-9]+/i', '', $label) ?? '');
        if ($norm === '') {
            return null;
        }
        if (str_contains($norm, 'carryon') || str_contains($norm, 'cabinbag') || str_contains($norm, 'handbag')) {
            return 'cabin';
        }
        if (str_contains($norm, 'checked') || str_contains($norm, 'checkin') || str_contains($norm, 'holdbag')) {
            return 'checked';
        }

        return null;
    }

    /**
     * @param  list<string>  $candidates
     */
    protected static function pushCandidate(array &$candidates, mixed $value): void
    {
        if ($value === null) {
            return;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return;
        }
        $candidates[] = $text;
    }

    /**
     * @param  list<string>  $candidates
     */
    protected static function bestAllowance(array $candidates): ?string
    {
        $normalized = [];
        foreach ($candidates as $candidate) {
            $label = BaggageDisplayNormalizer::normalizeLabel($candidate);
            if ($label !== null && $label !== '') {
                $normalized[] = $label;
            }
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return null;
        }

        usort($normalized, static fn (string $a, string $b): int => self::specificityScore($b) <=> self::specificityScore($a));

        return $normalized[0];
    }

    protected static function specificityScore(string $label): int
    {
        if (preg_match('/\d+\s*kg\b/i', $label)) {
            return 3;
        }
        if (preg_match('/\d+\s*(piece|pieces)\b/i', $label)) {
            return 2;
        }

        return 1;
    }
}
