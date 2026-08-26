<?php

namespace App\Services\Suppliers\Sabre;

use App\Models\SupplierConnection;
use App\Support\Sabre\SabreSandboxQaLifecycleGuard;
use InvalidArgumentException;

/**
 * Pre-HTTP gate for sandbox QA PNR create. Ordinary production paths must not call this.
 */
final class SabreSandboxQaPnrCreateGuard
{
    /**
     * @return array{
     *     allowed: bool,
     *     block_reason: string|null,
     *     resolved_environment: string,
     *     resolved_host: string,
     *     host_classification: string,
     *     production_sabre_host_selected: bool,
     *     connection_id: int|null,
     *     connection_alias_safe: string|null
     * }
     */
    public static function assertBeforeHttp(
        ?SupplierConnection $connection,
        ?int $forbiddenLiveConnectionId = null,
    ): array {
        return SabreSandboxQaLifecycleGuard::assertSandboxQaPnrCreateAllowed(
            $connection,
            $forbiddenLiveConnectionId,
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function requireBeforeHttp(
        ?SupplierConnection $connection,
        ?int $forbiddenLiveConnectionId = null,
    ): SupplierConnection {
        $guard = self::assertBeforeHttp($connection, $forbiddenLiveConnectionId);
        if (! ($guard['allowed'] ?? false) || ! ($connection instanceof SupplierConnection)) {
            throw new InvalidArgumentException(
                'QA_SANDBOX_PNR_CREATE_PRODUCTION_GUARD: '.($guard['block_reason'] ?? 'blocked')
            );
        }

        return $connection;
    }
}
