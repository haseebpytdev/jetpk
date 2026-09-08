<?php

namespace App\Services\Homepage\FeaturedDeals;

use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventorySearchService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves CMS Featured Deal TARGET slots to one eligible GroupInventory each.
 */
final class FeaturedDealInventoryResolver
{
    public const RULE_EXACT_AIRLINE = 'exact_airline';

    public const RULE_SAME_SECTOR = 'same_sector';

    public const RULE_GLOBAL_FALLBACK = 'global_fallback';

    public function __construct(
        private readonly GroupInventorySearchService $search,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $editorialItems
     * @return list<array{inventory: GroupInventory, editorial: array<string, mixed>, rule: string}>
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
     * @return array{inventory: GroupInventory, editorial: array<string, mixed>, rule: string}|null
     */
    private function matchSlot(array $item, Collection $eligible, array $usedIds): ?array
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
                    return $this->hit($winner, $item, self::RULE_EXACT_AIRLINE);
                }
            }

            $sectorWinner = $this->cheapest($sectorPool);
            if ($sectorWinner !== null) {
                return $this->hit($sectorWinner, $item, self::RULE_SAME_SECTOR);
            }
        }

        $fallback = $this->cheapest($pool);
        if ($fallback === null) {
            return null;
        }

        return $this->hit($fallback, $item, self::RULE_GLOBAL_FALLBACK);
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

                return (int) $left->id <=> (int) $right->id;
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{inventory: GroupInventory, editorial: array<string, mixed>, rule: string}
     */
    private function hit(GroupInventory $inventory, array $item, string $rule): array
    {
        return [
            'inventory' => $inventory,
            'editorial' => $item,
            'rule' => $rule,
        ];
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
