<?php

namespace App\Services\Suppliers\Iati;

use App\Enums\SupplierEnvironment;
use App\Models\SupplierConnection;

/**
 * Resolves IATI auth and flight API base URLs from SupplierConnection environment.
 * Flight v2 calls exchange auth_code:secret for JWT when secret is stored; otherwise auth_code Bearer.
 */
class IatiConfigResolver
{
    /**
     * @return array{
     *     environment: string,
     *     is_test: bool,
     *     host_base: string,
     *     auth_base: string,
     *     flight_base: string,
     *     auth_code: string,
     *     secret: string,
     *     organization_id: string,
     *     language_code: string
     * }
     */
    public function resolve(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $authCode = trim((string) ($credentials['auth_code'] ?? ''));
        $secret = trim((string) ($credentials['secret'] ?? ''));
        $organizationId = $this->optionalString($credentials['organization_id'] ?? null);

        if ($authCode === '') {
            throw new \InvalidArgumentException('IATI auth_code is required.');
        }

        $isTest = $this->isTestEnvironment($connection);
        $hostBase = $this->hostBase($connection, $isTest);

        return [
            'environment' => $connection->environment?->value ?? 'sandbox',
            'is_test' => $isTest,
            'host_base' => $hostBase,
            'auth_base' => rtrim($hostBase, '/').'/rest/auth',
            'flight_base' => rtrim($hostBase, '/').'/rest/flight/v2',
            'auth_code' => $authCode,
            'secret' => $secret,
            'organization_id' => $organizationId ?? '',
            'language_code' => $this->optionalString($credentials['language_code'] ?? null) ?: 'en',
        ];
    }

    public function isTestEnvironment(SupplierConnection $connection): bool
    {
        $env = $connection->environment;

        return in_array($env, [SupplierEnvironment::Demo, SupplierEnvironment::Sandbox], true);
    }

    private function hostBase(SupplierConnection $connection, bool $isTest): string
    {
        $override = trim((string) ($connection->base_url ?? ''));
        if ($override !== '') {
            return $this->hostBaseFromUrl($override);
        }

        $default = $isTest
            ? (string) config('suppliers.iati.test_host_base', 'https://testapi.iati.com')
            : (string) config('suppliers.iati.prod_host_base', 'https://api.iati.com');

        return rtrim($default, '/');
    }

    private function hostBaseFromUrl(string $url): string
    {
        $normalized = rtrim(trim($url), '/');
        foreach (['/rest/flight/v2', '/rest/flight'] as $suffix) {
            if (str_ends_with($normalized, $suffix)) {
                return substr($normalized, 0, -strlen($suffix));
            }
        }

        return $normalized;
    }

    private function optionalString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed !== '' ? $trimmed : null;
    }
}
