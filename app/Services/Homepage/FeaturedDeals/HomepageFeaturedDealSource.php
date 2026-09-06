<?php

namespace App\Services\Homepage\FeaturedDeals;

/**
 * Small source contract for homepage featured deals (group_ticket now; tours later).
 */
interface HomepageFeaturedDealSource
{
    public function key(): string;

    /**
     * @param  list<array<string, mixed>>  $editorialItems
     * @return list<array<string, mixed>>
     */
    public function deals(array $editorialItems = []): array;
}
