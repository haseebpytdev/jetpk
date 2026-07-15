<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcProfileService;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Console\Command;

class PiaNdcProfileCommand extends Command
{
    protected $signature = 'pia-ndc:profile
        {--connection= : Supplier connection ID}
        {--origin= : Optional origin IATA code for airline profile filter}';

    protected $description = 'Fetch PIA NDC general params and airline profile diagnostics';

    public function handle(PiaNdcProfileService $profileService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No PIA NDC SupplierConnection found.');

            return self::FAILURE;
        }

        $origin = $this->option('origin');
        $origin = is_string($origin) && trim($origin) !== '' ? strtoupper(trim($origin)) : null;

        $result = $profileService->fetchProfile($connection, $origin);

        foreach (['username_header', 'password_header'] as $headerKey) {
            if (isset($result[$headerKey]) && is_string($result[$headerKey])) {
                $this->line($headerKey.'='.$result[$headerKey]);
            }
        }

        $result = SensitiveDataRedactor::redact($result);

        foreach (['connection_id', 'endpoint', 'agency_id', 'owner_code', 'carrier_code', 'currency', 'payment_type', 'mco_invoice_configured', 'correlation_id', 'error_code'] as $key) {
            if (array_key_exists($key, $result)) {
                $value = $result[$key];
                if (is_bool($value)) {
                    $this->line($key.'='.($value ? 'true' : 'false'));
                } elseif (is_scalar($value) && $value !== '') {
                    $this->line($key.'='.$value);
                }
            }
        }

        if (isset($result['http_status']) && is_scalar($result['http_status'])) {
            $this->line('http_status='.$result['http_status']);
        }

        if (isset($result['provider_errors']) && is_array($result['provider_errors'])) {
            $this->line('provider_errors='.json_encode($result['provider_errors'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        foreach (['fault_code', 'fault_message', 'operation'] as $key) {
            if (isset($result[$key]) && is_scalar($result[$key])) {
                $this->line($key.'='.$result[$key]);
            }
        }

        $general = is_array($result['general_params'] ?? null) ? $result['general_params'] : null;
        if ($general !== null) {
            $this->newLine();
            $this->info('General params:');
            foreach (['allowed_currencies', 'trip_types', 'cabin_classes', 'pax_types'] as $key) {
                if (! empty($general[$key]) && is_array($general[$key])) {
                    $this->line($key.'='.json_encode($general[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        $profile = is_array($result['airline_profile'] ?? null) ? $result['airline_profile'] : null;
        if ($profile !== null) {
            $this->newLine();
            $this->info('Airline profile:');
            if (! empty($profile['owner_codes']) && is_array($profile['owner_codes'])) {
                $this->line('owner_codes='.json_encode($profile['owner_codes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
            if (! empty($profile['routes']) && is_array($profile['routes'])) {
                $this->line('routes='.json_encode($profile['routes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        if (isset($result['error_code'])) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id !== null && $id !== '') {
            return SupplierConnection::query()
                ->where('id', (int) $id)
                ->where('provider', SupplierProvider::PiaNdc)
                ->first();
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::PiaNdc)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();
    }
}
