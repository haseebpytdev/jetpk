<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Diagnostics\SabrePccCapabilityMatrix;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;
use Throwable;

/**
 * Q1: Sabre PCC/credential capability matrix (local/testing certification/discovery only).
 */
class SabrePccCapabilityMatrixCommand extends Command
{
    protected $signature = 'sabre:pcc-capability-matrix
                            {--booking= : Booking ID (optional; enables wire inspect + certification-safe live create)}
                            {--connection= : Supplier connection ID (Sabre)}
                            {--send : Live safe probes only (capped by --max-calls; no ticketing issue / cancel / void)}
                            {--json : Emit pcc_capability_matrix_json=... only}
                            {--max-calls=25 : Cap live HTTP probes when using --send}
                            {--output= : Optional path to write redacted JSON (e.g. storage/app/sabre-pcc-capability.json)}';

    protected $description = '[local/testing only] Q1: Sabre PCC/credential capability matrix across REST surfaces (inspect default; safe --send probes)';

    public function handle(SabrePccCapabilityMatrix $matrix): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $send = (bool) $this->option('send');
        $maxCalls = max(1, (int) $this->option('max-calls'));
        $connectionId = $this->option('connection');
        $bookingId = $this->option('booking');
        $hasConnection = $connectionId !== null && $connectionId !== '';
        $hasBooking = $bookingId !== null && $bookingId !== '' && is_numeric($bookingId);

        if (! $hasConnection && ! $hasBooking) {
            $this->components->error('Pass --connection={id} or --booking={id} (or both).');

            return self::FAILURE;
        }

        $booking = null;
        if ($hasBooking) {
            $booking = Booking::query()->find((int) $bookingId);
            if ($booking === null) {
                $this->components->error('Booking not found.');

                return self::FAILURE;
            }
            if (! $this->isSabreBooking($booking)) {
                $this->components->error('Booking is not a Sabre booking.');

                return self::FAILURE;
            }
        }

        $connection = SabrePccCapabilityMatrix::resolveConnection(
            $hasConnection ? (int) $connectionId : null,
            $booking,
        );
        if ($connection === null) {
            $this->components->error('No Sabre supplier connection found.');

            return self::FAILURE;
        }

        $this->line('Q1 PCC capability matrix: inspect-only by default. No ticketing issue, cancel, void, or refund.');
        $this->line('live_call_attempted='.($send ? 'true' : 'false').' max_calls='.$maxCalls);
        $this->line('ticketing_enabled_config='.(config('suppliers.sabre.ticketing_enabled', false) ? 'true' : 'false').' (unchanged)');
        $this->newLine();

        try {
            $payload = $matrix->build($connection, $booking, $send, $maxCalls);
        } catch (Throwable $e) {
            $this->components->error('Matrix build failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line('pcc_capability_matrix_json='.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->printHumanSummary($payload);
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

    protected function isSabreBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return $provider === SupplierProvider::Sabre->value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function printHumanSummary(array $payload): void
    {
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $this->line('connection_id='.($payload['connection_id'] ?? ''));
        $this->line('booking_id='.($payload['booking_id'] ?? ''));
        $this->line('calls_made='.($payload['calls_made'] ?? 0).' max_calls='.($payload['max_calls'] ?? 0));
        $this->line('recommended_current_booking_path='.($summary['recommended_current_booking_path'] ?? ''));
        $this->newLine();
        $this->line('certified_categories='.json_encode($summary['certified_categories'] ?? []));
        $this->line('blocked_categories='.json_encode($summary['blocked_categories'] ?? []));
        $this->line('next_actions='.json_encode($summary['next_actions'] ?? []));
        $this->newLine();

        $tableRows = [];
        foreach ((array) ($payload['rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $tableRows[] = [
                (string) ($row['section'] ?? ''),
                (string) ($row['endpoint_path'] ?? ''),
                (string) ($row['payload_style_label'] ?? $row['payload_style'] ?? ''),
                (($row['live_call_attempted'] ?? false) === true) ? 'true' : 'false',
                (string) ($row['http_status'] ?? ''),
                (string) ($row['access_result'] ?? ''),
            ];
        }
        $this->table(
            ['section', 'endpoint', 'style', 'live', 'http', 'access_result'],
            $tableRows,
        );
    }

    protected function resolveOutputPath(string $p): string
    {
        $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($p));
        if ($p === '') {
            return storage_path('app/sabre-pcc-capability.json');
        }
        if (preg_match('#^[A-Za-z]:\\\\#', $p) || str_starts_with($p, DIRECTORY_SEPARATOR)) {
            return $p;
        }

        return base_path($p);
    }
}
