<?php

namespace App\Services\Homepage\FeaturedDeals;

use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventorySearchService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves CMS Featured Deal slots to one eligible GroupInventory each.
 *
 * Modes:
 * - exact_inventory: pinned public_id
 * - auto_cheapest_by_criteria: filter by airline/destination/origin then cheapest
 * - legacy target slots without resolution_mode use auto_cheapest_by_criteria
 */
final class FeaturedDealInventoryResolver
{
    public const MODE_EXACT_INVENTORY = 'exact_inventory';

    public const MODE_AUTO_CHEAPEST = 'auto_cheapest_by_criteria';

    public const RULE_EXACT_INVENTORY = 'exact_inventory';

    public const RULE_EXACT_AIRLINE = 'exact_airline';

    public const RULE_SAME_SECTOR = 'same_sector';

    public const RULE_AIRLINE_ONLY = 'airline_only';

    public const RULE_DESTINATION_ONLY = 'destination_only';

    public const RULE_AIRLINE_DESTINATION = 'airline_destination';

    public const RULE_GLOBAL_FALLBACK = 'global_fallback';

    public function __construct(
        private readonly GroupInventorySearchService $search,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $editorialItems
     * @return list<array{
     *   inventory: GroupInventory,
     *   editorial: array<string, mixed>,
     *   rule: string,
     *   resolution_mode: string,
     *   match_reason: string,
     *   fallback_used: bool,
     *   available_seats: int
     * }>
     */
    public function resolve(array $editorialItems): array
    {
        $eligible = $this->eligibleInventories();
        if ($eligible->isEmpty()) {
            return [];
        }

        $limit = max(1, (int) config('jetpk_homepage.featured_deal_limit', 6));
        $usedIds = [];
        $resolved = [];

        foreach ($editorialItems as $item) {
            if (count($resolved) >= $limit) {
                break;
            }
            if (! is_array($item) || $this->isDisabled($item)) {
                continue;
            }

            $match = $this->matchSlot($item, $eligible, $usedIds);
            if ($match === null) {
                continue;
            }

            $usedIds[] = (int) $match['inventory']->id;
            $resolved[] = $match;
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<int, GroupInventory>  $eligible
     * @param  list<int>  $usedIds
     * @return array{
     *   inventory: GroupInventory,
     *   editorial: array<string, mixed>,
     *   rule: string,
     *   resolution_mode: string,
     *   match_reason: string,
     *   fallback_used: bool,
     *   available_seats: int
     * }|null
     */
    private function matchSlot(array $item, Collection $eligible, array $usedIds): ?array
    {
        $mode = $this->resolutionMode($item);

        if ($mode === self::MODE_EXACT_INVENTORY) {
            return $this->matchExactInventory($item, $eligible, $usedIds);
        }

        return $this->matchAutoCheapest($item, $eligible, $usedIds);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<int, GroupInventory>  $eligible
     * @param  list<int>  $usedIds
     * @return array{
     *   inventory: GroupInventory,
     *   editorial: array<string, mixed>,
     *   rule: string,
     *   resolution_mode: string,
     *   match_reason: string,
     *   fallback_used: bool,
     *   available_seats: int
     * }|null
     */
    private function matchExactInventory(array $item, Collection $eligible, array $usedIds): ?array
    {
        $publicId = trim((string) ($item['inventory_public_id'] ?? $item['public_id'] ?? ''));
        if ($publicId === '') {
            return null;
        }

        $inventory = $eligible->first(
            static fn (GroupInventory $row): bool => strcasecmp(trim((string) ($row->public_id ?: $row->id)), $publicId) === 0
                && ! in_array((int) $row->id, $usedIds, true),
        );

        if ($inventory === null) {
            return null;
        }

        return $this->hit(
            $inventory,
            $item,
            self::RULE_EXACT_INVENTORY,
            self::MODE_EXACT_INVENTORY,
            'Pinned inventory public_id matched eligible inventory.',
            false,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<int, GroupInventory>  $eligible
     * @param  list<int>  $usedIds
     * @return array{
     *   inventory: GroupInventory,
     *   editorial: array<string, mixed>,
     *   rule: string,
     *   resolution_mode: string,
     *   match_reason: string,
     *   fallback_used: bool,
     *   available_seats: int
     * }|null
     */
    private function matchAutoCheapest(array $item, Collection $eligible, array $usedIds): ?array
    {
        $pool = $eligible->filter(
            static fn (GroupInventory $inventory): bool => ! in_array((int) $inventory->id, $usedIds, true),
        );
        if ($pool->isEmpty()) {
            return null;
        }

        $origin = $this->targetIata($item['from'] ?? $item['origin'] ?? '');
        $destination = $this->targetIata($item['to'] ?? $item['destination'] ?? '');
        $airline = $this->preferredAirline($item);
        $autoFilter = strtolower(trim((string) ($item['auto_filter'] ?? '')));

        if ($autoFilter === '' && $origin !== null && $destination !== null && $airline !== '') {
            $autoFilter = 'airline_destination';
        } elseif ($autoFilter === '' && $destination !== null && $airline === '') {
            $autoFilter = 'destination_only';
        } elseif ($autoFilter === '' && $airline !== '' && $destination === null) {
            $autoFilter = 'airline_only';
        }

        if ($autoFilter === 'airline_only' && $airline !== '') {
            $filtered = $pool->filter(fn (GroupInventory $inventory): bool => $this->airlineMatches($inventory, $airline));
            $winner = $this->cheapest($filtered);
            if ($winner !== null) {
                return $this->hit($winner, $item, self::RULE_AIRLINE_ONLY, self::MODE_AUTO_CHEAPEST, 'Cheapest eligible inventory for airline filter.', false);
            }
        }

        if (in_array($autoFilter, ['destination_only', 'airline_destination'], true) && $destination !== null) {
            $filtered = $pool->filter(function (GroupInventory $inventory) use ($destination, $origin, $autoFilter, $airline): bool {
                [, $to] = $this->parseSector($inventory->sector);
                if ($to !== $destination) {
                    return false;
                }
                if ($origin !== null && $this->parseSector($inventory->sector)[0] !== $origin) {
                    return false;
                }
                if ($autoFilter === 'airline_destination' && $airline !== '' && ! $this->airlineMatches($inventory, $airline)) {
                    return false;
                }

                return true;
            });

            $winner = $this->cheapest($filtered);
            if ($winner !== null) {
                $rule = $autoFilter === 'airline_destination' && $airline !== ''
                    ? self::RULE_EXACT_AIRLINE
                    : self::RULE_DESTINATION_ONLY;

                return $this->hit(
                    $winner,
                    $item,
                    $rule,
                    self::MODE_AUTO_CHEAPEST,
                    $rule === self::RULE_EXACT_AIRLINE
                        ? 'Cheapest eligible inventory for airline + destination criteria.'
                        : 'Cheapest eligible inventory for destination criteria.',
                    false,
                );
            }
        }

        if ($origin !== null && $destination !== null) {
            $sectorPool = $pool->filter(
                fn (GroupInventory $inventory): bool => $this->sectorMatches($inventory, $origin, $destination),
            );

            if ($airline !== '' && $sectorPool->isNotEmpty()) {
                $exact = $sectorPool->filter(
                    fn (GroupInventory $inventory): bool => $this->airlineMatches($inventory, $airline),
                );
                $winner = $this->cheapest($exact);
                if ($winner !== null) {
                    return $this->hit($winner, $item, self::RULE_EXACT_AIRLINE, self::MODE_AUTO_CHEAPEST, 'Legacy target: exact airline within sector.', false);
                }
            }

            $sectorWinner = $this->cheapest($sectorPool);
            if ($sectorWinner !== null) {
                return $this->hit($sectorWinner, $item, self::RULE_SAME_SECTOR, self::MODE_AUTO_CHEAPEST, 'Legacy target: same sector without airline match.', $airline !== '');
            }
        }

        $fallback = $this->cheapest($pool);
        if ($fallback === null) {
            return null;
        }

        return $this->hit(
            $fallback,
            $item,
            self::RULE_GLOBAL_FALLBACK,
            self::MODE_AUTO_CHEAPEST,
            'No criteria match; deterministic global cheapest eligible inventory.',
            true,
        );
    }

    /**
     * @return Collection<int, GroupInventory>
     */
    private function eligibleInventories(): Collection
    {
        $today = Carbon::now(config('app.timezone', 'Asia/Karachi'))->toDateString();

        return $this->search->searchQuery([
            'viewer' => null,
            'sort' => 'price',
        ])
            ->where('price', '>', 0)
            ->whereDate('departure_date', '>=', $today)
            ->orderBy('price')
            ->orderBy('departure_date')
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    /**
     * @param  Collection<int, GroupInventory>  $pool
     */
    private function cheapest(Collection $pool): ?GroupInventory
    {
        if ($pool->isEmpty()) {
            return null;
        }

        return $pool
            ->sort(function (GroupInventory $left, GroupInventory $right): int {
                $price = ((float) $left->price) <=> ((float) $right->price);
                if ($price !== 0) {
                    return $price;
                }

                $leftDeparture = (string) ($left->departure_date?->toDateString() ?? '9999-12-31');
                $rightDeparture = (string) ($right->departure_date?->toDateString() ?? '9999-12-31');
                $departure = strcmp($leftDeparture, $rightDeparture);
                if ($departure !== 0) {
                    return $departure;
                }

                $leftPublic = trim((string) ($left->public_id ?: $left->id));
                $rightPublic = trim((string) ($right->public_id ?: $right->id));
                $public = strcmp($leftPublic, $rightPublic);
                if ($public !== 0) {
                    return $public;
                }

                return (int) $left->id <=> (int) $right->id;
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *   inventory: GroupInventory,
     *   editorial: array<string, mixed>,
     *   rule: string,
     *   resolution_mode: string,
     *   match_reason: string,
     *   fallback_used: bool,
     *   available_seats: int
     * }
     */
    private function hit(
        GroupInventory $inventory,
        array $item,
        string $rule,
        string $resolutionMode,
        string $matchReason,
        bool $fallbackUsed,
    ): array {
        return [
            'inventory' => $inventory,
            'editorial' => $item,
            'rule' => $rule,
            'resolution_mode' => $resolutionMode,
            'match_reason' => $matchReason,
            'fallback_used' => $fallbackUsed,
            'available_seats' => $this->availableSeats($inventory),
        ];
    }

    private function availableSeats(GroupInventory $inventory): int
    {
        return max(0, (int) $inventory->total_seats - (int) $inventory->held_seats - (int) $inventory->sold_seats);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolutionMode(array $item): string
    {
        $mode = strtolower(trim((string) ($item['resolution_mode'] ?? '')));
        if ($mode === self::MODE_EXACT_INVENTORY) {
            return self::MODE_EXACT_INVENTORY;
        }

        return self::MODE_AUTO_CHEAPEST;
    }

    private function sectorMatches(GroupInventory $inventory, string $origin, string $destination): bool
    {
        [$from, $to] = $this->parseSector($inventory->sector);

        return $from === $origin && $to === $destination;
    }

    private function airlineMatches(GroupInventory $inventory, string $preferred): bool
    {
        $name = trim((string) ($inventory->airline_name ?? ''));

        return $name !== '' && strcasecmp($name, $preferred) === 0;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseSector(mixed $sector): array
    {
        $sector = trim((string) $sector);
        if ($sector === '') {
            return [null, null];
        }

        $parts = preg_split('/\s*[-–→]\s*/u', $sector);
        if (! is_array($parts) || count($parts) < 2) {
            return [null, null];
        }

        return [$this->targetIata($parts[0]), $this->targetIata($parts[1])];
    }

    private function targetIata(mixed $value): ?string
    {
        $code = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function preferredAirline(array $item): string
    {
        return trim((string) ($item['airline'] ?? $item['preferred_airline'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isDisabled(array $item): bool
    {
        if (in_array((string) ($item['enabled'] ?? '1'), ['0', 'false', 'no', 'off'], true)) {
            return true;
        }

        $featured = $item['featured'] ?? $item['featured_on_home'] ?? '1';

        return in_array((string) $featured, ['0', 'false', 'no', 'off'], true);
    }
}
