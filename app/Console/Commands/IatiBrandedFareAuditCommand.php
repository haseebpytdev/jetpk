<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiClient;
use App\Services\Suppliers\Iati\IatiPayloadBuilder;
use App\Services\Suppliers\Iati\IatiResponseNormalizer;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Console\Command;

class IatiBrandedFareAuditCommand extends Command
{
    protected $signature = 'iati:branded-fare-audit
        {--connection=12 : Supplier connection ID}
        {--from=LHE : Origin}
        {--to=DXB : Destination}
        {--date= : Departure YYYY-MM-DD}
        {--adults=1}
        {--children=0}
        {--infants=0}
        {--fixture= : Optional local fixture path relative to base_path()}';

    protected $description = 'Audit IATI raw vs normalized branded fare and fallback detail coverage';

    public function handle(
        IatiClient $client,
        IatiPayloadBuilder $payloadBuilder,
        IatiResponseNormalizer $normalizer,
    ): int {
        $fixture = trim((string) $this->option('fixture'));
        $connection = $fixture !== ''
            ? new SupplierConnection([
                'id' => max(1, (int) $this->option('connection')),
                'provider' => SupplierProvider::Iati,
                'environment' => SupplierEnvironment::Sandbox,
            ])
            : SupplierConnection::query()->find((int) $this->option('connection'));
        if ($connection === null) {
            $this->error('Supplier connection not found.');

            return self::FAILURE;
        }

        $date = (string) ($this->option('date') ?: now()->addMonth()->format('Y-m-d'));
        $criteria = [
            'origin' => strtoupper((string) $this->option('from')),
            'destination' => strtoupper((string) $this->option('to')),
            'depart_date' => $date,
            'adults' => (int) $this->option('adults'),
            'children' => (int) $this->option('children'),
            'infants' => (int) $this->option('infants'),
            'trip_type' => 'one_way',
        ];

        if ($fixture !== '') {
            $path = base_path($fixture);
            if (! is_file($path)) {
                $this->error('Fixture not found: '.$path);

                return self::FAILURE;
            }
            $response = json_decode((string) file_get_contents($path), true);
        } else {
            $request = FlightSearchRequestData::fromArray($criteria);
            $payload = $payloadBuilder->buildSearchPayload($request);
            $response = $client->post($connection, '/search', $payload, [
                'request_context' => 'iati:branded-fare-audit',
            ]);
        }

        $rawOffers = $this->extractRawOffers($response);
        $normalized = $normalizer->normalizeSearchResponse(
            is_array($response) ? $response : [],
            $connection,
            'iati-brand-audit',
            (int) $criteria['adults'],
            (int) $criteria['children'],
            (int) $criteria['infants'],
        );

        $withBrandData = 0;
        $withFareOptions = 0;
        $withBaggage = 0;
        $withFareBasis = 0;
        $withBookingClass = 0;
        $sampleBrandKeys = [];

        foreach ($rawOffers as $rawOffer) {
            if ($this->rawOfferHasBrandData($rawOffer)) {
                $withBrandData++;
            }
            foreach ($this->collectRawBrandKeys($rawOffer) as $key) {
                if (! in_array($key, $sampleBrandKeys, true) && count($sampleBrandKeys) < 12) {
                    $sampleBrandKeys[] = $key;
                }
            }
        }

        $sampleNormalizedKeys = [];
        foreach ($normalized as $offer) {
            $array = $offer->toArray();
            if (($array['branded_fares'] ?? []) !== []) {
                $withFareOptions++;
            }
            if (trim((string) data_get($array, 'baggage.summary', data_get($array, 'baggage.checked', ''))) !== '') {
                $withBaggage++;
            }
            if (trim((string) ($array['fare_basis'] ?? '')) !== '') {
                $withFareBasis++;
            }
            if (trim((string) ($array['booking_class'] ?? '')) !== '') {
                $withBookingClass++;
            }
            foreach (FlightOfferDisplayPresenter::fareFamilyOptionKeysSample($array, 4) as $key) {
                if (! in_array($key, $sampleNormalizedKeys, true) && count($sampleNormalizedKeys) < 12) {
                    $sampleNormalizedKeys[] = $key;
                }
            }
        }

        $first = $normalized[0] ?? null;
        $firstArray = $first?->toArray() ?? [];
        $firstPresentation = $firstArray !== []
            ? FlightOfferDisplayPresenter::buildPresentation($firstArray, $criteria, [])
            : [];

        $this->line('iati_raw_offer_count='.count($rawOffers));
        $this->line('normalized_offer_count='.count($normalized));
        $this->line('offers_with_brand_data='.$withBrandData);
        $this->line('offers_with_fare_options='.$withFareOptions);
        $this->line('offers_with_baggage='.$withBaggage);
        $this->line('offers_with_fare_basis='.$withFareBasis);
        $this->line('offers_with_booking_class='.$withBookingClass);
        $this->line('sample_raw_brand_keys='.json_encode($sampleBrandKeys));
        $this->line('sample_normalized_fare_option_keys='.json_encode($sampleNormalizedKeys));
        $this->line('first_offer_id='.(string) ($firstArray['offer_id'] ?? ''));
        $this->line('first_offer_has_branded_options='.(($firstPresentation['has_branded_fares'] ?? false) ? 'yes' : 'no'));
        $this->line('first_offer_has_fallback_details='.(($firstPresentation['has_fallback_details'] ?? false) ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    protected function extractRawOffers(array $response): array
    {
        $data = $response['result'] ?? $response;
        if (! is_array($data)) {
            return [];
        }

        return array_values(is_array($data['departure_flights'] ?? null) ? $data['departure_flights'] : []);
    }

    /**
     * @param  array<string, mixed>  $rawOffer
     */
    protected function rawOfferHasBrandData(array $rawOffer): bool
    {
        $fares = is_array($rawOffer['fares'] ?? null) ? $rawOffer['fares'] : [];
        foreach ($fares as $fare) {
            if (! is_array($fare)) {
                continue;
            }
            if (trim((string) data_get($fare, 'default_offer.brand_name', '')) !== '') {
                return true;
            }
            if (trim((string) data_get($fare, 'default_offer.brand_code', '')) !== '') {
                return true;
            }
        }

        return count($fares) >= 2;
    }

    /**
     * @param  array<string, mixed>  $rawOffer
     * @return list<string>
     */
    protected function collectRawBrandKeys(array $rawOffer): array
    {
        $keys = [];
        $fares = is_array($rawOffer['fares'] ?? null) ? $rawOffer['fares'] : [];
        foreach ($fares as $fare) {
            if (! is_array($fare)) {
                continue;
            }
            foreach (['default_offer.brand_name', 'default_offer.brand_code', 'fare_key'] as $path) {
                $value = trim((string) data_get($fare, $path, ''));
                if ($value !== '') {
                    $keys[] = $path.':'.$value;
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
