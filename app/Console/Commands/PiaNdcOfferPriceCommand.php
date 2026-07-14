<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcOfferPriceService;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlBuilder;
use Illuminate\Console\Command;

class PiaNdcOfferPriceCommand extends Command
{
    protected $signature = 'pia-ndc:offer-price
        {--connection= : Supplier connection ID}
        {--diagnostic-path= : Path to AirShopping diagnostic folder with normalized.json}
        {--offer-index=0 : Offer index in normalized.json}
        {--probe-shapes : Run OfferPrice request-shape compatibility probe}
        {--shape= : Run a single probe shape (e.g. current_priced_offer_selected_offer)}
        {--shopping-response-ref-id= : Direct ShoppingResponseRefID}
        {--offer-ref-id= : Direct raw Hitit OfferID}
        {--offer-item-ref-id= : Direct OfferItemRefID}
        {--pax-ref-id=ADTPax-1 : Direct PaxRefID}
        {--owner-code=PK : Direct OwnerCode}';

    protected $description = 'CLI OfferPrice / revalidation for PIA NDC Hitit Crane 20.1 (no PNR)';

    public function handle(PiaNdcOfferPriceService $offerPriceService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No PIA NDC SupplierConnection found.');

            return self::FAILURE;
        }

        try {
            [$providerContext, $sourcePath, $offerIndex, $publicOfferId, $airShoppingTotal] = $this->resolveInput();
        } catch (PiaNdcValidationException $exception) {
            $this->error($exception->safeMessage);

            return self::FAILURE;
        }

        $shape = trim((string) $this->option('shape'));
        $probeShapes = (bool) $this->option('probe-shapes');

        if ($probeShapes || $shape !== '') {
            $results = $offerPriceService->runOfferPriceShapeProbe(
                connection: $connection,
                providerContext: $providerContext,
                selectedOfferIndex: $offerIndex,
                selectedPublicOfferId: $publicOfferId,
                airShoppingSupplierTotal: $airShoppingTotal,
                sourceDiagnosticPath: $sourcePath,
                onlyShape: $shape !== '' ? $shape : null,
            );

            $anySuccess = false;
            foreach ($results as $result) {
                $this->printProbeSummaryLine($result['summary']);
                $anySuccess = $anySuccess || ($result['success'] ?? false);
            }

            return $anySuccess ? self::SUCCESS : self::FAILURE;
        }

        $result = $offerPriceService->runOfferPriceDiagnostic(
            connection: $connection,
            providerContext: $providerContext,
            selectedOfferIndex: $offerIndex,
            selectedPublicOfferId: $publicOfferId,
            airShoppingSupplierTotal: $airShoppingTotal,
            sourceDiagnosticPath: $sourcePath,
            shape: $shape !== '' ? $shape : PiaNdcXmlBuilder::OFFER_PRICE_SHAPE_CURRENT,
        );

        $summary = $result['summary'];
        $this->line('connection_id='.($summary['connection_id'] ?? ''));
        $this->line('correlation_id='.($summary['correlation_id'] ?? ''));
        $this->line('http_status='.($summary['http_status'] ?? ''));
        $this->line('success='.(($summary['success'] ?? false) ? 'true' : 'false'));
        $this->line('provider_error_code='.($summary['provider_error_code'] ?? ''));
        $this->line('provider_error_message='.($summary['provider_error_message'] ?? ''));
        $this->line('selected_offer_id='.($summary['selected_public_offer_id'] ?? ''));
        $this->line('shopping_response_ref_id='.($summary['shopping_response_ref_id'] ?? ''));
        $this->line('offer_item_ref_id='.($summary['offer_item_ref_id'] ?? ''));
        $this->line('total_amount='.($summary['offer_price_total'] ?? ''));
        $this->line('offer_price_total='.($summary['offer_price_total'] ?? ''));
        $this->line('air_shopping_total='.($summary['air_shopping_total'] ?? ''));
        $this->line('fee_amount_total='.($summary['fee_amount_total'] ?? ''));
        $this->line('currency='.($summary['currency'] ?? ''));
        $this->line('zero_price='.(($summary['zero_price'] ?? false) ? 'true' : 'false'));
        $this->line('fee_only_price='.(($summary['fee_only_price'] ?? false) ? 'true' : 'false'));
        $this->line('partial_price='.(($summary['partial_price'] ?? false) ? 'true' : 'false'));
        $this->line('commercially_valid_price='.(($summary['commercially_valid_price'] ?? false) ? 'true' : 'false'));
        $fareChanged = $summary['fare_changed'] ?? null;
        $this->line('fare_changed='.($fareChanged === null ? '' : ($fareChanged ? 'true' : 'false')));
        $this->line('diagnostic_path='.($summary['diagnostic_path'] ?? ''));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function printProbeSummaryLine(array $summary): void
    {
        $this->line('shape='.($summary['shape'] ?? ''));
        $this->line('http_status='.($summary['http_status'] ?? ''));
        $this->line('provider_error_code='.($summary['provider_error_code'] ?? ''));
        $this->line('priced_offer_count='.($summary['priced_offer_count'] ?? ''));
        $this->line('air_shopping_total='.($summary['air_shopping_total'] ?? ''));
        $this->line('offer_price_total='.($summary['offer_price_total'] ?? ''));
        $this->line('fee_amount_total='.($summary['fee_amount_total'] ?? ''));
        $this->line('fee_only_price='.(($summary['fee_only_price'] ?? false) ? 'true' : 'false'));
        $this->line('partial_price='.(($summary['partial_price'] ?? false) ? 'true' : 'false'));
        $this->line('zero_price='.(($summary['zero_price'] ?? false) ? 'true' : 'false'));
        $this->line('commercially_valid_price='.(($summary['commercially_valid_price'] ?? false) ? 'true' : 'false'));
        $this->line('success='.(($summary['success'] ?? false) ? 'true' : 'false'));
        $this->line('diagnostic_path='.($summary['diagnostic_path'] ?? ''));
        $this->line('');
    }

    /**
     * @return array{
     *     0: array<string, mixed>,
     *     1: ?string,
     *     2: int,
     *     3: ?string,
     *     4: ?float
     * }
     */
    private function resolveInput(): array
    {
        $diagnosticPath = trim((string) $this->option('diagnostic-path'));
        $offerIndex = max(0, (int) $this->option('offer-index'));

        if ($diagnosticPath !== '') {
            $normalizedPath = rtrim($diagnosticPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'normalized.json';
            if (! is_file($normalizedPath)) {
                throw new PiaNdcValidationException('missing_normalized_json', 422, 'normalized.json not found in diagnostic path.');
            }

            $payload = json_decode((string) file_get_contents($normalizedPath), true);
            if (! is_array($payload)) {
                throw new PiaNdcValidationException('invalid_normalized_json', 422, 'normalized.json is not valid JSON.');
            }

            $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];
            $offer = is_array($offers[$offerIndex] ?? null) ? $offers[$offerIndex] : null;
            if ($offer === null) {
                throw new PiaNdcValidationException('offer_index_out_of_range', 422, 'Offer index not found in normalized.json.');
            }

            $rawPayload = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
            $providerContext = is_array($rawPayload['provider_context'] ?? null) ? $rawPayload['provider_context'] : [];
            if ($providerContext === []) {
                throw new PiaNdcValidationException('missing_provider_context', 422, 'Selected offer has no provider_context.');
            }

            $fareBreakdown = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
            $supplierTotal = isset($fareBreakdown['supplier_total']) ? (float) $fareBreakdown['supplier_total'] : null;

            return [
                $this->mergeDirectOverrides($providerContext),
                $diagnosticPath,
                $offerIndex,
                isset($offer['offer_id']) ? (string) $offer['offer_id'] : null,
                $supplierTotal,
            ];
        }

        $directContext = array_filter([
            'shopping_response_ref_id' => trim((string) $this->option('shopping-response-ref-id')),
            'offer_ref_id' => trim((string) $this->option('offer-ref-id')),
            'offer_item_ref_id' => trim((string) $this->option('offer-item-ref-id')),
            'pax_ref_id' => trim((string) $this->option('pax-ref-id')),
            'owner_code' => trim((string) $this->option('owner-code')),
        ], fn ($value) => $value !== '');

        if (($directContext['shopping_response_ref_id'] ?? '') === '' || ($directContext['offer_ref_id'] ?? '') === '') {
            throw new PiaNdcValidationException(
                'missing_input',
                422,
                'Provide --diagnostic-path or direct --shopping-response-ref-id and --offer-ref-id.',
            );
        }

        if (($directContext['offer_item_ref_id'] ?? '') === '') {
            $directContext['offer_item_ref_id'] = 'OfferItem-1';
        }

        return [$directContext, null, $offerIndex, null, null];
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @return array<string, mixed>
     */
    private function mergeDirectOverrides(array $providerContext): array
    {
        $overrides = array_filter([
            'shopping_response_ref_id' => trim((string) $this->option('shopping-response-ref-id')),
            'offer_ref_id' => trim((string) $this->option('offer-ref-id')),
            'offer_item_ref_id' => trim((string) $this->option('offer-item-ref-id')),
            'pax_ref_id' => trim((string) $this->option('pax-ref-id')),
            'owner_code' => trim((string) $this->option('owner-code')),
        ], fn ($value) => $value !== '');

        return array_merge($providerContext, $overrides);
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::PiaNdc)->first();
        }

        return SupplierConnection::query()->where('provider', SupplierProvider::PiaNdc)->orderByDesc('is_active')->first();
    }
}
