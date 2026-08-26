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
 * Creates/updates the dedicated Sabre sandbox QA SupplierConnection from CERT env profiles.
 * Never copies live production credentials. Never mutates a live connection row.
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
     *     profile: string,
     *     guard: array<string, mixed>,
     *     live_connection_untouched: bool
     * }
     */
    public function ensure(
        ?Agency $agency = null,
        string $alias = self::DEFAULT_ALIAS,
        string $profile = 'cert_6md8',
        ?int $forbiddenLiveConnectionId = null,
    ): array {
        $agency ??= Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
        if ($agency === null) {
            throw new InvalidArgumentException('Default agency not found for sandbox QA connection.');
        }

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

        $baseUrl = (string) config(
            'suppliers.sabre.cert_stl.base_url',
            SabreSupplierConnectionNormalizer::CERT_BASE_URL,
        );

        return DB::transaction(function () use (
            $agency,
            $alias,
            $profile,
            $user,
            $secret,
            $pcc,
            $domain,
            $baseUrl,
            $forbiddenLiveConnectionId,
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
            $settings['sabre_cert_profile'] = $profile;
            $settings['epr_domain'] = $domain !== '' ? $domain : 'AA';

            $connection->fill([
                'environment' => SupplierEnvironment::Sandbox,
                'status' => SupplierConnectionStatus::Active,
                'is_active' => true,
                'base_url' => rtrim($baseUrl, '/'),
                'display_name' => 'Sabre Sandbox QA (CERT)',
                'credentials' => [
                    'sign_in' => $user,
                    'username' => $user,
                    'client_id' => $user,
                    'password' => $secret,
                    'client_secret' => $secret,
                    'pcc' => $pcc,
                ],
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
                'profile' => $profile,
                'guard' => $guard,
                'live_connection_untouched' => true,
            ];
        });
    }
}
