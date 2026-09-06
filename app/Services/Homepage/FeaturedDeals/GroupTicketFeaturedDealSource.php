<?php

namespace App\Services\Homepage\FeaturedDeals;

use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventorySearchService;
use App\Support\Client\JetpkHomepageFareDisplay;
use App\Support\GroupTicketing\GroupInventoryCardPresenter;
use App\Support\Media\PublicMediaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only homepage featured deals from public group ticketing inventory.
 */
final class GroupTicketFeaturedDealSource implements HomepageFeaturedDealSource
{
    public function __construct(
        private readonly GroupInventorySearchService $search,
        private readonly GroupInventoryCardPresenter $presenter,
    ) {}

    public function key(): string
    {
        return 'group_ticket';
    }

    /**
     * @param  list<array<string, mixed>>  $editorialItems
     * @return list<array<string, mixed>>
     */
    public function deals(array $editorialItems = []): array
    {
        $inventories = $this->eligibleInventories();
        if ($inventories->isEmpty()) {
            return [];
        }

        $cards = $this->presenter->presentMany($inventories, false);
        $byId = $inventories->keyBy(fn (GroupInventory $row): int => (int) $row->id);
        $limit = max(1, (int) config('jetpk_homepage.featured_deal_limit', 6));

        $featuredIds = $this->featuredInventoryIds($editorialItems);
        $ordered = $featuredIds === []
            ? $inventories->take($limit)
            : collect($featuredIds)
                ->map(fn (int $id): ?GroupInventory => $byId->get($id))
                ->filter()
                ->take($limit);

        $editorialByInventory = $this->editorialByInventory($editorialItems);
        $deals = [];

        foreach ($ordered as $inventory) {
            $card = $cards->get($inventory->id, []);
            $editorial = $editorialByInventory[(int) $inventory->id] ?? [];
            $price = (float) $inventory->price;
            if ($price <= 0) {
                continue;
            }

            $from = strtoupper((string) ($card['origin_code'] ?? ''));
            $to = strtoupper((string) ($card['dest_code'] ?? ''));
            $image = PublicMediaUrl::normalize((string) ($editorial['image'] ?? '')) ?? null;
            $headline = trim((string) ($editorial['title'] ?? $editorial['headline'] ?? ''));
            if ($headline === '') {
                $headline = trim((string) ($inventory->title ?? ''));
            }

            $deals[] = [
                'id' => (string) ($inventory->public_id ?: $inventory->id),
                'source' => $this->key(),
                'inventory_id' => (int) $inventory->id,
                'airline' => (string) ($card['airline_name'] ?? $inventory->airline_name ?? ''),
                'from' => $from,
                'to' => $to,
                'depart' => (string) ($card['departure_date_short'] ?? ''),
                'arrive' => (string) ($inventory->return_date?->format('j M Y') ?? ''),
                'dur' => '',
                'stops' => 0,
                'price' => (int) round($price),
                'price_label' => JetpkHomepageFareDisplay::formatLabel($price, (string) ($inventory->currency ?: 'PKR')),
                'title' => $headline,
                'badge' => trim((string) ($editorial['badge'] ?? '')),
                'description' => trim((string) ($editorial['description'] ?? '')),
                'image' => $image,
                'image_alt' => trim((string) ($editorial['image_alt'] ?? $headline)),
                'image_asset_key' => trim((string) ($editorial['image_asset_key'] ?? '')),
                'media_source' => $image !== null ? 'cms' : 'none',
                'href' => (string) (route('group-ticketing.show', $inventory->public_id ?: $inventory->id, false) ?: ''),
            ];
        }

        return $deals;
    }

    /**
     * @return Collection<int, GroupInventory>
     */
    private function eligibleInventories(): Collection
    {
        $today = Carbon::now(config('app.timezone', 'Asia/Karachi'))->toDateString();

        return $this->search->searchQuery([
            'viewer' => null,
        ])
            ->where('price', '>', 0)
            ->whereDate('departure_date', '>=', $today)
            ->orderBy('departure_date')
            ->orderBy('price')
            ->limit(24)
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $editorialItems
     * @return list<int>
     */
    private function featuredInventoryIds(array $editorialItems): array
    {
        $ids = [];
        foreach ($editorialItems as $item) {
            if (! is_array($item) || (($item['enabled'] ?? '1') === '0')) {
                continue;
            }
            $featured = $item['featured'] ?? $item['featured_on_home'] ?? '1';
            if (in_array((string) $featured, ['0', 'false', 'no', 'off'], true)) {
                continue;
            }
            $id = (int) ($item['inventory_id'] ?? $item['group_inventory_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<array<string, mixed>>  $editorialItems
     * @return array<int, array<string, mixed>>
     */
    private function editorialByInventory(array $editorialItems): array
    {
        $map = [];
        foreach ($editorialItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = (int) ($item['inventory_id'] ?? $item['group_inventory_id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $item;
            }
        }

        return $map;
    }
}
