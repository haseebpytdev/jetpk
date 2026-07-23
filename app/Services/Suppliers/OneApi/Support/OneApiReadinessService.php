<?php

namespace App\Services\Suppliers\OneApi\Support;

use App\Models\SupplierConnection;

/**
 * Admin readiness dimensions for One API connections (no supplier calls unless live audit).
 */
class OneApiReadinessService
{
    public function __construct(
        private readonly OneApiConfigResolver $configResolver,
    ) {}

    /**
     * @return array<string, array{ready: bool, label: string, detail: string}>
     */
    public function dimensions(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $keys = array_map('strtolower', array_keys($credentials));

        $credentialsComplete = in_array('username', $keys, true)
            && in_array('password', $keys, true)
            && in_array('agent_code', $keys, true)
            && in_array('rest_auth_url', $keys, true)
            && in_array('rest_search_url', $keys, true);

        $soapUrl = trim((string) ($credentials['soap_url'] ?? ''));
        $restAuth = trim((string) ($credentials['rest_auth_url'] ?? ''));
        $restSearch = trim((string) ($credentials['rest_search_url'] ?? ''));

        try {
            $config = $this->configResolver->resolve($connection);
        } catch (\Throwable) {
            $config = [];
        }

        return [
            'credentials_complete' => [
                'ready' => $credentialsComplete,
                'label' => 'Credentials complete',
                'detail' => $credentialsComplete ? 'Required REST fields present.' : 'Missing username, password, agent, or REST URLs.',
            ],
            'REST_auth_endpoint_present' => [
                'ready' => $restAuth !== '',
                'label' => 'REST auth ready',
                'detail' => $restAuth !== '' ? 'Auth URL configured.' : 'rest_auth_url missing.',
            ],
            'REST_search_endpoint_present' => [
                'ready' => $restSearch !== '',
                'label' => 'REST search ready',
                'detail' => $restSearch !== '' ? 'Search URL configured.' : 'rest_search_url missing.',
            ],
            'SOAP_endpoint_present' => [
                'ready' => $soapUrl !== '',
                'label' => 'SOAP price ready',
                'detail' => $soapUrl !== '' ? 'SOAP URL configured.' : 'soap_url missing — live SOAP blocked.',
            ],
            'booking_ready' => [
                'ready' => $soapUrl !== '' && ($config['direct_bill_enabled'] ?? true),
                'label' => 'Booking ready',
                'detail' => $soapUrl !== '' ? 'SOAP + DirectBill configuration present.' : 'Configure soap_url before booking.',
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
}
