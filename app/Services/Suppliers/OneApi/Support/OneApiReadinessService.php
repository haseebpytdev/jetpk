<?php

namespace App\Services\Suppliers\OneApi\Support;

use App\Models\SupplierConnection;
use App\Support\Suppliers\OneApiAccountContext;

/**
 * Admin readiness dimensions for One API connections (no supplier calls unless live audit).
 */
class OneApiReadinessService
{
    public const CREDENTIAL_READINESS_KEY = 'ONEAPI_CREDENTIAL_READINESS';

    public const SEARCH_READINESS_KEY = 'ONEAPI_SEARCH_READINESS';

    public function __construct(
        private readonly OneApiConfigResolver $configResolver,
    ) {}

    /**
     * @return array<string, array{ready: bool, label: string, detail: string}>
     */
    public function dimensions(SupplierConnection $connection): array
    {
        $credential = $this->credentialReadiness($connection);
        $search = $this->searchReadiness($connection);

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $soapUrl = trim((string) ($credentials['soap_url'] ?? ''));

        try {
            $config = $this->configResolver->resolve($connection);
            $soapUrl = trim((string) ($config['soap_url'] ?? $soapUrl));
        } catch (\Throwable) {
            $config = [];
        }

        return [
            self::CREDENTIAL_READINESS_KEY => $credential,
            self::SEARCH_READINESS_KEY => $search,
            'SOAP_credentials_present' => [
                'ready' => $credential['ready'],
                'label' => 'SOAP identity present',
                'detail' => $credential['detail'],
            ],
            'REST_auth_endpoint_present' => [
                'ready' => ($config['rest_auth_url'] ?? '') !== '',
                'label' => 'REST auth ready',
                'detail' => ($config['rest_auth_url'] ?? '') !== '' ? 'Auth URL resolved.' : 'REST auth URL missing from system config.',
            ],
            'REST_search_endpoint_present' => [
                'ready' => ($config['rest_search_url'] ?? '') !== '',
                'label' => 'REST search ready',
                'detail' => ($config['rest_search_url'] ?? '') !== '' ? 'Search URL resolved.' : 'REST search URL missing from system config.',
            ],
            'SOAP_endpoint_present' => [
                'ready' => $soapUrl !== '',
                'label' => 'SOAP price ready',
                'detail' => $soapUrl !== '' ? 'SOAP URL configured.' : 'SOAP URL missing — live SOAP blocked.',
            ],
            'booking_ready' => [
                'ready' => $soapUrl !== '' && ($config['direct_bill_enabled'] ?? true),
                'label' => 'Booking ready',
                'detail' => $soapUrl !== '' ? 'SOAP + DirectBill configuration present.' : 'Configure SOAP endpoint before booking.',
            ],
            'hold_payment_ready' => [
                'ready' => $soapUrl !== '' && (bool) ($config['hold_payment_enabled'] ?? false),
                'label' => 'Hold payment ready',
                'detail' => (bool) ($config['hold_payment_enabled'] ?? false)
                    ? 'Hold payment flag enabled (verify live permission).'
                    : 'hold_payment_enabled is off.',
            ],
            'live_search_allowed' => [
                'ready' => (bool) ($config['live_search_enabled'] ?? false),
                'label' => 'Live search allowed',
                'detail' => (bool) ($config['live_search_enabled'] ?? false) ? 'Connection allows live search.' : 'Disabled on connection.',
            ],
            'live_booking_allowed' => [
                'ready' => (bool) ($config['live_booking_enabled'] ?? false),
                'label' => 'Live booking allowed',
                'detail' => (bool) ($config['live_booking_enabled'] ?? false) ? 'Connection allows live booking.' : 'Disabled on connection.',
            ],
            'live_payment_allowed' => [
                'ready' => (bool) ($config['live_payment_modification_enabled'] ?? false),
                'label' => 'Live payment allowed',
                'detail' => (bool) ($config['live_payment_modification_enabled'] ?? false) ? 'Hold payment live flag on.' : 'Disabled on connection.',
            ],
        ];
    }

    /**
     * @return array{ready: bool, label: string, detail: string}
     */
    public function credentialReadiness(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        $restUser = trim((string) ($credentials['rest_username'] ?? ''));
        if ($restUser === '') {
            $restUser = trim((string) ($credentials['username'] ?? ''));
        }
        $restPass = trim((string) ($credentials['rest_password'] ?? ''));
        if ($restPass === '') {
            $restPass = trim((string) ($credentials['password'] ?? ''));
        }
        $soapUser = trim((string) ($credentials['soap_username'] ?? ''));
        if ($soapUser === '') {
            $soapUser = trim((string) ($credentials['username'] ?? ''));
        }
        $soapPass = trim((string) ($credentials['soap_password'] ?? ''));
        if ($soapPass === '') {
            $soapPass = trim((string) ($credentials['password'] ?? ''));
        }
        $agentCode = trim((string) ($credentials['agent_code'] ?? ''));

        $ready = $restUser !== ''
            && $restPass !== ''
            && $soapUser !== ''
            && $soapPass !== ''
            && $agentCode !== '';

        return [
            'ready' => $ready,
            'label' => 'Credential readiness',
            'detail' => $ready
                ? 'REST/SOAP identities and agent code are present.'
                : 'Missing REST/SOAP identity or agent code.',
        ];
    }

    /**
     * @return array{ready: bool, label: string, detail: string}
     */
    public function searchReadiness(SupplierConnection $connection): array
    {
        $credential = $this->credentialReadiness($connection);
        if (! $credential['ready']) {
            return [
                'ready' => false,
                'label' => 'Search readiness',
                'detail' => 'Complete credential readiness before search.',
            ];
        }

        $accountContext = OneApiAccountContext::resolve($connection);
        if (! $accountContext['account_context_confirmed']) {
            return [
                'ready' => false,
                'label' => 'Search readiness',
                'detail' => OneApiAccountContext::SEARCH_BLOCKED_MESSAGE,
            ];
        }

        try {
            $endpoints = $this->configResolver->resolveEndpointUrls(
                $connection,
                is_array($connection->credentials) ? $connection->credentials : [],
            );
            if ($endpoints['rest_auth_url'] === '' || $endpoints['rest_search_url'] === '') {
                return [
                    'ready' => false,
                    'label' => 'Search readiness',
                    'detail' => 'System REST endpoints are not configured for this environment.',
                ];
            }
        } catch (\Throwable) {
            return [
                'ready' => false,
                'label' => 'Search readiness',
                'detail' => 'System REST endpoints are not configured for this environment.',
            ];
        }

        return [
            'ready' => true,
            'label' => 'Search readiness',
            'detail' => 'Account POS/currency context confirmed and system endpoints resolved.',
        ];
    }

    public function isSearchReady(SupplierConnection $connection): bool
    {
        return $this->searchReadiness($connection)['ready'];
    }
}
