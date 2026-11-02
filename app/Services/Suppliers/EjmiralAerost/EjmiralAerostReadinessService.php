<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Models\SupplierConnection;

/**
 * Admin readiness dimensions for EJMIRAL AEROST (no supplier calls).
 */
class EjmiralAerostReadinessService
{
    public function __construct(
        private readonly EjmiralAerostConfigResolver $configResolver,
    ) {}

    /**
     * @return array<string, array{ready: bool, label: string, detail: string, code?: string}>
     */
    public function dimensions(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));
        $credentialReady = $username !== '' && $password !== '';

        $apiStatus = $this->configResolver->apiBaseUrlStatus();
        $apiConfigured = $apiStatus === EjmiralAerostConfigResolver::API_BASE_URL_STATUS_CONFIGURED;

        return [
            'AEROST_CREDENTIAL_READINESS' => [
                'ready' => $credentialReady,
                'label' => 'Credential readiness',
                'detail' => $credentialReady
                    ? 'Sub Travel Agent username and password present.'
                    : 'Sub Travel Agent username/password required.',
                'code' => $credentialReady ? 'PASS' : 'FAIL',
            ],
            'AEROST_API_BASE_URL_STATUS' => [
                'ready' => $apiConfigured,
                'label' => 'API host status',
                'detail' => $apiConfigured
                    ? 'EJMIRAL_AEROST_API_BASE_URL configured by system.'
                    : 'UNRESOLVED — set EJMIRAL_AEROST_API_BASE_URL before API calls.',
                'code' => $apiStatus,
            ],
            'AEROST_API_READINESS' => [
                'ready' => $apiConfigured,
                'label' => 'API readiness',
                'detail' => $apiConfigured
                    ? 'System API base URL ready.'
                    : 'FAIL_CLOSED — AEROST API host unresolved; no PSA portal fallback.',
                'code' => $apiConfigured ? 'PASS' : 'FAIL_CLOSED',
            ],
        ];
    }
}
