<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Sabre\Scenario\SabreGdsLiveRevalidationOnlyProbe;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioPresetResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Controlled Sabre GDS revalidation-only probe — search, exact-offer linkage, one revalidation call, stop.
 * No booking, PNR, cancel, ticket, void, refund, or customer communications.
 */
class SabreGdsLiveRevalidationOnlyProbeCommand extends Command
{
    protected $signature = 'sabre:gds-live-revalidation-only-probe
                            {--connection=1 : Sabre supplier connection ID}
                            {--origin=LHE : Origin IATA}
                            {--destination=JED : Destination IATA}
                            {--departure-date= : Departure date YYYY-MM-DD (required)}
                            {--preset= : Scenario preset (e.g. qr-connecting for LHE-JED)}
                            {--candidate-index=0 : Eligible offer index to select}
                            {--payload-style= : Override revalidation payload style}
                            {--endpoint-path= : Override revalidation endpoint path}
                            {--passenger-json= : Absolute path to private passenger JSON (required for plan draft digest and --send)}
                            {--plan : Plan/read-only mode (default when --send omitted)}
                            {--send : Execute exactly one live revalidation HTTP call}
                            {--confirm-production= : Production: APPROVE-LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE}
                            {--confirm-revalidation= : Send: LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE}
                            {--output= : Optional absolute path for probe artifact JSON}';

    protected $description = '[operator] Sabre GDS live revalidation-only probe — search, linkage, one revalidation call, stop';

    public function handle(SabreGdsLiveRevalidationOnlyProbe $probe): int
    {
        if ((bool) config('suppliers.sabre.ticketing_enabled', false)) {
            $this->components->error('Probe blocked: suppliers.sabre.ticketing_enabled must remain false.');

            return self::FAILURE;
        }

        $sendRequested = $this->option('send') === true;
        if ($sendRequested) {
            $gateError = $this->validateSendGates();
            if ($gateError !== null) {
                $this->components->error($gateError);

                return self::FAILURE;
            }
        } else {
            $productionError = $this->validateProductionGateForLiveSearch();
            if ($productionError !== null) {
                $this->components->error($productionError);

                return self::FAILURE;
            }
        }

        $departureDate = trim((string) ($this->option('departure-date') ?? ''));
        if ($departureDate === '') {
            $this->components->error('--departure-date=YYYY-MM-DD is required.');

            return self::FAILURE;
        }

        $connectionId = (int) $this->option('connection');
        $connection = SupplierConnection::query()
            ->where('id', $connectionId)
            ->where('provider', SupplierProvider::Sabre->value)
            ->first();
        if ($connection === null) {
            $this->components->error('Sabre supplier connection not found for --connection='.$connectionId);

            return self::FAILURE;
        }

        if (! $connection->is_active) {
            $this->components->error('Sabre supplier connection is not active.');

            return self::FAILURE;
        }

        $presetOpt = $this->option('preset');
        $preset = is_string($presetOpt) && trim($presetOpt) !== '' ? strtolower(trim($presetOpt)) : null;
        if ($preset !== null && ! in_array($preset, SabreGdsLiveScenarioPresetResolver::PRESET_KEYS, true)) {
            $this->components->error('Unknown --preset value.');

            return self::FAILURE;
        }

        $lock = Cache::lock('sabre_gds_live_revalidation_only_probe_'.$connectionId, 300);
        if (! $lock->get()) {
            $this->components->error('Duplicate protection lock active for connection '.$connectionId.'.');

            return self::FAILURE;
        }

        try {
            $summary = $probe->run([
                'connection_id' => $connectionId,
                'origin' => strtoupper(trim((string) $this->option('origin'))),
                'destination' => strtoupper(trim((string) $this->option('destination'))),
                'departure_date' => $departureDate,
                'preset' => $preset,
                'candidate_index' => max(0, (int) $this->option('candidate-index')),
                'payload_style' => trim((string) ($this->option('payload-style') ?? '')),
                'endpoint_path' => trim((string) ($this->option('endpoint-path') ?? '')),
                'passenger_json' => trim((string) ($this->option('passenger-json') ?? '')),
                'send' => $sendRequested,
                'output' => trim((string) ($this->option('output') ?? '')),
            ]);

            $this->printSummary($summary, $sendRequested);

            if (($summary['db_mutation_detected'] ?? false) !== false) {
                return self::FAILURE;
            }

            if (isset($summary['error'])) {
                if ($sendRequested) {
                    return self::FAILURE;
                }
                if (in_array($summary['error'], [
                    'passenger_json_required',
                    'passenger_json_invalid',
                    'output_safety_check_failed',
                ], true)) {
                    return self::FAILURE;
                }
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    protected function validateSendGates(): ?string
    {
        $productionError = $this->validateProductionGateForLiveSearch();
        if ($productionError !== null) {
            return $productionError;
        }

        if (trim((string) $this->option('confirm-revalidation')) !== SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE) {
            return '--confirm-revalidation='.SabreGdsLiveRevalidationOnlyProbe::CONFIRM_REVALIDATION_PHRASE.' is required for --send.';
        }

        if (! (bool) config('suppliers.sabre.booking_enabled', false)
            || ! (bool) config('suppliers.sabre.booking_live_call_enabled', false)) {
            return 'Live revalidation requires suppliers.sabre.booking_enabled and booking_live_call_enabled.';
        }

        return null;
    }

    protected function validateProductionGateForLiveSearch(): ?string
    {
        if (SabreInspectGate::allowed()) {
            return null;
        }

        $env = (string) config('app.env', 'production');
        if ($env !== 'production') {
            return null;
        }

        $approval = trim((string) $this->option('confirm-production'));
        if ($approval === SabreGdsLiveRevalidationOnlyProbe::CONFIRM_PRODUCTION_PHRASE) {
            return null;
        }

        if ($approval === '') {
            return 'Production probe requires --confirm-production='.SabreGdsLiveRevalidationOnlyProbe::CONFIRM_PRODUCTION_PHRASE;
        }

        return 'Invalid --confirm-production phrase for production revalidation-only probe.';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function printSummary(array $summary, bool $sendRequested): void
    {
        $this->line('run_id='.($summary['run_id'] ?? ''));
        $this->line('mode='.($summary['mode'] ?? 'revalidation-only'));
        $this->line('probe_mode='.($summary['probe_mode'] ?? ($sendRequested ? 'send' : 'plan')));
        $this->line('ticketing_attempted=false');
        $this->line('airticket_attempted=false');
        $this->line('pnr_attempted=false');
        $this->line('booking_planned=false');
        $this->line('pnr_planned=false');
        $this->line('cancellation_planned=false');
        $this->line('ticketing_planned=false');
        $this->line('output_json_path='.($summary['output_json_path'] ?? ''));

        foreach ([
            'search_correlation_id',
            'revalidation_correlation_id',
            'scenario',
            'preset',
            'route',
            'departure_date',
            'eligible_offer_count',
            'selected_candidate_index',
            'carrier',
            'segment_count',
            'selected_total',
            'selected_currency',
            'selected_offer_fingerprint',
            'selected_segment_signature_hash',
            'selected_source_identifier_hash',
            'payload_style',
            'endpoint_path',
            'payload_schema_valid',
            'payload_schema_reason_code',
            'root_version_present',
            'root_version_type_valid',
            'root_child_keys',
            'root_target_present',
            'requestor_id_present',
            'requestor_id_type_valid',
            'requestor_id_non_empty',
            'requestor_identity_source_present',
            'pseudo_city_code_present',
            'pseudo_city_code_type_valid',
            'pseudo_city_code_non_empty',
            'pseudo_city_code_source_present',
            'contains_invalid_direct_flight_segment',
            'airline_marketing_type_valid',
            'airline_operating_type_valid',
            'contains_unsupported_segment_number',
            'contains_unsupported_resbookdesigcode',
            'contains_unsupported_fare_basis_code',
            'contains_unsupported_cabin_code',
            'contains_unsupported_single_branded_fare',
            'unsupported_branded_fare_indicator_keys',
            'branded_fare_indicator_child_keys',
            'branded_fare_context_present',
            'booking_class_context_present',
            'cabin_context_present',
            'pricing_context_present',
            'fare_component_references_present',
            'fare_basis_context_present',
            'unsupported_flight_child_keys',
            'revalidation_reason_code',
            'revalidation_failure_category',
            'revalidation_http_status',
            'block_reason',
            'safe_error_code',
            'supplier_revalidation_call_count',
            'error',
            'db_mutation_detected',
        ] as $key) {
            if (! array_key_exists($key, $summary)) {
                continue;
            }
            $value = $summary[$key];
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif (is_array($value)) {
                $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
            } elseif ($value !== null) {
                $this->line($key.'='.(string) $value);
            }
        }

        if (array_key_exists('revalidation_linkage_ready', $summary)) {
            $this->line('revalidation_linkage_ready='.(($summary['revalidation_linkage_ready'] ?? false) ? 'true' : 'false'));
        }
        if (array_key_exists('supplier_call_planned', $summary)) {
            $this->line('supplier_call_planned='.(($summary['supplier_call_planned'] ?? false) ? 'true' : 'false'));
        }
        foreach ([
            'revalidation_attempted',
            'revalidation_success',
            'supplier_call_attempted',
            'supplier_response_received',
            'freshness_satisfied',
            'fare_changed',
            'retry_safe',
        ] as $boolKey) {
            if (! array_key_exists($boolKey, $summary)) {
                continue;
            }
            $this->line($boolKey.'='.(($summary[$boolKey] ?? false) ? 'true' : 'false'));
        }
        if (isset($summary['revalidation_linkage_missing_components']) && is_array($summary['revalidation_linkage_missing_components'])) {
            $this->line('revalidation_linkage_missing_components='.json_encode(array_values($summary['revalidation_linkage_missing_components']), JSON_UNESCAPED_SLASHES));
        }
        if (isset($summary['ambiguous_outcome_stopped']) && ($summary['ambiguous_outcome_stopped'] ?? false) === true) {
            $this->line('ambiguous_outcome_stopped=true');
        }
    }
}
