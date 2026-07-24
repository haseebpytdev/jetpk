<?php

namespace App\Services\Suppliers\OneApi\Checkout;

use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContext;

class OneApiCheckoutSelectionValidator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(OneApiWorkflowContext $context, array $payload, bool $alreadyFinalized): void
    {
        if ($alreadyFinalized && ! ($payload['allow_reprice'] ?? false)) {
            throw new OneApiValidationException('validation_error', 422, 'Selections are locked after final price.');
        }

        if (isset($payload['client_total']) || isset($payload['posted_supplier_amount'])) {
            throw new OneApiValidationException('validation_error', 422, 'Client-posted prices are not accepted.');
        }

        $this->assertSearchTerminalsNotReplaced($context, $payload);

        $resolved = $this->resolveSubmittedPayload($context, $payload);
        $seats = is_array($resolved['seats'] ?? null) ? $resolved['seats'] : [];
        $seen = [];
        foreach ($seats as $seat) {
            if (! is_array($seat)) {
                continue;
            }
            if (($seat['available'] ?? true) === false) {
                throw new OneApiValidationException('seat_unavailable', 422, 'Selected seat is unavailable.');
            }
            $key = ($seat['passenger_ref'] ?? '').'|'.($seat['segment_ref'] ?? '');
            if ($key !== '|' && isset($seen[$key])) {
                throw new OneApiValidationException('validation_error', 422, 'Duplicate seat selection.');
            }
            $seen[$key] = true;
        }

        $baggage = is_array($resolved['baggage'] ?? null) ? $resolved['baggage'] : [];
        $baggageKeys = [];
        foreach ($baggage as $bag) {
            if (! is_array($bag)) {
                continue;
            }
            $key = ($bag['passenger_ref'] ?? '').'|'.($bag['segment_ref'] ?? '').'|'.($bag['code'] ?? '');
            if ($key !== '||' && isset($baggageKeys[$key])) {
                throw new OneApiValidationException('validation_error', 422, 'Duplicate baggage selection.');
            }
            $baggageKeys[$key] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{bundles: list<array<string, mixed>>, baggage: list<array<string, mixed>>, meals: list<array<string, mixed>>, seats: list<array<string, mixed>>}
     */
    public function resolveSubmittedPayload(OneApiWorkflowContext $context, array $payload): array
    {
        $registry = is_array($context->moneySnapshot['catalog_registry'] ?? null)
            ? $context->moneySnapshot['catalog_registry']
            : [];

        if ($registry === [] && $this->hasLegacyPayload($payload)) {
            return [
                'bundles' => is_array($payload['bundles'] ?? null) ? $payload['bundles'] : [],
                'baggage' => is_array($payload['baggage'] ?? null) ? $payload['baggage'] : [],
                'meals' => is_array($payload['meals'] ?? null) ? $payload['meals'] : [],
                'seats' => is_array($payload['seats'] ?? null) ? $payload['seats'] : [],
            ];
        }

        return [
            'bundles' => $this->resolveType($registry, $payload, 'bundle_selection_ids', 'bundle'),
            'baggage' => $this->resolveType($registry, $payload, 'baggage_selection_ids', 'baggage'),
            'meals' => $this->resolveType($registry, $payload, 'meal_selection_ids', 'meal'),
            'seats' => $this->resolveType($registry, $payload, 'seat_selection_ids', 'seat'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function normalizedAncillaries(array $payload): array
    {
        return array_values(array_filter([
            ...(is_array($payload['baggage'] ?? null) ? $payload['baggage'] : []),
            ...(is_array($payload['meals'] ?? null) ? $payload['meals'] : []),
            ...(is_array($payload['seats'] ?? null) ? $payload['seats'] : []),
        ]));
    }

    /**
     * @param  array<string, array<string, mixed>>  $registry
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function resolveType(array $registry, array $payload, string $idsKey, string $type): array
    {
        $ids = is_array($payload[$idsKey] ?? null) ? $payload[$idsKey] : [];
        if ($ids === [] && $type === 'bundle' && is_array($payload['bundles'] ?? null)) {
            return $payload['bundles'];
        }

        $resolved = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            if ($id === '' || ! isset($registry[$id])) {
                throw new OneApiValidationException('stale_selection', 422, 'One or more selections are no longer available.');
            }
            $entry = $registry[$id];
            if (($entry['type'] ?? '') !== $type) {
                throw new OneApiValidationException('validation_error', 422, 'Invalid selection identifier.');
            }
            $resolved[] = $entry;
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasLegacyPayload(array $payload): bool
    {
        return is_array($payload['bundles'] ?? null)
            || is_array($payload['baggage'] ?? null)
            || is_array($payload['meals'] ?? null)
            || is_array($payload['seats'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertSearchTerminalsNotReplaced(OneApiWorkflowContext $context, array $payload): void
    {
        $baseline = $context->terminalsBySegmentKey;
        if ($baseline === []) {
            return;
        }
        $candidate = is_array($payload['terminals_by_segment_key'] ?? null)
            ? $payload['terminals_by_segment_key']
            : [];
        if ($candidate === []) {
            return;
        }
        foreach ($candidate as $segmentKey => $terminal) {
            $key = (string) $segmentKey;
            if ($key === '' || ! isset($baseline[$key])) {
                continue;
            }
            if ((string) $baseline[$key] !== (string) $terminal) {
                throw new OneApiValidationException('validation_error', 422, 'Search terminal cannot replace price terminal.');
            }
        }
    }
}
