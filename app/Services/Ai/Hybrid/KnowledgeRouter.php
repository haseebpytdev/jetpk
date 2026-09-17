<?php

namespace App\Services\Ai\Hybrid;

use App\Services\Ai\KnowledgeSearchService;

/**
 * Routes support/FAQ queries to approved local knowledge only.
 */
final class KnowledgeRouter
{
    public function __construct(
        private readonly KnowledgeSearchService $knowledge,
    ) {}

    /**
     * @return array{hits: list<array{slug: string, title: string, excerpt: string, score: float}>, prefer_handoff: bool}
     */
    public function route(string $message): array
    {
        $lower = mb_strtolower($message);
        $preferHandoff = (bool) preg_match(
            '/payment dispute|ticket problem|refund dispute|my booking|passport|visa (issue|problem)|wrong charge|charged twice|document (issue|problem)/u',
            $lower
        );

        return [
            'hits' => $this->knowledge->search($message, 3),
            'prefer_handoff' => $preferHandoff,
        ];
    }
}
