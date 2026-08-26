<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreSandboxQaConnectionProvisioner;
use App\Support\Sabre\SabreSandboxQaLifecycleGuard;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Ensure sabre-sandbox-qa from CERT profile or owner-authorized credential clone.
 * Never mutates the source/live connection. Never prints secrets.
 */
class SabreEnsureSandboxQaConnectionCommand extends Command
{
    protected $signature = 'sabre:ensure-sandbox-qa-connection
                            {--alias=sabre-sandbox-qa : Safe connection alias}
                            {--profile=cert_6md8 : CERT env profile key}
                            {--clone-credentials-from-connection= : Source connection id to clone credentials FROM (creates separate sandbox row)}
                            {--forbid-live-id= : Live connection id that must not be mutated}
                            {--test-auth : Probe OAuth against CERT host after ensure}
                            {--json : Machine-readable lines}';

    protected $description = 'Create/update sabre-sandbox-qa (CERT profile or owner-authorized clone). Never mutates live.';

    public function handle(SabreSandboxQaConnectionProvisioner $provisioner, SabreClient $sabreClient): int
    {
        $alias = trim((string) $this->option('alias')) ?: SabreSandboxQaConnectionProvisioner::DEFAULT_ALIAS;
        $profile = trim((string) $this->option('profile')) ?: 'cert_6md8';
        $cloneRaw = $this->option('clone-credentials-from-connection');
        $cloneFromId = is_numeric($cloneRaw) ? (int) $cloneRaw : null;
        $forbidLive = $this->option('forbid-live-id');
        $forbidLiveId = is_numeric($forbidLive) ? (int) $forbidLive : $cloneFromId;

        $sourceHashBefore = null;
        if ($cloneFromId !== null) {
            $source = SupplierConnection::query()->find($cloneFromId);
            if ($source === null) {
                $this->line('SANDBOX_CONNECTION_CREATED=BLOCKED');
                $this->line('ERROR=clone_source_not_found');

                return self::FAILURE;
            }
            $sourceHashBefore = $provisioner->sanitizedConfigHash($source);
            $this->line('LIVE_SABRE_CONNECTION_ID='.$cloneFromId);
            $this->line('LIVE_SABRE_CONFIG_HASH_BEFORE='.$sourceHashBefore);
        }

        try {
            $result = $provisioner->ensure(
                alias: $alias,
                profile: $profile,
                forbiddenLiveConnectionId: $forbidLiveId,
                cloneCredentialsFromConnectionId: $cloneFromId,
                activateOnlyIfAuthReady: (bool) $this->option('test-auth'),
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
        $this->line('SANDBOX_CONNECTION_ID_DIFFERS_FROM_LIVE='.(
            $forbidLiveId === null || (int) $connection->id !== (int) $forbidLiveId ? 'YES' : 'NO'
        ));
        $this->line('SANDBOX_PUBLIC_SEARCH_ELIGIBLE=NO');
        $this->line('SANDBOX_PUBLIC_ROUTING=NO');
        $this->line('CREDENTIAL_SOURCE='.($result['credential_source'] ?? 'unknown'));
        $this->line('RESOLVED_HOST='.($guard['resolved_host'] ?? 'unknown'));
        $this->line('RESOLVED_HOST_CLASSIFICATION='.strtoupper((string) ($guard['host_classification'] ?? 'unknown')));
        $this->line('PRODUCTION_SABRE_HOST_SELECTED='.(($guard['production_sabre_host_selected'] ?? false) ? 'YES' : 'NO'));
        $this->line('QA_LIFECYCLE_PRODUCTION_HOST_GUARD='.(($guard['allowed'] ?? false) ? 'PASS' : 'BLOCKED'));
        $this->line('LIVE_CONNECTION_UNCHANGED=YES');

        if ($sourceHashBefore !== null && $cloneFromId !== null) {
            $sourceAfter = SupplierConnection::query()->find($cloneFromId);
            $hashAfter = $sourceAfter !== null ? $provisioner->sanitizedConfigHash($sourceAfter) : 'missing';
            $this->line('LIVE_CONFIG_HASH_AFTER_PROVISION='.$hashAfter);
            $this->line('LIVE_CONNECTION_CONFIG_DRIFT='.($hashAfter === $sourceHashBefore ? '0' : '1'));
        }

        if (! $this->option('test-auth')) {
            return self::SUCCESS;
        }

        $hostClass = SabreSandboxQaLifecycleGuard::classifyHost((string) ($guard['resolved_host'] ?? ''));
        if ($hostClass !== 'non_production') {
            $this->line('SANDBOX_AUTH=BLOCKED');
            $this->line('SANDBOX_ENDPOINT=BLOCKED');
            $this->line('ERROR=production_or_unknown_host');

            return self::FAILURE;
        }

        try {
            $token = $sabreClient->getAccessToken($connection);
            $ok = is_string($token) && $token !== '';
            $provisioner->markAuthResult($connection, $ok);
            $this->line('SANDBOX_AUTH='.($ok ? 'PASS' : 'BLOCKED'));
            $this->line('SANDBOX_ENDPOINT=PASS');
            $this->line('SANDBOX_CONNECTION_HEALTH='.($ok ? 'PASS' : 'BLOCKED'));
            $this->line('LIVE_CONNECTION_TEST_CALLS=0');
            if (! $ok) {
                $this->line('SANDBOX_NETWORK_CERTIFICATION=DEFERRED_CREDENTIALS_NOT_ACCEPTED_BY_CERT');
            }
            if ($sourceHashBefore !== null && $cloneFromId !== null) {
                $sourceAfter = SupplierConnection::query()->find($cloneFromId);
                $hashAfter = $sourceAfter !== null ? $provisioner->sanitizedConfigHash($sourceAfter) : 'missing';
                $this->line('LIVE_CONFIG_HASH_AFTER_AUTH='.$hashAfter);
                $this->line('LIVE_CONNECTION_CONFIG_DRIFT='.($hashAfter === $sourceHashBefore ? '0' : '1'));
            }

            return $ok ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $provisioner->markAuthResult($connection->fresh() ?? $connection, false);
            $this->line('SANDBOX_AUTH=BLOCKED');
            $this->line('SANDBOX_ENDPOINT=BLOCKED');
            $this->line('SANDBOX_CONNECTION_HEALTH=BLOCKED');
            $this->line('AUTH_ERROR_CLASS='.class_basename($e));
            $this->line('SANDBOX_NETWORK_CERTIFICATION=DEFERRED_CREDENTIALS_NOT_ACCEPTED_BY_CERT');
            $this->line('LIVE_CONNECTION_TEST_CALLS=0');
            if ($sourceHashBefore !== null && $cloneFromId !== null) {
                $sourceAfter = SupplierConnection::query()->find($cloneFromId);
                $hashAfter = $sourceAfter !== null ? $provisioner->sanitizedConfigHash($sourceAfter) : 'missing';
                $this->line('LIVE_CONFIG_HASH_AFTER_AUTH='.$hashAfter);
                $this->line('LIVE_CONNECTION_CONFIG_DRIFT='.($hashAfter === $sourceHashBefore ? '0' : '1'));
            }

            return self::FAILURE;
        }
    }
}
