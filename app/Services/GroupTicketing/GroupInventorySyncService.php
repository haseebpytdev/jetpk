<?php

namespace App\Services\GroupTicketing;

use App\Data\UmrahGroupPackageData;
use App\Models\GroupCategory;
use App\Models\GroupInventory;
use App\Services\Suppliers\AlHaider\AlHaiderUmrahGroupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Syncs supplier group packages into local group_inventories for search facets and booking.
 */
class GroupInventorySyncService
{
    public function __construct(
        private readonly AlHaiderUmrahGroupService $umrahGroups,
    ) {}

    /**
     * @return array{synced: int, deactivated: int, skipped: bool, message: ?string}
     */
    public function sync(bool $dryRun = false, bool $forceFresh = false): array
    {
        $result = $this->umrahGroups->search([], $forceFresh);

        if ($result->api_disabled) {
            return ['synced' => 0, 'deactivated' => 0, 'skipped' => true, 'message' => 'Al-Haider API disabled.'];
        }

        if ($result->api_unavailable && $result->packages === []) {
            return ['synced' => 0, 'deactivated' => 0, 'skipped' => true, 'message' => 'Al-Haider API unavailable.'];
        }

        $seenKeys = [];
        $synced = 0;
        $deactivatedCount = 0;

        if ($dryRun) {
            return [
                'synced' => count($result->packages),
                'deactivated' => 0,
                'skipped' => false,
                'message' => 'Dry run — no database changes.',
            ];
        }

        DB::transaction(function () use ($result, &$seenKeys, &$synced, &$deactivatedCount): void {
            foreach ($result->packages as $package) {
                $key = $package->supplier.':'.$package->supplier_package_id;
                $seenKeys[$key] = true;

                $categoryId = $this->resolveCategoryId($package->package_type);

                $existing = GroupInventory::query()
                    ->where('supplier', $package->supplier)
                    ->where('supplier_package_id', $package->supplier_package_id)
                    ->first();

                $held = $existing?->held_seats ?? 0;
                $sold = $existing?->sold_seats ?? 0;
                $totalSeats = max($held + $sold + max(0, $package->seats_available), $held + $sold);

                GroupInventory::query()->updateOrCreate(
                    [
                        'supplier' => $package->supplier,
                        'supplier_package_id' => $package->supplier_package_id,
                    ],
                    [
                        'public_id' => $package->public_id,
                        'group_category_id' => $categoryId,
                        'title' => $package->title,
                        'sector' => $package->sector,
                        'airline_id' => $this->resolveAirlineId($package),
                        'airline_name' => $package->airline,
                        'package_type' => $package->package_type,
                        'departure_date' => $package->departure_date,
                        'return_date' => $package->return_date,
                        'total_seats' => $totalSeats,
                        'price' => $package->price,
                        'price_child' => $package->price_child,
                        'price_infant' => $package->price_infant,
                        'currency' => $package->currency,
                        'baggage' => $package->baggage,
                        'refund_change_notes' => null,
                        'snapshot' => $package->toArray(),
                        'is_active' => ($totalSeats - $held - $sold) > 0,
                        'synced_at' => now(),
                    ],
                );

                $synced++;
            }

            GroupInventory::query()
                ->where('supplier', 'alhaider')
                ->where('is_active', true)
                ->chunkById(100, function ($rows) use ($seenKeys, &$deactivatedCount): void {
                    foreach ($rows as $row) {
                        $key = $row->supplier.':'.$row->supplier_package_id;
                        if (! isset($seenKeys[$key])) {
                            $row->update(['is_active' => false, 'synced_at' => now()]);
                            $deactivatedCount++;
                        }
                    }
                });
        });

        Log::info('group_ticketing.inventory_synced', [
            'synced' => $synced,
            'deactivated' => $deactivatedCount,
        ]);

        return [
            'synced' => $synced,
            'deactivated' => $deactivatedCount,
            'skipped' => false,
            'message' => null,
        ];
    }

    /**
     * Refresh a single inventory row from supplier package data (checkout revalidation).
     *
     * @return bool True when package still exists and row was updated; false when marked inactive.
     */
    public function refreshSingle(GroupInventory $inventory, ?UmrahGroupPackageData $package): bool
    {
        if ($package === null) {
            $inventory->update(['is_active' => false, 'synced_at' => now()]);

            return false;
        }

        $held = (int) $inventory->held_seats;
        $sold = (int) $inventory->sold_seats;
        $totalSeats = max($held + $sold + max(0, $package->seats_available), $held + $sold);
        $available = $totalSeats - $held - $sold;

        $inventory->update([
            'public_id' => $package->public_id,
            'group_category_id' => $this->resolveCategoryId($package->package_type),
            'title' => $package->title,
            'sector' => $package->sector,
            'airline_id' => $this->resolveAirlineId($package),
            'airline_name' => $package->airline,
            'package_type' => $package->package_type,
            'departure_date' => $package->departure_date,
            'return_date' => $package->return_date,
            'total_seats' => $totalSeats,
            'price' => $package->price,
            'price_child' => $package->price_child,
            'price_infant' => $package->price_infant,
            'currency' => $package->currency,
            'baggage' => $package->baggage,
            'snapshot' => $package->toArray(),
            'is_active' => $available > 0,
            'synced_at' => now(),
        ]);

        return $available > 0;
    }

    private function resolveCategoryId(?string $packageType): ?int
    {
        $packageType = trim((string) $packageType);
        if ($packageType === '') {
            return null;
        }

        $slug = Str::slug($packageType);
        if ($slug === '') {
            return null;
        }

        $category = GroupCategory::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $packageType, 'is_active' => true, 'sort_order' => 0],
        );

        return $category->id;
    }

    private function resolveAirlineId(UmrahGroupPackageData $package): ?int
    {
        $snapshot = $package->toArray();
        $airlineId = $snapshot['airline_id'] ?? null;
        if (is_numeric($airlineId)) {
            return (int) $airlineId;
        }

        return null;
    }
}
