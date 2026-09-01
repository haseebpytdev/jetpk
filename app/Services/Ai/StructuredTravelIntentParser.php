<?php

namespace App\Services\Ai;

use App\Data\Ai\HybridParseResult;
use App\Data\Ai\TravelIntent;
use App\Services\Ai\Hybrid\HybridTravelPipeline;
use Carbon\Carbon;

/**
 * Deterministic no-LLM TravelIntent parser — delegates to HybridTravelPipeline.
 */
final class StructuredTravelIntentParser
{
    public function __construct(
        private readonly HybridTravelPipeline $pipeline,
    ) {}

    /**
     * @param  array<string, mixed>|null  $prior
     */
    public function parse(string $message, ?array $prior = null): TravelIntent
    {
        return $this->parseHybrid($message, $prior)->intent;
    }

    /**
     * @param  array<string, mixed>|null  $prior
     */
    public function parseHybrid(string $message, ?array $prior = null, ?Carbon $now = null): HybridParseResult
    {
        return $this->pipeline->parse($message, $prior, $now);
    }
}
