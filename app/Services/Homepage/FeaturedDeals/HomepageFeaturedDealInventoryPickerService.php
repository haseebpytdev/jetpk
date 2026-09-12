<?php

namespace App\Services\Homepage\FeaturedDeals;

use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupInventorySearchService;
use App\Support\GroupTicketing\GroupInventoryCardPresenter;
use Illuminate\Support\Carbon;

/**
 * Admin inventory picker rows for homepage Featured Deals CMS slots.
 */
final class HomepageFeaturedDealInventoryPickerService
{
    public function __construct(
        private readonly GroupInventorySearchService $search,
        private readonly GroupInventoryCardPresenter $presenter,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listEligible(?string $query = null, int $limit = 50): array
    {
        $today = Carbon::now(config('app.timezone', 'Asia/Karachi'))->toDateString();
        $builder = $this->search->searchQuery([
            'viewer' => auth()->user(),
            'sort' => 'price',
        ])
            ->where('price', '>', 0)
            ->whereDate('departure_date', '>=', $today)
            ->orderBy('price')
            ->orderBy('departure_date')
            ->orderBy('public_id')
            ->limit(max(1, min(100, $limit)));

        $needle = trim((string) $query);
        if ($needle !== '') {
            $builder->where(function ($q) use ($needle): void {
                $q->where('public_id', 'like', '%'.$needle.'%')
                    ->orWhere('airline_name', 'like', '%'.$needle.'%')
                    ->orWhere('sector', 'like', '%'.$needle.'%')
                    ->orWhere('title', 'like', '%'.$needle.'%');
            });
        }

        $rows = $builder->get();
        $cards = $this->presenter->presentMany($rows, false);

        return $rows->map(function (GroupInventory $inventory) use ($cards): array {
            $card = $cards->get($inventory->id, []);
            [$origin, $destination] = $this->parseSector($inventory->sector);
            $available = max(0, (int) $inventory->total_seats - (int) $inventory->held_seats - (int) $inventory->sold_seats);

            return [
                'inventory_id' => (int) $inventory->id,
                'public_id' => trim((string) ($inventory->public_id ?: $inventory->id)),
                'airline' => (string) ($card['airline_name'] ?? $inventory->airline_name ?? ''),
                'origin' => (string) ($card['origin_code'] ?? $origin ?? ''),
                'destination' => (string) ($card['dest_code'] ?? $destination ?? ''),
                'departure_date' => (string) ($inventory->departure_date?->toDateString() ?? ''),
                'departure_date_label' => (string) ($card['departure_date_short'] ?? ''),
                'available_seats' => $available,
                'current_price' => (int) round((float) $inventory->price),
                'price_label' => (string) ($card['price_label'] ?? ''),
                'status' => $inventory->is_active ? 'active' : 'inactive',
                'sector' => (string) ($inventory->sector ?? ''),
            ];
        })->values()->all();
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

        return [strtoupper(trim($parts[0])), strtoupper(trim($parts[1]))];
    }
}
