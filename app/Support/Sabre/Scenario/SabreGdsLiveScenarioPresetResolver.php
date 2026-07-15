<?php

namespace App\Support\Sabre\Scenario;

/**
 * Resolves {@code --preset=} into search/booking scenario parameters.
 *
 * @phpstan-type ScenarioParams array{
 *     preset: string|null,
 *     origin: string,
 *     destination: string,
 *     departure_date: string,
 *     return_date: string|null,
 *     trip_type: string,
 *     carrier: string|null,
 *     stops: string,
 *     fare_pick: string,
 *     scenario_key: string,
 *     plan_only?: bool
 * }
 */
final class SabreGdsLiveScenarioPresetResolver
{
    /** @var list<string> */
    public const PRESET_KEYS = [
        'pk-direct',
        'qr-connecting',
        'gf-connecting',
        'ey-connecting',
        'two-stop',
        'three-stop',
        'four-stop',
        'mixed-connecting',
        'mixed-multistop',
        'mixed-return',
        'return-any',
        'all-basic',
        'multicity',
    ];

    /** @var list<string> */
    public const ADVANCED_PLAN_PRESETS = [
        'three-stop',
        'four-stop',
    ];

    /** @var list<string> */
    public const MIXED_CARRIER_PRESETS = [
        'mixed-connecting',
        'mixed-multistop',
        'mixed-return',
    ];

    public function isMixedCarrierPreset(?string $preset): bool
    {
        if ($preset === null || trim($preset) === '') {
            return false;
        }

        return in_array(strtolower(trim($preset)), self::MIXED_CARRIER_PRESETS, true);
    }

    /**
     * @return list<ScenarioParams>
     */
    public function resolve(
        ?string $preset,
        string $origin,
        string $destination,
        string $departureDate,
        ?string $returnDate,
        string $tripType,
        ?string $carrier,
        string $stops,
        string $farePick,
    ): array {
        $preset = $preset !== null ? strtolower(trim($preset)) : null;
        if ($preset === null || $preset === '') {
            return [$this->single(
                null,
                $origin,
                $destination,
                $departureDate,
                $returnDate,
                $tripType,
                $carrier,
                $stops,
                $farePick,
            )];
        }

        if ($preset === 'all-basic') {
            $matrix = [];
            foreach (['pk-direct', 'qr-connecting', 'gf-connecting', 'ey-connecting', 'two-stop', 'return-any'] as $key) {
                $matrix[] = $this->fromPresetKey(
                    $key,
                    $origin,
                    $destination,
                    $departureDate,
                    $returnDate,
                    $farePick,
                );
            }

            return $matrix;
        }

        if (! in_array($preset, self::PRESET_KEYS, true)) {
            throw new \InvalidArgumentException('Unknown preset: '.$preset);
        }

        return [$this->fromPresetKey($preset, $origin, $destination, $departureDate, $returnDate, $farePick)];
    }

    /**
     * @return ScenarioParams
     */
    public function fromPresetKey(
        string $preset,
        string $origin,
        string $destination,
        string $departureDate,
        ?string $returnDate,
        string $farePick,
    ): array {
        return match ($preset) {
            'pk-direct' => $this->single($preset, $origin, $destination, $departureDate, null, 'one_way', 'PK', '0', $farePick),
            'qr-connecting' => $this->single($preset, $origin, $destination, $departureDate, null, 'one_way', 'QR', '1', $farePick),
            'gf-connecting' => $this->single($preset, $origin, $destination, $departureDate, null, 'one_way', 'GF', '1', $farePick),
            'ey-connecting' => $this->single($preset, $origin, $destination, $departureDate, null, 'one_way', 'EY', '1', $farePick),
            'two-stop' => $this->single($preset, $origin, $destination, $departureDate, null, 'one_way', null, '2', $farePick),
            'three-stop' => $this->single($preset, $origin, $destination, $departureDate, null, 'one_way', null, '3', $farePick, 'ow_three_stop', true),
            'four-stop' => $this->single($preset, $origin, $destination, $departureDate, null, 'one_way', null, '4', $farePick, 'ow_four_stop', true),
            'mixed-connecting' => $this->single($preset, $origin, $destination, $departureDate, null, 'one_way', null, '1', $farePick, 'ow_mixed_connecting', false, true),
            'mixed-multistop' => $this->single($preset, $origin, $destination, $departureDate, null, 'one_way', null, 'ANY', $farePick, 'ow_mixed_multistop', false, true),
            'mixed-return' => $this->single($preset, $origin, $destination, $departureDate, $returnDate, 'return', null, 'ANY', $farePick, 'return_mixed', false, true),
            'return-any' => $this->single($preset, $origin, $destination, $departureDate, $returnDate, 'return', null, 'ANY', $farePick),
            default => throw new \InvalidArgumentException('Unknown preset: '.$preset),
        };
    }

    public function isAdvancedPlanPreset(?string $preset): bool
    {
        if ($preset === null || trim($preset) === '') {
            return false;
        }

        return in_array(strtolower(trim($preset)), self::ADVANCED_PLAN_PRESETS, true);
    }

    /**
     * @return ScenarioParams
     */
    protected function single(
        ?string $preset,
        string $origin,
        string $destination,
        string $departureDate,
        ?string $returnDate,
        string $tripType,
        ?string $carrier,
        string $stops,
        string $farePick,
        ?string $scenarioKey = null,
        bool $planOnly = false,
        bool $mixedCarrierPreset = false,
    ): array {
        $origin = strtoupper(trim($origin));
        $destination = strtoupper(trim($destination));
        $tripType = strtolower(trim($tripType));
        $stops = strtoupper(trim($stops));
        $carrier = $carrier !== null ? strtoupper(trim($carrier)) : null;
        if ($carrier === 'ANY') {
            $carrier = null;
        }

        $resolvedScenarioKey = $scenarioKey ?? match (true) {
            $tripType === 'return' && $preset === 'mixed-return' => 'return_mixed',
            $tripType === 'return' => 'return',
            $stops === '4' => 'ow_four_stop',
            $stops === '3' => 'ow_three_stop',
            $stops === '2' => 'ow_two_stop',
            $stops === '1' => 'ow_connecting',
            default => 'ow_direct',
        };

        $params = [
            'preset' => $preset,
            'origin' => $origin,
            'destination' => $destination,
            'departure_date' => trim($departureDate),
            'return_date' => $returnDate !== null && trim($returnDate) !== '' ? trim($returnDate) : null,
            'trip_type' => $tripType,
            'carrier' => $carrier,
            'stops' => $stops === '' ? 'ANY' : $stops,
            'fare_pick' => trim($farePick),
            'scenario_key' => $resolvedScenarioKey,
        ];
        if ($planOnly) {
            $params['plan_only'] = true;
        }
        if ($mixedCarrierPreset) {
            $params['mixed_carrier_preset'] = true;
        }

        return $params;
    }
}
