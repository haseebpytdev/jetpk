<?php

namespace App\Console\Commands;

use App\Models\AiEmbedTenant;
use App\Services\Ai\Embed\EmbedTenantManager;
use Illuminate\Console\Command;

class AiEmbedTenantSyncJetPakistanCommand extends Command
{
    protected $signature = 'ai-embed:tenant-sync-jetpakistan';

    protected $description = 'Sync JetPakistan tenant #1 from legacy env bridge without enabling embed globally.';

    public function handle(EmbedTenantManager $manager): int
    {
        $tenant = $manager->syncJetPakistanFromEnv();

        $this->line('TENANT_PUBLIC_ID='.$tenant->public_id);
        $this->line('TENANT_SLUG='.$tenant->slug);
        $this->line('EMBED_ENABLED='.($tenant->embed_enabled ? 'true' : 'false'));
        $this->line('ACTIVE_KEYS='.$tenant->keys()->where('status', 'active')->count());

        return self::SUCCESS;
    }
}
