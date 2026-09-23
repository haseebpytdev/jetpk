<?php

namespace App\Services\Ai\Embed\Adapters\JetPakistan;

use App\Contracts\Ai\Embed\KnowledgeProvider;
use App\Models\AiEmbedTenant;
use App\Services\Ai\KnowledgeSearchService;

final class JetPakistanKnowledgeProvider implements KnowledgeProvider
{
    public function __construct(
        private readonly AiEmbedTenant $tenant,
        private readonly KnowledgeSearchService $knowledge = new KnowledgeSearchService,
    ) {}

    public function search(string $query, int $limit = 3): array
    {
        if ($this->tenant->slug === 'jetpakistan') {
            return $this->knowledge->searchJetPakistanCorpus($query, $limit);
        }

        $namespace = trim($this->tenant->knowledge_namespace);
        if ($namespace === '' || strcasecmp($namespace, 'jetpakistan') === 0) {
            return [];
        }

        return $this->knowledge->search($query, $limit, $namespace);
    }
}
