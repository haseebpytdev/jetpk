<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;
use Throwable;

/**
 * B42: Expanded Sabre REST booking/PNR path discovery — POST {@code {}} probes only (OAuth once). Does not send real
 * booking payloads, passenger data, or {@code sabre:compare-booking-endpoints} live wires. Ticketing config is unchanged.
 * B43: Probe list includes Passenger Records {@code ?mode=create} / {@code ?mode=update} query variants; {@see SabreBookingService::discoveryEndpointFlags()} classifies {@code mode=update} as non-create.
 */
class SabreDiscoverBookingEndpointsCommand extends Command
{
    protected $signature = 'sabre:discover-booking-endpoints
                            {--connection= : Supplier connection ID (Sabre); defaults to first Sabre connection}
                            {--write-report= : Optional path to write JSON summary (e.g. storage/app/sabre-booking-endpoint-discovery.json)}';

    protected $description = '[local/testing only] B42: OAuth once; POST {} to expanded Sabre booking/PNR REST matrix; safe table + optional JSON report';

    public function handle(SabreBookingService $sabreBooking): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $connectionId = $this->option('connection');
        $query = SupplierConnection::query()->where('provider', SupplierProvider::Sabre);

        if ($connectionId !== null && $connectionId !== '') {
            $query->whereKey((int) $connectionId);
        }

        $connection = $query->orderBy('id')->first();

        if ($connection === null) {
            $this->components->error('No Sabre supplier connection found. Create one in API settings or pass --connection=');

            return self::FAILURE;
        }

        $this->line('B42 discovery: POST {} only (empty JSON object). No passenger data. No real booking payloads. No ticketing changes.');
        $this->line('Live booking payloads stay on sabre:compare-booking-endpoints --send --endpoint=... --style=...');
        $this->line('ticketing_enabled_config='.(config('suppliers.sabre.ticketing_enabled', false) ? 'true' : 'false').' (not modified by this command)');
        $this->newLine();

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
        $this->line('base_host='.(is_string($host) && $host !== '' ? $host : 'unknown'));
        $this->line('connection_id='.$connection->id);
        $this->newLine();

        try {
            $rows = $sabreBooking->discoverBookingEndpointsProbeForConnection($connection);
        } catch (Throwable $e) {
            $this->components->error('Discovery failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                (string) ($row['endpoint_path'] ?? ''),
                (string) ($row['method'] ?? 'POST'),
                (string) ($row['http_status'] ?? ''),
                (string) ($row['access_result'] ?? ''),
                (($row['likely_create_endpoint'] ?? false) === true) ? 'true' : 'false',
                (($row['non_create_endpoint'] ?? false) === true) ? 'true' : 'false',
                (string) ($row['entitlement_hint'] ?? ''),
                (string) ($row['safe_error_code'] ?? ''),
                (string) ($row['safe_error_message_truncated'] ?? ''),
            ];
        }

        $this->table(
            [
                'endpoint_path',
                'method',
                'http_status',
                'access_result',
                'likely_create_endpoint',
                'non_create_endpoint',
                'entitlement_hint',
                'safe_error_code',
                'safe_error_message_truncated',
            ],
            $tableRows,
        );

        if (SabreBookingService::discoveryShouldEmitSoapHint($rows)) {
            $this->newLine();
            $this->line('sabre_rest_probe_soap_hint='.SabreBookingService::discoverySoapHintMessage());
        }

        $writeOpt = $this->option('write-report');
        $writeStr = is_string($writeOpt) ? trim($writeOpt) : '';
        if ($writeStr !== '') {
            $path = $this->resolveDiscoveryReportWritePath($writeStr);
            $payload = $sabreBooking->buildBookingEndpointDiscoveryReportPayload($connection, $rows);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->newLine();
            $this->line('wrote_report='.$path);
        }

        return self::SUCCESS;
    }

    protected function resolveDiscoveryReportWritePath(string $p): string
    {
        $t = trim($p);
        if ($t === '') {
            return '';
        }
        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $t)) {
            return $t;
        }
        if (str_starts_with($t, '/') || str_starts_with($t, '\\')) {
            return $t;
        }
        $trim = str_replace('\\', '/', $t);
        if (str_starts_with($trim, 'storage/')) {
            $trim = substr($trim, strlen('storage/'));
        }

        return storage_path(str_replace('/', DIRECTORY_SEPARATOR, $trim));
    }
}
