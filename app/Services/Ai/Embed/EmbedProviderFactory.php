<?php

namespace App\Services\Ai\Embed;

use App\Contracts\Ai\Embed\BookingLookupProvider;
use App\Contracts\Ai\Embed\IdentityProvider;
use App\Contracts\Ai\Embed\KnowledgeProvider;
use App\Contracts\Ai\Embed\LeadProvider;
use App\Contracts\Ai\Embed\SearchProvider;
use App\Contracts\Ai\Embed\SupportHandoffProvider;
use App\Contracts\Ai\Embed\TenantConfigProvider;
use App\Models\AiEmbedTenant;
use App\Services\Ai\Embed\Adapters\Disabled\DisabledBookingLookupProvider;
use App\Services\Ai\Embed\Adapters\Disabled\DisabledHandoffProvider;
use App\Services\Ai\Embed\Adapters\Disabled\DisabledKnowledgeProvider;
use App\Services\Ai\Embed\Adapters\Disabled\DisabledLeadProvider;
use App\Services\Ai\Embed\Adapters\Disabled\DisabledSearchProvider;
use App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanBookingLookupProvider;
use App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanHandoffProvider;
use App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanIdentityProvider;
use App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanKnowledgeProvider;
use App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanLeadProvider;
use App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanSearchProvider;
use App\Services\Ai\Embed\Adapters\JetPakistan\JetPakistanTenantConfigProvider;
use App\Services\Ai\Embed\Adapters\Tenant\GenericIdentityProvider;
use App\Services\Ai\Embed\Adapters\Tenant\GenericTenantConfigProvider;
use App\Support\Ai\Embed\EmbedTenantCapability;

final class EmbedProviderFactory
{
    public function tenantConfig(AiEmbedTenant $tenant): TenantConfigProvider
    {
        if ($tenant->slug === 'jetpakistan') {
            return new JetPakistanTenantConfigProvider($tenant);
        }

        return new GenericTenantConfigProvider($tenant);
    }

    public function knowledge(AiEmbedTenant $tenant): KnowledgeProvider
    {
        if (! $tenant->hasCapability(EmbedTenantCapability::KNOWLEDGE)) {
            return new DisabledKnowledgeProvider;
        }

        return new JetPakistanKnowledgeProvider($tenant);
    }

    public function lead(AiEmbedTenant $tenant): LeadProvider
    {
        if (! $tenant->hasCapability(EmbedTenantCapability::LEAD_CAPTURE)) {
            return new DisabledLeadProvider;
        }

        if ($tenant->slug === 'jetpakistan') {
            return new JetPakistanLeadProvider($tenant);
        }

        return new DisabledLeadProvider;
    }

    public function search(AiEmbedTenant $tenant): SearchProvider
    {
        if (! $tenant->hasCapability(EmbedTenantCapability::FLIGHT_SEARCH)) {
            return new DisabledSearchProvider;
        }

        if ($tenant->slug === 'jetpakistan') {
            return new JetPakistanSearchProvider($tenant);
        }

        return new DisabledSearchProvider;
    }

    public function bookingLookup(AiEmbedTenant $tenant): BookingLookupProvider
    {
        if (! $tenant->hasCapability(EmbedTenantCapability::BOOKING_LOOKUP)) {
            return new DisabledBookingLookupProvider;
        }

        if ($tenant->slug === 'jetpakistan') {
            return new JetPakistanBookingLookupProvider($tenant);
        }

        return new DisabledBookingLookupProvider;
    }

    public function handoff(AiEmbedTenant $tenant): SupportHandoffProvider
    {
        if (! $tenant->hasCapability(EmbedTenantCapability::SUPPORT_HANDOFF)) {
            return new DisabledHandoffProvider;
        }

        if ($tenant->slug === 'jetpakistan') {
            return new JetPakistanHandoffProvider($tenant);
        }

        return new DisabledHandoffProvider;
    }

    public function identity(AiEmbedTenant $tenant): IdentityProvider
    {
        if ($tenant->slug === 'jetpakistan') {
            return new JetPakistanIdentityProvider;
        }

        return new GenericIdentityProvider($tenant);
    }
}
