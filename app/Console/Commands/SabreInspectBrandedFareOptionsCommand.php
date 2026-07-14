<?php

namespace App\Console\Commands;

use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Console\Command;

class SabreInspectBrandedFareOptionsCommand extends Command
{
    protected $signature = 'sabre:inspect-branded-fare-options
        {--search-id= : Cached search id (read-only)}
        {--offer-id= : Offer id within search cache}
        {--confirm= : Must be READONLY-BRANDED-FARE-OPTIONS}';

    protected $description = 'Read-only branded fare option audit from cached search (no live supplier call)';

    public function handle(FlightSearchResultStore $searchStore): int
    {
        if (trim((string) $this->option('confirm')) !== 'READONLY-BRANDED-FARE-OPTIONS') {
            $this->error('Pass --confirm=READONLY-BRANDED-FARE-OPTIONS');

            return self::FAILURE;
        }

        $searchId = trim((string) $this->option('search-id'));
        $offerId = trim((string) $this->option('offer-id'));
        if ($searchId === '' || $offerId === '') {
            $this->error('Provide --search-id and --offer-id');

            return self::FAILURE;
        }

        $payload = $searchStore->get($searchId);
        if ($payload === null) {
            $this->error('Search not found: '.$searchId);

            return self::FAILURE;
        }

        $offer = $searchStore->findOffer($searchId, $offerId);
        if ($offer === null) {
            $this->error('Offer not found: '.$offerId);

            return self::FAILURE;
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $audit = FlightOfferDisplayPresenter::auditBrandedFareOptionsVisibility($offer);
        $report = array_merge([
            'search_id' => $searchId,
            'route' => strtoupper((string) ($criteria['origin'] ?? '')).'-'.strtoupper((string) ($criteria['destination'] ?? '')),
            'depart_date' => (string) ($criteria['depart_date'] ?? ''),
            'supplier_mutation_attempted' => false,
        ], $audit);

        foreach ($report as $key => $value) {
            $this->line($key.'='.(is_array($value) ? json_encode($value) : (string) $value));
        }

        return self::SUCCESS;
    }
}
