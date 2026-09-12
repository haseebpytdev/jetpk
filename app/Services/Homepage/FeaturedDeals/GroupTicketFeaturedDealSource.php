<?php

namespace App\Services\Homepage\FeaturedDeals;

use App\Models\GroupInventory;
use App\Support\Client\JetpkHomepageFareDisplay;
use App\Support\GroupTicketing\GroupInventoryCardPresenter;
use App\Support\GroupTicketing\GroupTicketingNextFrontend;
use App\Support\Media\PublicMediaUrl;

/**
 * Read-only homepage featured deals from public group ticketing inventory.
 *
 * CMS items are TARGET + editorial only. Commercial fields come from one
 * resolved GroupInventory per slot.
 */
final class GroupTicketFeaturedDealSource implements HomepageFeaturedDealSource
{
    public function __construct(
        private readonly FeaturedDealInventoryResolver $resolver,
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
        $resolved = $this->resolver->resolve($editorialItems);
        if ($resolved === []) {
            return [];
        }

        $inventories = collect(array_map(
            static fn (array $row): GroupInventory => $row['inventory'],
            $resolved,
        ));
        $cards = $this->presenter->presentMany($inventories, false);
        $deals = [];

        foreach ($resolved as $row) {
            $inventory = $row['inventory'];
            $editorial = $row['editorial'];
            $card = $cards->get($inventory->id, []);
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
            $publicId = trim((string) ($inventory->public_id ?: $inventory->id));

            $deals[] = [
                'id' => $publicId,
                'source' => $this->key(),
                'inventory_id' => (int) $inventory->id,
                'public_id' => $publicId,
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
                'availability' => 'available',
                'resolution_rule' => (string) $row['rule'],
                'resolution_mode' => (string) ($row['resolution_mode'] ?? ''),
                'match_reason' => (string) ($row['match_reason'] ?? ''),
                'fallback_used' => (bool) ($row['fallback_used'] ?? false),
                'available_seats' => (int) ($row['available_seats'] ?? 0),
                'cms_slot_id' => trim((string) ($editorial['id'] ?? '')),
                'href' => GroupTicketingNextFrontend::detailPath($inventory),
            ];
        }

        return $deals;
    }
}
