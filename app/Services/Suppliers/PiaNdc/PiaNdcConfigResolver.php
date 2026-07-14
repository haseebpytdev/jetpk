<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Enums\SupplierEnvironment;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;

/**
 * Resolves PIA NDC endpoint, credentials, and party fields from SupplierConnection.
 */
class PiaNdcConfigResolver
{
    /**
     * @return array{
     *     environment: string,
     *     is_test: bool,
     *     endpoint_url: string,
     *     username: string,
     *     password: string,
     *     agency_id: string,
     *     agency_name: string,
     *     owner_code: string,
     *     carrier_code: string,
     *     currency: string,
     *     language_code: string,
     *     agency_contact_email: string,
     *     mco_invoice_number: string,
     *     payment_type: string,
     *     username_header: string,
     *     password_header: string
     * }
     */
    public function resolve(SupplierConnection $connection, bool $requireMco = false): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));
        $agencyId = trim((string) ($credentials['agency_id'] ?? ''));
        $agencyName = trim((string) ($credentials['agency_name'] ?? ''));
        $ownerCode = trim((string) ($credentials['owner_code'] ?? ''));
        $agencyContactEmail = strtoupper(trim((string) (
            $credentials['agency_email']
            ?? $credentials['contact_email']
            ?? $credentials['agency_contact_email']
            ?? ''
        )));
        if ($agencyContactEmail === '') {
            $agencyContactEmail = 'ADMIN@JETPAKISTAN.COM';
        }
        $endpoint = trim((string) ($connection->base_url ?? ''));

        if ($username === '' || $password === '') {
            throw new PiaNdcValidationException(
                'missing_credentials',
                422,
                'PIA NDC username and password are required.',
            );
        }

        if ($agencyId === '' || $agencyName === '' || $ownerCode === '') {
            throw new PiaNdcValidationException(
                'missing_agency_fields',
                422,
                'PIA NDC agency ID, agency name, and owner code are required.',
            );
        }

        if ($endpoint === '') {
            throw new PiaNdcValidationException(
                'missing_endpoint',
                422,
                'PIA NDC base URL is required. Configure the endpoint in API settings.',
            );
        }

        $mcoInvoiceNumber = trim((string) ($credentials['mco_invoice_number'] ?? ''));
        $paymentType = strtoupper(trim((string) ($credentials['payment_type'] ?? '')) ?: 'MCO');

        if ($requireMco && $mcoInvoiceNumber === '') {
            throw new PiaNdcValidationException(
                'missing_mco_invoice',
                422,
                'PIA NDC MCO / Invoice Number is required for ticketing.',
            );
        }

        return [
            'environment' => $connection->environment?->value ?? 'sandbox',
            'is_test' => $this->isTestEnvironment($connection),
            'endpoint_url' => rtrim($endpoint, '/'),
            'username' => $username,
            'password' => $password,
            'agency_id' => $agencyId,
            'agency_name' => $agencyName,
            'owner_code' => $ownerCode,
            'carrier_code' => trim((string) ($credentials['carrier_code'] ?? '')) ?: 'PK',
            'currency' => strtoupper(trim((string) ($credentials['currency'] ?? '')) ?: 'PKR'),
            'language_code' => strtoupper(trim((string) ($credentials['language_code'] ?? '')) ?: 'EN'),
            'agency_contact_email' => $agencyContactEmail,
            'mco_invoice_number' => $mcoInvoiceNumber,
            'payment_type' => $paymentType,
            'username_header' => (string) config('suppliers.pia_ndc.username_header', 'username'),
            'password_header' => (string) config('suppliers.pia_ndc.password_header', 'password'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveForTicketing(SupplierConnection $connection): array
    {
        return $this->resolve($connection, requireMco: true);
    }

    public function isTestEnvironment(SupplierConnection $connection): bool
    {
        $env = $connection->environment;

        return in_array($env, [SupplierEnvironment::Demo, SupplierEnvironment::Sandbox], true);
    }
}
