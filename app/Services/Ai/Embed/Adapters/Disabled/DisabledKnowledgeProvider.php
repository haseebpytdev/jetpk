<?php

namespace App\Services\Ai\Embed\Adapters\Disabled;

use App\Contracts\Ai\Embed\KnowledgeProvider;

final class DisabledKnowledgeProvider implements KnowledgeProvider
{
    public function search(string $query, int $limit = 3): array
    {
        return [];
    }
}
