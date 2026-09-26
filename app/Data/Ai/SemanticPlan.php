<?php

namespace App\Data\Ai;

/**
 * Model-proposed semantic plan. Never authorizes tools or mutations.
 *
 * @phpstan-type TravelSlots array{
 *   trip_type: ?string,
 *   legs: list<array{origin: ?string, destination: ?string, departure_date: ?string}>,
 *   return_date: ?string,
 *   adults: int,
 *   children: int,
 *   infants: int,
 *   cabin: ?string,
 *   airline: ?string,
 *   max_stops: ?int,
 *   budget: ?float,
 *   time_preference: ?string
 * }
 */
final class SemanticPlan
{
    public const DOMAINS = ['travel', 'booking', 'knowledge', 'current', 'general', 'support', 'casual'];

    public const OPERATIONS = ['answer', 'clarify', 'prepare_search', 'lookup', 'handoff', 'none'];

    /**
     * @param  list<array{origin: ?string, destination: ?string, departure_date: ?string}>  $legs
     * @param  list<string>  $missing
     * @param  array<string, mixed>  $corrections
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $intent,
        public readonly string $operation,
        public readonly ?string $tripType,
        public readonly array $legs,
        public readonly ?string $origin,
        public readonly ?string $destination,
        public readonly ?string $departDate,
        public readonly ?string $returnDate,
        public readonly int $adults,
        public readonly int $children,
        public readonly int $infants,
        public readonly ?string $cabin,
        public readonly ?string $airline,
        public readonly ?int $maxStops,
        public readonly ?float $budget,
        public readonly ?string $timePreference,
        public readonly ?string $bookingReference,
        public readonly ?string $knowledgeQuery,
        public readonly bool $refActiveSearch,
        public readonly bool $refPendingConfirmation,
        public readonly array $corrections,
        public readonly array $missing,
        public readonly ?string $responseIntent,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromModelArray(array $raw): self
    {
        $operation = strtolower(trim((string) ($raw['operation'] ?? 'none')));
        $operationAliases = [
            'travel' => 'prepare_search',
            'search' => 'prepare_search',
            'flight_search' => 'prepare_search',
            'prepare' => 'prepare_search',
            'confirm' => 'prepare_search',
            'ask' => 'clarify',
            'question' => 'clarify',
            'help' => 'answer',
            'respond' => 'answer',
            'reply' => 'answer',
            'human' => 'handoff',
            'support' => 'handoff',
            'agent' => 'handoff',
            'booking' => 'lookup',
            'booking_lookup' => 'lookup',
        ];
        if (isset($operationAliases[$operation])) {
            $operation = $operationAliases[$operation];
        }
        if (! in_array($operation, self::OPERATIONS, true)) {
            $operation = 'none';
        }

        $domain = strtolower(trim((string) ($raw['domain'] ?? 'general')));
        $domainAliases = [
            'flight' => 'travel',
            'flights' => 'travel',
            'weather' => 'current',
            'live' => 'current',
            'faq' => 'knowledge',
            'human' => 'support',
            'agent' => 'support',
            'chat' => 'casual',
            'greeting' => 'casual',
        ];
        if (isset($domainAliases[$domain])) {
            $domain = $domainAliases[$domain];
        }
        if (! in_array($domain, self::DOMAINS, true)) {
            $domain = 'general';
        }

        $travel = is_array($raw['travel'] ?? null) ? $raw['travel'] : [];
        $legsIn = is_array($travel['legs'] ?? null) ? $travel['legs'] : [];
        $legs = [];
        foreach ($legsIn as $leg) {
            if (! is_array($leg)) {
                continue;
            }
            $legs[] = [
                'origin' => isset($leg['origin']) ? trim((string) $leg['origin']) : null,
                'destination' => isset($leg['destination']) ? trim((string) $leg['destination']) : null,
                'departure_date' => isset($leg['departure_date']) ? trim((string) $leg['departure_date']) : null,
            ];
        }

        $refs = is_array($raw['references'] ?? null) ? $raw['references'] : [];
        $missing = [];
        if (is_array($raw['missing'] ?? null)) {
            foreach ($raw['missing'] as $m) {
                if (is_string($m) && $m !== '') {
                    $missing[] = $m;
                }
            }
        }

        $corrections = is_array($raw['corrections'] ?? null) ? $raw['corrections'] : [];

        $origin = self::nullableString($travel['origin'] ?? ($legs[0]['origin'] ?? null));
        $destination = self::nullableString($travel['destination'] ?? ($legs[0]['destination'] ?? null));
        $depart = self::nullableString($travel['departure_date'] ?? $travel['depart_date'] ?? ($legs[0]['departure_date'] ?? null));

        return new self(
            domain: $domain,
            intent: trim((string) ($raw['intent'] ?? $domain)),
            operation: $operation,
            tripType: self::nullableString($travel['trip_type'] ?? null),
            legs: $legs,
            origin: $origin,
            destination: $destination,
            departDate: $depart,
            returnDate: self::nullableString($travel['return_date'] ?? null),
            adults: max(1, min(9, (int) ($travel['adults'] ?? 1))),
            children: max(0, min(9, (int) ($travel['children'] ?? 0))),
            infants: max(0, min(9, (int) ($travel['infants'] ?? 0))),
            cabin: self::nullableString($travel['cabin'] ?? null),
            airline: self::nullableString($travel['airline'] ?? null),
            maxStops: isset($travel['max_stops']) ? max(0, min(3, (int) $travel['max_stops'])) : null,
            budget: isset($travel['budget']) && is_numeric($travel['budget']) ? (float) $travel['budget'] : null,
            timePreference: self::nullableString($travel['time_preference'] ?? null),
            bookingReference: self::nullableString($raw['booking_reference'] ?? null),
            knowledgeQuery: self::nullableString($raw['knowledge_query'] ?? null),
            refActiveSearch: (bool) ($refs['active_search'] ?? false),
            refPendingConfirmation: (bool) ($refs['pending_confirmation'] ?? false),
            corrections: $corrections,
            missing: $missing,
            responseIntent: self::nullableString($raw['response_intent'] ?? null),
            raw: $raw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toTravelIntentArray(): array
    {
        $legs = [];
        foreach ($this->legs as $leg) {
            if (($leg['origin'] ?? null) && ($leg['destination'] ?? null)) {
                $legs[] = [
                    'origin' => $leg['origin'],
                    'destination' => $leg['destination'],
                ];
            }
        }

        return [
            'intent' => match ($this->domain) {
                'travel' => 'flight_search',
                'booking' => 'booking_lookup',
                'knowledge' => 'knowledge',
                'support' => 'handoff',
                default => 'unknown',
            },
            'origin' => $this->origin,
            'destination' => $this->destination,
            'depart_date' => $this->departDate,
            'return_date' => $this->returnDate,
            'adults' => $this->adults,
            'children' => $this->children,
            'infants' => $this->infants,
            'cabin' => $this->cabin,
            'airline' => $this->airline,
            'max_stops' => $this->maxStops,
            'budget' => $this->budget,
            'time_preference' => $this->timePreference,
            'trip_type' => $this->tripType,
            'legs' => $legs === [] ? null : $legs,
            'booking_reference' => $this->bookingReference,
        ];
    }

    private static function nullableString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
