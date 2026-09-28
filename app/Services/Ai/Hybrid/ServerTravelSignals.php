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
        private readonly PassengerExpressionResolver $passengers,
        private readonly TravelConstraintResolver $constraints,
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
        $auth = $this->progressiveTravelAuthority($message);

        return [
            'explicit' => $auth['explicit_route'],
            'origin' => $auth['origin'],
            'destination' => $auth['destination'],
            'server_single_route' => $auth['explicit_route'] && $auth['origin'] && $auth['destination']
                ? $auth['origin'].'-'.$auth['destination']
                : null,
        ];
    }

    /**
     * Progressive travel authority for destination-led / origin-only / refinements (CQ43-R1).
     *
     * @param  array<string, mixed>|null  $priorState
     * @return array{
     *   active: bool,
     *   explicit_route: bool,
     *   origin: ?string,
     *   destination: ?string,
     *   origin_explicit: bool,
     *   destination_explicit: bool,
     *   travel_start: bool,
     *   travel_refinement: bool,
     *   date_refinement: bool,
     *   pax_refinement: bool,
     *   cabin_refinement: bool,
     *   return_refinement: bool,
     *   stop_refinement: bool,
     *   origin_only: bool,
     *   destination_only: bool
     * }
     */
    public function progressiveTravelAuthority(string $message, ?array $priorState = null): array
    {
        $norm = $this->normalizer->normalize($message);
        $od = $this->locations->extractProgressiveOd($norm['normalized'], $norm['original']);
        $lower = mb_strtolower(trim($message));

        $hasActiveTravel = is_array($priorState)
            && (
                (isset($priorState['origin']) && is_string($priorState['origin']) && $priorState['origin'] !== '')
                || (isset($priorState['destination']) && is_string($priorState['destination']) && $priorState['destination'] !== '')
                || ! empty($priorState['flight_search_pending_confirmation'])
                || (isset($priorState['intent']) && in_array($priorState['intent'], ['flight_search', 'group_search'], true))
            );

        $dateRefinement = $this->isDateRefinement($lower, $norm['normalized']);
        $returnRefinement = $this->isReturnRefinement($lower, $norm['normalized'], $hasActiveTravel);
        $paxRefinement = $this->isPaxRefinement($norm['normalized'], $norm['original']);
        $cabinRefinement = $this->isCabinRefinement($norm['normalized'], $norm['original']);

        // CQ45: stop-count / directness from TravelConstraintResolver (max_stops=0 is valid).
        $constraints = $this->constraints->resolve($norm['normalized'], $norm['original']);
        $stopRefinement = array_key_exists('max_stops', $constraints)
            && $constraints['max_stops'] !== null;

        $travelStart = $od['destination_only'] || $od['explicit_route']
            || ($od['origin_only'] && $hasActiveTravel);
        $travelRefinement = $hasActiveTravel && (
            $od['origin_only']
            || $od['destination_only']
            || $od['explicit_route']
            || $dateRefinement
            || $returnRefinement
            || $paxRefinement
            || $cabinRefinement
            || $stopRefinement
        );

        $active = $od['explicit_route']
            || $od['origin_only']
            || $od['destination_only']
            || $travelStart
            || $travelRefinement
            || $dateRefinement
            || $returnRefinement
            || $paxRefinement
            || $cabinRefinement
            || $stopRefinement;

        // Merge progressive slots with prior when role-partial.
        $origin = $od['origin'];
        $destination = $od['destination'];
        if ($od['origin_only'] && $destination === null && is_array($priorState)) {
            $destination = is_string($priorState['destination'] ?? null) ? (string) $priorState['destination'] : null;
        }
        if ($od['destination_only'] && $origin === null && is_array($priorState)) {
            $origin = is_string($priorState['origin'] ?? null) ? (string) $priorState['origin'] : null;
        }

        return [
            'active' => $active,
            'explicit_route' => $od['explicit_route'],
            'origin' => $origin,
            'destination' => $destination,
            'origin_explicit' => $od['origin_explicit'],
            'destination_explicit' => $od['destination_explicit'],
            'travel_start' => $travelStart || $od['destination_only'] || $od['explicit_route'],
            'travel_refinement' => $travelRefinement,
            'date_refinement' => $dateRefinement,
            'pax_refinement' => $paxRefinement,
            'cabin_refinement' => $cabinRefinement,
            'return_refinement' => $returnRefinement,
            'stop_refinement' => $stopRefinement,
            'origin_only' => $od['origin_only'],
            'destination_only' => $od['destination_only'],
            'origin_ambiguous' => (bool) ($od['origin_ambiguous'] ?? false),
            'dest_ambiguous' => (bool) ($od['dest_ambiguous'] ?? false),
        ];
    }

    /**
     * CQ44-PERF-01: true when shared server authorities fully resolve a material
     * refinement against active travel context — Qwen planner may be skipped.
     *
     * @param  array<string, mixed>|null  $priorState
     * @return array{
     *   complete: bool,
     *   reason: string,
     *   classes: list<string>,
     *   authority: array<string, mixed>
     * }
     */
    public function deterministicAuthorityComplete(string $message, ?array $priorState = null): array
    {
        $norm = $this->normalizer->normalize($message);
        $authority = $this->progressiveTravelAuthority($message, $priorState);
        $classes = [];

        $hasActiveTravel = is_array($priorState)
            && (
                (isset($priorState['origin']) && is_string($priorState['origin']) && $priorState['origin'] !== '')
                || (isset($priorState['destination']) && is_string($priorState['destination']) && $priorState['destination'] !== '')
                || ! empty($priorState['flight_search_pending_confirmation'])
                || (isset($priorState['intent']) && in_array($priorState['intent'], ['flight_search', 'group_search'], true))
            );

        // Structural blockers first (before no_active_travel / prior multi-leg).
        $openJaw = $this->locations->extractOpenJawLegs($norm['normalized'], $norm['original']);
        if (is_array($openJaw) && count($openJaw) >= 2) {
            return ['complete' => false, 'reason' => 'open_jaw_multi_leg', 'classes' => [], 'authority' => $authority];
        }

        if (! empty($authority['origin_ambiguous']) || ! empty($authority['dest_ambiguous'])) {
            return ['complete' => false, 'reason' => 'ambiguous_location', 'classes' => [], 'authority' => $authority];
        }

        // CQ44-PERF-02: complete clear explicit simple A→B with CURRENT-TURN date.
        // Qualifies even with no_active_travel or prior multi-leg (explicit replacement).
        // Return-route optimization is NOT enabled in this phase.
        $currentDates = $this->resolveTripDates($message, null, null);
        $origin = is_string($authority['origin'] ?? null) ? (string) $authority['origin'] : '';
        $destination = is_string($authority['destination'] ?? null) ? (string) $authority['destination'] : '';
        if (
            ! empty($authority['explicit_route'])
            && $origin !== ''
            && $destination !== ''
            && strtoupper($origin) !== strtoupper($destination)
            && ! empty($currentDates['depart_explicit'])
            && is_string($currentDates['depart_date'] ?? null)
            && $currentDates['depart_date'] !== ''
            && empty($currentDates['return_explicit'])
            && ! $this->explicitReturnTripCue($message)
        ) {
            // CQ44-PERF-02.1: simple A→B fast path cannot silently drop a third
            // resolved routing location (via / through / stopover / etc.).
            $routeMentions = $this->locations->resolvedLocationMentions(
                $norm['normalized'],
                $norm['original']
            );
            if (count($routeMentions) > 2) {
                return [
                    'complete' => false,
                    'reason' => 'multi_location_requires_semantic',
                    'classes' => [],
                    'authority' => $authority,
                ];
            }

            return [
                'complete' => true,
                'reason' => 'deterministic_authority_complete',
                'classes' => ['explicit_route_complete'],
                'authority' => $authority,
            ];
        }

        if (! $hasActiveTravel) {
            return ['complete' => false, 'reason' => 'no_active_travel', 'classes' => [], 'authority' => $authority];
        }

        // CQ44-PERF-01.1: prior open-jaw / multi-leg state makes bare refinements
        // ambiguous (which leg?). Keep Qwen — do not short-circuit.
        $priorLegs = is_array($priorState) ? ($priorState['legs'] ?? null) : null;
        $priorMultiLeg = is_array($priorState) && (
            ($priorState['trip_type'] ?? null) === 'open_jaw'
            || (is_array($priorLegs) && count($priorLegs) >= 2)
        );
        if ($priorMultiLeg) {
            return [
                'complete' => false,
                'reason' => 'prior_multi_leg_requires_semantic',
                'classes' => [],
                'authority' => $authority,
            ];
        }

        // Explicit A→B without current-turn date (or with return cue) keeps Qwen.
        if (! empty($authority['explicit_route'])) {
            return ['complete' => false, 'reason' => 'explicit_route_keep_qwen', 'classes' => [], 'authority' => $authority];
        }
        if (! empty($authority['destination_only']) && ! (
            is_array($priorState)
            && isset($priorState['origin'])
            && is_string($priorState['origin'])
            && $priorState['origin'] !== ''
        )) {
            return ['complete' => false, 'reason' => 'destination_led_start', 'classes' => [], 'authority' => $authority];
        }

        $priorDepart = is_array($priorState) && is_string($priorState['depart_date'] ?? null)
            ? (string) $priorState['depart_date']
            : null;

        // Origin follow-up: "from Lahore" / "Lahore se" with prior destination.
        if (! empty($authority['origin_only'])
            && is_string($authority['origin'] ?? null)
            && $authority['origin'] !== ''
            && is_string($authority['destination'] ?? null)
            && $authority['destination'] !== ''
            && strtoupper((string) $authority['origin']) !== strtoupper((string) $authority['destination'])
        ) {
            $classes[] = 'origin_followup';
        }

        // Destination correction with prior origin: "Make it Doha".
        if (! empty($authority['destination_only'])
            && is_string($authority['destination'] ?? null)
            && $authority['destination'] !== ''
            && is_string($authority['origin'] ?? null)
            && $authority['origin'] !== ''
            && strtoupper((string) $authority['origin']) !== strtoupper((string) $authority['destination'])
        ) {
            $classes[] = 'destination_correction';
        }

        if (! empty($authority['date_refinement'])) {
            $dates = $this->resolveTripDates($message, null, $priorDepart);
            if (! empty($dates['depart_explicit']) && is_string($dates['depart_date'] ?? null)) {
                $classes[] = 'date_refinement';
            } elseif (! empty($dates['return_explicit']) && is_string($dates['return_date'] ?? null)) {
                $classes[] = 'date_refinement';
            } else {
                return ['complete' => false, 'reason' => 'date_unresolved', 'classes' => [], 'authority' => $authority];
            }
        }

        if (! empty($authority['pax_refinement'])) {
            $pax = $this->passengers->resolve($norm['normalized'], $norm['original']);
            if (($pax['adults'] ?? null) === null
                && ($pax['children'] ?? null) === null
                && ($pax['infants'] ?? null) === null
            ) {
                return ['complete' => false, 'reason' => 'pax_unresolved', 'classes' => [], 'authority' => $authority];
            }
            $classes[] = 'pax_refinement';
        }

        if (! empty($authority['cabin_refinement'])) {
            $cons = $this->constraints->resolve($norm['normalized'], $norm['original']);
            if (! is_string($cons['cabin'] ?? null) || $cons['cabin'] === '') {
                return ['complete' => false, 'reason' => 'cabin_unresolved', 'classes' => [], 'authority' => $authority];
            }
            $classes[] = 'cabin_refinement';
        }

        if (! empty($authority['return_refinement'])) {
            $dates = $this->resolveTripDates($message, null, $priorDepart);
            if (empty($dates['return_explicit']) || ! is_string($dates['return_date'] ?? null)) {
                // English "come back on Sunday" / contextual wapis must resolve a return date.
                return ['complete' => false, 'reason' => 'return_unresolved', 'classes' => [], 'authority' => $authority];
            }
            $classes[] = 'return_refinement';
        }

        // CQ45: stop-count / directness after prior_multi_leg_requires_semantic blocker.
        if (! empty($authority['stop_refinement'])) {
            $cons = $this->constraints->resolve($norm['normalized'], $norm['original']);
            // max_stops=0 (direct) is a valid explicit value — never use truthiness.
            if (! array_key_exists('max_stops', $cons) || $cons['max_stops'] === null) {
                return ['complete' => false, 'reason' => 'stop_unresolved', 'classes' => [], 'authority' => $authority];
            }
            $classes[] = 'stop_refinement';
        }

        if ($classes === []) {
            return ['complete' => false, 'reason' => 'no_material_refinement', 'classes' => [], 'authority' => $authority];
        }

        return [
            'complete' => true,
            'reason' => 'deterministic_authority_complete',
            'classes' => array_values(array_unique($classes)),
            'authority' => $authority,
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
            // Bare wapis with a weekday / date and no fresh A→B route = contextual return.
            if ($this->isContextualWapisReturn($m)) {
                return true;
            }

            return false;
        }

        if (preg_match('/\b(come|coming)\s+back\s+on\b/u', $m) === 1) {
            return true;
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

        $returnInfo = $this->dates->resolveReturn($normalized, $original, $now, $priorDepart);
        $returnDate = is_string($returnInfo['date'] ?? null) ? (string) $returnInfo['date'] : null;
        $returnExplicit = $returnDate !== null;

        $stripped = $normalized;
        $cueRegex = $this->dates->returnDateCueRegex();
        if (preg_match($cueRegex, $normalized, $m) === 1) {
            $stripped = trim((string) preg_replace($cueRegex, '', $normalized, 1));
            if ($returnDate === null) {
                // Re-resolve against the isolated return token when the full-string matcher missed.
                $retry = $this->dates->resolveReturn('return '.$m[1], 'return '.$m[1], $now, $priorDepart);
                if (is_string($retry['date'] ?? null)) {
                    $returnDate = (string) $retry['date'];
                    $returnExplicit = true;
                    $returnInfo = $retry;
                }
            }
        }

        // Bare "return 15 October" / "return on 15 October" → do not invent a new departure.
        $returnOnly = $stripped === ''
            || preg_match('/^(please\s+)?(make\s+it\s+)?(return(ing)?|come\s+back|wapis|wapas)(\s+date)?(\s+on)?\.?$/iu', $stripped) === 1
            || preg_match('/^(?:i\s+also\s+need\s+to\s+)?(?:come|coming)\s+back(\s+on)?\.?$/iu', $stripped) === 1;

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

    private function isDateRefinement(string $lower, string $normalized): bool
    {
        if (preg_match('/\b\d{4}-\d{2}-\d{2}\b/u', $lower) === 1) {
            return true;
        }
        if (preg_match('/\b(\d{1,2})(st|nd|rd|th)?\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t|tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\b/u', $lower) === 1) {
            return true;
        }
        if (preg_match('/\b(today|tomorrow|next\s+friday|next\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)|kal|parso)\b/u', $lower) === 1) {
            return true;
        }

        return preg_match('/\b(today|tomorrow|kal|parso|next\s+friday)\b/u', $normalized) === 1;
    }

    private function isReturnRefinement(string $lower, string $normalized, bool $hasActiveTravel): bool
    {
        if (preg_match('/\b(come|coming)\s+back\s+on\b/u', $lower) === 1) {
            return true;
        }
        if (preg_match('/\breturn(\s+on|\s+date)?\b/u', $lower) === 1
            && ! preg_match('/\b([a-z]{3}|[a-z ]{3,20})\s+(?:to|se)\s+([a-z]{3}|[a-z ]{3,20})\s+return\b/u', $lower)) {
            return true;
        }
        if ($hasActiveTravel && $this->isContextualWapisReturn($lower)) {
            return true;
        }

        return preg_match($this->dates->returnDateCueRegex(), $normalized) === 1;
    }

    private function isContextualWapisReturn(string $lower): bool
    {
        // Fresh "A se B wapis" is directional one-way — not contextual return.
        if (preg_match('/\b([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+(?:to|se)\s+([a-z\p{Arabic}][a-z\p{Arabic} ]{1,24}?)\s+(?:wapis|wapas)\b/u', $lower) === 1) {
            return false;
        }

        return preg_match(
            '/\b(wapis|wapas)\s+(?:on\s+)?(sunday|monday|tuesday|wednesday|thursday|friday|saturday|\d{1,2}\s+[a-z]+|\d{4}-\d{2}-\d{2})\b/u',
            $lower
        ) === 1
            || preg_match(
                '/\b(sunday|monday|tuesday|wednesday|thursday|friday|saturday)\s+(wapis|wapas)\b/u',
                $lower
            ) === 1;
    }

    private function isPaxRefinement(string $normalized, string $original): bool
    {
        if (preg_match('/\b\d+\s*adults?\b|\bhum\s+dono\b|\bham\s+dono\b/u', $normalized.' '.$original) === 1) {
            return true;
        }
        $pax = $this->passengers->resolve($normalized, $original);

        return ($pax['adults'] ?? null) !== null
            || ($pax['children'] ?? null) !== null
            || ($pax['infants'] ?? null) !== null;
    }

    private function isCabinRefinement(string $normalized, string $original): bool
    {
        $cons = $this->constraints->resolve($normalized, $original);

        return is_string($cons['cabin'] ?? null) && $cons['cabin'] !== '';
    }
}
