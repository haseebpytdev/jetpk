<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Support\Suppliers\SabreNdcEntitlementEvidenceStore;
use Carbon\Carbon;

/**
 * Controlled Sabre NDC market/date matrix — /v5/offers/shop only, one cell at a time.
 */
final class SabreNdcSearchMarketMatrixService
{
    public const CONFIRM_PHRASE = 'SEND-SABRE-NDC-SEARCH-MATRIX';

    public function __construct(
        private readonly SabreNdcSearchDryRunService $dryRunService,
        private readonly SabreNdcEntitlementEvidenceStore $evidenceStore,
    ) {}

    /**
     * @param  list<string>  $routes
     * @param  list<string>  $carriers
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, mixed>
     * }
     */
    public function runMatrix(
        SupplierConnection $connection,
        array $routes,
        string $startDate,
        int $days,
        int $adults,
        string $variant,
        bool $sendLive,
        array $carriers = [],
        string $carrierMode = 'marketing',
    ): array {
        $rows = [];
        $start = Carbon::parse($startDate)->startOfDay();
        $sleepMs = max(0, (int) config('suppliers.sabre.ndc.search_market_matrix_sleep_ms', 1500));
        $carrierRuns = $carriers !== [] ? $carriers : [null];

        foreach ($routes as $route) {
            [$origin, $destination] = $this->parseRoute($route);
            if ($origin === '' || $destination === '') {
                continue;
            }

            for ($offset = 0; $offset < max(1, $days); $offset++) {
                $date = $start->copy()->addDays($offset)->toDateString();

                foreach ($carrierRuns as $carrier) {
                    $buildOptions = [
                        'carrier_code' => $carrier,
                        'carrier_mode' => $carrierMode,
                    ];
                    $request = new FlightSearchRequestData(
                        origin: $origin,
                        destination: $destination,
                        departure_date: $date,
                        adults: max(1, $adults),
                        search_id: 'matrix-'.$origin.'-'.$destination.'-'.$date.($carrier ? '-'.$carrier : ''),
                    );

                    $result = $this->dryRunService->run($connection, $request, $sendLive, $variant, $buildOptions);
                    $rows[] = $this->compactRow($origin, $destination, $date, $carrier, $result);

                    if ($sendLive && $sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                }
            }
        }

        $summary = [
            'connection_id' => $connection->id,
            'routes' => count($routes),
            'days' => max(1, $days),
            'cells' => count($rows),
            'variant' => $variant,
            'live_supplier_call_attempted' => $sendLive,
            'mutation_attempted' => false,
            'gds_called' => false,
            'endpoint_path' => config('suppliers.sabre.ndc.offer_shop_path', '/v5/offers/shop'),
            'sleep_ms_between_calls' => $sendLive ? $sleepMs : 0,
            'carrier_mode' => $carriers !== [] ? $carrierMode : null,
            'carriers_tested' => $carriers,
        ];

        if ($sendLive && $rows !== []) {
            $this->evidenceStore->storeMatrix((int) $connection->id, $summary, $rows);
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $carrier = trim((string) ($row['carrier'] ?? ''));
                if ($carrier !== '') {
                    $this->evidenceStore->storeCarrierProbe(
                        (int) $connection->id,
                        (string) ($row['route'] ?? ''),
                        $carrier,
                        $row,
                    );
                }
            }
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function compactRow(
        string $origin,
        string $destination,
        string $date,
        ?string $carrier,
        array $result,
    ): array {
        return [
            'route' => $origin.'-'.$destination,
            'date' => $date,
            'carrier' => $carrier,
            'http_status' => $result['http_status'] ?? null,
            'response_shape' => $result['response_shape'] ?? null,
            'offer_count_raw' => $result['offer_count_raw'] ?? ($result['offer_count'] ?? 0),
            'normalized_offer_count' => $result['normalized_offer_count'] ?? 0,
            'no_offer_reason' => $result['no_offer_reason'] ?? null,
            'message_code' => $result['message_code'] ?? null,
            'message_text' => $result['message_text'] ?? null,
            'message_count' => $result['message_count'] ?? null,
            'transaction_id' => $result['sabre_transaction_id'] ?? null,
            'safe_error_code' => $result['safe_error_code'] ?? null,
            'safe_error_message' => $result['safe_error_message'] ?? null,
            'itinerary_group_count' => $result['itinerary_group_count'] ?? null,
            'itinerary_count' => $result['itinerary_count'] ?? null,
            'schedule_desc_count' => $result['schedule_desc_count'] ?? null,
            'leg_desc_count' => $result['leg_desc_count'] ?? null,
            'pricing_information_count' => $result['pricing_information_count'] ?? null,
            'selected_variant' => $result['selected_variant'] ?? null,
            'carrier_filter_applied' => $result['carrier_filter_applied'] ?? false,
            'unsupported_carrier_filter' => $result['unsupported_carrier_filter'] ?? false,
            'diagnostic_data_source_variant' => $result['diagnostic_data_source_variant'] ?? false,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseRoute(string $route): array
    {
        $parts = array_map(
            static fn (string $v): string => strtoupper(trim($v)),
            explode('-', trim($route), 2),
        );

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }
}
