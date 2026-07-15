<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabreTicketingEndpointDiscovery;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;
use Throwable;

/**
 * T2B: Sabre ticketing REST endpoint discovery — inspect-only matrix by default; optional safe entitlement probes with --send.
 * Never issues tickets, sends FOP/payment, or calls cancelBooking/void/refund. No DB writes.
 */
class SabreDiscoverTicketingEndpointsCommand extends Command
{
    protected $signature = 'sabre:discover-ticketing-endpoints
                            {--connection= : Supplier connection ID (Sabre)}
                            {--booking= : Booking ID (resolves connection + optional PNR for read probes)}
                            {--path= : Optional extra endpoint path to include in matrix}
                            {--method=POST : HTTP method for custom --path (POST|GET|OPTIONS)}
                            {--send : Live safe entitlement probes (local/testing only; OAuth once)}
                            {--json : Emit ticketing_endpoint_discovery_json=...}
                            {--max-calls=12 : Cap live probe HTTP calls when using --send}
                            {--output= : Optional path to write redacted JSON (e.g. storage/app/sabre-ticketing-endpoint-discovery.json)}';

    protected $description = '[local/testing only] T2B: Sabre ticketing endpoint discovery matrix; safe entitlement probes with --send only';

    public function handle(SabreTicketingEndpointDiscovery $discovery): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $send = (bool) $this->option('send');
        if ($send && ! SabreInspectGate::allowed()) {
            $this->components->error('--send requires APP_ENV local or testing.');

            return self::FAILURE;
        }

        $connectionId = $this->option('connection');
        $bookingId = $this->option('booking');
        $hasConnection = $connectionId !== null && $connectionId !== '';
        $hasBooking = $bookingId !== null && $bookingId !== '' && is_numeric($bookingId);

        if (! $hasConnection && ! $hasBooking) {
            $this->components->error('Pass --connection={id} or --booking={id}.');

            return self::FAILURE;
        }

        $booking = null;
        if ($hasBooking) {
            $booking = Booking::query()->find((int) $bookingId);
            if ($booking === null) {
                $this->components->error('Booking not found.');

                return self::FAILURE;
            }
        }

        $connection = $this->resolveConnection($hasConnection ? (int) $connectionId : null, $booking);
        if ($connection === null) {
            $this->components->error('No Sabre supplier connection found.');

            return self::FAILURE;
        }

        if ($booking !== null && ! $this->isSabreBooking($booking)) {
            $this->components->error('Booking is not a Sabre booking.');

            return self::FAILURE;
        }

        $maxCalls = max(1, (int) $this->option('max-calls'));
        $method = strtoupper(trim((string) $this->option('method') ?: 'POST'));
        if (! in_array($method, ['POST', 'GET', 'OPTIONS'], true)) {
            $this->components->error('--method must be POST, GET, or OPTIONS.');

            return self::FAILURE;
        }

        $pathOverride = $this->option('path');
        $pathStr = is_string($pathOverride) ? trim($pathOverride) : '';
        if ($pathStr !== '' && $this->isDestructivePathOverride($pathStr)) {
            $this->components->warn('--path matches a destructive endpoint (e.g. cancelBooking); it will not be probed.');

            return self::FAILURE;
        }

        $this->line('T2B ticketing discovery: inspect-only by default. No issue-ticket, FOP, cancelBooking, or void/refund.');
        $this->line('live_call_attempted='.($send ? 'true' : 'false').' max_calls='.$maxCalls);
        $this->line('ticketing_enabled_config='.(config('suppliers.sabre.ticketing_enabled', false) ? 'true' : 'false').' (not modified)');
        $this->newLine();

        try {
            $payload = $discovery->discover(
                $connection,
                $booking,
                $send,
                $maxCalls,
                $pathStr !== '' ? $pathStr : null,
                $method,
            );
        } catch (Throwable $e) {
            $this->components->error('Discovery failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line('ticketing_endpoint_discovery_json='.json_encode($payload, JSON_UNESCAPED_SLASHES));
        } else {
            $this->printTable($payload);
            $this->newLine();
            $this->line('recommended_next_action='.($payload['recommended_next_action'] ?? ''));
        }

        $outputOpt = $this->option('output');
        $outputStr = is_string($outputOpt) ? trim($outputOpt) : '';
        if ($outputStr !== '') {
            $path = $this->resolveOutputPath($outputStr);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line('wrote_output='.$path);
        }

        return self::SUCCESS;
    }

    protected function resolveConnection(?int $connectionId, ?Booking $booking): ?SupplierConnection
    {
        if ($connectionId !== null && $connectionId > 0) {
            $c = SupplierConnection::query()
                ->where('provider', SupplierProvider::Sabre)
                ->find($connectionId);
            if ($c !== null) {
                return $c;
            }
        }

        if ($booking !== null) {
            return SabreTicketingEndpointDiscovery::resolveConnectionForBooking($booking);
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->orderBy('id')
            ->first();
    }

    protected function isSabreBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return $provider === SupplierProvider::Sabre->value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function printTable(array $payload): void
    {
        $rows = [];
        foreach ((array) ($payload['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            $rows[] = [
                (string) ($candidate['family'] ?? ''),
                (string) ($candidate['endpoint_path'] ?? ''),
                (string) ($candidate['method'] ?? ''),
                (string) ($candidate['body_style'] ?? ''),
                (($candidate['live_call_attempted'] ?? false) === true) ? 'true' : 'false',
                $candidate['http_status'] !== null ? (string) $candidate['http_status'] : '',
                (string) ($candidate['access_result'] ?? ''),
                (($candidate['destructive'] ?? false) === true) ? 'true' : 'false',
                (($candidate['should_probe'] ?? false) === true) ? 'true' : 'false',
                (string) ($candidate['entitlement_hint'] ?? ''),
            ];
        }

        $this->table(
            [
                'family',
                'endpoint_path',
                'method',
                'body_style',
                'live_call',
                'http_status',
                'access_result',
                'destructive',
                'should_probe',
                'entitlement_hint',
            ],
            $rows,
        );
        $this->line('connection_id='.($payload['connection_id'] ?? ''));
        $this->line('booking_id='.($payload['booking_id'] ?? ''));
        $this->line('pnr_present='.(($payload['pnr_present'] ?? false) ? 'true' : 'false'));
    }

    protected function isDestructivePathOverride(string $path): bool
    {
        $lower = strtolower($path);
        foreach (['/cancelbooking', 'cancelbooking', '/void', '/refund', 'voidticket', 'refundticket'] as $frag) {
            if (str_contains($lower, $frag)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveOutputPath(string $p): string
    {
        $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($p));
        if ($p === '') {
            return storage_path('app/sabre-ticketing-endpoint-discovery.json');
        }
        if (preg_match('#^[A-Za-z]:\\\\#', $p) || str_starts_with($p, DIRECTORY_SEPARATOR)) {
            return $p;
        }

        return base_path($p);
    }
}
