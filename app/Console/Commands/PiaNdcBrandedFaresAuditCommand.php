<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcFlightSearchService;
use App\Support\Bookings\PiaNdcBrandedFareDedup;
use App\Support\Bookings\PiaNdcFareFamilyPolicy;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use Illuminate\Console\Command;

class PiaNdcBrandedFaresAuditCommand extends Command
{
    protected $signature = 'pia:ndc-branded-fares-audit
        {--connection= : Supplier connection ID}
        {--origin=KHI : Origin IATA}
        {--destination=ISB : Destination IATA}
        {--date= : Departure YYYY-MM-DD}
        {--return-date= : Return YYYY-MM-DD (enables return search)}
        {--return : Shortcut for return trip when --return-date omitted}
        {--adults=1}
        {--children=0}
        {--infants=0}
        {--fixture= : Optional normalized fixture JSON path relative to base_path()}';

    protected $description = 'Read-only audit: PIA NDC branded fare cards before/after dedup (no booking calls)';

    public function handle(PiaNdcFlightSearchService $searchService): int
    {
        $fixture = trim((string) $this->option('fixture'));
        $connection = $this->resolveConnection();
        if ($connection === null && $fixture === '') {
            $this->error('No PIA NDC SupplierConnection found.');

            return self::FAILURE;
        }

        $returnDate = trim((string) ($this->option('return-date') ?: ''));
        $isReturn = $this->option('return') || $returnDate !== '';
        $date = (string) ($this->option('date') ?: now()->addMonth()->format('Y-m-d'));
        if ($isReturn && $returnDate === '') {
            $returnDate = now()->addMonth()->addDays(5)->format('Y-m-d');
        }

        $criteria = [
            'origin' => strtoupper((string) $this->option('origin')),
            'destination' => strtoupper((string) $this->option('destination')),
            'depart_date' => $date,
            'adults' => (int) $this->option('adults'),
            'children' => (int) $this->option('children'),
            'infants' => (int) $this->option('infants'),
            'trip_type' => $isReturn ? 'return' : 'one_way',
        ];
        if ($isReturn) {
            $criteria['return_date'] = $returnDate;
        }

        if ($fixture !== '') {
            $path = base_path($fixture);
            if (! is_file($path)) {
                $this->error('Fixture not found: '.$path);

                return self::FAILURE;
            }
            $offers = json_decode((string) file_get_contents($path), true);
            if (! is_array($offers)) {
                $this->error('Fixture must be a JSON array of offer arrays.');

                return self::FAILURE;
            }
        } else {
            $request = FlightSearchRequestData::fromArray($criteria);
            $bundle = $searchService->runAirShoppingDiagnostic($request, $connection);
            $result = $bundle['result'];
            if ($result->offers === []) {
                $this->warn('No offers returned for route/date.');

                return self::SUCCESS;
            }
            $offers = array_map(static fn ($offer) => $offer->toArray(), $result->offers);
        }

        $displayOffers = ItineraryFareConsolidator::consolidate($offers);
        $totalBefore = 0;
        $totalAfter = 0;
        $totalDropped = 0;
        $duplicateGroups = [];
        $sameBrandGroups = [];

        foreach ($displayOffers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            if (strtolower(trim((string) ($offer['supplier_provider'] ?? ''))) !== SupplierProvider::PiaNdc->value) {
                continue;
            }

            $before = PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer, applyDedup: false);
            $dedup = PiaNdcBrandedFareDedup::dedupeOptions($before, $offer, [
                'search_type' => $criteria['trip_type'],
            ]);
            $after = $dedup['options'];
            $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([], $offer);

            $beforeCount = count($before);
            $afterCount = count($after);
            $totalBefore += $beforeCount;
            $totalAfter += $afterCount;
            $totalDropped += (int) ($dedup['stats']['dropped_duplicate_count'] ?? 0);

            if ($beforeCount < 2) {
                continue;
            }

            $this->newLine();
            $this->line('offer_id='.($offer['offer_id'] ?? $offer['id'] ?? ''));
            $this->line('  before_dedup='.$beforeCount.' after_dedup='.$afterCount.' dropped='.($dedup['stats']['dropped_duplicate_count'] ?? 0));
            $this->line('  presentation_cards='.count($presentation['fare_family_options_display'] ?? []));

            foreach ($after as $row) {
                $ctx = is_array($row['provider_context'] ?? null) ? $row['provider_context'] : [];
                $this->line('  kept: brand='.($row['name'] ?? '')
                    .' offer_ref='.($ctx['offer_ref_id'] ?? '')
                    .' item='.($ctx['offer_item_ref_id'] ?? '')
                    .' basis='.($row['fare_basis'] ?? '')
                    .' rbd='.($row['booking_class'] ?? '')
                    .' price='.($row['price_total'] ?? '')
                    .($row['fare_product_disambiguator'] ?? '' ? ' hint='.($row['fare_product_disambiguator']) : ''));
            }

            foreach ($dedup['stats']['duplicate_groups'] ?? [] as $group) {
                $duplicateGroups[] = $group;
            }
            foreach ($dedup['stats']['same_brand_different_product_groups'] ?? [] as $group) {
                $sameBrandGroups[] = $group;
            }
        }

        $this->newLine();
        $this->info('PIA NDC branded fare audit summary');
        $this->line('search_type='.$criteria['trip_type']);
        $this->line('route='.$criteria['origin'].'-'.$criteria['destination']);
        $this->line('total_cards_before_dedup='.$totalBefore);
        $this->line('total_cards_after_dedup='.$totalAfter);
        $this->line('dropped_duplicate_count='.$totalDropped);
        $this->line('duplicate_groups='.count($duplicateGroups));
        $this->line('same_brand_different_price_groups='.count($sameBrandGroups));

        if ($sameBrandGroups !== []) {
            $this->newLine();
            $this->line('Same-brand different-product groups (kept):');
            foreach (array_slice($sameBrandGroups, 0, 8) as $group) {
                $this->line('  '.$group['brand_name'].' x'.$group['count']);
            }
        }

        return self::SUCCESS;
    }

    private function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id !== null && $id !== '') {
            return SupplierConnection::query()
                ->where('id', (int) $id)
                ->where('provider', SupplierProvider::PiaNdc)
                ->first();
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::PiaNdc)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}
