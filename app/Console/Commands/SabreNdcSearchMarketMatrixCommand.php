<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferShopRequestBuilder;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcSearchMarketMatrixService;
use Illuminate\Console\Command;

class SabreNdcSearchMarketMatrixCommand extends Command
{
    protected $signature = 'sabre:ndc-search-market-matrix
                            {--connection= : Supplier connection ID}
                            {--routes=LHE-DXB : Comma-separated origin-destination pairs}
                            {--start-date= : First departure date YYYY-MM-DD}
                            {--days=7 : Number of consecutive days per route}
                            {--adults=1 : Adult count}
                            {--variant=ndc_v5_pos_pcc_source : Request variant}
                            {--carriers= : Optional comma-separated airline codes (one per call)}
                            {--carrier-mode=marketing : marketing|operating|validating}
                            {--send : Live NDC shop calls only (/v5/offers/shop)}
                            {--confirm= : Required for --send: SEND-SABRE-NDC-SEARCH-MATRIX}
                            {--json : Emit compact JSON only}';

    protected $description = 'Sabre NDC route/date market matrix via /v5/offers/shop only (no GDS/BFM/order/ticketing/cancel)';

    public function handle(SabreNdcSearchMarketMatrixService $matrixService): int
    {
        $send = (bool) $this->option('send');
        if ($send && trim((string) $this->option('confirm')) !== SabreNdcSearchMarketMatrixService::CONFIRM_PHRASE) {
            $this->components->error('--send requires --confirm='.SabreNdcSearchMarketMatrixService::CONFIRM_PHRASE);

            return self::FAILURE;
        }

        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->components->error('Sabre supplier connection not found.');

            return self::FAILURE;
        }

        $startDate = trim((string) $this->option('start-date'));
        if ($startDate === '') {
            $this->components->error('--start-date is required (YYYY-MM-DD).');

            return self::FAILURE;
        }

        $variant = trim((string) $this->option('variant'));
        if ($variant === '' || ! in_array($variant, SabreNdcOfferShopRequestBuilder::VALID_VARIANTS, true)) {
            $this->components->error('Invalid --variant. Allowed: '.implode(', ', SabreNdcOfferShopRequestBuilder::VALID_VARIANTS));

            return self::FAILURE;
        }

        $routes = array_values(array_filter(array_map(
            static fn (string $v): string => strtoupper(trim($v)),
            explode(',', (string) $this->option('routes')),
        )));

        $carriers = array_values(array_filter(array_map(
            static fn (string $v): string => strtoupper(trim($v)),
            explode(',', trim((string) $this->option('carriers'))),
        )));

        $result = $matrixService->runMatrix(
            connection: $connection,
            routes: $routes,
            startDate: $startDate,
            days: max(1, (int) $this->option('days')),
            adults: max(1, (int) $this->option('adults')),
            variant: $variant,
            sendLive: $send,
            carriers: $carriers,
            carrierMode: strtolower(trim((string) $this->option('carrier-mode'))),
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        foreach ([
            'connection_id', 'routes', 'days', 'cells', 'variant', 'endpoint_path',
            'live_supplier_call_attempted', 'gds_called', 'mutation_attempted', 'sleep_ms_between_calls',
            'carrier_mode', 'carriers_tested',
        ] as $key) {
            if (array_key_exists($key, $summary)) {
                $this->line($key.'='.$this->scalar($summary[$key]));
            }
        }

        $this->newLine();
        $this->line(str_pad('route', 10).' '.str_pad('date', 12).' '.str_pad('http', 5).' '
            .str_pad('raw', 5).' '.str_pad('norm', 5).' reason');

        foreach (is_array($result['rows'] ?? null) ? $result['rows'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $this->line(
                str_pad((string) ($row['route'] ?? ''), 10).' '
                .str_pad((string) ($row['date'] ?? ''), 12).' '
                .str_pad((string) ($row['http_status'] ?? ''), 5).' '
                .str_pad((string) ($row['offer_count_raw'] ?? ''), 5).' '
                .str_pad((string) ($row['normalized_offer_count'] ?? ''), 5).' '
                .(string) ($row['no_offer_reason'] ?? '')
            );

            $detail = array_filter([
                'carrier' => $row['carrier'] ?? null,
                'message_code' => $row['message_code'] ?? null,
                'message_text' => $this->readableMessageText($row['message_text'] ?? null),
                'transaction_id' => $row['transaction_id'] ?? null,
                'itinerary_group_count' => $row['itinerary_group_count'] ?? null,
                'itinerary_count' => $row['itinerary_count'] ?? null,
                'schedule_desc_count' => $row['schedule_desc_count'] ?? null,
                'leg_desc_count' => $row['leg_desc_count'] ?? null,
                'pricing_information_count' => $row['pricing_information_count'] ?? null,
                'message_count' => $row['message_count'] ?? null,
                'unsupported_carrier_filter' => ($row['unsupported_carrier_filter'] ?? false) ? 'true' : null,
            ], static fn ($v): bool => $v !== null && $v !== '' && $v !== false);

            if ($detail !== []) {
                $parts = [];
                foreach ($detail as $key => $value) {
                    $parts[] = $key.'='.$this->scalar($value);
                }
                $this->line('  '.implode(' ', $parts));
            }
        }

        return self::SUCCESS;
    }

    private function readableMessageText(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{3,8}$/', $text) === 1) {
            return null;
        }

        return $text;
    }

    private function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id !== null && is_numeric($id)) {
            return SupplierConnection::query()->find((int) $id);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->orderByDesc('is_active')->orderBy('id')->first();
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return implode(',', $value);
        }

        return (string) $value;
    }
}
