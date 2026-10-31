<?php

namespace App\Support\FlightSearch;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Support\Platform\PlatformModuleEnforcer;

/**
 * Fail-closed readiness for AirBlue Zapways QA isolation (conn 23 TEST only).
 *
 * Never treats CLI --allow-live as an environment proof.
 */
final class AirBlueQaSearchScope
{
    public const SCOPE_TYPE = 'airblue_sandbox';

    /**
     * Approved Zapways TEST hosts (host only).
     *
     * @var list<string>
     */
    private const APPROVED_TEST_HOSTS = [
        'otatest4.zapways.com',
    ];

    public function __construct(
        private readonly AirBlueConfigResolver $configResolver,
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
    ) {}

    /**
     * @return array{
     *     allowed: bool,
     *     connection_id: int|null,
     *     provider: string|null,
     *     environment: string|null,
     *     protocol: string|null,
     *     test_host: string|null,
     *     service_target: string|null,
     *     reason: string
     * }
     */
    public function evaluate(?SupplierConnection $connection, ?User $actor = null): array
    {
        $expectedId = (int) config('ota.qa_staff_airblue.connection_id', 23);

        if ($actor !== null && ! QaStaffAirBlueContext::actorIsAllowed($actor)) {
            return $this->deny($expectedId, 'unauthorized_actor');
        }

        if (! $this->platformModuleEnforcer->effectiveModuleEnabled('supplier_search')) {
            return $this->deny($expectedId, 'supplier_search_module_disabled');
        }

        if ($connection === null) {
            return $this->deny($expectedId, 'connection_missing');
        }

        if ((int) $connection->id !== $expectedId) {
            return $this->deny($expectedId, 'connection_id_mismatch');
        }

        $provider = $connection->provider instanceof SupplierProvider
            ? $connection->provider
            : SupplierProvider::tryFrom((string) $connection->provider);

        if ($provider !== SupplierProvider::Airblue) {
            return $this->deny($expectedId, 'provider_not_airblue');
        }

        $statusValue = $connection->status instanceof \BackedEnum
            ? $connection->status->value
            : (string) $connection->status;
        if (! $connection->is_active && $statusValue !== 'active') {
            return $this->deny($expectedId, 'connection_inactive');
        }

        if (! $this->configResolver->isTestEnvironment($connection)) {
            return $this->deny($expectedId, 'environment_not_test');
        }

        $env = $connection->environment instanceof SupplierEnvironment
            ? $connection->environment->value
            : (string) $connection->environment;

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $protocol = AirBlueZapwaysProtocolVersion::fromCredentials($credentials);
        if (! $protocol->isV3()) {
            return $this->deny($expectedId, 'protocol_not_v3');
        }

        $host = strtolower((string) (parse_url((string) $connection->base_url, PHP_URL_HOST) ?: ''));
        if ($host === '' || ! in_array($host, self::APPROVED_TEST_HOSTS, true)) {
            return $this->deny($expectedId, 'host_not_approved_test');
        }

        $serviceTarget = $this->configResolver->resolveServiceTarget($connection, $credentials);
        if (strcasecmp(trim($serviceTarget), 'Test') !== 0) {
            return $this->deny($expectedId, 'service_target_not_test');
        }

        return [
            'allowed' => true,
            'connection_id' => (int) $connection->id,
            'provider' => SupplierProvider::Airblue->value,
            'environment' => $env,
            'protocol' => $protocol->value,
            'test_host' => $host,
            'service_target' => $serviceTarget,
            'reason' => 'ok',
        ];
    }

    /**
     * @return array{
     *     allowed: bool,
     *     connection_id: int|null,
     *     provider: string|null,
     *     environment: string|null,
     *     protocol: string|null,
     *     test_host: string|null,
     *     service_target: string|null,
     *     reason: string
     * }
     */
    private function deny(int $expectedId, string $reason): array
    {
        return [
            'allowed' => false,
            'connection_id' => $expectedId,
            'provider' => null,
            'environment' => null,
            'protocol' => null,
            'test_host' => null,
            'service_target' => null,
            'reason' => $reason,
        ];
    }
}
