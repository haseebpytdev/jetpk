<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Sabre\Diagnostics\SabreActiveAuthDiagnostic;
use Illuminate\Console\Command;

class SabreActiveAuthDiagnosticCommand extends Command
{
    public const CONFIRM_PHRASE = 'READONLY-SABRE-AUTH-DIAGNOSTIC';

    protected $signature = 'sabre:active-auth-diagnostic
                            {--connection= : supplier_connections id}
                            {--send : Perform live HTTP token probe (requires --confirm)}
                            {--confirm= : Required for --send: READONLY-SABRE-AUTH-DIAGNOSTIC}
                            {--source= : db or env-profile}
                            {--profile= : cert_6md8|cert_lu6k|cert_test3 when --source=env-profile}
                            {--compare : Compare DB connection against all env profiles}';

    protected $description = 'Safe Sabre active-connection auth diagnostic (DB vs env-profile comparison)';

    public function handle(SabreActiveAuthDiagnostic $diagnostic): int
    {
        $send = (bool) $this->option('send');
        if ($send && trim((string) $this->option('confirm')) !== self::CONFIRM_PHRASE) {
            $this->components->error('--send requires --confirm='.self::CONFIRM_PHRASE);

            return self::FAILURE;
        }

        $source = $this->option('source');
        $source = is_string($source) && trim($source) !== '' ? strtolower(trim($source)) : null;

        if ($source !== null && ! in_array($source, ['db', 'env-profile'], true)) {
            $this->components->error('Invalid --source. Use db or env-profile.');

            return self::FAILURE;
        }

        $connectionRaw = $this->option('connection');
        $connectionId = is_numeric($connectionRaw) ? (int) $connectionRaw : null;

        if ($source === 'env-profile') {
            $profile = trim((string) $this->option('profile'));
            if ($profile === '') {
                $this->components->error('--source=env-profile requires --profile=cert_6md8|cert_lu6k|cert_test3');

                return self::FAILURE;
            }

            $results = [$diagnostic->diagnoseEnvProfile($profile, $send)];
        } elseif ((bool) $this->option('compare')) {
            if ($connectionId === null) {
                $this->components->error('--compare requires --connection=ID');

                return self::FAILURE;
            }
            $results = $diagnostic->compareSources($connectionId, $send);
        } else {
            if ($connectionId === null && $source !== 'db') {
                $this->components->error('Pass --connection=ID or --source=env-profile --profile=...');

                return self::FAILURE;
            }
            $results = $diagnostic->diagnoseConnections($connectionId, $send, $source);
        }

        $this->line('classification=READ-ONLY');
        $this->line('live_http='.($send ? 'true' : 'false'));

        foreach ($results as $index => $result) {
            if (isset($result['error'])) {
                $this->line('error='.(string) $result['error']);

                continue;
            }

            $this->line('');
            $this->line('[section='.($index + 1).']');
            foreach ($diagnostic->formatLines($result) as $line) {
                $this->line($line);
            }
        }

        $failed = $send && collect($results)->contains(
            fn (array $r): bool => isset($r['token_obtained']) && $r['token_obtained'] === false
        );

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
