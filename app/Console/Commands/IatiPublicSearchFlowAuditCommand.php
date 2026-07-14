<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Booking\AgentBookingContext;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Console\Command;

class IatiPublicSearchFlowAuditCommand extends Command
{
    protected $signature = 'iati:public-search-flow-audit
        {--connection=12 : Expected IATI SupplierConnection ID}
        {--from=LHE : Origin}
        {--to=DXB : Destination}
        {--date= : Departure YYYY-MM-DD}
        {--adults=1}
        {--children=0}
        {--infants=0}
        {--cabin=economy}
        {--trip_type=one_way}';

    protected $description = 'Simulate public FlightSearchService path and report IATI eligibility/merge diagnostics';

    public function handle(
        FlightSearchService $flightSearch,
        PlatformModuleEnforcer $moduleEnforcer,
    ): int {
        $expectedConnectionId = max(0, (int) $this->option('connection'));
        $date = (string) ($this->option('date') ?: now()->addMonth()->format('Y-m-d'));
        $criteria = [
            'origin' => strtoupper((string) $this->option('from')),
            'destination' => strtoupper((string) $this->option('to')),
            'depart_date' => $date,
            'adults' => (int) $this->option('adults'),
            'children' => (int) $this->option('children'),
            'infants' => (int) $this->option('infants'),
            'cabin' => (string) $this->option('cabin'),
            'trip_type' => (string) $this->option('trip_type'),
            'search_id' => 'audit-'.now()->format('YmdHis'),
        ];

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
        $channel = [
            'agency' => $agency,
            'source_channel' => AgentBookingContext::SOURCE_CHANNEL_PUBLIC_GUEST,
            'agent_id' => null,
            'agent' => null,
            'agent_booking_mode' => false,
        ];

        $this->line('criteria='.json_encode($criteria, JSON_UNESCAPED_SLASHES));
        $this->line('agency_id='.($agency?->id ?? 'null'));
        $this->line('agency_slug='.($agency?->slug ?? 'null'));
        $this->line('source_channel='.$channel['source_channel']);

        $connections = $agency === null
            ? collect()
            : SupplierConnection::query()
                ->where('agency_id', $agency->id)
                ->where(function ($query): void {
                    $query->where('is_active', true)
                        ->orWhere('status', 'active');
                })
                ->orderBy('id')
                ->get();

        $eligibleRows = $connections->map(function (SupplierConnection $connection) use ($moduleEnforcer): array {
            $moduleKey = $moduleEnforcer->providerModuleKey($connection->provider->value);
            $providerEnabled = $connection->provider->value === 'sabre'
                ? $moduleEnforcer->sabreSearchEnabled()
                : $moduleEnforcer->providerChannelEnabled($connection->provider->value);

            return [
                'id' => $connection->id,
                'provider' => $connection->provider->value,
                'is_active' => (bool) $connection->is_active,
                'status' => $connection->status?->value ?? (string) $connection->status,
                'eligible' => $providerEnabled,
                'module_key' => $moduleKey,
                'module_enabled' => $moduleKey === null ? true : $moduleEnforcer->effectiveModuleEnabled($moduleKey),
            ];
        })->values()->all();

        $this->line('eligible_connections='.json_encode($eligibleRows, JSON_UNESCAPED_SLASHES));

        $gateModules = [
            'supplier_search' => $moduleEnforcer->effectiveModuleEnabled('supplier_search'),
            'iati_supplier' => $moduleEnforcer->effectiveModuleEnabled('iati_supplier'),
            'sabre_gds' => $moduleEnforcer->effectiveModuleEnabled('sabre_gds'),
            'sabre_ndc' => $moduleEnforcer->effectiveModuleEnabled('sabre_ndc'),
            'duffel_supplier' => $moduleEnforcer->effectiveModuleEnabled('duffel_supplier'),
        ];
        $this->line('gate_allowed_modules='.json_encode($gateModules, JSON_UNESCAPED_SLASHES));

        $blockingReason = null;
        if ($agency === null) {
            $blockingReason = 'agency_not_found';
        } elseif ($connections->isEmpty()) {
            $blockingReason = 'no_active_connections';
        } elseif (! $gateModules['supplier_search']) {
            $blockingReason = 'supplier_search_module_disabled';
        } elseif ($expectedConnectionId > 0 && ! collect($eligibleRows)->contains(
            fn (array $row): bool => (int) ($row['id'] ?? 0) === $expectedConnectionId && ($row['eligible'] ?? false) === true
        )) {
            $blockingReason = 'expected_iati_connection_not_eligible';
        }

        $result = $flightSearch->searchWithMeta(
            $criteria,
            $agency,
            $channel['source_channel'],
            $channel['agent_id'],
        );
        $allOffers = is_array($result['offers'] ?? null) ? $result['offers'] : [];

        $restrictPublicSuppliers = ! app()->environment('testing');
        /** @var list<string> $allowedList */
        $allowedList = config('ota.public_flight_results_suppliers', ['duffel', 'sabre', 'iati']);
        $allowed = array_values(array_filter(array_map(
            static fn (mixed $v): string => strtolower(trim((string) $v)),
            is_array($allowedList) ? $allowedList : ['duffel', 'sabre', 'iati']
        )));
        $storedOffers = $restrictPublicSuppliers
            ? array_values(array_filter(
                $allOffers,
                static function (array $offer) use ($allowed): bool {
                    $p = strtolower((string) ($offer['supplier_provider'] ?? ''));

                    return $p !== '' && in_array($p, $allowed, true);
                }
            ))
            : $allOffers;

        $preByProvider = collect($allOffers)
            ->map(fn (array $offer): string => strtolower((string) ($offer['supplier_provider'] ?? 'unknown')))
            ->countBy()
            ->all();
        $postByProvider = collect($storedOffers)
            ->map(fn (array $offer): string => strtolower((string) ($offer['supplier_provider'] ?? 'unknown')))
            ->countBy()
            ->all();

        $iatiPre = (int) ($preByProvider['iati'] ?? 0);
        $iatiPost = (int) ($postByProvider['iati'] ?? 0);
        $iatiCalled = $iatiPre > 0 ? 'yes' : 'no';
        if ($iatiCalled === 'no' && $blockingReason === null) {
            $blockingReason = 'iati_adapter_not_called_or_returned_zero';
        }
        if ($iatiPre > 0 && $iatiPost === 0) {
            $blockingReason = 'public_results_supplier_gate_dropped_iati';
        }

        $firstIatiOffer = collect($storedOffers)->first(
            fn (array $offer): bool => strtolower((string) ($offer['supplier_provider'] ?? '')) === 'iati'
        );
        $firstIatiOfferId = is_array($firstIatiOffer)
            ? (string) ($firstIatiOffer['offer_id'] ?? $firstIatiOffer['id'] ?? '')
            : '';

        $this->line('supplier_calls_pre_gate='.json_encode($preByProvider, JSON_UNESCAPED_SLASHES));
        $this->line('iati_called='.$iatiCalled);
        $this->line('iati_connection_id='.$expectedConnectionId);
        $this->line('iati_raw_count='.$iatiPre);
        $this->line('iati_normalized_count='.$iatiPre);
        $this->line('final_offer_count='.count($storedOffers));
        $this->line('final_offer_count_by_provider='.json_encode($postByProvider, JSON_UNESCAPED_SLASHES));
        $this->line('first_iati_offer_id='.($firstIatiOfferId !== '' ? $firstIatiOfferId : 'null'));
        $this->line('allowed_public_suppliers='.json_encode($allowed, JSON_UNESCAPED_SLASHES));
        $this->line('blocking_reason='.($blockingReason ?? 'null'));

        return $blockingReason === null && count($storedOffers) > 0 ? self::SUCCESS : self::FAILURE;
    }
}
