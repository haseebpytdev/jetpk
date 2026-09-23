<?php

namespace App\Providers;

use App\Services\Ai\Embed\EmbedAuditLogger;
use App\Services\Ai\Embed\EmbedProviderFactory;
use App\Services\Ai\Embed\EmbedRuntimeContext;
use App\Services\Ai\Embed\EmbedTenantManager;
use App\Services\Ai\Embed\EmbedTenantResolver;
use Illuminate\Support\ServiceProvider;

final class AiEmbedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmbedTenantResolver::class);
        $this->app->singleton(EmbedTenantManager::class);
        $this->app->singleton(EmbedProviderFactory::class);
        $this->app->singleton(EmbedAuditLogger::class);
        $this->app->scoped(EmbedRuntimeContext::class);
    }
}
