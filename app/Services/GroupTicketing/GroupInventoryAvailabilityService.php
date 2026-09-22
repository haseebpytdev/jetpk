<?php

namespace App\Services\GroupTicketing;

use App\Models\GroupInventory;
use App\Support\GroupTicketing\GroupTicketingLivePolicy;
use Illuminate\Support\Facades\Log;

/**
 * Per-package inventory revalidation before group checkout gates (read-only supplier calls).
 */
class GroupInventoryAvailabilityService
{
    /** @deprecated Use GroupTicketingLivePolicy::BOOKING_BLOCKED_MESSAGE */
    public const UNAVAILABLE_MESSAGE = GroupTicketingLivePolicy::BOOKING_BLOCKED_MESSAGE;

    public function __construct(
        private readonly GroupTicketSupplierRegistry $registry,
        private readonly GroupInventorySyncService $syncService,
    ) {}

    public static function insufficientSeatsMessage(int $availableSeats): string
    {
        $label = $availableSeats === 1 ? 'seat' : 'seats';

        return "Only {$availableSeats} {$label} are currently available for this group.";
    }

    public static function bookingBlockedMessage(): string
    {
        return GroupTicketingLivePolicy::BOOKING_BLOCKED_MESSAGE;
    }

    /**
     * @return array{
     *     ok: bool,
     *     unavailable: bool,
     *     insufficient_seats: bool,
     *     available_seats: int,
     *     provider_confirmed: bool,
     *     inventory: GroupInventory
     * }
     */
    public function revalidate(GroupInventory $inventory, int $requestedSeats): array
    {
        $requestedSeats = max(1, $requestedSeats);
        $requireLive = GroupTicketingLivePolicy::requireLiveProviderForReservation();
        $blockWhenUnavailable = GroupTicketingLivePolicy::blockBookingWhenProviderUnavailable();
        $providerConfirmed = false;

        $adapter = $this->registry->forInventory($inventory);
        $detailId = $inventory->public_id !== ''
            ? $inventory->public_id
            : $adapter->publicIdPrefix().$inventory->supplier_package_id;

        if ($requireLive && $blockWhenUnavailable) {
            if (! $adapter->isEnabled() || ! $adapter->isConfigured()) {
                Log::warning('group_inventory_booking_blocked_provider_unavailable', [
                    'inventory_id' => $inventory->id,
                    'supplier' => $inventory->supplier,
                    'supplier_package_id' => $inventory->supplier_package_id,
                    'reason' => 'provider_not_configured',
                ]);

                return $this->blockedResult($inventory);
            }

            try {
                $package = $adapter->getPackageDetail($detailId, forceFresh: true);

                if ($package === null) {
                    $this->syncService->refreshSingle($inventory, null);
                    Log::warning('group_inventory_booking_blocked_live_unavailable', [
                        'inventory_id' => $inventory->id,
                        'supplier' => $inventory->supplier,
                        'supplier_package_id' => $inventory->supplier_package_id,
                        'reason' => 'package_missing',
                    ]);
                    $inventory->refresh();

                    return $this->blockedResult($inventory);
                }

                $this->syncService->refreshSingle($inventory, $package);
                $providerConfirmed = true;
            } catch (\Throwable $exception) {
                Log::warning('group_inventory_booking_blocked_provider_failed', [
                    'inventory_id' => $inventory->id,
                    'supplier' => $inventory->supplier,
                    'supplier_package_id' => $inventory->supplier_package_id,
                    'message' => $exception->getMessage(),
                ]);

                return $this->blockedResult($inventory);
            }
        } else {
            try {
                if ($adapter->isEnabled() && $adapter->isConfigured() && trim((string) $inventory->supplier_package_id) !== '') {
                    $package = $adapter->getPackageDetail($detailId);

                    if ($package === null) {
                        $this->syncService->refreshSingle($inventory, null);
                        Log::warning('group_inventory_selected_package_unavailable', [
                            'inventory_id' => $inventory->id,
                            'supplier' => $inventory->supplier,
                            'supplier_package_id' => $inventory->supplier_package_id,
                        ]);
                    } else {
                        $this->syncService->refreshSingle($inventory, $package);
                        $providerConfirmed = true;
                    }
                }
            } catch (\Throwable $exception) {
                Log::warning('group_inventory_revalidate_failed', [
                    'inventory_id' => $inventory->id,
                    'supplier' => $inventory->supplier,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $inventory->refresh();

        if ($requireLive && $blockWhenUnavailable && ! $providerConfirmed) {
            return $this->blockedResult($inventory);
        }

        if (! $inventory->is_active || $inventory->availableSeats() <= 0) {
            if ($requireLive && $blockWhenUnavailable) {
                Log::warning('group_inventory_booking_blocked_live_unavailable', [
                    'inventory_id' => $inventory->id,
                    'supplier' => $inventory->supplier,
                    'supplier_package_id' => $inventory->supplier_package_id,
                    'reason' => 'no_seats_after_live_refresh',
                ]);
            }

            return $this->result(false, true, false, $inventory, $providerConfirmed);
        }

        if (! $inventory->hasAvailability($requestedSeats)) {
            Log::warning('group_inventory_selected_package_insufficient_seats', [
                'inventory_id' => $inventory->id,
                'requested_seats' => $requestedSeats,
                'available_seats' => $inventory->availableSeats(),
            ]);

            return $this->result(false, false, true, $inventory, $providerConfirmed);
        }

        return $this->result(true, false, false, $inventory, $providerConfirmed);
    }

    /**
     * @return array{
     *     ok: bool,
     *     unavailable: bool,
     *     insufficient_seats: bool,
     *     available_seats: int,
     *     provider_confirmed: bool,
     *     inventory: GroupInventory
     * }
     */
    private function blockedResult(GroupInventory $inventory): array
    {
        return $this->result(false, true, false, $inventory, false);
    }

    /**
     * @return array{
     *     ok: bool,
     *     unavailable: bool,
     *     insufficient_seats: bool,
     *     available_seats: int,
     *     provider_confirmed: bool,
     *     inventory: GroupInventory
     * }
     */
    private function result(
        bool $ok,
        bool $unavailable,
        bool $insufficientSeats,
        GroupInventory $inventory,
        bool $providerConfirmed,
    ): array {
        return [
            'ok' => $ok,
            'unavailable' => $unavailable,
            'insufficient_seats' => $insufficientSeats,
            'available_seats' => $inventory->availableSeats(),
            'provider_confirmed' => $providerConfirmed,
            'inventory' => $inventory,
        ];
    }
}
