<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioPresetResolver;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Operator-only Sabre GDS live scenario runner: search, book, retrieve, and optional unticketed cancel (no ticketing).
 */
class SabreGdsLiveScenarioRunnerCommand extends Command
{
    protected $signature = 'sabre:gds-live-scenario-runner
                            {--connection=2 : Sabre supplier connection ID}
                            {--origin=LHE : Origin IATA}
                            {--destination=DXB : Destination IATA}
                            {--departure-date= : Departure date YYYY-MM-DD}
                            {--return-date= : Return date YYYY-MM-DD (required for return trip)}
                            {--trip-type=one_way : one_way or return}
                            {--carrier= : PK|QR|GF|EY|ANY optional}
                            {--stops=ANY : 0|1|2|ANY}
                            {--fare-pick=lowest : lowest|highest|first|brand:<CODE>|all-brands}
                            {--max-bookings=1 : Maximum bookings to create in book modes}
                            {--passenger-json= : Absolute path to private passenger JSON (required for book modes)}
                            {--mode=plan : plan|book|book-and-retrieve|book-retrieve-and-cancel}
                            {--preset= : pk-direct|qr-connecting|gf-connecting|ey-connecting|two-stop|three-stop|four-stop|mixed-connecting|mixed-multistop|mixed-return|return-any|all-basic|multicity}
                            {--multicity-json= : Multicity preset: path or JSON string with slices[]}
                            {--min-stops= : Minimum stops filter for plan discovery}
                            {--max-stops= : Maximum stops filter for plan discovery}
                            {--same-carrier= : true|false same-carrier filter}
                            {--mixed-carrier= : true|false mixed-carrier filter}
                            {--min-segments= : Minimum segment count filter}
                            {--max-segments= : Maximum segment count filter}
                            {--carrier-chain= : Carrier chain substring filter e.g. QR+BA}
                            {--validating-carrier= : Validating carrier IATA filter}
                            {--plan-only-candidates=10 : Max plan-mode candidates per scenario}
                            {--strategy=auto : auto|iati_like_cpnr_v2_4_gds|traditional_pnr_create_passenger_name_record_v1|passenger_records_v2_5_gds}
                            {--confirm= : Required for live search/PNR: LIVE-SABRE-GDS-SCENARIO-RUNNER}
                            {--production-ops-approval= : Production only: APPROVE-LIVE-SABRE-GDS-SCENARIO-RUNNER}
                            {--mixed-carrier-certification-approval= : Mixed-carrier book modes: APPROVE-MIXED-CARRIER-GDS-PNR}
                            {--cancel-approval= : book-retrieve-and-cancel only: CANCEL-UNTICKETED-SABRE-GDS-TEST-PNRS}
                            {--include-mixed-carrier-results : Internal diagnostics only: include mixed-carrier plan/search candidates}';

    protected $description = '[operator] Sabre GDS live scenario runner — search, book, retrieve, optional unticketed cancel (no ticketing)';

    public function handle(SabreGdsLiveScenarioRunner $runner): int
    {
        $productionOpsApproved = $this->resolveProductionGate();
        if ($productionOpsApproved === null) {
            return self::FAILURE;
        }

        $confirm = trim((string) $this->option('confirm'));
        if ($confirm !== SabreGdsLiveScenarioRunner::CONFIRM_PHRASE) {
            $this->components->error('--confirm='.SabreGdsLiveScenarioRunner::CONFIRM_PHRASE.' is required for live Sabre GDS search and booking.');

            return self::FAILURE;
        }

        $mode = strtolower(trim((string) $this->option('mode')));
        if (! in_array($mode, ['plan', 'book', 'book-and-retrieve', 'book-retrieve-and-cancel'], true)) {
            $this->components->error('Invalid --mode; use plan, book, book-and-retrieve, or book-retrieve-and-cancel.');

            return self::FAILURE;
        }

        $departureDate = trim((string) ($this->option('departure-date') ?? ''));
        $presetRaw = $this->option('preset');
        $presetEarly = is_string($presetRaw) && trim($presetRaw) !== '' ? strtolower(trim($presetRaw)) : null;
        if ($departureDate === '' && $presetEarly !== 'multicity') {
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

        $preset = $this->option('preset');
        if (is_string($preset) && trim($preset) !== '') {
            $preset = strtolower(trim($preset));
            if (! in_array($preset, SabreGdsLiveScenarioPresetResolver::PRESET_KEYS, true)) {
                $this->components->error('Unknown --preset value.');

                return self::FAILURE;
            }
        } else {
            $preset = null;
        }

        $tripType = strtolower(trim((string) $this->option('trip-type')));
        $returnDate = trim((string) ($this->option('return-date') ?? ''));
        if ($preset === 'multicity') {
            $multicityJson = trim((string) ($this->option('multicity-json') ?? ''));
            if ($multicityJson === '') {
                $this->components->error('--multicity-json is required when --preset=multicity.');

                return self::FAILURE;
            }
            if ($mode !== 'plan') {
                $this->components->error('Multicity preset is plan-only in this phase; use --mode=plan.');

                return self::FAILURE;
            }
        } elseif (($tripType === 'return' || in_array($preset, ['return-any', 'mixed-return'], true)) && $returnDate === '') {
            $this->components->error('--return-date=YYYY-MM-DD is required for return trips.');

            return self::FAILURE;
        }

        $strategy = strtolower(trim((string) $this->option('strategy')));
        if ($strategy === '') {
            $strategy = 'auto';
        }
        $allowedStrategies = array_merge(['auto'], SabreGdsPnrCreateStrategyRegistry::SUPPORTED_STRATEGY_CODES);
        if (! in_array($strategy, $allowedStrategies, true)) {
            $this->components->error('Invalid --strategy; use auto or a supported Sabre GDS PNR create strategy code.');

            return self::FAILURE;
        }

        $lock = Cache::lock('sabre_gds_live_scenario_runner_'.$connectionId, 300);
        if (! $lock->get()) {
            $this->components->error('Duplicate protection lock active for connection '.$connectionId.'.');

            return self::FAILURE;
        }

        $mixedCertApproval = trim((string) ($this->option('mixed-carrier-certification-approval') ?? ''));
        $mixedCertApproved = $mixedCertApproval === SabreGdsLiveScenarioRunner::MIXED_CARRIER_CERTIFICATION_APPROVAL_PHRASE;
        if ($mode !== 'plan'
            && is_string($preset)
            && app(SabreGdsLiveScenarioPresetResolver::class)->isMixedCarrierPreset($preset)
            && ! $mixedCertApproved) {
            $this->components->error(
                'Mixed-carrier book modes require --mixed-carrier-certification-approval='
                .SabreGdsLiveScenarioRunner::MIXED_CARRIER_CERTIFICATION_APPROVAL_PHRASE
            );

            return self::FAILURE;
        }

        try {
            $carrierOpt = $this->option('carrier');
            $carrier = is_string($carrierOpt) && trim($carrierOpt) !== '' ? strtoupper(trim($carrierOpt)) : null;
            if ($carrier === 'ANY') {
                $carrier = null;
            }

            $summary = $runner->run([
                'connection_id' => $connectionId,
                'origin' => strtoupper(trim((string) $this->option('origin'))),
                'destination' => strtoupper(trim((string) $this->option('destination'))),
                'departure_date' => $departureDate !== '' ? $departureDate : '2026-01-01',
                'return_date' => $returnDate !== '' ? $returnDate : null,
                'trip_type' => $tripType,
                'carrier' => $carrier,
                'stops' => strtoupper(trim((string) ($this->option('stops') ?? 'ANY'))),
                'fare_pick' => trim((string) ($this->option('fare-pick'))),
                'max_bookings' => max(1, (int) $this->option('max-bookings')),
                'passenger_json' => trim((string) ($this->option('passenger-json') ?? '')),
                'mode' => $mode,
                'preset' => $preset,
                'multicity_json' => trim((string) ($this->option('multicity-json') ?? '')),
                'include_mixed_carrier_results' => $this->option('include-mixed-carrier-results') === true,
                'cancel_approval' => trim((string) ($this->option('cancel-approval') ?? '')),
                'operator_approved' => $confirm === SabreGdsLiveScenarioRunner::CONFIRM_PHRASE,
                'mixed_carrier_certification_approved' => $mixedCertApproved,
                'strategy' => $strategy,
                'min_stops' => $this->option('min-stops'),
                'max_stops' => $this->option('max-stops'),
                'same_carrier' => $this->option('same-carrier'),
                'mixed_carrier' => $this->option('mixed-carrier'),
                'min_segments' => $this->option('min-segments'),
                'max_segments' => $this->option('max-segments'),
                'carrier_chain' => trim((string) ($this->option('carrier-chain') ?? '')),
                'validating_carrier' => trim((string) ($this->option('validating-carrier') ?? '')),
                'plan_only_candidates' => max(1, (int) $this->option('plan-only-candidates')),
            ]);

            $this->printSummary($summary, $productionOpsApproved);

            if (isset($summary['error'])) {
                return self::FAILURE;
            }

            $hasFailure = false;
            foreach ((array) ($summary['scenario_results'] ?? []) as $result) {
                if (($result['booking_created'] ?? false) !== true && isset($result['error'])) {
                    $hasFailure = true;
                }
            }

            return $hasFailure && $mode !== 'plan' ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function printSummary(array $summary, bool $productionOpsApproved): void
    {
        if ($productionOpsApproved) {
            $this->line('production_ops_approved=true');
        }
        $this->line('run_id='.($summary['run_id'] ?? ''));
        $this->line('mode='.($summary['mode'] ?? ''));
        $this->line('ticketing_attempted=false');
        $this->line('airticket_attempted=false');
        $this->line('output_json_path='.($summary['output_json_path'] ?? ''));

        if (isset($summary['error'])) {
            $this->components->error('Scenario runner blocked: '.(string) $summary['error']);

            return;
        }

        foreach ((array) ($summary['scenario_results'] ?? []) as $index => $result) {
            $prefix = 'scenario['.$index.'].';
            $this->newLine();
            $this->line($prefix.'scenario='.($result['scenario'] ?? ''));
            if (isset($result['eligible_offer_count'])) {
                $this->line($prefix.'eligible_offer_count='.(int) $result['eligible_offer_count']);
            }
            if (isset($result['booking_id'])) {
                $this->line($prefix.'booking_id='.(int) $result['booking_id']);
            }
            if (array_key_exists('booking_reference', $result)) {
                $this->line($prefix.'booking_reference='.($result['booking_reference'] ?? '—'));
            }
            if (array_key_exists('pnr', $result)) {
                $this->line($prefix.'pnr='.($result['pnr'] ?? '—'));
            }
            if (array_key_exists('live_call_attempted', $result)) {
                $this->line($prefix.'live_call_attempted='.(($result['live_call_attempted'] ?? false) ? 'true' : 'false'));
            }
            if (array_key_exists('scenario_live_pnr_create_approved', $result)) {
                $this->line($prefix.'scenario_live_pnr_create_approved='.(($result['scenario_live_pnr_create_approved'] ?? false) ? 'true' : 'false'));
            }
            if (array_key_exists('selected_strategy', $result) && ($result['selected_strategy'] ?? null) !== null) {
                $this->line($prefix.'selected_strategy='.(string) $result['selected_strategy']);
            }
            if (array_key_exists('scenario_runner_override_applied', $result)) {
                $this->line($prefix.'scenario_runner_override_applied='.(($result['scenario_runner_override_applied'] ?? false) ? 'true' : 'false'));
            }
            if (array_key_exists('pnr_strategy_used', $result) && ($result['pnr_strategy_used'] ?? null) !== null) {
                $this->line($prefix.'pnr_strategy_used='.(string) $result['pnr_strategy_used']);
            }
            if (array_key_exists('pnr_attempted', $result)) {
                $this->line($prefix.'pnr_attempted='.(($result['pnr_attempted'] ?? false) ? 'true' : 'false'));
            }
            if (array_key_exists('auto_pnr_context_completion_status', $result)) {
                $this->line($prefix.'auto_pnr_context_completion_status='.($result['auto_pnr_context_completion_status'] ?? '—'));
            }
            if (array_key_exists('cancellation_attempted', $result)) {
                $this->line($prefix.'cancellation_attempted='.(($result['cancellation_attempted'] ?? false) ? 'true' : 'false'));
            }
            if (isset($result['multicity_plan_ready'])) {
                $this->line($prefix.'multicity_plan_ready='.(($result['multicity_plan_ready'] ?? false) ? 'true' : 'false'));
            }
            if (isset($result['multicity_search_executed'])) {
                $this->line($prefix.'multicity_search_executed='.(($result['multicity_search_executed'] ?? false) ? 'true' : 'false'));
            }
            if (isset($result['multicity_shop_request_supported'])) {
                $this->line($prefix.'multicity_shop_request_supported='.(($result['multicity_shop_request_supported'] ?? false) ? 'true' : 'false'));
            }
            if (isset($result['multicity_block_reason']) && ($result['multicity_block_reason'] ?? null) !== null) {
                $this->line($prefix.'multicity_block_reason='.(string) $result['multicity_block_reason']);
            }
            if (isset($result['customer_message']) && ($result['customer_message'] ?? '') !== '') {
                $this->line($prefix.'customer_message='.(string) $result['customer_message']);
            }
            if (isset($result['admin_debug_message']) && ($result['admin_debug_message'] ?? '') !== '') {
                $this->line($prefix.'admin_debug_message='.(string) $result['admin_debug_message']);
            }
            foreach ([
                'mixed_carrier_filter_enabled',
                'offers_before_mixed_filter',
                'offers_after_mixed_filter',
                'mixed_carrier_offers_filtered_count',
                'same_carrier_offers_remaining_count',
                'multicity_dedup_enabled',
                'multicity_dedup_key_version',
                'multicity_candidates_before_dedup',
                'multicity_candidates_after_dedup',
                'multicity_duplicate_candidates_removed_count',
            ] as $mixedCounterKey) {
                if (array_key_exists($mixedCounterKey, $result)) {
                    $mixedCounterValue = $result[$mixedCounterKey];
                    if (is_bool($mixedCounterValue)) {
                        $this->line($prefix.$mixedCounterKey.'='.($mixedCounterValue ? 'true' : 'false'));
                    } else {
                        $this->line($prefix.$mixedCounterKey.'='.(string) $mixedCounterValue);
                    }
                }
            }
            if (isset($result['block_reason']) && ($result['block_reason'] ?? null) !== null) {
                $this->line($prefix.'block_reason='.(string) $result['block_reason']);
            }
            if (isset($result['error'])) {
                $this->line($prefix.'error='.(string) $result['error']);
            }
            if (isset($result['safe_reason_code']) && (string) $result['safe_reason_code'] !== '') {
                $this->line($prefix.'safe_reason_code='.(string) $result['safe_reason_code']);
            }
            if ($summary['mode'] === 'plan' && isset($result['candidates']) && is_array($result['candidates'])) {
                $this->line($prefix.'candidate_count='.count($result['candidates']));
            }
        }
    }

    /**
     * @return bool|null true when production ops approved; false when non-production; null when blocked
     */
    protected function resolveProductionGate(): ?bool
    {
        if (SabreInspectGate::allowed()) {
            return false;
        }

        $env = (string) config('app.env', 'production');
        if ($env !== 'production') {
            return false;
        }

        $approval = trim((string) $this->option('production-ops-approval'));
        if ($approval === SabreGdsLiveScenarioRunner::PRODUCTION_OPS_APPROVAL_PHRASE) {
            return true;
        }

        if ($approval === '') {
            $this->components->error(
                'Production scenario runner requires --production-ops-approval='.SabreGdsLiveScenarioRunner::PRODUCTION_OPS_APPROVAL_PHRASE
            );
        } else {
            $this->components->error('Invalid --production-ops-approval phrase for production scenario runner.');
        }

        return null;
    }
}
