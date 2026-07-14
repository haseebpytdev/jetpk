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

        foreach ($result['checks'] as $check) {
            $this->line(($check['status'] ?? 'info').' ['.($check['code'] ?? '').'] '.($check['message'] ?? ''));
        }

        return ($result['fail_count'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
