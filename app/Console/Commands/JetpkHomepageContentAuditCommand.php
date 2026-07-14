<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Audits\JetpkHomepageContentAuditService;
use Illuminate\Console\Command;

class JetpkHomepageContentAuditCommand extends Command
{
    protected $signature = 'jetpk:homepage-content-audit {--profile= : Client profile slug}';

    protected $description = 'Audit JetPakistan homepage trending routes, destinations, and support CTA content.';

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

        $result = $auditService->auditProfile($profile);
        $this->line('fail_count='.$result['fail_count']);

        foreach ($result['checks'] as $check) {
            $this->line(($check['status'] ?? 'info').' ['.($check['code'] ?? '').'] '.($check['message'] ?? ''));
        }

        return ($result['fail_count'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
