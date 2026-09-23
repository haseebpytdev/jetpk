<?php

namespace App\Console\Commands;

use App\Models\AiEmbedTenant;
use App\Services\Ai\Embed\EmbedTenantManager;
use Illuminate\Console\Command;

class AiEmbedKeyRotateCommand extends Command
{
    protected $signature = 'ai-embed:key-rotate {slug : Internal tenant slug}';

    protected $description = 'Revoke active embed keys and issue a new key (printed once).';

    public function handle(EmbedTenantManager $manager): int
    {
        $slug = (string) $this->argument('slug');
        $tenant = AiEmbedTenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $issued = $manager->rotateKey($tenant);
        $this->line('TENANT_PUBLIC_ID='.$tenant->public_id);
        $this->line('EMBED_KEY_PREFIX='.$issued['key_prefix']);
        $this->line('EMBED_KEY='.$issued['embed_key']);

        return self::SUCCESS;
    }
}
