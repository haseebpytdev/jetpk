<?php

namespace App\Services\Ai\Embed;

use App\Contracts\Ai\Embed\BookingLookupProvider;
use App\Contracts\Ai\Embed\KnowledgeProvider;
use App\Contracts\Ai\Embed\LeadProvider;
use App\Contracts\Ai\Embed\SearchProvider;
use App\Contracts\Ai\Embed\SupportHandoffProvider;
use App\Models\AiEmbedTenant;
use App\Support\Ai\Embed\EmbedTenantCapability;

/**
 * Request-scoped embed execution context. Inactive on public Ask JetPakistan routes.
 */
final class EmbedRuntimeContext
{
    private bool $active = false;

    private ?AiEmbedTenant $tenant = null;

    private ?KnowledgeProvider $knowledge = null;

    private ?LeadProvider $lead = null;

    private ?SearchProvider $search = null;

    private ?BookingLookupProvider $bookingLookup = null;

    private ?SupportHandoffProvider $handoff = null;

    public function activate(
        AiEmbedTenant $tenant,
        KnowledgeProvider $knowledge,
        LeadProvider $lead,
        SearchProvider $search,
        BookingLookupProvider $bookingLookup,
        SupportHandoffProvider $handoff,
    ): void {
        $this->active = true;
        $this->tenant = $tenant;
        $this->knowledge = $knowledge;
        $this->lead = $lead;
        $this->search = $search;
        $this->bookingLookup = $bookingLookup;
        $this->handoff = $handoff;
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->tenant = null;
        $this->knowledge = null;
        $this->lead = null;
        $this->search = null;
        $this->bookingLookup = null;
        $this->handoff = null;
    }

    public function isActive(): bool
    {
        return $this->active && $this->tenant !== null;
    }

    public function tenant(): ?AiEmbedTenant
    {
        return $this->tenant;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->id;
    }

    public function knowledgeProvider(): ?KnowledgeProvider
    {
        return $this->knowledge;
    }

    public function leadCaptureEnabled(): bool
    {
        return $this->lead?->isEnabled() ?? false;
    }

    public function flightSearchEnabled(): bool
    {
        return $this->search?->isEnabled() ?? false;
    }

    public function bookingLookupEnabled(): bool
    {
        return $this->bookingLookup?->isEnabled() ?? false;
    }

    public function handoffEnabled(): bool
    {
        return $this->handoff?->isEnabled() ?? false;
    }

    public function knowledgeEnabled(): bool
    {
        return $this->tenant?->hasCapability(EmbedTenantCapability::KNOWLEDGE) ?? false;
    }
}
