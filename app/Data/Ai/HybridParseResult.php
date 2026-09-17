<?php

namespace App\Data\Ai;

/**
 * Result of the model-free hybrid language pipeline.
 */
final class HybridParseResult
{
    /**
     * @param  array<string, string>  $provenance
     * @param  list<array{label: string, value: string}>  $clarificationOptions
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public readonly TravelIntent $intent,
        public readonly bool $clarificationRequired = false,
        public readonly ?string $clarificationMessage = null,
        public readonly array $clarificationOptions = [],
        public readonly array $provenance = [],
        public readonly ?string $rankingPreference = null,
        public readonly string $language = 'en',
        public readonly array $state = [],
        public readonly bool $llmBypassed = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMeta(): array
    {
        return [
            'intent' => $this->intent->toArray(),
            'clarification_required' => $this->clarificationRequired,
            'clarification_message' => $this->clarificationMessage,
            'clarification_options' => $this->clarificationOptions,
            'provenance' => $this->provenance,
            'ranking_preference' => $this->rankingPreference,
            'language' => $this->language,
            'llm_bypassed' => $this->llmBypassed,
            'LOCAL_LLM_REQUIRED_FOR_CORE' => false,
        ];
    }
}
