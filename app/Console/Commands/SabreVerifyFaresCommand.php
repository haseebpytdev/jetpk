<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Pricing\PricingRuleService;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\FlightSearch\SabreFareVerificationDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SabreVerifyFaresCommand extends Command
{
    protected $signature = 'sabre:verify-fares
                            {--from=LHE : Origin IATA}
                            {--to=DXB : Destination IATA}
                            {--date=2026-05-30 : Departure date YYYY-MM-DD}
                            {--adults=1 : Adult count}
                            {--cabin=economy : Cabin class}
                            {--connection= : Supplier connection ID (Sabre); defaults to first Sabre connection}
                            {--carrier= : Filter to itineraries touching this IATA marketing/operating or validating carrier}
                            {--limit=80 : Max rows in the verification table}';

    protected $description = '[local/testing only] Tabular Sabre fare path: raw shop total → normalized → priced customer total (no secrets, no raw payload)';

    public function handle(
        SabreFlightSearchRequestBuilder $builder,
        SabreClient $client,
        SabreFlightSearchNormalizer $normalizer,
        PricingRuleService $pricingRuleService,
    ): int {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $connectionId = $this->option('connection');
        $query = SupplierConnection::query()->where('provider', SupplierProvider::Sabre);
        if ($connectionId !== null && $connectionId !== '') {
            $query->whereKey((int) $connectionId);
        }
        $connection = $query->orderBy('id')->first();
        if ($connection === null) {
            $this->components->error('No Sabre supplier connection found. Create one in API settings or pass --connection=');

            return self::FAILURE;
        }

        $request = FlightSearchRequestData::fromArray([
            'origin' => strtoupper(trim((string) $this->option('from'))),
            'destination' => strtoupper(trim((string) $this->option('to'))),
            'depart_date' => (string) $this->option('date'),
            'adults' => max(1, (int) $this->option('adults')),
            'children' => 0,
            'infants' => 0,
            'cabin' => (string) $this->option('cabin'),
            'trip_type' => 'one_way',
            'currency' => 'PKR',
        ]);

        $payload = $builder->build($request, $connection);
        try {
            $response = $client->postShopPayload($connection, $payload);
        } catch (\Throwable) {
            $this->components->error('Shop request failed (details omitted).');

            return self::FAILURE;
        }

        $json = $response->json();
        if (! is_array($json)) {
            $this->components->error('Response was not JSON; verification skipped.');

            return self::FAILURE;
        }

        $pack = $normalizer->inspectRawItineraryDigests($json, $connection, $request);
        $rows = is_array($pack['itineraries'] ?? null) ? $pack['itineraries'] : [];

        $carrierFilter = strtoupper(trim((string) ($this->option('carrier') ?? '')));
        if ($carrierFilter !== '') {
            $rows = array_values(array_filter($rows, fn (array $r): bool => $this->rowTouchesCarrier($r, $carrierFilter)));
        } else {
            $rows = array_values(array_filter($rows, fn (array $r): bool => ($r['normalizer_status'] ?? '') === 'accepted'));
        }

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
        $origin = strtoupper(trim((string) $this->option('from')));
        $destination = strtoupper(trim((string) $this->option('to')));
        $departDate = (string) $this->option('date');
        $pricingContext = [
            'route' => $origin.'-'.$destination,
            'origin' => $origin,
            'destination' => $destination,
            'supplier' => SupplierProvider::Sabre->value,
            'travel_date' => $departDate,
            'source_channel' => 'sabre_verify_fares',
        ];
        foreach ($rows as $i => $r) {
            if (! is_array($r)) {
                continue;
            }
            $rows[$i] = SabreFareVerificationDigest::enrichInspectRowWithPricing($r, $agency, $pricingRuleService, $pricingContext);
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $rows = array_slice($rows, 0, $limit);

        Log::info('sabre.verify_fares', [
            'component' => 'sabre_verify_fares',
            'connection_id' => $connection->id,
            'row_count' => count($rows),
            'carrier_filter' => $carrierFilter !== '' ? $carrierFilter : null,
        ]);

        $table = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $short = SabreFareVerificationDigest::shortOfferId((string) ($r['normalized_offer_id'] ?? ''));
            $table[] = [
                'short_offer_id' => $short,
                'carriers' => (string) ($r['carrier_chain'] ?? ''),
                'flights' => (string) ($r['flight_numbers'] ?? ''),
                'route' => (string) ($r['route_chain'] ?? ''),
                'raw' => $this->formatMoney((float) ($r['total_fare'] ?? 0), (string) ($r['fare_currency'] ?? '')),
                'normalized' => $this->formatMoney((float) ($r['normalized_total'] ?? 0), (string) ($r['normalized_currency'] ?? '')),
                'final_customer' => $this->formatMoney((float) ($r['final_customer_price'] ?? 0), (string) ($r['pricing_currency'] ?? 'PKR')),
                'expected_ui' => (string) ($r['display_price_candidate'] ?? ''),
                'status' => (string) ($r['fare_verification_status'] ?? ''),
            ];
        }

        $this->table(
            ['short_offer_id', 'carriers', 'flights', 'route', 'raw fare', 'normalized', 'final customer', 'expected UI', 'verification'],
            array_map(fn (array $t): array => array_values($t), $table)
        );

        return self::SUCCESS;
    }

    protected function formatMoney(float $amount, string $currency): string
    {
        $c = strtoupper(trim($currency));

        return $c !== '' ? (string) round($amount, 0).' '.$c : (string) round($amount, 0);
    }

    protected function rowTouchesCarrier(array $row, string $carrier): bool
    {
        $c = strtoupper(trim($carrier));
        if ($c === '') {
            return true;
        }
        foreach ($row['raw_marketing_carriers'] ?? [] as $x) {
            if (strtoupper(trim((string) $x)) === $c) {
                return true;
            }
        }
        foreach ($row['raw_operating_carriers'] ?? [] as $x) {
            if (strtoupper(trim((string) $x)) === $c) {
                return true;
            }
        }
        if (strtoupper(trim((string) ($row['validating_carrier'] ?? ''))) === $c) {
            return true;
        }

        return false;
    }
}
