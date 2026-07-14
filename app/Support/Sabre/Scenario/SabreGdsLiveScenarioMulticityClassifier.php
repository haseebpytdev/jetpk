<?php

namespace App\Support\Sabre\Scenario;

/**
 * Classifies true Sabre multi-city plan candidates (same/mixed/interline/discontinuous).
 */
final class SabreGdsLiveScenarioMulticityClassifier
{
    public const CATEGORY_SAME_CARRIER = 'multicity_same_carrier';

    public const CATEGORY_MIXED_CARRIER = 'multicity_mixed_carrier';

    public const CATEGORY_INTERLINE = 'multicity_interline';

    public const CATEGORY_DISCONTINUOUS = 'multicity_discontinuous';

    /**
     * @param  list<array{origin: string, destination: string}>  $requestedSlices
     * @param  list<array{origin: string, destination: string}>  $returnedSlices
     * @param  list<string>  $marketingCarriers
     * @param  list<string>  $operatingCarriers
     * @param  list<array<string, mixed>>  $segments
     * @return array{
     *     classification: string,
     *     discontinuity_detected: bool,
     *     requested_discontinuous: bool,
     *     returned_discontinuous: bool,
     *     interline_detected: bool,
     *     same_carrier: bool,
     *     mixed_carrier: bool
     * }
     */
    public function classify(
        array $requestedSlices,
        array $returnedSlices,
        array $marketingCarriers,
        array $operatingCarriers,
        array $segments = [],
    ): array {
        $requestedDiscontinuous = $this->detectDiscontinuity($requestedSlices);
        $returnedDiscontinuous = $this->detectDiscontinuity($returnedSlices);
        $discontinuityDetected = $requestedDiscontinuous || $returnedDiscontinuous;

        $marketing = array_values(array_unique(array_filter(array_map(
            static fn ($c): string => strtoupper(trim((string) $c)),
            $marketingCarriers,
        ), static fn (string $c): bool => $c !== '')));

        $sameCarrier = count($marketing) <= 1;
        $mixedCarrier = count($marketing) > 1;
        $interlineDetected = $this->detectInterline($marketingCarriers, $operatingCarriers, $segments);

        if ($discontinuityDetected) {
            return [
                'classification' => self::CATEGORY_DISCONTINUOUS,
                'discontinuity_detected' => true,
                'requested_discontinuous' => $requestedDiscontinuous,
                'returned_discontinuous' => $returnedDiscontinuous,
                'interline_detected' => $interlineDetected,
                'same_carrier' => $sameCarrier,
                'mixed_carrier' => $mixedCarrier,
            ];
        }

        if ($interlineDetected) {
            return [
                'classification' => self::CATEGORY_INTERLINE,
                'discontinuity_detected' => false,
                'requested_discontinuous' => false,
                'returned_discontinuous' => false,
                'interline_detected' => true,
                'same_carrier' => $sameCarrier,
                'mixed_carrier' => $mixedCarrier,
            ];
        }

        if ($mixedCarrier) {
            return [
                'classification' => self::CATEGORY_MIXED_CARRIER,
                'discontinuity_detected' => false,
                'requested_discontinuous' => false,
                'returned_discontinuous' => false,
                'interline_detected' => false,
                'same_carrier' => false,
                'mixed_carrier' => true,
            ];
        }

        return [
            'classification' => self::CATEGORY_SAME_CARRIER,
            'discontinuity_detected' => false,
            'requested_discontinuous' => false,
            'returned_discontinuous' => false,
            'interline_detected' => false,
            'same_carrier' => true,
            'mixed_carrier' => false,
        ];
    }

    /**
     * @param  list<array{origin: string, destination: string}>  $slices
     */
    public function detectDiscontinuity(array $slices): bool
    {
        if (count($slices) < 2) {
            return false;
        }

        for ($i = 0; $i < count($slices) - 1; $i++) {
            $currentDest = strtoupper(trim((string) ($slices[$i]['destination'] ?? '')));
            $nextOrigin = strtoupper(trim((string) ($slices[$i + 1]['origin'] ?? '')));
            if ($currentDest !== '' && $nextOrigin !== '' && $currentDest !== $nextOrigin) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $marketingCarriers
     * @param  list<string>  $operatingCarriers
     * @param  list<array<string, mixed>>  $segments
     */
    protected function detectInterline(array $marketingCarriers, array $operatingCarriers, array $segments): bool
    {
        $marketing = array_values(array_filter(array_map(
            static fn ($c): string => strtoupper(trim((string) $c)),
            $marketingCarriers,
        ), static fn (string $c): bool => $c !== ''));

        $operating = array_values(array_filter(array_map(
            static fn ($c): string => strtoupper(trim((string) $c)),
            $operatingCarriers,
        ), static fn (string $c): bool => $c !== ''));

        if (count(array_unique($operating)) > 1) {
            $marketingSet = array_unique($marketing);
            $operatingSet = array_unique($operating);
            if ($marketingSet === $operatingSet) {
                return false;
            }

            return true;
        }

        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $mkt = strtoupper(trim((string) ($seg['marketing_carrier'] ?? $seg['carrier'] ?? '')));
            $op = strtoupper(trim((string) ($seg['operating_carrier'] ?? $seg['operating_airline'] ?? '')));
            if ($mkt !== '' && $op !== '' && $mkt !== $op) {
                return true;
            }
        }

        if ($marketing !== [] && $operating !== []) {
            $mktSet = array_unique($marketing);
            $opSet = array_unique($operating);
            if (count($mktSet) === 1 && count($opSet) === 1 && $mktSet[0] !== $opSet[0]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  list<array{origin: string, destination: string, departure_date?: string}>  $requestedSlices
     * @return list<array{origin: string, destination: string}>
     */
    public function buildReturnedSlicesFromSegments(array $segments, array $requestedSlices): array
    {
        if ($segments === [] || $requestedSlices === []) {
            return [];
        }

        $out = [];
        $segIdx = 0;
        $segCount = count($segments);

        foreach ($requestedSlices as $slice) {
            $sliceOrigin = strtoupper(trim((string) ($slice['origin'] ?? '')));
            $sliceDest = strtoupper(trim((string) ($slice['destination'] ?? '')));
            if ($sliceOrigin === '' || $sliceDest === '') {
                continue;
            }

            while ($segIdx < $segCount) {
                $seg = $segments[$segIdx];
                if (! is_array($seg)) {
                    $segIdx++;

                    continue;
                }
                $segOrigin = strtoupper(trim((string) ($seg['origin'] ?? '')));
                if ($segOrigin === $sliceOrigin) {
                    break;
                }
                $segIdx++;
            }

            if ($segIdx >= $segCount) {
                $out[] = ['origin' => $sliceOrigin, 'destination' => $sliceDest];

                continue;
            }

            $endIdx = $segIdx;
            for ($i = $segIdx; $i < $segCount; $i++) {
                $seg = $segments[$i];
                if (! is_array($seg)) {
                    continue;
                }
                $segDest = strtoupper(trim((string) ($seg['destination'] ?? '')));
                if ($segDest === $sliceDest) {
                    $endIdx = $i;
                    break;
                }
            }

            $out[] = ['origin' => $sliceOrigin, 'destination' => $sliceDest];
            $segIdx = $endIdx + 1;
        }

        return $out;
    }
}
