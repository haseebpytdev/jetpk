<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Sabre\Diagnostics\SabreCertEntitlementMatrix;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SabreCertEntitlementMatrixCommand extends Command
{
    protected $signature = 'sabre:cert-entitlement-matrix
                            {--connection= : Sabre supplier connection ID}
                            {--send : Perform live empty/minimal HTTP probes (capped by --max-calls)}
                            {--json : Emit cert_entitlement_matrix_json=... only}
                            {--output= : Optional path to write redacted JSON (e.g. storage/app/sabre-cert-entitlement.json)}
                            {--max-calls=15 : Cap live HTTP probes when using --send}
                            {--log : Also write a redacted summary line to Laravel logs}';

    protected $description = 'CERT Sabre REST entitlement matrix (SSH-only; empty/minimal probes; no ticketing/cancel side effects)';

    public function handle(SabreCertEntitlementMatrix $matrix): int
    {
        $connectionId = $this->option('connection');
        $hasConnection = $connectionId !== null && $connectionId !== '' && is_numeric($connectionId);
        $connection = SabreCertEntitlementMatrix::resolveConnection(
            $hasConnection ? (int) $connectionId : null,
        );

        if ($connection === null) {
            $this->components->error('No Sabre supplier connection found. Pass --connection={id} or configure one in API settings.');

            return self::FAILURE;
        }

        $send = (bool) $this->option('send');
        $maxCalls = max(1, (int) $this->option('max-calls'));
        $baseUrlContext = SabreInspectGate::resolveSabreBaseUrlContext($connection);

        $this->printBaseUrlResolution($baseUrlContext);
        $this->line('connection_id='.$connection->id);
        $this->line('CERT entitlement matrix: empty/minimal probes only. No ticketing, no real cancel, no PNR create.');
        $this->line('live_call_attempted='.($send ? 'true' : 'false').' max_calls='.$maxCalls);
        $this->newLine();

        if ($send && ! SabreInspectGate::certEntitlementMatrixSendAllowed($connection)) {
            $reason = SabreInspectGate::certEntitlementMatrixSendBlockReason($connection) ?? 'blocked';
            $this->components->error('Sabre CERT entitlement matrix --send is not allowed ('.$reason.').');

            return self::FAILURE;
        }

        if (! SabreInspectGate::certEntitlementMatrixAllowed($connection)) {
            $reason = SabreInspectGate::certEntitlementMatrixBlockReason($connection) ?? 'blocked';
            $this->components->error('Sabre CERT entitlement matrix is not allowed in this environment ('.$reason.').');

            return self::FAILURE;
        }

        try {
            $payload = $matrix->build($connection, $send, $maxCalls);
        } catch (Throwable $e) {
            $this->components->error('Matrix build failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line('cert_entitlement_matrix_json='.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->printHumanSummary($payload);
        }

        if ((bool) $this->option('log')) {
            $resolution = is_array($payload['base_url_resolution'] ?? null) ? $payload['base_url_resolution'] : [];
            Log::info('sabre.cert_entitlement_matrix', [
                'connection_id' => $payload['connection_id'] ?? null,
                'resolved_source' => $resolution['resolved_source'] ?? null,
                'connection_base_url' => $resolution['connection_base_url'] ?? null,
                'config_base_url' => $resolution['config_base_url'] ?? null,
                'resolved_base_url' => $resolution['resolved_base_url'] ?? null,
                'resolved_base_host' => $payload['resolved_base_host'] ?? ($resolution['resolved_base_host'] ?? null),
                'inspect_only' => $payload['inspect_only'] ?? true,
                'calls_made' => $payload['calls_made'] ?? 0,
                'row_count' => count((array) ($payload['rows'] ?? [])),
                'endpoints' => array_map(
                    static fn (array $row): array => [
                        'endpoint' => $row['endpoint'] ?? '',
                        'http_status' => $row['http_status'] ?? null,
                        'access_result' => $row['access_result'] ?? null,
                        'entitled_guess' => $row['entitled_guess'] ?? null,
                    ],
                    array_values(array_filter((array) ($payload['rows'] ?? []), 'is_array')),
                ),
            ]);
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

    /**
     * @param  array{
     *     connection_base_url: string|null,
     *     config_base_url: string,
     *     resolved_base_url: string,
     *     resolved_base_host: string,
     *     resolved_source: string,
     * }  $context
     */
    protected function printBaseUrlResolution(array $context): void
    {
        $this->line('resolved_source='.$context['resolved_source']);
        $this->line('connection_base_url='.($context['connection_base_url'] ?? 'null'));
        $this->line('config_base_url='.$context['config_base_url']);
        $this->line('resolved_base_url='.$context['resolved_base_url']);
        $this->line('resolved_base_host='.$context['resolved_base_host']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function printHumanSummary(array $payload): void
    {
        $this->line('calls_made='.($payload['calls_made'] ?? 0).' max_calls='.($payload['max_calls'] ?? 0));
        $this->newLine();

        $tableRows = [];
        foreach ((array) ($payload['rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $entitled = $row['entitled_guess'] ?? null;
            $tableRows[] = [
                (string) ($row['endpoint'] ?? ''),
                (string) ($row['method'] ?? ''),
                (string) ($row['http_status'] ?? ''),
                (string) ($row['sabre_error_code'] ?? ''),
                (string) ($row['access_result'] ?? ''),
                $entitled === null ? 'unknown' : ($entitled ? 'yes' : 'no'),
            ];
        }

        $this->table(
            ['endpoint', 'method', 'http', 'sabre_error_code', 'access_result', 'entitled_guess'],
            $tableRows,
        );
    }

    protected function resolveOutputPath(string $p): string
    {
        $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($p));
        if ($p === '') {
            return storage_path('app/sabre-cert-entitlement.json');
        }
        if (preg_match('#^[A-Za-z]:\\\\#', $p) || str_starts_with($p, DIRECTORY_SEPARATOR)) {
            return $p;
        }

        return base_path($p);
    }
}
