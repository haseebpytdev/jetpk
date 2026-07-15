<?php

namespace App\Console\Commands;

use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Iati\IatiFareRevalidationService;
use App\Support\Booking\AgentBookingContext;
use Illuminate\Console\Command;

class IatiRevalidationAuditCommand extends Command
{
    protected $signature = 'iati:revalidation-audit
        {--search-id= : Cached public search id}
        {--offer-id= : Cached offer id}
        {--connection= : Supplier connection ID for direct search mode}
        {--from=LHE : Origin for direct search mode}
        {--to=DXB : Destination for direct search mode}
        {--date= : Departure YYYY-MM-DD for direct search mode}
        {--adults=1}
        {--children=0}
        {--infants=0}
        {--pick= : Pick offer (first)}
        {--selected-fare-option-id= : Branded fare option id}';

    protected $description = 'Audit IATI fare confirmation (/fare) for a cached or live-search offer (no booking/ticket/payment)';

    public function handle(
        FlightSearchResultStore $searchStore,
        FlightSearchService $flightSearch,
        IatiFareRevalidationService $revalidationService,
    ): int {
        $searchId = trim((string) $this->option('search-id'));
        $offerId = trim((string) $this->option('offer-id'));
        $selectedFareOptionId = trim((string) $this->option('selected-fare-option-id')) ?: null;

        if ($searchId !== '' && $offerId !== '') {
            return $this->auditCachedOffer($searchStore, $revalidationService, $searchId, $offerId, $selectedFareOptionId);
        }

        return $this->auditDirectSearch($flightSearch, $revalidationService, $selectedFareOptionId);
    }

    protected function auditCachedOffer(
        FlightSearchResultStore $searchStore,
        IatiFareRevalidationService $revalidationService,
        string $searchId,
        string $offerId,
        ?string $selectedFareOptionId,
    ): int {
        $payload = $searchStore->get($searchId);
        if ($payload === null) {
            $this->error('Search not found or expired: '.$searchId);

            return self::FAILURE;
        }

        $offer = $searchStore->findOffer($searchId, $offerId);
        if ($offer === null) {
            $this->error('Offer not found in search cache: '.$offerId);

            return self::FAILURE;
        }

        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'iati') !== 0) {
            $this->error('Offer provider is not iati.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnectionForOffer($offer);
        if ($connection === null) {
            $this->error('No active IATI connection for cached offer.');

            return self::FAILURE;
        }

        $normalized = NormalizedFlightOfferData::fromArray($offer);
        $linkage = $revalidationService->auditLinkageFromOffer($normalized, $selectedFareOptionId);
        $validation = $revalidationService->revalidate($normalized, $connection, $selectedFareOptionId);
        $report = $revalidationService->buildPublicRevalidationReport($validation, $offer, $selectedFareOptionId);

        $this->printAuditLines($searchId, array_merge($linkage, $report));

        return in_array((string) ($report['revalidation_status'] ?? ''), ['valid', 'changed'], true)
            ? self::SUCCESS
            : self::FAILURE;
    }

    protected function auditDirectSearch(
        FlightSearchService $flightSearch,
        IatiFareRevalidationService $revalidationService,
        ?string $selectedFareOptionId,
    ): int {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No IATI SupplierConnection found.');

            return self::FAILURE;
        }

        $date = (string) ($this->option('date') ?: now()->addMonth()->format('Y-m-d'));
        $criteria = [
            'origin' => strtoupper((string) $this->option('from')),
            'destination' => strtoupper((string) $this->option('to')),
            'depart_date' => $date,
            'adults' => max(1, (int) $this->option('adults')),
            'children' => max(0, (int) $this->option('children')),
            'infants' => max(0, (int) $this->option('infants')),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
        ];

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
        if ($agency === null) {
            $this->error('Default agency not found.');

            return self::FAILURE;
        }

        $result = $flightSearch->searchWithMeta(
            $criteria,
            $agency,
            AgentBookingContext::SOURCE_CHANNEL_PUBLIC_GUEST,
        );

        $offers = collect(is_array($result['offers'] ?? null) ? $result['offers'] : [])
            ->filter(fn (array $row): bool => strcasecmp((string) ($row['supplier_provider'] ?? ''), 'iati') === 0)
            ->values();

        if ($offers->isEmpty()) {
            $this->error('No IATI offers returned from public search path.');

            return self::FAILURE;
        }

        $pick = strtolower(trim((string) $this->option('pick')));
        $offerRow = $pick === 'first' ? $offers->first() : $offers->first();
        if (! is_array($offerRow)) {
            $this->error('Could not select an IATI offer.');

            return self::FAILURE;
        }

        $normalized = NormalizedFlightOfferData::fromArray($offerRow);
        $linkage = $revalidationService->auditLinkageFromOffer($normalized, $selectedFareOptionId);
        $validation = $revalidationService->revalidate($normalized, $connection, $selectedFareOptionId);
        $report = $revalidationService->buildPublicRevalidationReport($validation, $offerRow, $selectedFareOptionId);

        $this->printAuditLines('direct_search', array_merge($linkage, $report));

        return in_array((string) ($report['revalidation_status'] ?? ''), ['valid', 'changed'], true)
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function printAuditLines(string $searchId, array $report): void
    {
        $this->line('search_id='.$searchId);
        $this->line('offer_id='.(string) ($report['offer_id'] ?? $report['original_offer_id'] ?? ''));
        $this->line('provider='.(string) ($report['provider'] ?? 'iati'));
        $this->line('selected_fare_option_id='.(string) ($report['selected_fare_option_id'] ?? ''));
        $this->line('selected_fare_option_matched='.(array_key_exists('selected_fare_option_matched', $report)
            ? (($report['selected_fare_option_matched'] ?? false) ? 'yes' : 'no')
            : ''));
        $this->line('selected_fare_option_price='.(string) ($report['selected_fare_option_price'] ?? ''));
        $this->line('selected_fare_option_original_total='.(string) ($report['selected_fare_option_original_total'] ?? ''));
        $this->line('selected_fare_option_key_field='.(string) ($report['selected_fare_option_key_field'] ?? ''));
        $this->line('selected_fare_option_fare_key_present='.(array_key_exists('selected_fare_option_fare_key_present', $report)
            ? (($report['selected_fare_option_fare_key_present'] ?? false) ? 'yes' : 'no')
            : ''));
        $this->line('has_fare_key='.(($report['has_fare_key'] ?? false) ? 'yes' : 'no'));
        $this->line('fare_key_present='.(($report['fare_key_present'] ?? false) ? 'yes' : 'no'));
        $this->line('submitted_departure_fare_key_present='.(($report['submitted_departure_fare_key_present'] ?? false) ? 'yes' : 'no'));
        $this->line('submitted_departure_fare_key_suffix='.(string) ($report['submitted_departure_fare_key_suffix'] ?? ''));
        $this->line('returned_offer_count='.(string) ($report['returned_offer_count'] ?? ''));
        $this->line('returned_offer_total_values='.json_encode($report['returned_offer_total_values'] ?? []));
        $this->line('returned_offer_key_match_count='.(string) ($report['returned_offer_key_match_count'] ?? ''));
        $this->line('original_total_match_count='.(string) ($report['original_total_match_count'] ?? ''));
        $this->line('matched_reason='.(string) ($report['matched_reason'] ?? ''));
        $this->line('matched_offer_index='.(string) ($report['matched_offer_index'] ?? ''));
        $this->line('matched_confirmed_total_source_path='.(string) ($report['matched_confirmed_total_source_path'] ?? ''));
        $this->line('revalidation_endpoint='.(string) ($report['revalidation_endpoint'] ?? IatiFareRevalidationService::REVALIDATION_ENDPOINT));
        $this->line('revalidation_http_status='.(string) ($report['revalidation_http_status'] ?? 'n/a'));
        $this->line('revalidation_status='.(string) ($report['revalidation_status'] ?? 'failed'));
        $this->line('original_total='.(string) ($report['original_total'] ?? ''));
        $this->line('confirmed_total='.($report['confirmed_total'] === null ? '' : (string) $report['confirmed_total']));
        $this->line('confirmed_total_source_path='.(string) ($report['confirmed_total_source_path'] ?? ''));
        $this->line('confirmed_total_raw_value='.(string) ($report['confirmed_total_raw_value'] ?? ''));
        $this->line('price_changed='.(($report['price_changed'] ?? false) ? 'yes' : 'no'));
        $this->line('baggage_confirmed='.(($report['baggage_confirmed'] ?? false) ? 'yes' : 'no'));
        $this->line('booking_class_confirmed='.(($report['booking_class_confirmed'] ?? false) ? 'yes' : 'no'));
        $this->line('fare_rules_confirmed='.(($report['fare_rules_confirmed'] ?? false) ? 'yes' : 'no'));
        $this->line('supplier_mutation_attempted=false');
        $this->line('booking_created=false');
        $this->line('ticketing_attempted=false');
        $this->line('cancellation_attempted=false');
        $this->line('emails_sent=false');
        $this->line('safe_customer_message='.(string) ($report['safe_customer_message'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function resolveConnectionForOffer(array $offer): ?SupplierConnection
    {
        $connectionId = $offer['supplier_connection_id'] ?? null;
        if ($connectionId !== null) {
            $connection = SupplierConnection::query()
                ->where('id', (int) $connectionId)
                ->where('provider', SupplierProvider::Iati)
                ->first();
            if ($connection !== null) {
                return $connection;
            }
        }

        return $this->resolveConnection();
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()
                ->where('id', (int) $id)
                ->where('provider', SupplierProvider::Iati)
                ->first();
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Iati)
            ->orderByDesc('is_active')
            ->first();
    }
}
