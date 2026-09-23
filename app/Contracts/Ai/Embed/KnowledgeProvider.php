<?php

namespace App\Contracts\Ai\Embed;

interface KnowledgeProvider
{
    /**
     * @return list<array{slug: string, title: string, excerpt: string, score: float}>
     */
    public function search(string $query, int $limit = 3): array;
}
