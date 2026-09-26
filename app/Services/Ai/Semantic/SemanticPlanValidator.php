<?php

namespace App\Services\Ai\Semantic;

use App\Data\Ai\SemanticPlan;
use App\Data\Ai\TravelIntent;
use App\Services\Ai\Hybrid\AirlineResolver;
use App\Services\Ai\Hybrid\DateExpressionResolver;
use App\Services\Ai\Hybrid\LocationResolver;
use App\Services\Ai\Hybrid\PassengerExpressionResolver;
use App\Services\Ai\TravelIntentCanonicalizer;
use Carbon\Carbon;

/**
 * Server-side validation / canonicalization of model semantic plans.
 */
final class SemanticPlanValidator
{
    public function __construct(
        private readonly LocationResolver $locations,
        private readonly DateExpressionResolver $dates,
        private readonly AirlineResolver $airlines,
        private readonly TravelIntentCanonicalizer $canonicalizer,
        private readonly PassengerExpressionResolver $passengers,
    ) {}

    /**
     * @param  array<string, mixed>  $priorState
     * @return array{
     *   valid: bool,
     *   plan: SemanticPlan,
     *   intent: ?TravelIntent,
     *   rejects: list<string>,
     *   missing: list<string>,
     *   explicit_route_precedence: bool,
     *   stale_route_contamination: int
     * }
     */
    public function validate(SemanticPlan $plan, array $priorState, string $userMessage): array
    {
        $rejects = [];
        // Qwen missing[] is advisory only for travel completeness slots — server rebuilds those.
        $travelCompletenessSlots = [
            'origin', 'destination', 'departure_date', 'return_date',
            'leg1_departure_date', 'leg2_departure_date', 'leg3_departure_date',
        ];
        $missing = array_values(array_filter(
            $plan->missing,
            static fn (string $m): bool => ! in_array($m, $travelCompletenessSlots, true)
        ));

        // Forbidden ops already constrained by DTO allowlist; re-check raw for injection.
        $raw = $plan->raw;
        foreach (['execute_now', 'authorize', 'mutation', 'supplier_credentials', 'database'] as $bad) {
            if (array_key_exists($bad, $raw)) {
                $rejects[] = 'forbidden_key:'.$bad;
            }
        }

        $origin = $this->resolveAirport($plan->origin, $rejects);
        $destination = $this->resolveAirport($plan->destination, $rejects);

        // Current-turn explicit multi-leg authority (independent of Qwen).
        $explicitLegs = $this->locations->extractOpenJawLegs(
            mb_strtolower($userMessage),
            $userMessage
        );
        $tripTypeForced = null;
        $stale = 0;
        $legs = [];
        $falseOpenJawDemoted = false;
        $serverSingleRoute = null;

        if (is_array($explicitLegs) && count($explicitLegs) >= 2) {
            foreach ($explicitLegs as $leg) {
                $legs[] = [
                    'origin' => $leg['origin'],
                    'destination' => $leg['destination'],
                    'departure_date' => null,
                ];
            }
            // Preserve matching plan leg dates when sectors match; never keep rewritten sectors.
            foreach ($plan->legs as $i => $planLeg) {
                if (! isset($legs[$i])) {
                    break;
                }
                $poRaw = is_string($planLeg['origin'] ?? null) ? trim((string) $planLeg['origin']) : '';
                $pdRaw = is_string($planLeg['destination'] ?? null) ? trim((string) $planLeg['destination']) : '';
                $po = $poRaw !== '' ? ($this->locations->resolve($poRaw)['code'] ?? null) : null;
                $pd = $pdRaw !== '' ? ($this->locations->resolve($pdRaw)['code'] ?? null) : null;
                if ($po && $pd
                    && $legs[$i]['origin'] === $po
                    && $legs[$i]['destination'] === $pd) {
                    $silentRejects = [];
                    $pdd = $this->resolveDate($planLeg['departure_date'] ?? null, $silentRejects);
                    if ($pdd) {
                        $legs[$i]['departure_date'] = $pdd;
                    }
                }
            }
            $tripTypeForced = 'open_jaw';
            $origin = $legs[0]['origin'];
            $destination = $legs[0]['destination'];
        } else {
            // CQ42-R2: server single-route authority — Qwen must not invent reciprocal open-jaw legs.
            $route = $this->locations->extractRoute(mb_strtolower($userMessage), $userMessage);
            $serverOrigin = is_string($route[0] ?? null) ? (string) $route[0] : null;
            $serverDestination = is_string($route[1] ?? null) ? (string) $route[1] : null;
            $hasServerSingleRoute = $serverOrigin !== null && $serverOrigin !== ''
                && $serverDestination !== null && $serverDestination !== '';

            $qwenLegs = [];
            foreach ($plan->legs as $leg) {
                $o = $this->resolveAirport($leg['origin'] ?? null, $rejects);
                $d = $this->resolveAirport($leg['destination'] ?? null, $rejects);
                $dd = $this->resolveDate($leg['departure_date'] ?? null, $rejects);
                if ($o || $d || $dd) {
                    $qwenLegs[] = [
                        'origin' => $o,
                        'destination' => $d,
                        'departure_date' => $dd,
                    ];
                }
            }

            if ($hasServerSingleRoute) {
                $origin = $serverOrigin;
                $destination = $serverDestination;
                $serverSingleRoute = $serverOrigin.'-'.$serverDestination;
                $qwenTrip = is_string($plan->tripType) ? strtolower((string) $plan->tripType) : null;
                if ($qwenTrip === 'round_trip') {
                    $qwenTrip = 'return';
                }
                $inventedMulti = count($qwenLegs) >= 2
                    || in_array($qwenTrip, ['open_jaw', 'multi_city'], true);
                if ($inventedMulti) {
                    $falseOpenJawDemoted = true;
                    $legs = [];
                    // Preserve genuine return when a return date is present; otherwise one_way.
                    // "X se Y wapis" alone does not invent a second leg.
                } elseif (count($qwenLegs) === 1
                    && ($qwenLegs[0]['origin'] ?? null) === $serverOrigin
                    && ($qwenLegs[0]['destination'] ?? null) === $serverDestination) {
                    $legs = $qwenLegs;
                } else {
                    $legs = [[
                        'origin' => $serverOrigin,
                        'destination' => $serverDestination,
                        'departure_date' => $qwenLegs[0]['departure_date'] ?? null,
                    ]];
                }
            } else {
                $legs = $qwenLegs;
            }
        }

        if ($origin === null && isset($legs[0]['origin'])) {
            $origin = $legs[0]['origin'];
        }
        if ($destination === null && isset($legs[0]['destination'])) {
            $destination = $legs[0]['destination'];
        }

        $depart = $this->resolveDate($plan->departDate, $rejects);
        if ($depart === null && isset($legs[0]['departure_date'])) {
            $depart = $legs[0]['departure_date'];
        }
        $return = $this->resolveDate($plan->returnDate, $rejects);

        // CQ42-R2.1: English return/round-trip cue ≠ Roman-Urdu wapis/wapas.
        $explicitReturnCue = $this->messageImpliesExplicitReturnTripCue($userMessage);

        // Apply demotion trip_type after return date / return cue are known.
        if ($falseOpenJawDemoted) {
            $tripTypeForced = ($explicitReturnCue || $return !== null) ? 'return' : 'one_way';
        }

        $cabin = $this->normalizeCabin($plan->cabin, $rejects);
        $airline = null;
        if ($plan->airline !== null && $plan->airline !== '') {
            $airline = $this->airlines->resolve($plan->airline);
            if ($airline === null) {
                $rejects[] = 'unsupported_airline';
            }
        }

        $tripType = $plan->tripType;
        if (is_string($tripType)) {
            $tripType = strtolower($tripType);
            if ($tripType === 'round_trip') {
                $tripType = 'return';
            }
            if (! in_array($tripType, ['one_way', 'return', 'open_jaw', 'multi_city'], true)) {
                $rejects[] = 'unsupported_trip_type';
                $tripType = null;
            }
        }

        if ($tripTypeForced !== null) {
            $tripType = $tripTypeForced;
        }

        // Server-authoritative English return/round-trip wording (never from wapis/wapas alone).
        if (
            $explicitReturnCue
            && ! in_array($tripType, ['open_jaw', 'multi_city'], true)
        ) {
            $tripType = 'return';
        }

        if (count($legs) >= 2 && $tripType === null) {
            $tripType = 'open_jaw';
        }

        // Explicit current-turn route wins over stale prior origin/destination.
        $explicitRoute = $this->messageImpliesExplicitRoute($userMessage) || $tripTypeForced !== null;
        if ($explicitRoute && $origin && $destination && $tripTypeForced === null) {
            $priorO = isset($priorState['origin']) ? strtoupper((string) $priorState['origin']) : null;
            $priorD = isset($priorState['destination']) ? strtoupper((string) $priorState['destination']) : null;
            if ($priorO && $priorO !== $origin && $origin === $priorO) {
                $stale = 1;
            }
            // Contamination if we kept prior origin despite explicit new route in plan.
            if ($priorO && $priorD && ($priorO !== $origin || $priorD !== $destination)) {
                // Good — plan differs from prior.
                $stale = 0;
            }
        }

        // Server-authoritative passenger normalization (relational pair + explicit counts).
        $pax = $this->passengers->resolve(mb_strtolower($userMessage), $userMessage);
        $adults = $plan->adults;
        $children = $plan->children;
        $infants = $plan->infants;
        if ($pax['adults'] !== null) {
            $adultProv = (string) ($pax['provenance']['adults'] ?? '');
            if ($adultProv === 'EXPLICIT_USER') {
                $adults = (int) $pax['adults'];
            } elseif ($adultProv === 'RELATIONAL_PAIR' && ($adults === null || (int) $adults <= 1)) {
                $adults = (int) $pax['adults'];
            }
        }
        if ($pax['children'] !== null) {
            $children = (int) $pax['children'];
        }
        if ($pax['infants'] !== null) {
            $infants = (int) $pax['infants'];
        }

        if ($plan->domain === 'travel' && in_array($plan->operation, ['prepare_search', 'clarify'], true)) {
            if ($tripType === null || ! in_array($tripType, ['open_jaw', 'multi_city'], true)) {
                if ($origin === null) {
                    $missing[] = 'origin';
                }
                if ($destination === null) {
                    $missing[] = 'destination';
                }
            }
            if ($depart === null && ! in_array($tripType, ['open_jaw', 'multi_city'], true)) {
                $missing[] = 'departure_date';
            }
            // Server-owned: return trip cannot be actionable without return_date.
            if ($tripType === 'return' && $return === null) {
                $missing[] = 'return_date';
            }
            if (in_array($tripType, ['open_jaw', 'multi_city'], true)) {
                if (count($legs) < 2) {
                    $rejects[] = 'open_jaw_requires_two_legs';
                }
                foreach ($legs as $i => $leg) {
                    if (($leg['departure_date'] ?? null) === null) {
                        $missing[] = 'leg'.($i + 1).'_departure_date';
                    }
                }
            }
        }

        $missing = array_values(array_unique($missing));

        if ($rejects !== []) {
            return [
                'valid' => false,
                'plan' => $plan,
                'intent' => null,
                'rejects' => $rejects,
                'missing' => $missing,
                'explicit_route_precedence' => $explicitRoute,
                'stale_route_contamination' => $stale,
                'false_open_jaw_demoted' => $falseOpenJawDemoted,
                'server_single_route' => $serverSingleRoute,
                'explicit_return_trip_cue' => $explicitReturnCue,
            ];
        }

        $intentArray = [
            'intent' => match ($plan->domain) {
                'travel' => 'flight_search',
                'booking' => 'booking_lookup',
                'knowledge' => 'knowledge',
                'support' => 'handoff',
                default => 'unknown',
            },
            'origin' => $origin,
            'destination' => $destination,
            'depart_date' => $depart,
            'return_date' => $return,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'cabin' => $cabin,
            'airline' => $airline,
            'max_stops' => $plan->maxStops,
            'budget' => $plan->budget,
            'time_preference' => $plan->timePreference,
            'trip_type' => $tripType,
            'legs' => array_map(static function (array $leg): array {
                return [
                    'origin' => (string) ($leg['origin'] ?? ''),
                    'destination' => (string) ($leg['destination'] ?? ''),
                ];
            }, array_values(array_filter($legs, static fn (array $l): bool => ($l['origin'] ?? null) && ($l['destination'] ?? null)))),
        ];

        $canon = $this->canonicalizer->canonicalize($intentArray, $priorState);
        $intent = TravelIntent::fromArray(
            is_array($canon['intent'] ?? null) ? $canon['intent'] : $intentArray,
            'QWEN_SEMANTIC'
        );

        // Post-canonicalize server authority: do not inherit prior return_date incorrectly.
        $needsRebuild = false;
        $intentData = $intent->toArray();
        if ($tripType === 'one_way' && ($intentData['return_date'] ?? null) !== null) {
            $intentData['return_date'] = null;
            $intentData['trip_type'] = 'one_way';
            $needsRebuild = true;
        }
        // Fresh English return/round-trip cue without a stated return date must clarify —
        // never treat prior shopping_state return_date as satisfying this turn.
        if ($explicitReturnCue && $return === null && ($intentData['return_date'] ?? null) !== null) {
            $intentData['return_date'] = null;
            $intentData['trip_type'] = 'return';
            $needsRebuild = true;
        }
        if ($needsRebuild) {
            $intent = TravelIntent::fromArray($intentData, 'QWEN_SEMANTIC');
        }

        // Rebuild travel completeness missing after canonicalize (server-owned; drop stale Qwen slots).
        if ($plan->domain === 'travel' && in_array($plan->operation, ['prepare_search', 'clarify'], true)) {
            $missing = array_values(array_filter(
                $missing,
                static fn (string $m): bool => ! in_array($m, $travelCompletenessSlots, true)
            ));
            if (! in_array($intent->tripType, ['open_jaw', 'multi_city'], true)) {
                if ($intent->origin === null) {
                    $missing[] = 'origin';
                }
                if ($intent->destination === null) {
                    $missing[] = 'destination';
                }
                if ($intent->departDate === null) {
                    $missing[] = 'departure_date';
                }
            }
            if ($intent->tripType === 'return' && $intent->returnDate === null) {
                $missing[] = 'return_date';
            }
            if (in_array($intent->tripType, ['open_jaw', 'multi_city'], true)) {
                $intentLegs = is_array($intent->legs) ? $intent->legs : [];
                foreach ($intentLegs as $i => $leg) {
                    if (($leg['departure_date'] ?? null) === null && ($legs[$i]['departure_date'] ?? null) === null) {
                        $missing[] = 'leg'.($i + 1).'_departure_date';
                    }
                }
            }
        }

        return [
            'valid' => true,
            'plan' => $plan,
            'intent' => $intent,
            'rejects' => [],
            'missing' => array_values(array_unique($missing)),
            'explicit_route_precedence' => $explicitRoute,
            'stale_route_contamination' => $stale,
            'false_open_jaw_demoted' => $falseOpenJawDemoted,
            'server_single_route' => $serverSingleRoute,
            'explicit_return_trip_cue' => $explicitReturnCue,
        ];
    }

    /**
     * @param  list<string>  $rejects
     */
    private function resolveAirport(?string $text, array &$rejects): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $r = $this->locations->resolve($text);
        if (($r['ambiguous'] ?? false) === true) {
            $rejects[] = 'ambiguous_airport:'.$text;

            return null;
        }
        $code = $r['code'] ?? null;
        if ($code === null) {
            // Invented IATA (3-letter unknown) or unknown city.
            if (preg_match('/^[A-Za-z]{3}$/', trim($text)) === 1) {
                $rejects[] = 'invented_iata:'.strtoupper(trim($text));
            } else {
                $rejects[] = 'unresolved_airport:'.$text;
            }

            return null;
        }

        return $code;
    }

    /**
     * @param  list<string>  $rejects
     */
    private function resolveDate(?string $text, array &$rejects): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        // Already ISO?
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($text)) === 1) {
            try {
                $d = Carbon::createFromFormat('Y-m-d', trim($text));
                if ($d === false) {
                    $rejects[] = 'invalid_date';

                    return null;
                }

                return $d->toDateString();
            } catch (\Throwable) {
                $rejects[] = 'invalid_date';

                return null;
            }
        }
        $normalized = mb_strtolower(trim($text));
        $resolved = $this->dates->resolveDepart($normalized, trim($text), Carbon::now());
        $iso = is_array($resolved) ? ($resolved['date'] ?? null) : null;
        if (! is_string($iso) || $iso === '') {
            $rejects[] = 'invalid_date';

            return null;
        }

        return $iso;
    }

    /**
     * @param  list<string>  $rejects
     */
    private function normalizeCabin(?string $cabin, array &$rejects): ?string
    {
        if ($cabin === null || trim($cabin) === '') {
            return null;
        }
        $c = strtolower(trim($cabin));
        $map = [
            'economy' => 'economy',
            'y' => 'economy',
            'premium_economy' => 'premium_economy',
            'premium economy' => 'premium_economy',
            'business' => 'business',
            'j' => 'business',
            'first' => 'first',
            'f' => 'first',
        ];
        if (! isset($map[$c])) {
            $rejects[] = 'unsupported_cabin';

            return null;
        }

        return $map[$c];
    }

    private function messageImpliesExplicitRoute(string $message): bool
    {
        $m = mb_strtolower($message);

        return (bool) preg_match(
            '/\b(from|to|into|out of|fly|flight|ticket|se|wapis|wapas|return|'.
            'lahore|lahor|doha|jeddah|medina|islamabad|karachi|dubai|dubay|'.
            'lhe|doh|jed|med|isb|khi|dxb)\b/u',
            $m
        );
    }

    /**
     * English return / round-trip trip-shape cue.
     * Does NOT treat Roman-Urdu wapis/wapas as round-trip.
     */
    private function messageImpliesExplicitReturnTripCue(string $message): bool
    {
        $m = mb_strtolower(trim($message));
        if ($m === '') {
            return false;
        }
        // Roman-Urdu reverse-route wording is route direction, not English round-trip.
        if (preg_match('/\b(wapis|wapas)\b|واپس/u', $m) === 1) {
            return false;
        }

        return preg_match(
            '/\bround[\s-]?trips?\b|\breturn\s+tickets?\b|\breturn\s+flights?\b|\breturn\b/u',
            $m
        ) === 1;
    }
}
