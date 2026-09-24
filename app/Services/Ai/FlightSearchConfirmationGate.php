<?php

namespace App\Services\Ai;

use App\Data\Ai\TravelIntent;
use App\Models\AiConversation;

/**
 * Orchestrator-layer confirmation before read-only flight search execution.
 * Does not live inside AiShoppingTools — that tool remains a pure read executor.
 */
final class FlightSearchConfirmationGate
{
    public const STATE_KEY = 'pending_flight_search_confirmation';

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshot(TravelIntent $intent): array
    {
        $tripType = $intent->returnDate ? 'return' : 'one_way';

        return [
            'origin' => $intent->origin,
            'destination' => $intent->destination,
            'departure_date' => $intent->departDate,
            'return_date' => $intent->returnDate,
            'trip_type' => $tripType,
            'adults' => $intent->adults ?? 1,
            'children' => $intent->children ?? 0,
            'infants' => $intent->infants ?? 0,
            'cabin' => $intent->cabin,
            'airline' => $intent->airline,
            'max_stops' => $intent->maxStops,
            'currency' => $intent->currency ?? 'PKR',
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function storePending(AiConversation $conversation, array $snapshot): void
    {
        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $state[self::STATE_KEY] = $snapshot;
        $conversation->shopping_state = $state;
        $conversation->save();
    }

    public function clearPending(AiConversation $conversation): void
    {
        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        unset($state[self::STATE_KEY]);
        $conversation->shopping_state = $state;
        $conversation->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingSnapshot(AiConversation $conversation): ?array
    {
        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $pending = $state[self::STATE_KEY] ?? null;

        return is_array($pending) ? $pending : null;
    }

    public function hasPending(AiConversation $conversation): bool
    {
        return $this->pendingSnapshot($conversation) !== null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function intentFromSnapshot(array $snapshot): TravelIntent
    {
        return TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => $snapshot['origin'] ?? null,
            'destination' => $snapshot['destination'] ?? null,
            'depart_date' => $snapshot['departure_date'] ?? null,
            'return_date' => $snapshot['return_date'] ?? null,
            'adults' => $snapshot['adults'] ?? 1,
            'children' => $snapshot['children'] ?? 0,
            'infants' => $snapshot['infants'] ?? 0,
            'cabin' => $snapshot['cabin'] ?? null,
            'airline' => $snapshot['airline'] ?? null,
            'max_stops' => $snapshot['max_stops'] ?? null,
            'currency' => $snapshot['currency'] ?? 'PKR',
            'mode' => 'STRUCTURED_FALLBACK',
        ], 'STRUCTURED_FALLBACK');
    }

    public function isAffirmative(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        return (bool) preg_match(
            '/^(yes|yep|yeah|yup|ok|okay|sure|confirm|correct|go ahead|please (search|do)|search( now)?|that\'?s right|haan|ji|yes please|yes,? search)[\s!.?]*$/u',
            $lower
        );
    }

    public function isNegative(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        return (bool) preg_match(
            '/^(no|nope|nah|cancel|stop|don\'?t( search)?|do not search|never ?mind|nahi)[\s!.?]*$/u',
            $lower
        );
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public function snapshotsEqual(array $a, array $b): bool
    {
        $keys = [
            'origin', 'destination', 'departure_date', 'return_date', 'trip_type',
            'adults', 'children', 'infants', 'cabin', 'airline', 'max_stops',
        ];
        foreach ($keys as $key) {
            $left = $a[$key] ?? null;
            $right = $b[$key] ?? null;
            if ((string) ($left ?? '') !== (string) ($right ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * True when the new searchable intent materially differs from the pending snapshot.
     *
     * @param  array<string, mixed>  $pending
     */
    public function isMaterialCorrection(array $pending, TravelIntent $intent): bool
    {
        if (! $intent->origin || ! $intent->destination) {
            return false;
        }

        return ! $this->snapshotsEqual($pending, $this->buildSnapshot($intent));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function confirmationMessage(array $snapshot): string
    {
        $origin = (string) ($snapshot['origin'] ?? '?');
        $destination = (string) ($snapshot['destination'] ?? '?');
        $trip = (($snapshot['trip_type'] ?? 'one_way') === 'return') ? 'return' : 'one-way';
        $date = (string) ($snapshot['departure_date'] ?? 'your selected date');
        $adults = (int) ($snapshot['adults'] ?? 1);
        $adultLabel = $adults === 1 ? '1 adult' : $adults.' adults';
        $extra = '';
        $children = (int) ($snapshot['children'] ?? 0);
        $infants = (int) ($snapshot['infants'] ?? 0);
        if ($children > 0) {
            $extra .= ', '.$children.' '.($children === 1 ? 'child' : 'children');
        }
        if ($infants > 0) {
            $extra .= ', '.$infants.' '.($infants === 1 ? 'infant' : 'infants');
        }

        return "Just to confirm: {$origin} to {$destination}, {$trip}, {$date}, for {$adultLabel}{$extra}. Shall I search?";
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function confirmationMeta(array $snapshot, array $meta = []): array
    {
        return array_merge($meta, [
            'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
            'CONFIRMATION_REQUIRED' => true,
            'confirmation_required' => true,
            'confirmation_type' => 'flight_search',
            'CONFIRMATION_BEFORE_SEARCH' => true,
            'CONFIRMATION_SNAPSHOT' => $snapshot,
        ]);
    }
}
