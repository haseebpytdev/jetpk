<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Audits\JetpkHomepageContentAuditService;
use Illuminate\Console\Command;

class JetpkHomepageMediaAuditCommand extends Command
{
    protected $signature = 'jetpk:homepage-media-audit {--profile= : Client profile slug}';

    protected $description = 'Audit JetPakistan homepage media storage paths and assets.';

    public function handle(
        JetpkHomepageContentAuditService $auditService,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        $profile = $clientContext->get() ?? $profileResolver->resolveDefault();
        $slug = trim((string) $this->option('profile'));
        if ($slug !== '') {
            $profile = \App\Models\ClientProfile::query()->where('slug', $slug)->first() ?? $profile;
        }

        $result = $auditService->auditMedia($profile);
        $this->line('fail_count='.$result['fail_count']);
        $this->line('db_write_attempted='.(($result['db_write_attempted'] ?? false) ? 'true' : 'false'));
        $this->line('cms_mutation_attempted='.(($result['cms_mutation_attempted'] ?? false) ? 'true' : 'false'));
        $this->line('publish_attempted='.(($result['publish_attempted'] ?? false) ? 'true' : 'false'));

        foreach ($result['slots'] ?? [] as $slot) {
            $prefix = ($slot['slot'] ?? 'unknown');
            if ($prefix === 'support_cta_background') {
                $this->line('support_cta.enabled=true');
                $this->line('support_cta.asset_slot=support_cta_background');
            }
            foreach ([
                'published_asset_found',
                'published_asset_id_present',
                'resolved_url_present',
                'using_fallback',
                'file_exists',
                'public_http_expected',
                'draft_only',
                'stale_cache_risk',
            ] as $key) {
                if (! array_key_exists($key, $slot)) {
                    continue;
                }
                $value = $slot[$key];
                $formatted = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $this->line($prefix.'.'.$key.'='.$formatted);
            }
        }

        foreach ($result['checks'] as $check) {
            $this->line(($check['status'] ?? 'info').' ['.($check['code'] ?? '').'] '.($check['message'] ?? ''));
        }

        return ($result['fail_count'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
