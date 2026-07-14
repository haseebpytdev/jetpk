<?php

namespace App\Console\Commands;

use App\Enums\SupplierConnectionStatus;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * JetPK flight search diagnostic — explains empty results for a route/date without exposing secrets.
 */
class OtaJetpkFlightSearchDiagnosticCommand extends Command
{
    protected $signature = 'ota:jetpk-flight-search-diagnostic
                            {--from=LHE : Origin IATA}
                            {--to=ISB : Destination IATA}
                            {--depart=2026-07-31 : Departure date Y-m-d}
                            {--trip=one_way : Trip type}
                            {--adults=1 : Adults}
                            {--children=0 : Children}
                            {--infants=0 : Infants}
                            {--cabin=economy : Cabin}
                            {--client=jetpk : Client slug (context label only — search uses master agency)}
                            {--execute : Run live supplier search (may call supplier APIs)}';

    protected $description = 'Diagnose JetPK public flight search — modules, connections, and optional live search summary';

    public function handle(
        FlightSearchService $flightSearchService,
        PlatformModuleEnforcer $moduleEnforcer,
    ): int {
        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('live_supplier_call_attempted='.($this->option('execute') ? 'true' : 'false'));
        $this->line('db_write_attempted=false');
        $this->newLine();

        $from = strtoupper(trim((string) $this->option('from')));
        $to = strtoupper(trim((string) $this->option('to')));
        $depart = trim((string) $this->option('depart'));
        $clientSlug = trim((string) $this->option('client'));
        $searchId = 'diag-'.Str::lower(Str::random(12));

        $this->line("Client context label: {$clientSlug}");
        $this->line("Search id (diagnostic): {$searchId}");
        $this->newLine();

        $agencySlug = (string) config('ota.default_agency_slug');
        $agency = Agency::query()->where('slug', $agencySlug)->first();

        $this->table(['check', 'value'], [
            ['default_agency_slug', $agencySlug],
            ['agency_found', $agency !== null ? 'yes' : 'no'],
            ['agency_id', $agency !== null ? (string) $agency->id : '—'],
            ['supplier_search_module', $moduleEnforcer->effectiveModuleEnabled('supplier_search') ? 'enabled' : 'disabled'],
            ['public_flight_search_module', $moduleEnforcer->effectiveModuleEnabled('public_flight_search') ? 'enabled' : 'disabled'],
            ['iati_supplier_module', $moduleEnforcer->effectiveModuleEnabled('iati_supplier') ? 'enabled' : 'disabled'],
            ['pia_ndc_supplier_module', $moduleEnforcer->effectiveModuleEnabled('pia_ndc_supplier') ? 'enabled' : 'disabled'],
            ['sabre_gds_module', $moduleEnforcer->effectiveModuleEnabled('sabre_gds') ? 'enabled' : 'disabled'],
        ]);

        if ($agency === null) {
            $this->error('Default agency not found — flight search cannot run.');

            return self::FAILURE;
        }

        $connections = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->orderBy('id')
            ->get();

        $connectionRows = $connections->map(function (SupplierConnection $connection): array {
            $skipReason = null;
            $eligible = $connection->is_active || $connection->status === SupplierConnectionStatus::Active;
            if (! $eligible) {
                $skipReason = 'inactive_connection';
            } elseif (! $connection->isEligibleForSupplierSearch()) {
                $skipReason = 'not_eligible_for_search';
            } elseif (! $connection->supplierHealthHealthy()) {
                $skipReason = 'supplier_health_unhealthy';
            }

            return [
                (string) $connection->id,
                $connection->provider->value,
                $connection->is_active ? 'yes' : 'no',
                $connection->status?->value ?? (string) $connection->status,
                $skipReason === null ? 'yes' : 'no',
                $skipReason ?? '—',
            ];
        })->all();

        $this->newLine();
        $this->line('Supplier connections (same DB for JetPK and Master):');
        $this->table(
            ['id', 'provider', 'is_active', 'status', 'search_eligible', 'skip_reason'],
            $connectionRows === [] ? [['—', '—', '—', '—', '—', 'no_active_connections']] : $connectionRows,
        );

        if (! $this->option('execute')) {
            $this->newLine();
            $this->warn('Live supplier search skipped. Re-run with --execute to call supplier APIs and report offer counts.');
            $this->line('Note: JetPK shares Master agency/supplier settings — empty results usually mean suppliers returned no offers or connections are inactive.');

            return self::SUCCESS;
        }

        $criteria = [
            'search_id' => $searchId,
            'origin' => $from,
            'destination' => $to,
            'from' => $from,
            'to' => $to,
            'depart_date' => $depart,
            'depart' => $depart,
            'trip_type' => (string) $this->option('trip'),
            'adults' => (int) $this->option('adults'),
            'children' => (int) $this->option('children'),
            'infants' => (int) $this->option('infants'),
            'cabin' => (string) $this->option('cabin'),
        ];

        $this->newLine();
        $this->line("Executing live search: {$from} → {$to} on {$depart} …");

        $result = $flightSearchService->searchWithMeta($criteria, $agency, 'public_guest');
        $offers = $result['offers'] ?? [];
        $warnings = $result['warnings'] ?? [];

        $byProvider = collect($offers)
            ->map(fn (array $offer): string => strtolower((string) ($offer['supplier_provider'] ?? 'unknown')))
            ->countBy()
            ->all();

        $this->table(['metric', 'value'], [
            ['normalized_offer_count', (string) count($offers)],
            ['warnings_count', (string) count($warnings)],
            ['offers_by_provider', $byProvider === [] ? '—' : json_encode($byProvider)],
        ]);

        if ($warnings !== []) {
            $this->newLine();
            $this->line('Warnings:');
            foreach ($warnings as $warning) {
                $this->line(' - '.(string) $warning);
            }
        }

        if (count($offers) === 0) {
            $this->newLine();
            $this->warn('No offers returned. Check storage/logs/laravel.log for flight_search.public_diagnostics entries with search_id='.$searchId);

            return self::FAILURE;
        }

        $this->info('Live search returned '.count($offers).' offer(s).');

        return self::SUCCESS;
    }
}
