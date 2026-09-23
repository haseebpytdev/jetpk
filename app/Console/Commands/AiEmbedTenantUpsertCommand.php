<?php

namespace App\Console\Commands;

use App\Models\AiEmbedTenant;
use App\Services\Ai\Embed\EmbedTenantManager;
use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Console\Command;

class AiEmbedTenantUpsertCommand extends Command
{
    protected $signature = 'ai-embed:tenant-upsert
        {slug : Internal tenant slug}
        {--display-name= : Public display name}
        {--assistant-name= : Assistant name}
        {--origin=* : Allowed HTTPS origin (repeatable)}
        {--capability=* : Enabled capability (repeatable)}
        {--enabled : Mark tenant embed_enabled=true}
        {--disabled : Mark tenant embed_enabled=false}
        {--namespace=default : Knowledge namespace}
        {--issue-key : Issue a new embed key and print once}';

    protected $description = 'Create or update an AI embed tenant record (no secrets logged).';

    public function handle(EmbedTenantManager $manager): int
    {
        $slug = (string) $this->argument('slug');
        $capabilities = $this->capabilities();
        if ($capabilities === null) {
            return self::FAILURE;
        }

        $tenant = $manager->upsertTenant(
            slug: $slug,
            displayName: (string) ($this->option('display-name') ?: $slug),
            assistantName: (string) ($this->option('assistant-name') ?: 'AI Assistant'),
            allowedOrigins: array_values((array) $this->option('origin')),
            capabilities: $capabilities,
            embedEnabled: (bool) $this->option('enabled') && ! (bool) $this->option('disabled'),
            status: AiEmbedTenant::STATUS_ACTIVE,
            knowledgeNamespace: (string) $this->option('namespace'),
        );

        $this->line('TENANT_PUBLIC_ID='.$tenant->public_id);
        $this->line('TENANT_SLUG='.$tenant->slug);
        $this->line('EMBED_ENABLED='.($tenant->embed_enabled ? 'true' : 'false'));

        if ((bool) $this->option('issue-key')) {
            $issued = $manager->issueKey($tenant);
            $this->line('EMBED_KEY_PREFIX='.$issued['key_prefix']);
            $this->line('EMBED_KEY='.$issued['embed_key']);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    private function capabilities(): ?array
    {
        $requested = array_values(array_filter((array) $this->option('capability')));
        if ($requested === []) {
            return [];
        }

        $known = EmbedTenantCapability::all();
        $valid = array_values(array_intersect($requested, $known));
        $unknown = array_values(array_diff($requested, $known));

        foreach ($unknown as $capability) {
            $this->warn('Ignoring unknown capability: '.$capability);
        }

        if ($valid === []) {
            $this->error('No valid capabilities provided.');

            return null;
        }

        return $valid;
    }
}
