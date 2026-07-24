<?php

namespace App\Services\Suppliers\OneApi\Checkout;

use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContext;

/**
 * Builds customer-safe catalog payloads with opaque selection IDs backed by server registry.
 */
class OneApiCheckoutCatalogPresenter
{
    /**
     * @param  list<array<string, mixed>>  $bundles
     * @param  list<array<string, mixed>>  $baggage
     * @param  list<array<string, mixed>>  $meals
     * @param  list<array<string, mixed>>  $seats
     * @return array{bundles: list<array<string, mixed>>, baggage: list<array<string, mixed>>, meals: list<array<string, mixed>>, seats: list<array<string, mixed>>}
     */
    public function present(
        OneApiWorkflowContext $context,
        array $bundles,
        array $baggage,
        array $meals,
        array $seats,
    ): array {
        $registry = [];
        $presentedBundles = [];
        foreach ($bundles as $bundle) {
            if (! is_array($bundle)) {
                continue;
            }
            $ond = (int) ($bundle['applicableOndSequence'] ?? $bundle['ond_sequence'] ?? 1);
            $group = $ond === 2 ? 'inbound' : 'outbound';
            $entry = [
                'type' => 'bundle',
                'group' => $group,
                'ond_sequence' => $ond,
                'bunldedServiceId' => (string) ($bundle['bunldedServiceId'] ?? ''),
                'bundledServiceName' => (string) ($bundle['bundledServiceName'] ?? ''),
                'included_price' => (bool) ($bundle['included_price'] ?? false),
            ];
            $selectionId = $this->issueId($entry);
            $registry[$selectionId] = $entry;
            $presentedBundles[] = [
                'selection_id' => $selectionId,
                'group' => $group,
                'ond_sequence' => $ond,
                'label' => $entry['bundledServiceName'] !== '' ? $entry['bundledServiceName'] : $entry['bunldedServiceId'],
                'included_price' => $entry['included_price'],
            ];
        }

        $presentedBaggage = $this->presentAncillaryList($baggage, 'baggage', $registry);
        $presentedMeals = $this->presentAncillaryList($meals, 'meal', $registry);
        $presentedSeats = $this->presentSeatList($seats, $registry);

        $context->moneySnapshot['catalog_registry'] = $registry;
        $context->moneySnapshot['catalog_registry_issued_at'] = now()->toIso8601String();

        return [
            'bundles' => $presentedBundles,
            'baggage' => $presentedBaggage,
            'meals' => $presentedMeals,
            'seats' => $presentedSeats,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $registry
     * @return list<array<string, mixed>>
     */
    private function presentAncillaryList(array $items, string $type, array &$registry): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $entry = array_merge($item, ['type' => $type]);
            $selectionId = $this->issueId($entry);
            $registry[$selectionId] = $entry;
            $out[] = [
                'selection_id' => $selectionId,
                'passenger_ref' => (string) ($item['passenger_ref'] ?? 'A1'),
                'segment_ref' => (string) ($item['segment_ref'] ?? '1'),
                'ond_sequence' => (int) ($item['ond_sequence'] ?? 1),
                'label' => (string) ($item['description'] ?? $item['code'] ?? $type),
                'amount' => (string) ($item['amount'] ?? '0'),
                'currency' => (string) ($item['currency'] ?? ''),
                'included_price' => ((string) ($item['amount'] ?? '0')) === '0' || ((string) ($item['amount'] ?? '')) === '',
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $seats
     * @param  array<string, array<string, mixed>>  $registry
     * @return list<array<string, mixed>>
     */
    private function presentSeatList(array $seats, array &$registry): array
    {
        $out = [];
        foreach ($seats as $seat) {
            if (! is_array($seat)) {
                continue;
            }
            $available = ! in_array(strtoupper((string) ($seat['status'] ?? 'A')), ['O', 'Z', 'BLOCKED'], true);
            $entry = [
                'type' => 'seat',
                'passenger_ref' => (string) ($seat['passenger_ref'] ?? 'A1'),
                'segment_ref' => (string) ($seat['segment_ref'] ?? '1'),
                'seat_number' => (string) ($seat['number'] ?? ''),
                'available' => $available,
                'amount' => (string) ($seat['amount'] ?? ''),
                'currency' => (string) ($seat['currency'] ?? ''),
            ];
            $selectionId = $this->issueId($entry);
            $registry[$selectionId] = $entry;
            $out[] = [
                'selection_id' => $selectionId,
                'passenger_ref' => $entry['passenger_ref'],
                'segment_ref' => $entry['segment_ref'],
                'seat_number' => $entry['seat_number'],
                'available' => $available,
                'amount' => $entry['amount'],
                'currency' => $entry['currency'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function issueId(array $entry): string
    {
        return 'oa_sel_'.substr(hash('sha256', json_encode($entry, JSON_THROW_ON_ERROR)), 0, 24);
    }
}
