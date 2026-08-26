<?php

namespace App\Support\Sabre;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use InvalidArgumentException;

/**
 * Exact-connection pin for sandbox QA search/PNR/cancel.
 * Internal callers only — never accept as a public URL parameter.
 */
final class SabreSandboxQaConnectionPin
{
    public const SOURCE_CHANNEL = 'admin_qa_sandbox';

    /**
     * @return array{
     *     allowed: bool,
     *     block_reason: string|null,
     *     connection: SupplierConnection|null,
     *     connection_count: int,
     *     live_connection_eligible: bool
     * }
     */
    public static function resolveExact(
        int $sandboxConnectionId,
        ?int $forbiddenLiveConnectionId = null,
    ): array {
        if ($sandboxConnectionId <= 0) {
            return [
                'allowed' => false,
                'block_reason' => 'qa_sandbox_connection_id_missing',
                'connection' => null,
                'connection_count' => 0,
                'live_connection_eligible' => false,
            ];
        }

        if ($forbiddenLiveConnectionId !== null && $sandboxConnectionId === $forbiddenLiveConnectionId) {
            return [
                'allowed' => false,
                'block_reason' => 'live_production_connection_selected_for_sandbox_qa',
                'connection' => null,
                'connection_count' => 0,
                'live_connection_eligible' => true,
            ];
        }

        $connection = SupplierConnection::query()->find($sandboxConnectionId);
        if ($connection === null) {
            return [
                'allowed' => false,
                'block_reason' => 'qa_sandbox_connection_not_found',
                'connection' => null,
                'connection_count' => 0,
                'live_connection_eligible' => false,
            ];
        }

        $shape = self::assertQaSandboxShape($connection, $forbiddenLiveConnectionId);
        if (! ($shape['allowed'] ?? false)) {
            return [
                'allowed' => false,
                'block_reason' => $shape['block_reason'] ?? 'qa_sandbox_shape_invalid',
                'connection' => $connection,
                'connection_count' => 0,
                'live_connection_eligible' => $connection->environment === SupplierEnvironment::Live
                    || ($forbiddenLiveConnectionId !== null && (int) $connection->id === (int) $forbiddenLiveConnectionId),
            ];
        }

        $hostGuard = SabreSandboxQaLifecycleGuard::assertSandboxQaAllowed($connection, $forbiddenLiveConnectionId);
        if (! ($hostGuard['allowed'] ?? false)) {
            return [
                'allowed' => false,
                'block_reason' => $hostGuard['block_reason'] ?? 'qa_lifecycle_host_blocked',
                'connection' => $connection,
                'connection_count' => 0,
                'live_connection_eligible' => false,
            ];
        }

        return [
            'allowed' => true,
            'block_reason' => null,
            'connection' => $connection,
            'connection_count' => 1,
            'live_connection_eligible' => false,
        ];
    }

    /**
     * @return array{allowed: bool, block_reason: string|null}
     */
    public static function assertQaSandboxShape(
        SupplierConnection $connection,
        ?int $forbiddenLiveConnectionId = null,
    ): array {
        if ($forbiddenLiveConnectionId !== null && (int) $connection->id === (int) $forbiddenLiveConnectionId) {
            return ['allowed' => false, 'block_reason' => 'live_production_connection_selected_for_sandbox_qa'];
        }

        if ($connection->provider !== SupplierProvider::Sabre) {
            return ['allowed' => false, 'block_reason' => 'provider_not_sabre'];
        }

        if ($connection->environment !== SupplierEnvironment::Sandbox) {
            return ['allowed' => false, 'block_reason' => 'environment_not_sandbox'];
        }

        $settings = is_array($connection->settings) ? $connection->settings : [];
        if (($settings['qa_sandbox_only'] ?? null) !== true) {
            return ['allowed' => false, 'block_reason' => 'qa_sandbox_only_required'];
        }
        if (($settings['public_customer_routing'] ?? null) !== false) {
            return ['allowed' => false, 'block_reason' => 'public_customer_routing_must_be_false'];
        }

        return ['allowed' => true, 'block_reason' => null];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function requireExact(
        int $sandboxConnectionId,
        ?int $forbiddenLiveConnectionId = null,
    ): SupplierConnection {
        $resolved = self::resolveExact($sandboxConnectionId, $forbiddenLiveConnectionId);
        if (! ($resolved['allowed'] ?? false) || ! ($resolved['connection'] instanceof SupplierConnection)) {
            throw new InvalidArgumentException(
                'QA_SANDBOX_EXACT_CONNECTION_PIN: '.($resolved['block_reason'] ?? 'blocked')
            );
        }

        return $resolved['connection'];
    }
}
