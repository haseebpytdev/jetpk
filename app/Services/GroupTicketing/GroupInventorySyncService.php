<?php

namespace App\Services\GroupTicketing;

use App\Contracts\GroupTicketing\GroupTicketSupplierInterface;
use App\Data\UmrahGroupPackageData;
use App\Models\GroupCategory;
use App\Models\GroupInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Syncs supplier group packages into local group_inventories for search facets and booking.
 */
class GroupInventorySyncService
{
    public function __construct(
        private readonly GroupTicketSupplierRegistry $registry,
    ) {}

    /**
     * @return array{
     *     synced: int,
     *     deactivated: int,
     *     skipped: bool,
     *     message: ?string,
     *     providers: array<string, array<string, mixed>>,
     *     successful_providers: list<string>,
     *     failed_providers: list<string>
     * }
     */
    public function sync(?string $provider = null, bool $dryRun = false, bool $forceFresh = false): array
    {
        $adapters = $this->resolveAdapters($provider);
        if ($adapters === []) {
            return $this->aggregateResult(
                synced: 0,
                deactivated: 0,
                skipped: true,
                message: $provider !== null
                    ? "Provider [{$provider}] is not enabled or configured."
                    : 'No enabled group ticket providers are configured.',
                providers: [],
                successfulProviders: [],
                failedProviders: $provider !== null ? [$provider] : [],
            );
        }

        $providers = [];
        $successfulProviders = [];
        $failedProviders = [];
        $messages = [];
        $totalSynced = 0;
        $totalDeactivated = 0;

        foreach ($adapters as $adapter) {
            $key = $adapter->providerKey();

            try {
                $providerResult = $this->syncProvider($adapter, $dryRun, $forceFresh);
                $providers[$key] = $providerResult;

                if ($this->providerSyncSucceeded($providerResult)) {
                    $successfulProviders[] = $key;
                    $totalSynced += (int) ($providerResult['synced'] ?? 0);
                    $totalDeactivated += (int) ($providerResult['deactivated'] ?? 0);
                } else {
                    $failedProviders[] = $key;
                    if (! empty($providerResult['message'])) {
                        $messages[] = $key.': '.$providerResult['message'];
                    }
                }
            } catch (\Throwable $exception) {
                $failedProviders[] = $key;
                $providers[$key] = [
                    'synced' => 0,
                    'deactivated' => 0,
                    'skipped' => true,
                    'message' => $exception->getMessage(),
                    'error' => true,
                ];

                Log::error('group_ticketing.provider_sync_failed', [
                    'provider' => $key,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $overallSkipped = $successfulProviders === [];

        return $this->aggregateResult(
            synced: $totalSynced,
            deactivated: $totalDeactivated,
            skipped: $overallSkipped,
            message: $overallSkipped ? ($messages[0] ?? 'All group ticket providers failed or were skipped.') : null,
            providers: $providers,
            successfulProviders: $successfulProviders,
            failedProviders: $failedProviders,
        );
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

    /**
     * @return list<GroupTicketSupplierInterface>
     */
    private function resolveAdapters(?string $provider): array
    {
        if ($provider !== null && trim($provider) !== '') {
            $adapter = $this->registry->for(trim($provider));

            return ($adapter->isEnabled() && $adapter->isConfigured()) ? [$adapter] : [];
        }

        return $this->registry->enabledConfigured();
    }

    /**
     * @return array{synced: int, deactivated: int, skipped: bool, message: ?string}
     */
    private function syncProvider(GroupTicketSupplierInterface $adapter, bool $dryRun, bool $forceFresh): array
    {
        $providerKey = $adapter->providerKey();
        $result = $adapter->searchPackages([], $forceFresh);

        if ($result->api_disabled) {
            return [
                'synced' => 0,
                'deactivated' => 0,
                'skipped' => true,
                'message' => ucfirst(str_replace('_', '-', $providerKey)).' API disabled.',
            ];
        }

        if ($result->api_unavailable && $result->packages === []) {
            return [
                'synced' => 0,
                'deactivated' => 0,
                'skipped' => true,
                'message' => ucfirst(str_replace('_', '-', $providerKey)).' API unavailable.',
            ];
        }

        if ($dryRun) {
            return [
                'synced' => count($result->packages),
                'deactivated' => 0,
                'skipped' => false,
                'message' => 'Dry run — no database changes.',
            ];
        }

        $seenKeys = [];
        $synced = 0;
        $deactivatedCount = 0;

        DB::transaction(function () use ($result, $providerKey, &$seenKeys, &$synced, &$deactivatedCount): void {
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
                ->where('supplier', $providerKey)
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
            'provider' => $providerKey,
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
     * @param  array<string, mixed>  $providerResult
     */
    private function providerSyncSucceeded(array $providerResult): bool
    {
        return ($providerResult['skipped'] ?? false) === false;
    }

    /**
     * @param  array<string, array<string, mixed>>  $providers
     * @param  list<string>  $successfulProviders
     * @param  list<string>  $failedProviders
     * @return array{
     *     synced: int,
     *     deactivated: int,
     *     skipped: bool,
     *     message: ?string,
     *     providers: array<string, array<string, mixed>>,
     *     successful_providers: list<string>,
     *     failed_providers: list<string>
     * }
     */
    private function aggregateResult(
        int $synced,
        int $deactivated,
        bool $skipped,
        ?string $message,
        array $providers,
        array $successfulProviders,
        array $failedProviders,
    ): array {
        return [
            'synced' => $synced,
            'deactivated' => $deactivated,
            'skipped' => $skipped,
            'message' => $message,
            'providers' => $providers,
            'successful_providers' => $successfulProviders,
            'failed_providers' => $failedProviders,
        ];
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
