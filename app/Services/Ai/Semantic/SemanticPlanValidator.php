<?php

namespace App\Services\Ai\Semantic;

use App\Data\Ai\SemanticPlan;
use App\Data\Ai\TravelIntent;
use App\Services\Ai\Hybrid\AirlineResolver;
use App\Services\Ai\Hybrid\DateExpressionResolver;
use App\Services\Ai\Hybrid\LocationResolver;
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
        $missing = $plan->missing;

        // Forbidden ops already constrained by DTO allowlist; re-check raw for injection.
        $raw = $plan->raw;
        foreach (['execute_now', 'authorize', 'mutation', 'supplier_credentials', 'database'] as $bad) {
            if (array_key_exists($bad, $raw)) {
                $rejects[] = 'forbidden_key:'.$bad;
            }
        }

        $origin = $this->resolveAirport($plan->origin, $rejects);
        $destination = $this->resolveAirport($plan->destination, $rejects);

        $legs = [];
        foreach ($plan->legs as $leg) {
            $o = $this->resolveAirport($leg['origin'] ?? null, $rejects);
            $d = $this->resolveAirport($leg['destination'] ?? null, $rejects);
            $dd = $this->resolveDate($leg['departure_date'] ?? null, $rejects);
            if ($o || $d || $dd) {
                $legs[] = [
                    'origin' => $o,
                    'destination' => $d,
                    'departure_date' => $dd,
                ];
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

        if (count($legs) >= 2 && $tripType === null) {
            $tripType = 'open_jaw';
        }

        // Explicit current-turn route wins over stale prior origin/destination.
        $explicitRoute = $this->messageImpliesExplicitRoute($userMessage);
        $stale = 0;
        if ($explicitRoute && $origin && $destination) {
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
            'adults' => $plan->adults,
            'children' => $plan->children,
            'infants' => $plan->infants,
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

        // Rebuild plan-facing missing after canonicalize.
        if ($plan->domain === 'travel' && $plan->operation === 'prepare_search') {
            if (! $intent->isSearchable() && ! in_array($intent->tripType, ['open_jaw', 'multi_city'], true)) {
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
        }

        return [
            'valid' => true,
            'plan' => $plan,
            'intent' => $intent,
            'rejects' => [],
            'missing' => array_values(array_unique($missing)),
            'explicit_route_precedence' => $explicitRoute,
            'stale_route_contamination' => $stale,
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
            '/\b(from|to|into|out of|fly|flight|ticket|lahore|doha|jeddah|medina|islamabad|karachi|lhe|doh|jed|med|isb|khi)\b/u',
            $m
        );
    }
}
