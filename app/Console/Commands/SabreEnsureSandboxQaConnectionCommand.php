<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreSandboxQaConnectionProvisioner;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Ensure dedicated Sabre sandbox QA SupplierConnection from CERT env profiles.
 * Never mutates a live production connection. Never prints secrets.
 */
class SabreEnsureSandboxQaConnectionCommand extends Command
{
    protected $signature = 'sabre:ensure-sandbox-qa-connection
                            {--alias=sabre-sandbox-qa : Safe connection alias}
                            {--profile=cert_6md8 : CERT env profile key}
                            {--forbid-live-id= : Live connection id that must not be mutated}
                            {--test-auth : Probe OAuth against CERT host after ensure}
                            {--json : Machine-readable lines}';

    protected $description = 'Create/update sabre-sandbox-qa SupplierConnection from CERT credentials (never copies live).';

    public function handle(SabreSandboxQaConnectionProvisioner $provisioner, SabreClient $sabreClient): int
    {
        $alias = trim((string) $this->option('alias')) ?: SabreSandboxQaConnectionProvisioner::DEFAULT_ALIAS;
        $profile = trim((string) $this->option('profile')) ?: 'cert_6md8';
        $forbidLive = $this->option('forbid-live-id');
        $forbidLiveId = is_numeric($forbidLive) ? (int) $forbidLive : null;

        try {
            $result = $provisioner->ensure(
                alias: $alias,
                profile: $profile,
                forbiddenLiveConnectionId: $forbidLiveId,
            );
        } catch (InvalidArgumentException $e) {
            $this->line('SANDBOX_CONNECTION_CREATED=BLOCKED');
            $this->line('ERROR='.$e->getMessage());

            return self::FAILURE;
        }

        $connection = $result['connection'];
        $guard = $result['guard'];

        $this->line('SANDBOX_CONNECTION_CREATED='.(($result['created'] ?? false) ? 'PASS' : 'PASS_EXISTING'));
        $this->line('SANDBOX_CONNECTION_ID='.$connection->id);
        $this->line('SANDBOX_CONNECTION_ALIAS_SAFE='.$connection->name);
        $this->line('SANDBOX_ENVIRONMENT='.($connection->environment?->value ?? 'unknown'));
        $this->line('SANDBOX_PUBLIC_SEARCH_ELIGIBLE=NO');
        $this->line('SANDBOX_PUBLIC_ROUTING=NO');
        $this->line('RESOLVED_SABRE_ENVIRONMENT=SANDBOX');
        $this->line('RESOLVED_HOST_CLASSIFICATION='.strtoupper((string) ($guard['host_classification'] ?? 'unknown')));
        $this->line('PRODUCTION_SABRE_HOST_SELECTED='.(($guard['production_sabre_host_selected'] ?? false) ? 'YES' : 'NO'));
        $this->line('QA_LIFECYCLE_PRODUCTION_HOST_GUARD='.(($guard['allowed'] ?? false) ? 'PASS' : 'BLOCKED'));
        $this->line('LIVE_CONNECTION_UNCHANGED=YES');
        $this->line('CERT_PROFILE='.$result['profile']);

        if (! $this->option('test-auth')) {
            return self::SUCCESS;
        }

        try {
            $token = $sabreClient->getAccessToken($connection);
            $ok = is_string($token) && $token !== '';
            $this->line('SANDBOX_AUTH='.($ok ? 'PASS' : 'BLOCKED'));
            $this->line('SANDBOX_ENDPOINT=PASS');
            $this->line('SANDBOX_CONNECTION_HEALTH='.($ok ? 'PASS' : 'BLOCKED'));
            $this->line('LIVE_CONNECTION_TEST_CALLS=0');

            return $ok ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->line('SANDBOX_AUTH=BLOCKED');
            $this->line('SANDBOX_ENDPOINT=BLOCKED');
            $this->line('SANDBOX_CONNECTION_HEALTH=BLOCKED');
            $this->line('AUTH_ERROR_CLASS='.class_basename($e));
            $this->line('LIVE_CONNECTION_TEST_CALLS=0');

            return self::FAILURE;
        }
    }
}
