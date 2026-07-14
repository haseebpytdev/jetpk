<?php

namespace App\Console\Commands;

use App\Http\Controllers\Frontend\FlightController;
use App\Models\Agency;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\TravelData\AirlineBrandingService;
use App\Support\Booking\AgentBookingContext;
use App\Support\FlightSearch\AirlineDisplayNameResolver;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use App\Support\FlightSearch\PublicFlightSearchSecurity;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class FlightsPublicOfferDetailsAuditCommand extends Command
{
    protected $signature = 'flights:public-offer-details-audit
        {--from=LHE : Origin}
        {--to=DXB : Destination}
        {--date= : Departure YYYY-MM-DD}
        {--provider=iati : Provider filter (iati|all)}
        {--adults=1}
        {--children=0}
        {--infants=0}
        {--cabin=economy}
        {--trip_type=one_way}';

    protected $description = 'Audit public results offer detail coverage for a provider';

    public function handle(
        FlightSearchService $flightSearch,
        FlightSearchResultStore $searchStore,
        AirlineBrandingService $airlineBranding,
    ): int {
        $date = (string) ($this->option('date') ?: now()->addMonth()->format('Y-m-d'));
        $providerFilter = strtolower(trim((string) $this->option('provider')));
        $tripType = (string) $this->option('trip_type');

        // Mirror PublicFlightSearchRequest::criteria() keys used by /flights/results.
        $criteria = [
            'origin' => strtoupper((string) $this->option('from')),
            'destination' => strtoupper((string) $this->option('to')),
            'depart_date' => $date,
            'return_date' => $tripType === 'round_trip' ? (string) ($this->option('return_date') ?: '') : null,
            'adults' => max(1, (int) $this->option('adults')),
            'children' => max(0, (int) $this->option('children')),
            'infants' => max(0, (int) $this->option('infants')),
            'cabin' => strtolower((string) $this->option('cabin')),
            'trip_type' => $tripType,
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

        $allOffers = is_array($result['offers'] ?? null) ? $result['offers'] : [];
        $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];
        $storedOffers = $this->applyPublicResultsProviderGate($allOffers);
        if ($allOffers !== [] && $storedOffers === []) {
            $warnings[] = 'Flight provider fares are currently unavailable. Please try again shortly.';
        }

        // Same persistence path as FlightController::runSearch → /flights/results/data.
        $searchId = $searchStore->store($criteria, $storedOffers, $warnings);
        $payload = $searchStore->get($searchId);
        if ($payload === null) {
            $this->error('Search store write failed for search_id='.$searchId);

            return self::FAILURE;
        }

        /** @var list<array<string, mixed>> $offers */
        $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];

        $providerCounts = [];
        $filteredOffers = [];
        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $provider = strtolower((string) ($offer['supplier_provider'] ?? 'unknown'));
            $providerCounts[$provider] = ($providerCounts[$provider] ?? 0) + 1;
            if ($providerFilter === 'all' || $provider === $providerFilter) {
                $filteredOffers[] = $offer;
            }
        }

        $filteredOffers = ItineraryFareConsolidator::consolidate($filteredOffers);

        $groupedParent = null;
        foreach ($filteredOffers as $rawOffer) {
            if (is_array($rawOffer) && ItineraryFareConsolidator::isConsolidatedParent($rawOffer)) {
                $groupedParent = $rawOffer;
                break;
            }
        }

        if (is_array($groupedParent)) {
            $group = is_array($groupedParent['itinerary_fare_group'] ?? null) ? $groupedParent['itinerary_fare_group'] : [];
            $groupOpts = is_array($groupedParent['fare_family_options'] ?? null) ? $groupedParent['fare_family_options'] : [];
            $this->line('grouped_offer_count='.(int) ($group['grouped_offer_count'] ?? count($groupOpts)));
            $this->line('grouped_parent_offer_id='.(string) ($group['parent_offer_id'] ?? $groupedParent['offer_id'] ?? ''));
            $this->line('grouped_provider='.(string) ($group['grouped_provider'] ?? $groupedParent['supplier_provider'] ?? ''));
            $this->line('grouped_signature_hash='.(string) ($group['signature_hash'] ?? ''));
            $this->line('grouped_option_count='.count($groupOpts));
            foreach ([0, 1] as $idx) {
                $opt = is_array($groupOpts[$idx] ?? null) ? $groupOpts[$idx] : [];
                $this->line('grouped_option_'.$idx.'_source_offer_id='.(string) ($opt['source_offer_id'] ?? ''));
                $this->line('grouped_option_'.$idx.'_price='.(string) ($opt['price_total'] ?? ''));
                $this->line('grouped_option_'.$idx.'_checked_baggage='.(string) ($opt['check_in_summary'] ?? ''));
            }
        } else {
            $this->line('grouped_offer_count=0');
            $this->line('grouped_parent_offer_id=');
            $this->line('grouped_provider=');
            $this->line('grouped_signature_hash=');
            $this->line('grouped_option_count=0');
            $this->line('grouped_option_0_source_offer_id=');
            $this->line('grouped_option_0_price=');
            $this->line('grouped_option_0_checked_baggage=');
            $this->line('grouped_option_1_source_offer_id=');
            $this->line('grouped_option_1_price=');
            $this->line('grouped_option_1_checked_baggage=');
        }

        $apiRequest = Request::create('/flights/results/data', 'GET', ['search_id' => $searchId]);
        $airlineNameMap = AirlineDisplayNameResolver::mapForCodes(
            AirlineDisplayNameResolver::collectCodesFromOffers($filteredOffers)
        );
        $airlineLogos = $airlineBranding->mapLogosForOffers($filteredOffers);
        $iataCodes = [];
        foreach ($filteredOffers as $offRow) {
            $iataCodes = array_merge($iataCodes, FlightOfferDisplayPresenter::collectIataCodes($offRow));
        }
        $cityMap = FlightOfferDisplayPresenter::airportCityMap($iataCodes);

        $controller = app(FlightController::class);
        $mapMethod = new \ReflectionMethod($controller, 'mapOfferForResultsApi');
        $mapMethod->setAccessible(true);

        $mappedOffers = [];
        $sabreUiMismatchCount = 0;
        $sabreUiMismatchSamples = [];
        foreach ($filteredOffers as $rawOffer) {
            $mapped = $mapMethod->invokeArgs($controller, [
                $rawOffer,
                $payload,
                $searchId,
                $apiRequest,
                $airlineLogos,
                $cityMap,
                $airlineNameMap,
                &$sabreUiMismatchCount,
                &$sabreUiMismatchSamples,
            ]);
            $mappedOffers[] = PublicFlightSearchSecurity::sanitizeResultsOffer($mapped, false);
        }

        $withBranded = 0;
        $withFallback = 0;
        $missingCounts = [
            'baggage' => 0,
            'fare_basis' => 0,
            'booking_class' => 0,
            'segments' => 0,
            'fare_breakdown' => 0,
        ];

        foreach ($mappedOffers as $offer) {
            if (! empty($offer['has_branded_fares'])) {
                $withBranded++;
            }
            if (! empty($offer['has_fallback_details'])) {
                $withFallback++;
            }
            if (empty($offer['baggage_summary_display']) && empty($offer['baggage_checked_display']) && empty($offer['baggage'])) {
                $missingCounts['baggage']++;
            }
            if (empty($offer['fare_basis'])) {
                $missingCounts['fare_basis']++;
            }
            if (empty($offer['booking_class']) && empty(data_get($offer, 'fallback_details.fare_rules.booking_class'))) {
                $missingCounts['booking_class']++;
            }
            if (empty($offer['segments'])) {
                $missingCounts['segments']++;
            }
            if ((float) ($offer['supplier_total'] ?? 0) <= 0) {
                $missingCounts['fare_breakdown']++;
            }
        }

        $first = $mappedOffers[0] ?? null;
        $sections = is_array($first['fallback_detail_sections_present'] ?? null)
            ? $first['fallback_detail_sections_present']
            : [];

        $this->line('search_id='.$searchId);
        $this->line('public_total='.count($offers));
        $this->line('provider_counts='.json_encode($providerCounts));
        $this->line('filtered_offer_count='.count($mappedOffers));
        $this->line('offers_with_branded_options='.$withBranded);
        $this->line('offers_with_fallback_details='.$withFallback);
        $this->line('missing_required_detail_counts='.json_encode($missingCounts));
        $this->line('first_offer_id='.(string) ($first['offer_id'] ?? ''));
        $this->line('first_has_branded_options='.(empty($first['has_branded_fares']) ? '0' : '1'));
        $this->line('first_branded_selection_active='.(empty($first['branded_fares_selection_active']) ? '0' : '1'));
        $this->line('first_detail_sections_present='.json_encode($sections));
        if (is_array($first)) {
            $this->line('first_supplier_total='.(string) ($first['supplier_total'] ?? ''));
            $this->line('first_markup='.(string) ($first['markup'] ?? ''));
            $this->line('first_service_fee='.(string) ($first['service_fee'] ?? ''));
            $this->line('first_displayed_price='.(string) ($first['displayed_price'] ?? ''));
            $this->line('first_final_customer_price='.(string) ($first['final_customer_price'] ?? ''));
            $fareOpts = is_array($first['fare_family_options_display'] ?? null) ? $first['fare_family_options_display'] : [];
            $this->line('first_fare_option_count='.count($fareOpts));
            foreach (array_slice($fareOpts, 0, 3) as $idx => $opt) {
                if (! is_array($opt)) {
                    continue;
                }
                $this->line('fare_option_'.$idx.'_id='.(string) ($opt['option_key'] ?? ''));
                $this->line('fare_option_'.$idx.'_price='.(string) ($opt['displayed_price'] ?? $opt['price_total'] ?? ''));
                $this->line('fare_option_'.$idx.'_cabin_baggage='.(string) ($opt['carry_on_summary'] ?? ''));
                $this->line('fare_option_'.$idx.'_checked_baggage='.(string) ($opt['check_in_summary'] ?? ''));
                $this->line('fare_option_'.$idx.'_cabin_baggage_source='.(string) ($opt['cabin_baggage_source'] ?? ''));
                $this->line('fare_option_'.$idx.'_checked_baggage_source='.(string) ($opt['checked_baggage_source'] ?? ''));
            }
            $fallback = is_array($first['fallback_details'] ?? null) ? $first['fallback_details'] : [];
            $this->line('fallback_sections='.json_encode(array_keys($fallback)));
        }

        $nonBranded = null;
        foreach ($mappedOffers as $offer) {
            if (! empty($offer['has_branded_fares'])) {
                continue;
            }
            if (! empty($offer['has_synthetic_default_fare']) || ! empty($offer['has_fare_choice_options'])) {
                $nonBranded = $offer;
                break;
            }
            $opts = is_array($offer['fare_family_options_display'] ?? null) ? $offer['fare_family_options_display'] : [];
            if ($opts !== []) {
                $nonBranded = $offer;
                break;
            }
        }

        if (is_array($nonBranded)) {
            $nbOpts = is_array($nonBranded['fare_family_options_display'] ?? null) ? $nonBranded['fare_family_options_display'] : [];
            $nbOpt0 = is_array($nbOpts[0] ?? null) ? $nbOpts[0] : [];
            $this->line('non_branded_offer_id='.(string) ($nonBranded['offer_id'] ?? ''));
            $this->line('non_branded_has_fare_choice_options='.(empty($nonBranded['has_fare_choice_options']) ? '0' : '1'));
            $this->line('non_branded_has_synthetic_default_fare='.(empty($nonBranded['has_synthetic_default_fare']) ? '0' : '1'));
            $this->line('non_branded_universal_fare_selection_active='.(empty($nonBranded['universal_fare_selection_active']) ? '0' : '1'));
            $this->line('non_branded_fare_option_count='.count($nbOpts));
            $this->line('non_branded_fare_option_0_key='.(string) ($nbOpt0['option_key'] ?? ''));
            $this->line('non_branded_fare_option_0_name='.(string) ($nbOpt0['name'] ?? ''));
            $this->line('non_branded_fare_option_0_price='.(string) ($nbOpt0['displayed_price'] ?? $nbOpt0['price_total'] ?? ''));
            $this->line('non_branded_fare_option_0_baggage_summary='.(string) ($nbOpt0['baggage_summary'] ?? ''));
            $this->line('non_branded_fare_option_0_carry_on_summary='.(string) ($nbOpt0['carry_on_summary'] ?? ''));
            $this->line('non_branded_fare_option_0_check_in_summary='.(string) ($nbOpt0['check_in_summary'] ?? ''));
            $this->line('non_branded_fare_option_0_is_synthetic_default='.(! empty($nbOpt0['is_synthetic_default']) ? '1' : '0'));
        } else {
            $this->line('non_branded_offer_id=');
            $this->line('non_branded_has_fare_choice_options=0');
            $this->line('non_branded_has_synthetic_default_fare=0');
            $this->line('non_branded_universal_fare_selection_active=0');
            $this->line('non_branded_fare_option_count=0');
        }

        if (count($offers) === 0) {
            $this->warn('public_total=0 — no offers stored after public provider gate.');

            return self::FAILURE;
        }

        if ($providerFilter !== 'all' && count($mappedOffers) === 0) {
            $this->warn('No offers matched provider filter: '.$providerFilter);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Same supplier allow-list as FlightController::runSearch (public /flights/results).
     *
     * @param  list<array<string, mixed>>  $allOffers
     * @return list<array<string, mixed>>
     */
    protected function applyPublicResultsProviderGate(array $allOffers): array
    {
        $restrictPublicSuppliers = ! app()->environment('testing');
        if (! $restrictPublicSuppliers) {
            return $allOffers;
        }

        /** @var list<string> $allowedList */
        $allowedList = config('ota.public_flight_results_suppliers', ['duffel', 'sabre']);
        $allowed = array_values(array_filter(array_map(
            static fn (mixed $v): string => strtolower(trim((string) $v)),
            is_array($allowedList) ? $allowedList : ['duffel', 'sabre']
        )));

        return array_values(array_filter(
            $allOffers,
            static function (array $offer) use ($allowed): bool {
                $provider = strtolower((string) ($offer['supplier_provider'] ?? ''));

                return $provider !== '' && in_array($provider, $allowed, true);
            }
        ));
    }
}
