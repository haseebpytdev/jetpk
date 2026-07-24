<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunner;
use Illuminate\Console\Command;

/**
 * Plan-only readiness gate for Sabre GDS revalidation → unticketed PNR lifecycle (no PNR create, no cancel).
 */
class SabreGdsRevalidationToPnrReadinessPlanCommand extends Command
{
    protected $signature = 'sabre:gds-revalidation-to-pnr-readiness-plan
                            {--connection=1 : Sabre supplier connection ID}
                            {--departure-date= : Departure date YYYY-MM-DD (required)}
                            {--fare-pick=brand : lowest|highest|first|brand:<CODE>|all-brands}
                            {--confirm= : Required: LIVE-SABRE-GDS-SCENARIO-RUNNER}
                            {--production-ops-approval= : Production only: APPROVE-LIVE-SABRE-GDS-SCENARIO-RUNNER}';

    protected $description = '[plan-only] QR LHE–DOH–JED revalidation-to-PNR readiness discovery (no PNR create, no cancel)';

    public function handle(): int
    {
        $departureDate = trim((string) ($this->option('departure-date') ?? ''));
        if ($departureDate === '') {
            $this->components->error('--departure-date=YYYY-MM-DD is required.');

            return self::FAILURE;
        }

        $confirm = trim((string) ($this->option('confirm') ?? ''));
        if ($confirm !== SabreGdsLiveScenarioRunner::CONFIRM_PHRASE) {
            $this->components->error('--confirm='.SabreGdsLiveScenarioRunner::CONFIRM_PHRASE.' is required for live shop discovery.');

            return self::FAILURE;
        }

        if ($this->resolveProductionGate() === null) {
            return self::FAILURE;
        }

        $this->line('mode=readiness_plan_only');
        $this->line('pnr_create_authorized=false');
        $this->line('cancellation_authorized=false');
        $this->line('ticketing_authorized=false');
        $this->line('revalidation_supplier_call_count=0');
        $this->line('pnr_supplier_call_count=0');
        $this->line('route=LHE-DOH-JED');
        $this->line('carrier=QR');
        $this->line('stops=1');
        $this->line('hint=use --mode=book with --passenger-json for one controlled unticketed PNR after plan passes');

        return $this->call('sabre:gds-live-scenario-runner', $this->buildScenarioRunnerDelegateOptions($confirm));
    }

    /**
     * @return array<string, string>
     */
    protected function buildScenarioRunnerDelegateOptions(string $confirm): array
    {
        $options = [
            '--connection' => (string) $this->option('connection'),
            '--origin' => 'LHE',
            '--destination' => 'JED',
            '--departure-date' => trim((string) ($this->option('departure-date') ?? '')),
            '--trip-type' => 'one_way',
            '--preset' => 'qr-connecting',
            '--carrier' => 'QR',
            '--stops' => '1',
            '--fare-pick' => (string) ($this->option('fare-pick') ?: 'brand'),
            '--mode' => 'plan',
            '--max-bookings' => '1',
            '--confirm' => $confirm,
        ];

        $approval = trim((string) $this->option('production-ops-approval'));
        if ($approval !== '') {
            $options['--production-ops-approval'] = $approval;
        }

        return $options;
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
                'Production readiness plan requires --production-ops-approval='.SabreGdsLiveScenarioRunner::PRODUCTION_OPS_APPROVAL_PHRASE
            );
        } else {
            $this->components->error('Invalid --production-ops-approval phrase for production readiness plan.');
        }

        return null;
    }
}
