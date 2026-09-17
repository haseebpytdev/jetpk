<?php

namespace App\Services\Ai\Hybrid;

final class PassengerExpressionResolver
{
    /**
     * @return array{adults: ?int, children: ?int, infants: ?int, provenance: array<string, string>}
     */
    public function resolve(string $normalized, string $original): array
    {
        $prov = [];
        $adults = null;
        $children = null;
        $infants = null;
        $hay = $normalized.' '.$original;

        if (preg_match('/(\d+)\s*adults?/iu', $hay, $m) === 1
            || preg_match('/(\d+)\s*bara\b/u', $hay, $m) === 1
            || preg_match('/(two|do|دو)\s*(adults?|bara|بڑے)/u', $hay, $m) === 1) {
            $adults = isset($m[1]) && is_numeric($m[1]) ? (int) $m[1] : 2;
            if (preg_match('/^(two|do|دو)$/u', (string) ($m[1] ?? '')) === 1) {
                $adults = 2;
            }
            $prov['adults'] = 'EXPLICIT_USER';
        }
        if (preg_match('/(دو)\s*بڑے/u', $original) === 1) {
            $adults = 2;
            $prov['adults'] = 'EXPLICIT_USER';
        }

        if (preg_match('/(\d+)\s*(child|children|kids?|bacha)\b/iu', $hay, $m) === 1
            || preg_match('/(aik|ek|one|ایک)\s*(bacha|child|بچہ)/u', $hay, $m) === 1) {
            $children = is_numeric($m[1] ?? null) ? (int) $m[1] : 1;
            if (preg_match('/^(aik|ek|one|ایک)$/u', (string) ($m[1] ?? '')) === 1) {
                $children = 1;
            }
            $prov['children'] = 'EXPLICIT_USER';
        }
        if (preg_match('/ایک\s*بچہ/u', $original) === 1) {
            $children = 1;
            $prov['children'] = 'EXPLICIT_USER';
        }

        if (preg_match('/(\d+)\s*infants?/iu', $hay, $m) === 1
            || preg_match('/(aik|ek|one|ایک)\s*(infant|شیر\s*خوار)/u', $hay, $m) === 1) {
            $infants = is_numeric($m[1] ?? null) ? (int) $m[1] : 1;
            if (preg_match('/^(aik|ek|one|ایک)$/u', (string) ($m[1] ?? '')) === 1) {
                $infants = 1;
            }
            $prov['infants'] = 'EXPLICIT_USER';
        }

        return [
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'provenance' => $prov,
        ];
    }
}
