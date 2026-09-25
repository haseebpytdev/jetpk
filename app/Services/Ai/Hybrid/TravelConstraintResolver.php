<?php

namespace App\Services\Ai\Hybrid;

final class TravelConstraintResolver
{
    /**
     * @return array{max_stops: ?int, ranking: ?string, time_preference: ?string, cabin: ?string, provenance: array<string, string>}
     */
    public function resolve(string $normalized, string $original): array
    {
        $prov = [];
        $maxStops = null;
        $ranking = null;
        $time = null;
        $cabin = null;
        $hay = $normalized.' '.$original;

        if (preg_match('/\b(business\s*class|business\s*cabin|make (it|that) business)\b/u', $hay) === 1
            && preg_match('/\b(economy|premium\s*economy|first\s*class)\b/u', $hay) !== 1) {
            $cabin = 'business';
            $prov['cabin'] = 'EXPLICIT_USER';
        } elseif (preg_match('/\b(first\s*class|\bfirst\s*cabin)\b/u', $hay) === 1) {
            $cabin = 'first';
            $prov['cabin'] = 'EXPLICIT_USER';
        } elseif (preg_match('/\b(premium\s*economy)\b/u', $hay) === 1) {
            $cabin = 'premium_economy';
            $prov['cabin'] = 'EXPLICIT_USER';
        } elseif (preg_match('/\b(economy\s*class|\beconomy\b)/u', $hay) === 1) {
            $cabin = 'economy';
            $prov['cabin'] = 'EXPLICIT_USER';
        }

        if (preg_match('/\bdirect\b|\bseedhi\b|\bnonstop\b|براہ\s*راست|سیدھا/u', $hay) === 1) {
            $maxStops = 0;
            $prov['max_stops'] = 'EXPLICIT_USER';
        } elseif (preg_match('/\b1\s*stop\b|\bone\s*stop\b/u', $hay) === 1) {
            $maxStops = 1;
            $prov['max_stops'] = 'EXPLICIT_USER';
        }

        if (preg_match('/\bcheapest\b|\bcheap\b|\bsasti\b|سستی/u', $hay) === 1) {
            $ranking = 'CHEAPEST';
            $prov['ranking_preference'] = 'EXPLICIT_USER';
        } elseif (preg_match('/\bfastest\b|\bfast\b|\bjaldi\b/u', $hay) === 1) {
            $ranking = 'FASTEST';
            $prov['ranking_preference'] = 'EXPLICIT_USER';
        } elseif (preg_match('/short(est)?\s*layover|long\s*layover\s*nahi/u', $hay) === 1) {
            $ranking = 'SHORTEST_LAYOVER';
            $prov['ranking_preference'] = 'EXPLICIT_USER';
        } elseif (preg_match('/\bbest value\b|\bbest option\b|\bbest\b/u', $hay) === 1) {
            $ranking = 'BEST_VALUE';
            $prov['ranking_preference'] = 'EXPLICIT_USER';
        }

        if (preg_match('/\bmorning\b|\bsubah\b|صبح/u', $hay) === 1) {
            $time = 'morning';
            $prov['time_preference'] = 'EXPLICIT_USER';
        } elseif (preg_match('/\bevening\b|\bshaam\b|شام/u', $hay) === 1) {
            $time = 'evening';
            $prov['time_preference'] = 'EXPLICIT_USER';
        } elseif (preg_match('/\bnight\b|\braat\b|رات/u', $hay) === 1) {
            $time = 'night';
            $prov['time_preference'] = 'EXPLICIT_USER';
        }

        return [
            'max_stops' => $maxStops,
            'ranking' => $ranking,
            'time_preference' => $time,
            'cabin' => $cabin,
            'provenance' => $prov,
        ];
    }
}
