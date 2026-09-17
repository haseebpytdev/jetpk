<?php

namespace App\Services\Ai\Hybrid;

final class BudgetNormalizer
{
    /**
     * @return array{amount: ?float, currency: string, provenance: ?string}
     */
    public function resolve(string $normalized, string $original): array
    {
        $s = mb_strtolower($normalized.' '.$original);
        $s = str_replace([',', 'pkr', 'rs.', 'rs'], ['', '', '', ''], $s);

        if (preg_match('/under\s+(\d+(?:\.\d+)?)\s*k\b/u', $s, $m) === 1
            || preg_match('/(\d+(?:\.\d+)?)\s*k\b/u', $s, $m) === 1) {
            return ['amount' => (float) $m[1] * 1000, 'currency' => 'PKR', 'provenance' => 'DETERMINISTIC_NORMALIZATION'];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*hazar/u', $s, $m) === 1 || preg_match('/(\d+)\s*ہزار/u', $original, $m) === 1) {
            return ['amount' => (float) $m[1] * 1000, 'currency' => 'PKR', 'provenance' => 'DETERMINISTIC_NORMALIZATION'];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(lac|lakh)/u', $s, $m) === 1) {
            return ['amount' => (float) $m[1] * 100000, 'currency' => 'PKR', 'provenance' => 'DETERMINISTIC_NORMALIZATION'];
        }
        if (preg_match('/ڈیڑھ\s*لاکھ|dedh\s*lakh|1\.5\s*lakh/u', $s.$original) === 1) {
            return ['amount' => 150000.0, 'currency' => 'PKR', 'provenance' => 'DETERMINISTIC_NORMALIZATION'];
        }
        if (preg_match('/ایک\s*لاکھ\s*پچاس\s*ہزار/u', $original) === 1) {
            return ['amount' => 150000.0, 'currency' => 'PKR', 'provenance' => 'DETERMINISTIC_NORMALIZATION'];
        }
        if (preg_match('/(\d{4,7})/u', $s, $m) === 1 && preg_match('/under|neeche|kam|budget|below|se kam/u', $s) === 1) {
            return ['amount' => (float) $m[1], 'currency' => 'PKR', 'provenance' => 'DETERMINISTIC_NORMALIZATION'];
        }

        return ['amount' => null, 'currency' => 'PKR', 'provenance' => null];
    }
}
