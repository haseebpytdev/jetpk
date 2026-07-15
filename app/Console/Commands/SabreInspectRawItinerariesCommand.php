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

class SabreInspectRawItinerariesCommand extends Command
{
    protected $signature = 'sabre:inspect-raw-itineraries
                            {--from=LHE : Origin IATA}
                            {--to=DXB : Destination IATA}
                            {--date=2026-05-30 : Departure date YYYY-MM-DD}
                            {--adults=1 : Adult count}
                            {--cabin=economy : Cabin class}
                            {--connection= : Supplier connection ID (Sabre); defaults to first Sabre connection}
                            {--carrier= : Filter to itineraries touching this IATA marketing/operating or validating carrier}
                            {--limit=50 : Max itineraries to print in detail}
                            {--show-rejected : When no --carrier filter: include rejected itineraries (default: all shown)}
                            {--summary-only : Print summary block only}';

    protected $description = '[local/testing only] Safe Sabre shop digest: raw itinerary carriers/routes vs normalizer accept/reject (no raw payload, no secrets)';

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
        $shopPath = (string) config('suppliers.sabre.shop_path', '/v4/offers/shop');
        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
        $endpointHost = is_string($host) && $host !== '' ? $host : 'unknown';

        try {
            $response = $client->postShopPayload($connection, $payload);
        } catch (\Throwable) {
            $this->components->error('Shop request failed (details omitted).');

            return self::FAILURE;
        }

        $httpStatus = $response->status();
        $json = $response->json();

        $this->line('endpoint_host='.$endpointHost);
        $this->line('endpoint_path='.$shopPath);
        $this->line('http_status='.$httpStatus);

        if (! is_array($json)) {
            $this->components->error('Response was not JSON; digest skipped.');

            return self::FAILURE;
        }

        $pack = $normalizer->inspectRawItineraryDigests($json, $connection, $request);
        $summary = $pack['summary'] ?? [];
        $rows = is_array($pack['itineraries'] ?? null) ? $pack['itineraries'] : [];

        $carrierFilter = strtoupper(trim((string) ($this->option('carrier') ?? '')));
        if ($carrierFilter !== '') {
            $rows = array_values(array_filter($rows, fn (array $r): bool => $this->rowTouchesCarrier($r, $carrierFilter)));
        }
        if ($carrierFilter === '' && ! $this->option('show-rejected')) {
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
            'source_channel' => 'sabre_inspect_raw_itineraries',
        ];
        foreach ($rows as $i => $r) {
            if (! is_array($r)) {
                continue;
            }
            $rows[$i] = SabreFareVerificationDigest::enrichInspectRowWithPricing($r, $agency, $pricingRuleService, $pricingContext);
        }

        $pkRaw = 0;
        $pkAccepted = 0;
        $pkRejected = 0;
        foreach ($pack['itineraries'] ?? [] as $r) {
            if (! is_array($r)) {
                continue;
            }
            if (! $this->rowTouchesCarrier($r, 'PK')) {
                continue;
            }
            $pkRaw++;
            if (($r['normalizer_status'] ?? '') === 'accepted') {
                $pkAccepted++;
            } elseif (($r['normalizer_status'] ?? '') === 'rejected') {
                $pkRejected++;
            }
        }

        $summary['pk_raw_itinerary_count'] = $pkRaw;
        $summary['pk_accepted_count'] = $pkAccepted;
        $summary['pk_rejected_count'] = $pkRejected;

        Log::info('sabre.raw_itinerary_digest', [
            'component' => 'sabre_inspect_raw_itineraries',
            'connection_id' => $connection->id,
            'http_status' => $httpStatus,
            'itinerary_count' => $summary['itinerary_count'] ?? 0,
            'normalized_accepted_count' => $summary['normalized_accepted_count'] ?? 0,
            'normalized_rejected_count' => $summary['normalized_rejected_count'] ?? 0,
            'pk_raw_itinerary_count' => $pkRaw,
            'pk_accepted_count' => $pkAccepted,
            'pk_rejected_count' => $pkRejected,
            'carrier_filter' => $carrierFilter !== '' ? $carrierFilter : null,
        ]);

        $this->newLine();
        $this->line('summary=');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($this->option('summary-only')) {
            return self::SUCCESS;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $printed = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($printed >= $limit) {
                break;
            }
            $this->newLine();
            $rowOut = $row;
            $rowOut['correlation_confidence'] = ($row['normalizer_status'] ?? '') === 'accepted' ? 'high' : 'n/a';
            $this->line('itinerary_digest=');
            $this->line(json_encode($rowOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $printed++;
        }

        if ($printed < count($rows)) {
            $this->newLine();
            $this->comment('Omitted '.(count($rows) - $printed).' rows over --limit='.$limit);
        }

        return self::SUCCESS;
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
