<?php

namespace App\Services\Ai\Hybrid;

use Carbon\Carbon;

/**
 * Shared server-owned travel signals (route / English return cue / trip dates).
 * Consumed by SemanticPlanValidator, HybridTravelPipeline, ConversationIntentRouter.
 */
final class ServerTravelSignals
{
    public function __construct(
        private readonly LanguageNormalizer $normalizer,
        private readonly LocationResolver $locations,
        private readonly DateExpressionResolver $dates,
    ) {}

    /**
     * @return array{
     *   explicit: bool,
     *   origin: ?string,
     *   destination: ?string,
     *   server_single_route: ?string
     * }
     */
    public function explicitTravelRoute(string $message): array
    {
        $norm = $this->normalizer->normalize($message);
        $route = $this->locations->extractRoute($norm['normalized'], $norm['original']);
        $origin = is_string($route[0] ?? null) && $route[0] !== '' ? (string) $route[0] : null;
        $destination = is_string($route[1] ?? null) && $route[1] !== '' ? (string) $route[1] : null;
        $explicit = $origin !== null && $destination !== null;

        return [
            'explicit' => $explicit,
            'origin' => $origin,
            'destination' => $destination,
            'server_single_route' => $explicit ? $origin.'-'.$destination : null,
        ];
    }

    /**
     * English return / round-trip trip-shape cue.
     * Does NOT treat Roman-Urdu wapis/wapas as round-trip.
     */
    public function explicitReturnTripCue(string $message): bool
    {
        $m = mb_strtolower(trim($message));
        if ($m === '') {
            return false;
        }
        if (preg_match('/\b(wapis|wapas)\b|واپس/u', $m) === 1) {
            return false;
        }

        return preg_match(
            '/\bround[\s-]?trips?\b|\breturn\s+tickets?\b|\breturn\s+flights?\b|\breturn\b/u',
            $m
        ) === 1;
    }

    /**
     * Current-turn trip dates from the user message (pair-aware).
     * Explicit return portion is stripped before departure parsing so
     * "return 15 October" does not rewrite departure.
     *
     * @return array{
     *   depart_date: ?string,
     *   return_date: ?string,
     *   depart_explicit: bool,
     *   return_explicit: bool,
     *   depart_provenance: ?string,
     *   return_provenance: ?string
     * }
     */
    public function resolveTripDates(string $message, ?Carbon $now = null, ?string $priorDepart = null): array
    {
        $now ??= Carbon::now();
        $norm = $this->normalizer->normalize($message);
        $normalized = $norm['normalized'];
        $original = $norm['original'];

        $returnInfo = $this->dates->resolveReturn($normalized, $original, $now);
        $returnDate = is_string($returnInfo['date'] ?? null) ? (string) $returnInfo['date'] : null;
        $returnExplicit = $returnDate !== null;

        $stripped = $normalized;
        if (preg_match(
            '/(?:,?\s*)?(?:return(?:ing)?(?:\s+date)?|wapas|make it return)\s+(\d{1,2}\s+[A-Za-z]+|\d{4}-\d{2}-\d{2}|\d{1,2}(?:st|nd|rd|th)?)/iu',
            $normalized,
            $m
        ) === 1) {
            $stripped = trim((string) preg_replace(
                '/(?:,?\s*)?(?:return(?:ing)?(?:\s+date)?|wapas|make it return)\s+'.preg_quote($m[1], '/').'/iu',
                '',
                $normalized
            ));
            if ($returnDate === null) {
                // Re-resolve against the isolated return token when the full-string matcher missed.
                $retry = $this->dates->resolveReturn('return '.$m[1], 'return '.$m[1], $now);
                if (is_string($retry['date'] ?? null)) {
                    $returnDate = (string) $retry['date'];
                    $returnExplicit = true;
                    $returnInfo = $retry;
                }
            }
        }

        // Bare "return 15 October" / "return on 15 October" → do not invent a new departure.
        $returnOnly = $stripped === ''
            || preg_match('/^(please\s+)?(make\s+it\s+)?return(ing)?(\s+date)?\.?$/iu', $stripped) === 1;

        $departDate = null;
        $departExplicit = false;
        $departProv = null;
        if (! $returnOnly) {
            $departInfo = $this->dates->resolveDepart($stripped, $stripped, $now, $priorDepart);
            if (is_string($departInfo['date'] ?? null)) {
                $departDate = (string) $departInfo['date'];
                $departProv = is_string($departInfo['provenance'] ?? null) ? (string) $departInfo['provenance'] : null;
                $departExplicit = $departProv !== null && $departProv !== 'INHERITED_CONVERSATION_STATE';
            }
        }

        return [
            'depart_date' => $departDate,
            'return_date' => $returnDate,
            'depart_explicit' => $departExplicit,
            'return_explicit' => $returnExplicit,
            'depart_provenance' => $departProv,
            'return_provenance' => is_string($returnInfo['provenance'] ?? null) ? (string) $returnInfo['provenance'] : null,
        ];
    }
}
