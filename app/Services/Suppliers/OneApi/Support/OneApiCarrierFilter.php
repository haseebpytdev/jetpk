<?php

namespace App\Services\Suppliers\OneApi\Support;

/**
 * Filters parsed search itineraries by carrier allowlists and interline policy.
 */
class OneApiCarrierFilter
{
    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $config
     */
    public function itineraryPermitted(array $segments, array $config): bool
    {
        if ($segments === []) {
            return false;
        }

        $carrierAllow = $config['carrier_allowlist'] ?? [];
        $marketingAllow = $config['marketing_carrier_allowlist'] ?? [];
        $operatingAllow = $config['operating_carrier_allowlist'] ?? [];
        $allowInterline = (bool) ($config['allow_interline'] ?? false);

        $carriers = [];
        foreach ($segments as $segment) {
            $marketing = strtoupper(trim((string) ($segment['marketing_carrier'] ?? $segment['marketingCarrier'] ?? '')));
            $operating = strtoupper(trim((string) ($segment['operating_carrier'] ?? $segment['operatingCarrier'] ?? '')));
            if ($marketing !== '') {
                $carriers[] = $marketing;
            }
            if ($operating !== '' && $operating !== $marketing) {
                if (! $allowInterline) {
                    return false;
                }
                $carriers[] = $operating;
            }
        }

        $carriers = array_values(array_unique(array_filter($carriers)));
        if ($carriers === []) {
            return false;
        }

        if ($carrierAllow !== [] && count(array_diff($carriers, $carrierAllow)) > 0) {
            return false;
        }

        foreach ($segments as $segment) {
            $marketing = strtoupper(trim((string) ($segment['marketing_carrier'] ?? '')));
            $operating = strtoupper(trim((string) ($segment['operating_carrier'] ?? '')));
            if ($marketingAllow !== [] && $marketing !== '' && ! in_array($marketing, $marketingAllow, true)) {
                return false;
            }
            if ($operatingAllow !== [] && $operating !== '' && ! in_array($operating, $operatingAllow, true)) {
                return false;
            }
        }

        return true;
    }
}
