<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;

class PiaNdcProfileService
{
    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fetchProfile(SupplierConnection $connection, ?string $origin = null): array
    {
        $output = [
            'connection_id' => $connection->id,
            'endpoint' => trim((string) ($connection->base_url ?? '')),
            'agency_id' => null,
            'owner_code' => null,
            'carrier_code' => null,
            'currency' => null,
            'username_header' => (string) config('suppliers.pia_ndc.username_header', 'username'),
            'password_header' => (string) config('suppliers.pia_ndc.password_header', 'password'),
            'payment_type' => null,
            'mco_invoice_configured' => false,
            'general_params' => null,
            'airline_profile' => null,
            'provider_errors' => null,
            'correlation_id' => null,
            'error_code' => null,
        ];

        try {
            $config = $this->configResolver->resolve($connection);
        } catch (PiaNdcException $exception) {
            return array_merge($output, $exception->safeDiagnosticMeta());
        }

        $output['endpoint'] = $config['endpoint_url'];
        $output['agency_id'] = $config['agency_id'];
        $output['owner_code'] = $config['owner_code'];
        $output['carrier_code'] = $config['carrier_code'];
        $output['currency'] = $config['currency'];
        $output['username_header'] = $config['username_header'];
        $output['password_header'] = $config['password_header'];
        $output['payment_type'] = $config['payment_type'];
        $output['mco_invoice_configured'] = $config['mco_invoice_number'] !== '';

        try {
            $generalXml = $this->xmlBuilder->buildGeneralParamsRequest($config);
            $generalResponse = $this->client->call($connection, 'general_params', $generalXml, ['request_context' => 'profile']);
            $diagnostic = is_array($generalResponse['_ota_diagnostic'] ?? null) ? $generalResponse['_ota_diagnostic'] : [];
            $output['correlation_id'] = (string) ($diagnostic['correlation_id'] ?? '');
            $parsed = is_array($generalResponse['parsed'] ?? null) ? $generalResponse['parsed'] : [];
            $output['general_params'] = is_array($parsed['general_params'] ?? null) ? $parsed['general_params'] : [];
        } catch (PiaNdcException $exception) {
            $output = array_merge($output, $exception->safeDiagnosticMeta('general_params'));
            $output['general_params_failed'] = true;
        }

        try {
            $profileXml = $this->xmlBuilder->buildAirlineProfileRequest($config, $origin);
            $profileResponse = $this->client->call($connection, 'airline_profile', $profileXml, ['request_context' => 'profile']);
            $diagnostic = is_array($profileResponse['_ota_diagnostic'] ?? null) ? $profileResponse['_ota_diagnostic'] : [];
            if ($output['correlation_id'] === '') {
                $output['correlation_id'] = (string) ($diagnostic['correlation_id'] ?? '');
            }
            $parsed = is_array($profileResponse['parsed'] ?? null) ? $profileResponse['parsed'] : [];
            $output['airline_profile'] = is_array($parsed['airline_profile'] ?? null) ? $parsed['airline_profile'] : [];
        } catch (PiaNdcException $exception) {
            $profileMeta = $exception->safeDiagnosticMeta('airline_profile');
            if (! isset($output['error_code'])) {
                $output = array_merge($output, $profileMeta);
            } elseif (! isset($output['provider_errors']) && isset($profileMeta['provider_errors'])) {
                $output['airline_profile_provider_errors'] = $profileMeta['provider_errors'];
            }
            $output['airline_profile_failed'] = true;
        }

        return $output;
    }
}
