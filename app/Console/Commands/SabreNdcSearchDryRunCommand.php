<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcSearchDryRunService;
use Illuminate\Console\Command;

class SabreNdcSearchDryRunCommand extends Command
{
    protected $signature = 'sabre:ndc-search-dry-run
                            {--connection= : Supplier connection ID}
                            {--origin=LHE : Origin IATA}
                            {--destination=DXB : Destination IATA}
                            {--date= : Departure date YYYY-MM-DD}
                            {--adults=1 : Adult count}
                            {--children=0 : Child count}
                            {--infants=0 : Infant count}
                            {--cabin=economy : Cabin class}
                            {--variant= : Request variant: ndc_v5_gir_datasources_only|ndc_v5_minimal_shop|ndc_v5_pos_pcc_source}
                            {--send : Live NDC shop call only (/v5/offers/shop)}
                            {--confirm= : Required for --send: SEND-SABRE-NDC-SEARCH}
                            {--json : Emit compact JSON only}';

    protected $description = 'Sabre NDC offer shop dry-run (gates + request shape); optional live /v5/offers/shop only';

    public function handle(SabreNdcSearchDryRunService $dryRunService): int
    {
        $send = (bool) $this->option('send');
        if ($send && trim((string) $this->option('confirm')) !== SabreNdcSearchDryRunService::CONFIRM_PHRASE) {
            $this->components->error('--send requires --confirm='.SabreNdcSearchDryRunService::CONFIRM_PHRASE);

            return self::FAILURE;
        }

        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->components->error('Sabre supplier connection not found.');

            return self::FAILURE;
        }

        $date = trim((string) $this->option('date'));
        if ($date === '') {
            $this->components->error('--date is required (YYYY-MM-DD).');

            return self::FAILURE;
        }

        $request = new FlightSearchRequestData(
            origin: strtoupper(trim((string) $this->option('origin'))),
            destination: strtoupper(trim((string) $this->option('destination'))),
            departure_date: $date,
            adults: max(1, (int) $this->option('adults')),
            children: max(0, (int) $this->option('children')),
            infants: max(0, (int) $this->option('infants')),
            cabin: (string) $this->option('cabin'),
            search_id: 'dry-run-'.uniqid('', true),
        );

        $variant = trim((string) $this->option('variant'));
        $result = $dryRunService->run(
            $connection,
            $request,
            $send,
            $variant !== '' ? $variant : null,
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

            return $this->exitCode($result, $send);
        }

        foreach ([
            'search_id',
            'route',
            'endpoint_path',
            'dry_run',
            'selected_variant',
            'selected_sabre_lanes',
            'ndc_live_search_http_enabled',
            'gds_results_suppressed',
            'pcc_present',
            'gds_called',
            'mutation_attempted',
            'live_supplier_call_attempted',
            'http_status',
            'response_shape',
            'application_results_status',
            'safe_error_code',
            'safe_error_message',
            'offer_count_raw',
            'normalized_offer_count',
            'reason_code',
            'no_offer_reason',
            'safe_error_family',
        ] as $key) {
            if (array_key_exists($key, $result)) {
                $this->line($key.'='.$this->scalar($result[$key]));
            }
        }

        if (is_array($result['request_shape_summary'] ?? null)) {
            $this->line('request_shape_summary='.$this->scalar($result['request_shape_summary']));
        }

        $validationPaths = is_array($result['validation_paths'] ?? null) ? $result['validation_paths'] : [];
        if ($validationPaths !== []) {
            $this->line('validation_paths='.$this->scalar($validationPaths));
        }

        $blockers = is_array($result['blockers'] ?? null) ? $result['blockers'] : [];
        $this->line('blockers='.implode(',', $blockers));

        return $this->exitCode($result, $send);
    }

    private function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id !== null && is_numeric($id)) {
            return SupplierConnection::query()->find((int) $id);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->orderByDesc('is_active')->orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function exitCode(array $result, bool $send): int
    {
        if (! $send) {
            return self::SUCCESS;
        }

        $blockers = is_array($result['blockers'] ?? null) ? $result['blockers'] : [];
        if ($blockers !== []) {
            return self::FAILURE;
        }

        return ((int) ($result['http_status'] ?? 0)) >= 200 && ((int) ($result['http_status'] ?? 0)) < 300
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }
}
