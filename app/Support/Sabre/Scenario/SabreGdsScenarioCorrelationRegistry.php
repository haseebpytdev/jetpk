<?php

namespace App\Support\Sabre\Scenario;

use Illuminate\Support\Str;

/**
 * Request-scoped correlation IDs for Sabre GDS scenario search/revalidation diagnostics.
 */
final class SabreGdsScenarioCorrelationRegistry
{
    private ?string $searchCorrelationId = null;

    private ?string $revalidationCorrelationId = null;

    public function startSearchCorrelation(): string
    {
        $this->searchCorrelationId = (string) Str::uuid();

        return $this->searchCorrelationId;
    }

    public function startRevalidationCorrelation(): string
    {
        $this->revalidationCorrelationId = (string) Str::uuid();

        return $this->revalidationCorrelationId;
    }

    public function searchCorrelationId(): ?string
    {
        return $this->searchCorrelationId;
    }

    public function revalidationCorrelationId(): ?string
    {
        return $this->revalidationCorrelationId;
    }

    public function reset(): void
    {
        $this->searchCorrelationId = null;
        $this->revalidationCorrelationId = null;
    }
}
