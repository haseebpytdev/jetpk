<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Diagnostics\ProviderActiveAuthAuditService;
use Illuminate\Console\Command;

class ProviderActiveAuthAuditCommand extends Command
{
    public const CONFIRM_PHRASE = 'READONLY-PROVIDER-AUTH-AUDIT';

    protected $signature = 'provider:active-auth-audit
                            {--provider=all : all|sabre|alhaider}
                            {--connection= : Optional Sabre supplier_connections id}
                            {--send : Perform live HTTP auth probe (requires --confirm)}
                            {--confirm= : Required for --send: READONLY-PROVIDER-AUTH-AUDIT}';

    protected $description = 'Read-only provider auth audit for Sabre and Al-Haider (no HTTP by default)';

    public function handle(ProviderActiveAuthAuditService $audit): int
    {
        $provider = strtolower(trim((string) $this->option('provider')));
        if (! in_array($provider, ['all', 'sabre', 'alhaider'], true)) {
            $this->components->error('Invalid --provider. Use all, sabre, or alhaider.');

            return self::FAILURE;
        }

        $send = (bool) $this->option('send');
        if ($send && trim((string) $this->option('confirm')) !== self::CONFIRM_PHRASE) {
            $this->components->error('--send requires --confirm='.self::CONFIRM_PHRASE);

            return self::FAILURE;
        }

        $connectionId = $this->option('connection');
        $connectionIdInt = is_numeric($connectionId) ? (int) $connectionId : null;

        $this->line('classification=READ-ONLY');
        $this->line('live_http='.($send ? 'true' : 'false'));

        $sections = $audit->audit($provider, $connectionIdInt, $send);

        if ($sections === []) {
            $this->line('sections=0');

            return self::SUCCESS;
        }

        foreach ($sections as $index => $section) {
            $this->line('');
            $this->line('[section='.($index + 1).']');
            foreach ($audit->formatLines($section) as $line) {
                $this->line($line);
            }
        }

        return self::SUCCESS;
    }
}
