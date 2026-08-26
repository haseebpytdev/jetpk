<?php

namespace App\Services\Suppliers\Sabre;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabreCertTokenProbe;
use App\Support\Sabre\SabreSandboxQaLifecycleGuard;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use App\Support\Suppliers\SabreSupplierConnectionNormalizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates/updates sabre-sandbox-qa from CERT env profiles OR owner-authorized credential clone.
 * Never mutates the source/live connection row. Never logs credential values.
 */
final class SabreSandboxQaConnectionProvisioner
{
    public const DEFAULT_ALIAS = 'sabre-sandbox-qa';

    public function __construct(
        protected SabreCertTokenProbe $certTokenProbe,
    ) {}

    /**
     * @return array{
     *     connection: SupplierConnection,
     *     created: bool,
     *     credential_source: string,
     *     profile: string|null,
     *     clone_from_connection_id: int|null,
     *     guard: array<string, mixed>,
     *     live_connection_untouched: bool
     * }
     */
    public function ensure(
        ?Agency $agency = null,
        string $alias = self::DEFAULT_ALIAS,
        string $profile = 'cert_6md8',
        ?int $forbiddenLiveConnectionId = null,
        ?int $cloneCredentialsFromConnectionId = null,
        bool $activateOnlyIfAuthReady = false,
    ): array {
        $agency ??= Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
        if ($agency === null) {
            throw new InvalidArgumentException('Default agency not found for sandbox QA connection.');
        }

        if ($cloneCredentialsFromConnectionId !== null) {
            return $this->ensureFromClone(
                $agency,
                $alias,
                $cloneCredentialsFromConnectionId,
                $forbiddenLiveConnectionId ?? $cloneCredentialsFromConnectionId,
                $activateOnlyIfAuthReady,
            );
        }

        return $this->ensureFromCertProfile(
            $agency,
            $alias,
            $profile,
            $forbiddenLiveConnectionId,
            $activateOnlyIfAuthReady,
        );
    }

    /**
     * @return array{
     *     connection: SupplierConnection,
     *     created: bool,
     *     credential_source: string,
     *     profile: string|null,
     *     clone_from_connection_id: int|null,
     *     guard: array<string, mixed>,
     *     live_connection_untouched: bool
     * }
     */
    private function ensureFromCertProfile(
        Agency $agency,
        string $alias,
        string $profile,
        ?int $forbiddenLiveConnectionId,
        bool $activateOnlyIfAuthReady,
    ): array {
        $credentials = $this->certTokenProbe->resolveProfileCredentials($profile);
        if ($credentials === null) {
            throw new InvalidArgumentException('SABRE_SANDBOX_CREDENTIALS_REQUIRED: unknown CERT profile '.$profile);
        }

        $user = trim((string) ($credentials['user'] ?? ''));
        $secret = trim((string) ($credentials['secret'] ?? ''));
        $pcc = trim((string) ($credentials['pcc'] ?? ''));
        $domain = trim((string) ($credentials['domain'] ?? 'AA'));

        if ($user === '' || $secret === '' || $pcc === '') {
            throw new InvalidArgumentException('SABRE_SANDBOX_CREDENTIALS_REQUIRED: CERT profile incomplete');
        }

        $credentialPayload = [
            'sign_in' => $user,
            'username' => $user,
            'client_id' => $user,
            'password' => $secret,
            'client_secret' => $secret,
            'pcc' => $pcc,
        ];

        return $this->persistSandboxRow(
            agency: $agency,
            alias: $alias,
            credentialPayload: $credentialPayload,
            domain: $domain !== '' ? $domain : 'AA',
            credentialSource: 'cert_profile',
            profile: $profile,
            cloneFromConnectionId: null,
            forbiddenLiveConnectionId: $forbiddenLiveConnectionId,
            activate: ! $activateOnlyIfAuthReady,
        );
    }

    /**
     * Owner-authorized clone: read credentials from source connection into a NEW sandbox row only.
     *
     * @return array{
     *     connection: SupplierConnection,
     *     created: bool,
     *     credential_source: string,
     *     profile: string|null,
     *     clone_from_connection_id: int|null,
     *     guard: array<string, mixed>,
     *     live_connection_untouched: bool
     * }
     */
    private function ensureFromClone(
        Agency $agency,
        string $alias,
        int $sourceConnectionId,
        int $forbiddenLiveConnectionId,
        bool $activateOnlyIfAuthReady,
    ): array {
        if ($sourceConnectionId <= 0) {
            throw new InvalidArgumentException('clone_credentials_from_connection requires a positive connection id.');
        }

        $source = SupplierConnection::query()->find($sourceConnectionId);
        if ($source === null) {
            throw new InvalidArgumentException('Source SupplierConnection not found for credential clone.');
        }

        if ($source->provider !== SupplierProvider::Sabre) {
            throw new InvalidArgumentException('Credential clone source must be a Sabre connection.');
        }

        // Capture source fingerprint BEFORE any writes (must remain unchanged).
        $sourceHashBefore = $this->sanitizedConfigHash($source);

        $canonical = SabreSupplierConnectionNormalizer::canonicalCredentialsFromConnection($source);
        $signIn = trim((string) ($canonical['sign_in'] ?? ''));
        $password = trim((string) ($canonical['password'] ?? ''));
        $pcc = trim((string) ($canonical['pcc'] ?? ''));

        if ($signIn === '' || $password === '' || $pcc === '') {
            throw new InvalidArgumentException('Source Sabre connection credentials are incomplete for clone.');
        }

        $sourceSettings = is_array($source->settings) ? $source->settings : [];
        $domain = trim((string) ($sourceSettings['epr_domain'] ?? 'AA'));

        $credentialPayload = [
            'sign_in' => $signIn,
            'username' => $signIn,
            'client_id' => $signIn,
            'password' => $password,
            'client_secret' => $password,
            'pcc' => $pcc,
        ];

        $result = $this->persistSandboxRow(
            agency: $agency,
            alias: $alias,
            credentialPayload: $credentialPayload,
            domain: $domain !== '' ? $domain : 'AA',
            credentialSource: 'owner_authorized_clone',
            profile: null,
            cloneFromConnectionId: $sourceConnectionId,
            forbiddenLiveConnectionId: $forbiddenLiveConnectionId,
            activate: ! $activateOnlyIfAuthReady,
        );

        $sourceFresh = SupplierConnection::query()->find($sourceConnectionId);
        if ($sourceFresh === null || $this->sanitizedConfigHash($sourceFresh) !== $sourceHashBefore) {
            throw new InvalidArgumentException('LIVE_CONNECTION_MUTATED: source Sabre connection changed during sandbox clone.');
        }

        $result['live_connection_untouched'] = true;
        $result['source_config_hash'] = $sourceHashBefore;

        return $result;
    }

    /**
     * @param  array<string, string>  $credentialPayload
     * @return array{
     *     connection: SupplierConnection,
     *     created: bool,
     *     credential_source: string,
     *     profile: string|null,
     *     clone_from_connection_id: int|null,
     *     guard: array<string, mixed>,
     *     live_connection_untouched: bool
     * }
     */
    private function persistSandboxRow(
        Agency $agency,
        string $alias,
        array $credentialPayload,
        string $domain,
        string $credentialSource,
        ?string $profile,
        ?int $cloneFromConnectionId,
        ?int $forbiddenLiveConnectionId,
        bool $activate,
    ): array {
        $baseUrl = (string) config(
            'suppliers.sabre.cert_stl.base_url',
            SabreSupplierConnectionNormalizer::CERT_BASE_URL,
        );

        $host = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?: ''));
        if (SabreSandboxQaLifecycleGuard::classifyHost($host) !== 'non_production') {
            throw new InvalidArgumentException('QA_LIFECYCLE_PRODUCTION_HOST_GUARD: sandbox base_url is not CERT/non-production');
        }

        return DB::transaction(function () use (
            $agency,
            $alias,
            $credentialPayload,
            $domain,
            $credentialSource,
            $profile,
            $cloneFromConnectionId,
            $forbiddenLiveConnectionId,
            $activate,
            $baseUrl,
        ): array {
            $existing = SupplierConnection::query()
                ->where('agency_id', $agency->id)
                ->where('provider', SupplierProvider::Sabre->value)
                ->where('name', $alias)
                ->first();

            if ($existing !== null && $forbiddenLiveConnectionId !== null
                && (int) $existing->id === (int) $forbiddenLiveConnectionId) {
                throw new InvalidArgumentException('Refusing to mutate the live Sabre connection as sandbox QA.');
            }

            $created = $existing === null;
            $connection = $existing ?? new SupplierConnection([
                'agency_id' => $agency->id,
                'provider' => SupplierProvider::Sabre,
                'name' => $alias,
            ]);

            if ($forbiddenLiveConnectionId !== null && $connection->exists
                && (int) $connection->id === (int) $forbiddenLiveConnectionId) {
                throw new InvalidArgumentException('Refusing to mutate the live Sabre connection as sandbox QA.');
            }

            $settings = is_array($connection->settings) ? $connection->settings : [];
            $settings = SabreSupplierChannelConfig::mergeIntoSettings($settings, true, false);
            $settings['public_customer_routing'] = false;
            $settings['production_default_routing'] = false;
            $settings['qa_sandbox_only'] = true;
            $settings['epr_domain'] = $domain;
            $settings['credential_source'] = $credentialSource;
            if ($profile !== null) {
                $settings['sabre_cert_profile'] = $profile;
            }
            if ($cloneFromConnectionId !== null) {
                $settings['cloned_from_connection_id'] = $cloneFromConnectionId;
            }

            $connection->fill([
                'environment' => SupplierEnvironment::Sandbox,
                'status' => $activate ? SupplierConnectionStatus::Active : SupplierConnectionStatus::Inactive,
                'is_active' => $activate,
                'base_url' => rtrim($baseUrl, '/'),
                'display_name' => 'Sabre Sandbox QA (CERT)',
                'credentials' => $credentialPayload,
                'settings' => $settings,
            ]);
            $connection->save();

            $guard = SabreSandboxQaLifecycleGuard::assertSandboxQaAllowed(
                $connection->fresh(),
                $forbiddenLiveConnectionId,
            );
            if (! ($guard['allowed'] ?? false)) {
                throw new InvalidArgumentException(
                    'QA_LIFECYCLE_PRODUCTION_HOST_GUARD: '.($guard['block_reason'] ?? 'blocked')
                );
            }

            return [
                'connection' => $connection->fresh(),
                'created' => $created,
                'credential_source' => $credentialSource,
                'profile' => $profile,
                'clone_from_connection_id' => $cloneFromConnectionId,
                'guard' => $guard,
                'live_connection_untouched' => true,
            ];
        });
    }

    public function markAuthResult(SupplierConnection $connection, bool $authPassed): SupplierConnection
    {
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $settings['qa_sandbox_only'] = true;
        $settings['public_customer_routing'] = false;
        $settings['production_default_routing'] = false;
        $settings['last_cert_auth_pass'] = $authPassed;

        $connection->forceFill([
            'settings' => $settings,
            'status' => $authPassed ? SupplierConnectionStatus::Active : SupplierConnectionStatus::Inactive,
            'is_active' => $authPassed,
            'last_test_status' => $authPassed ? 'success' : 'auth_failed',
            'last_tested_at' => now(),
            'last_error' => $authPassed ? null : 'cert_oauth_failed',
        ])->save();

        return $connection->fresh();
    }

    /**
     * Sanitized config hash — never includes credential values.
     */
    public function sanitizedConfigHash(SupplierConnection $connection): string
    {
        $payload = [
            'id' => $connection->id,
            'name' => $connection->name,
            'environment' => $connection->environment?->value,
            'status' => $connection->status?->value,
            'is_active' => (bool) $connection->is_active,
            'base_url_host' => parse_url((string) $connection->base_url, PHP_URL_HOST) ?: 'empty',
            'settings_keys' => array_keys(is_array($connection->settings) ? $connection->settings : []),
            'meta_keys' => array_keys(is_array($connection->meta) ? $connection->meta : []),
            'cred_keys' => array_keys(is_array($connection->credentials) ? $connection->credentials : []),
        ];

        return hash('sha256', json_encode($payload));
    }
}
