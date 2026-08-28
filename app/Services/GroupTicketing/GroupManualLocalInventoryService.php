<?php

namespace App\Services\GroupTicketing;

use App\Models\GroupCategory;
use App\Models\GroupInventory;
use Illuminate\Support\Str;

/**
 * Creates and updates local-only group inventory (QA_GROUP_SOURCE=MANUAL_LOCAL).
 * Never binds supplier_connection_id or Al-Haider package IDs.
 */
class GroupManualLocalInventoryService
{
    /**
     * @param  array{
     *     title: string,
     *     sector: string,
     *     airline_name?: string|null,
     *     package_type?: string|null,
     *     departure_date: string,
     *     return_date?: string|null,
     *     total_seats: int,
     *     price: float|int|string,
     *     currency?: string,
     *     baggage?: string|null,
     *     refund_change_notes?: string|null,
     *     audience?: string,
     *     is_active?: bool
     * }  $input
     */
    public function create(array $input): GroupInventory
    {
        $audience = strtolower(trim((string) ($input['audience'] ?? 'b2c')));
        if (! in_array($audience, ['b2c', 'b2b', 'boundary'], true)) {
            $audience = 'b2c';
        }

        $packageKey = 'QA-ML-'.strtoupper(Str::random(10));
        $sector = strtoupper(trim((string) $input['sector']));
        $title = trim((string) $input['title']);
        $category = $this->resolveCategory(trim((string) ($input['package_type'] ?? 'QA Manual')));

        return GroupInventory::query()->create([
            'supplier' => GroupInventory::SUPPLIER_MANUAL_LOCAL,
            'supplier_package_id' => $packageKey,
            'public_id' => $packageKey,
            'group_category_id' => $category?->id,
            'title' => $title,
            'sector' => $sector,
            'airline_id' => null,
            'airline_name' => filled($input['airline_name'] ?? null) ? trim((string) $input['airline_name']) : 'QA LOCAL',
            'package_type' => $category?->name ?? 'QA Manual',
            'departure_date' => $input['departure_date'],
            'return_date' => filled($input['return_date'] ?? null) ? $input['return_date'] : null,
            'total_seats' => max(1, (int) $input['total_seats']),
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => round((float) $input['price'], 2),
            'price_child' => null,
            'price_infant' => null,
            'currency' => strtoupper(trim((string) ($input['currency'] ?? 'PKR'))) ?: 'PKR',
            'baggage' => filled($input['baggage'] ?? null) ? trim((string) $input['baggage']) : null,
            'refund_change_notes' => filled($input['refund_change_notes'] ?? null)
                ? trim((string) $input['refund_change_notes'])
                : 'QA manual/local inventory. Availability and fare will be confirmed before payment.',
            'snapshot' => [
                'qa_group_source' => 'MANUAL_LOCAL',
                'audience' => $audience,
                'supplier_connection_id' => null,
                'supplier_reservation_id' => null,
                'created_via' => 'admin_manual_local',
            ],
            'is_active' => (bool) ($input['is_active'] ?? false),
            'synced_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(GroupInventory $inventory, array $input): GroupInventory
    {
        if (! $inventory->isManualLocal()) {
            throw new \InvalidArgumentException('Only manual_local inventory can be updated by this service.');
        }

        $payload = [];
        foreach (['title', 'sector', 'airline_name', 'baggage', 'refund_change_notes'] as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = filled($input[$field]) ? trim((string) $input[$field]) : null;
            }
        }
        if (isset($input['sector'])) {
            $payload['sector'] = strtoupper(trim((string) $input['sector']));
        }
        if (array_key_exists('departure_date', $input)) {
            $payload['departure_date'] = $input['departure_date'];
        }
        if (array_key_exists('return_date', $input)) {
            $payload['return_date'] = filled($input['return_date']) ? $input['return_date'] : null;
        }
        if (array_key_exists('total_seats', $input)) {
            $payload['total_seats'] = max(0, (int) $input['total_seats']);
        }
        if (array_key_exists('price', $input)) {
            $payload['price'] = round((float) $input['price'], 2);
        }
        if (array_key_exists('is_active', $input)) {
            $payload['is_active'] = (bool) $input['is_active'];
        }
        if (array_key_exists('audience', $input) || array_key_exists('package_type', $input)) {
            $snapshot = is_array($inventory->snapshot) ? $inventory->snapshot : [];
            if (array_key_exists('audience', $input)) {
                $audience = strtolower(trim((string) $input['audience']));
                if (in_array($audience, ['b2c', 'b2b', 'boundary'], true)) {
                    $snapshot['audience'] = $audience;
                }
            }
            $snapshot['qa_group_source'] = 'MANUAL_LOCAL';
            $snapshot['supplier_connection_id'] = null;
            $payload['snapshot'] = $snapshot;
        }

        $inventory->update($payload);

        return $inventory->fresh();
    }

    private function resolveCategory(string $name): ?GroupCategory
    {
        $name = $name !== '' ? $name : 'QA Manual';
        $slug = Str::slug($name);

        return GroupCategory::query()->firstOrCreate(
            ['slug' => $slug !== '' ? $slug : 'qa-manual'],
            ['name' => $name, 'is_active' => true, 'sort_order' => 900],
        );
    }
}
