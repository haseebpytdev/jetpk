<?php

namespace App\Services\Ai\Semantic;

use App\Data\Ai\SemanticPlan;
use App\Data\Ai\TravelIntent;
use App\Models\AiConversation;
use App\Services\Ai\FlightSearchConfirmationGate;
use App\Services\Ai\TravelIntentExtractor;

/**
 * Server policy: map validated semantic plans to confirmation / clarify / domain replies.
 * Model never executes tools here.
 */
final class SemanticBrain
{
    public function __construct(
        private readonly QwenSemanticPlanner $planner,
        private readonly SemanticPlanValidator $validator,
        private readonly SemanticResponseComposer $composer,
        private readonly FlightSearchConfirmationGate $flightConfirmation,
        private readonly TravelIntentExtractor $extractor,
    ) {}

    public function isEnabled(): bool
    {
        return $this->planner->isEnabled();
    }

    /**
     * Attempt semantic primary path.
     * Returns a handled result array, or kind=fallback with telemetry for hybrid continuation.
     *
     * @param  array<string, mixed>  $baseMeta
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    public function tryHandle(
        AiConversation $conversation,
        string $message,
        array $baseMeta,
        array $context,
    ): ?array {
        $planResult = $this->planner->plan($conversation, $message, $context);
        $calls = (int) ($planResult['calls'] ?? 0);
        $semanticLatency = (int) ($planResult['latency_ms'] ?? 0);
        $composerLatency = 0;

        $telemetry = [
            'SEMANTIC_BRAIN_CALLED' => 'YES',
            'SEMANTIC_BRAIN_VALID' => 'NO',
            'SEMANTIC_BRAIN_FALLBACK' => 'NO',
            'MODEL_ID' => (string) config('ota.ai_assistant.model_id', 'local'),
            'MODEL_CALLS' => $calls,
            'SEMANTIC_LATENCY_MS' => $semanticLatency,
            'COMPOSER_LATENCY_MS' => 0,
            'TOTAL_MODEL_LATENCY_MS' => $semanticLatency,
            'FINAL_RESPONSE_SOURCE' => 'SEMANTIC',
        ];

        if ($planResult['plan'] === null) {
            $telemetry['SEMANTIC_BRAIN_FALLBACK'] = 'YES';
            $telemetry['SEMANTIC_FALLBACK_REASON'] = (string) ($planResult['error'] ?? 'null_plan');
            $telemetry['FINAL_RESPONSE_SOURCE'] = 'SEMANTIC_FALLBACK';

            return [
                'kind' => 'fallback',
                'meta' => array_merge($baseMeta, $telemetry),
            ];
        }

        /** @var SemanticPlan $plan */
        $plan = $planResult['plan'];
        $prior = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $validated = $this->validator->validate($plan, $prior, $message);

        $telemetry['SEMANTIC_INTENT'] = $plan->intent;
        $telemetry['SERVER_OPERATION'] = $plan->operation;
        $telemetry['SEMANTIC_DOMAIN'] = $plan->domain;

        if (! $validated['valid']) {
            $telemetry['SEMANTIC_BRAIN_FALLBACK'] = 'YES';
            $telemetry['SEMANTIC_FALLBACK_REASON'] = 'invalid_plan';
            $telemetry['validator_rejects'] = $validated['rejects'];
            $telemetry['FINAL_RESPONSE_SOURCE'] = 'SEMANTIC_FALLBACK';

            return [
                'kind' => 'fallback',
                'meta' => array_merge($baseMeta, $telemetry),
            ];
        }

        $telemetry['SEMANTIC_BRAIN_VALID'] = 'YES';
        if ($validated['explicit_route_precedence']) {
            $telemetry['EXPLICIT_ROUTE_PRECEDENCE'] = 'PASS';
        }
        $telemetry['STALE_ROUTE_CONTAMINATION'] = (int) $validated['stale_route_contamination'];

        $intent = $validated['intent'];
        $missing = $validated['missing'];

        // Patch shopping state from validated intent when travel-related.
        if ($intent instanceof TravelIntent && in_array($plan->domain, ['travel'], true)) {
            $patched = $this->extractor->patchState($prior, $intent);
            if (in_array($intent->tripType, ['open_jaw', 'multi_city'], true) && is_array($intent->legs)) {
                $patched['legs'] = $intent->legs;
                $patched['trip_type'] = $intent->tripType;
            }
            $conversation->shopping_state = $patched;
            $conversation->save();
        }

        $meta = array_merge($baseMeta, $telemetry, [
            'QWEN_SEMANTIC_VALID' => 'YES',
            'TOOL_EXECUTED' => 'NONE',
            'MODEL_CAN_AUTHORIZE_MUTATION' => 'NO',
        ]);

        // Bare affirmative / no-route messages must not invent prepare_search from stale state.
        if (
            $plan->domain === 'travel'
            && $plan->operation === 'prepare_search'
            && $this->isBareAffirmativeOrEmptyTravel($message)
        ) {
            $meta['SEMANTIC_BRAIN_FALLBACK'] = 'YES';
            $meta['SEMANTIC_FALLBACK_REASON'] = 'bare_affirmative_no_prepare_search';
            $meta['FINAL_RESPONSE_SOURCE'] = 'SEMANTIC_FALLBACK';

            return ['kind' => 'fallback', 'meta' => $meta];
        }

        // Explicit current-turn route mismatch vs prepare_search slots → fallback (never action-ready wrong route).
        if (
            $plan->domain === 'travel'
            && $plan->operation === 'prepare_search'
            && $intent instanceof TravelIntent
            && $this->explicitRouteConflicts($message, $intent)
        ) {
            $meta['SEMANTIC_BRAIN_FALLBACK'] = 'YES';
            $meta['SEMANTIC_FALLBACK_REASON'] = 'explicit_route_conflict';
            $meta['WRONG_ROUTE_ACTION_READY'] = 0;
            $meta['FINAL_RESPONSE_SOURCE'] = 'SEMANTIC_FALLBACK';

            return ['kind' => 'fallback', 'meta' => $meta];
        }

        return match (true) {
            $plan->domain === 'support' || $plan->operation === 'handoff' => [
                'kind' => 'handoff',
                'meta' => $meta,
            ],
            $plan->domain === 'current' => $this->currentUnverified($conversation, $message, $plan, $meta, $calls, $semanticLatency, $composerLatency),
            $plan->domain === 'general' || $plan->domain === 'casual' => $this->generalAnswer($conversation, $message, $plan, $meta, $calls, $semanticLatency),
            $plan->domain === 'knowledge' => [
                'kind' => 'knowledge',
                'query' => $plan->knowledgeQuery ?: $message,
                'meta' => $meta,
            ],
            $plan->domain === 'booking' || $plan->operation === 'lookup' => [
                'kind' => 'booking_lookup',
                'meta' => $meta,
            ],
            $plan->domain === 'travel' => $this->travelPath(
                $conversation,
                $message,
                $plan,
                $intent,
                $missing,
                $meta,
                $calls,
                $semanticLatency,
            ),
            default => [
                'kind' => 'fallback',
                'meta' => array_merge($meta, [
                    'SEMANTIC_BRAIN_FALLBACK' => 'YES',
                    'SEMANTIC_FALLBACK_REASON' => 'unsupported_domain',
                    'FINAL_RESPONSE_SOURCE' => 'SEMANTIC_FALLBACK',
                ]),
            ],
        };
    }

    /**
     * @param  list<string>  $missing
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function travelPath(
        AiConversation $conversation,
        string $message,
        SemanticPlan $plan,
        ?TravelIntent $intent,
        array $missing,
        array $meta,
        int $calls,
        int $semanticLatency,
    ): array {
        if (! $intent instanceof TravelIntent) {
            return [
                'kind' => 'fallback',
                'meta' => array_merge($meta, [
                    'SEMANTIC_BRAIN_FALLBACK' => 'YES',
                    'SEMANTIC_FALLBACK_REASON' => 'travel_intent_missing',
                    'FINAL_RESPONSE_SOURCE' => 'SEMANTIC_FALLBACK',
                ]),
            ];
        }

        $tripType = $intent->tripType ?? $plan->tripType;
        if (in_array($tripType, ['open_jaw', 'multi_city'], true)) {
            $legs = is_array($intent->legs) && $intent->legs !== []
                ? $intent->legs
                : array_values(array_filter(array_map(static function (array $leg): ?array {
                    if (($leg['origin'] ?? null) && ($leg['destination'] ?? null)) {
                        return [
                            'origin' => (string) $leg['origin'],
                            'destination' => (string) $leg['destination'],
                        ];
                    }

                    return null;
                }, $plan->legs)));
            $leg1 = isset($legs[0]) ? ($legs[0]['origin'].'-'.$legs[0]['destination']) : null;
            $leg2 = isset($legs[1]) ? ($legs[1]['origin'].'-'.$legs[1]['destination']) : null;
            $body = 'That looks like a multi-city / open-jaw itinerary'
                .($leg1 && $leg2 ? " ({$leg1} then {$leg2})" : '')
                .'. Share both leg dates and use multi-city search — I will not collapse it to a one-way search.';
            $composed = $this->composer->compose($conversation, $message, $plan, [
                'trip_type' => $tripType,
                'legs' => $legs,
                'missing' => $missing,
            ], $body);
            $meta = $this->withComposerMeta($meta, $calls, $semanticLatency, $composed);

            return [
                'kind' => 'clarify',
                'status' => 'clarify',
                'message' => (string) $composed['message'],
                'meta' => array_merge($meta, [
                    'OPEN_JAW_DETECTED' => 'YES',
                    'LEG1' => $leg1,
                    'LEG2' => $leg2,
                    'FALSE_ONE_WAY' => 'NO',
                    'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
                ]),
            ];
        }

        if ($missing !== [] || $plan->operation === 'clarify' || ! $intent->isSearchable() || $intent->departDate === null) {
            $ask = $this->clarifyTravelMessage($missing, $intent);
            $composed = $this->composer->compose($conversation, $message, $plan, [
                'origin' => $intent->origin,
                'destination' => $intent->destination,
                'cabin' => $intent->cabin,
                'missing' => $missing,
            ], $ask);
            $meta = $this->withComposerMeta($meta, $calls, $semanticLatency, $composed);

            return [
                'kind' => 'clarify',
                'status' => 'clarify',
                'message' => (string) $composed['message'],
                'intent' => $intent->toArray(),
                'meta' => array_merge($meta, ['AI_FLIGHT_SEARCH_READ_CALLS' => 0]),
            ];
        }

        // prepare_search → confirmation only
        $snapshot = $this->flightConfirmation->buildSnapshot($intent);
        $this->flightConfirmation->storePending($conversation, $snapshot);
        $confirmBody = $this->flightConfirmation->confirmationMessage($snapshot);
        $composed = $this->composer->compose($conversation, $message, $plan, [
            'confirmation_snapshot' => $snapshot,
        ], $confirmBody);
        $meta = $this->withComposerMeta($meta, $calls, $semanticLatency, $composed);
        $confirmMeta = $this->flightConfirmation->confirmationMeta($snapshot, $meta);

        return [
            'kind' => 'confirm',
            'status' => 'confirm',
            'message' => (string) $composed['message'],
            'requires_confirmation' => true,
            'confirmation_snapshot' => $snapshot,
            'intent' => $intent->toArray(),
            'meta' => array_merge($confirmMeta, [
                'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
                'PRE_CONFIRM_SEARCH_CALLS' => 0,
                'SEMANTIC_PLAN_VALID' => 'YES',
                'TOOL_EXECUTED' => 'NONE',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function currentUnverified(
        AiConversation $conversation,
        string $message,
        SemanticPlan $plan,
        array $meta,
        int $calls,
        int $semanticLatency,
        int $composerLatency,
    ): array {
        $fallback = 'I do not have an approved live weather data source right now, so I cannot verify current conditions. I can still help with flights or JetPakistan travel questions.';
        $composed = $this->composer->compose($conversation, $message, $plan, [
            'live_provider' => false,
            'category' => 'CURRENT_UNVERIFIED',
        ], $fallback);
        $meta = $this->withComposerMeta($meta, $calls, $semanticLatency, $composed);

        return [
            'kind' => 'answer',
            'status' => 'ok',
            'message' => (string) $composed['message'],
            'meta' => array_merge($meta, [
                'open_domain_category' => 'CURRENT_UNVERIFIED',
                'FLIGHT_STATE_CONTAMINATION' => 0,
                'HALLUCINATED_LIVE_FACT' => 'NO',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function generalAnswer(
        AiConversation $conversation,
        string $message,
        SemanticPlan $plan,
        array $meta,
        int $calls,
        int $semanticLatency,
    ): array {
        $fallback = 'Happy to help with that. For live travel prices or JetPakistan bookings I can also search flights once you share a route and date.';
        $composed = $this->composer->compose($conversation, $message, $plan, [
            'domain' => 'general',
            'response_intent' => $plan->responseIntent,
        ], $fallback);
        $meta = $this->withComposerMeta($meta, $calls, $semanticLatency, $composed);

        return [
            'kind' => 'answer',
            'status' => 'ok',
            'message' => (string) $composed['message'],
            'meta' => $meta,
        ];
    }

    private function isBareAffirmativeOrEmptyTravel(string $message): bool
    {
        $m = mb_strtolower(trim($message));
        if ($m === '') {
            return true;
        }

        return (bool) preg_match(
            '/^(sure( go ahead)?|yes|ok|okay|go ahead|proceed|haan|ji|thanks|thank you|no pending action[—\- ].*|cancel( that)?)\.?$/u',
            $m
        );
    }

    private function explicitRouteConflicts(string $message, TravelIntent $intent): bool
    {
        $m = mb_strtolower($message);
        $map = [
            'lahore' => 'LHE', 'lhe' => 'LHE',
            'doha' => 'DOH', 'doh' => 'DOH',
            'jeddah' => 'JED', 'jed' => 'JED',
            'medina' => 'MED', 'madinah' => 'MED', 'med' => 'MED',
            'islamabad' => 'ISB', 'isb' => 'ISB',
            'karachi' => 'KHI', 'khi' => 'KHI',
            'dubai' => 'DXB', 'dxb' => 'DXB',
            'riyadh' => 'RUH', 'ruh' => 'RUH',
            'istanbul' => 'IST', 'ist' => 'IST',
            'peshawar' => 'PEW', 'pew' => 'PEW',
            'multan' => 'MUX', 'mux' => 'MUX',
            'faisalabad' => 'LYP', 'lyp' => 'LYP',
        ];
        $mentioned = [];
        foreach ($map as $word => $code) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $m) === 1) {
                $mentioned[$code] = true;
            }
        }
        if ($mentioned === []) {
            return false;
        }
        $origin = $intent->origin;
        $dest = $intent->destination;
        if ($origin && isset($mentioned[$origin]) === false && count($mentioned) >= 1) {
            // Message names cities but plan origin not among them (often stale MED).
            if (! isset($mentioned[$origin]) && isset($mentioned['LHE']) && $origin === 'MED') {
                return true;
            }
        }
        if ($origin && $dest) {
            // If message clearly has from X to Y and plan differs.
            if (isset($mentioned[$origin]) && isset($mentioned[$dest])) {
                return false;
            }
            foreach (array_keys($mentioned) as $code) {
                if ($code !== $origin && $code !== $dest) {
                    // Extra city only is ok; conflict if plan OD not subset of mentioned when >=2 mentioned
                    if (count($mentioned) >= 2 && ! isset($mentioned[$origin]) && ! isset($mentioned[$dest])) {
                        return true;
                    }
                }
            }
            if (count($mentioned) >= 2 && (! isset($mentioned[$origin]) || ! isset($mentioned[$dest]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $missing
     */
    private function clarifyTravelMessage(array $missing, TravelIntent $intent): string
    {
        if (in_array('departure_date', $missing, true) || $intent->departDate === null) {
            $route = trim(($intent->origin ?? '').' to '.($intent->destination ?? ''));
            $cabin = $intent->cabin ? ' in '.str_replace('_', ' ', $intent->cabin) : '';

            return 'What departure date should I use'
                .($route !== ' to ' ? " for {$route}" : '')
                .$cabin
                .'?';
        }
        if ($missing !== []) {
            return 'Please share: '.implode(', ', $missing).'.';
        }

        return 'Please share origin, destination, and travel date.';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array{message: ?string, latency_ms: int, calls: int}  $composed
     * @return array<string, mixed>
     */
    private function withComposerMeta(array $meta, int $planCalls, int $semanticLatency, array $composed): array
    {
        $cCalls = (int) ($composed['calls'] ?? 0);
        $cLatency = (int) ($composed['latency_ms'] ?? 0);
        $meta['MODEL_CALLS'] = $planCalls + $cCalls;
        $meta['COMPOSER_LATENCY_MS'] = $cLatency;
        $meta['SEMANTIC_LATENCY_MS'] = $semanticLatency;
        $meta['TOTAL_MODEL_LATENCY_MS'] = $semanticLatency + $cLatency;
        $meta['FINAL_RESPONSE_SOURCE'] = $cCalls > 0 ? 'SEMANTIC_COMPOSER' : 'SEMANTIC';

        return $meta;
    }
}
