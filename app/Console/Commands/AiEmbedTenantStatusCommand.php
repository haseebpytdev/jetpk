<?php

namespace App\Console\Commands;

use App\Models\AiEmbedTenant;
use Illuminate\Console\Command;

class AiEmbedTenantStatusCommand extends Command
{
    protected $signature = 'ai-embed:tenant-status {slug : Internal tenant slug}';

    protected $description = 'Show safe operational status for an embed tenant.';

    public function handle(): int
    {
        $tenant = AiEmbedTenant::query()->where('slug', (string) $this->argument('slug'))->first();
        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $this->line('TENANT_PUBLIC_ID='.$tenant->public_id);
        $this->line('STATUS='.$tenant->status);
        $this->line('EMBED_ENABLED='.($tenant->embed_enabled ? 'true' : 'false'));
        $this->line('ORIGIN_COUNT='.count($tenant->normalizedAllowedOrigins()));
        $this->line('ACTIVE_KEYS='.$tenant->keys()->where('status', 'active')->count());
        $this->line('CAPABILITIES='.implode(',', is_array($tenant->capabilities) ? $tenant->capabilities : []));

        return self::SUCCESS;
    }
}
