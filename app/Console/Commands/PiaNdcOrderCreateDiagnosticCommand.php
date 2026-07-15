<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingService;
use Illuminate\Console\Command;

class PiaNdcOrderCreateDiagnosticCommand extends Command
{
    protected $signature = 'pia-ndc:order-create-diagnostic
        {--connection= : Supplier connection ID}
        {--diagnostic-path= : Path to AirShopping diagnostic folder with normalized.json}
        {--offer-index=0 : Offer index in normalized.json}
        {--execute-option-pnr : Call supplier DoOrderCreate (creates option PNR)}
        {--confirm= : Required confirmation phrase when executing}
        {--given-name= : Passenger given name}
        {--surname= : Passenger surname}
        {--title=MR : Passenger title}
        {--gender=M : Passenger gender}
        {--dob=1990-01-01 : Passenger date of birth}
        {--nationality=PK : Passenger nationality}
        {--passport-number= : Passenger passport number}
        {--passport-expiry= : Passenger passport expiry (YYYY-MM-DD)}
        {--email= : Contact email}
        {--phone= : Contact phone}
        {--owner-code=PK : OwnerCode override}';

    protected $description = 'CLI DoOrderCreate option PNR diagnostic for PIA NDC Hitit Crane 20.1 (dry-run by default)';

    public function handle(PiaNdcBookingService $bookingService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No PIA NDC SupplierConnection found.');

            return self::FAILURE;
        }

        try {
            [$providerContext, $sourcePath, $offerIndex, $publicOfferId, $supplierTotal, $currency] = $this->resolveOfferInput();
            $passengerInput = $this->resolvePassengerInput();
        } catch (PiaNdcValidationException $exception) {
            $this->error($exception->safeMessage);

            return self::FAILURE;
        }

        $executeOptionPnr = (bool) $this->option('execute-option-pnr');
        $confirmPhrase = trim((string) $this->option('confirm'));

        if ($executeOptionPnr && $confirmPhrase === '') {
            $this->error('Execute requires --confirm="CREATE_OPTION_PNR".');

            return self::FAILURE;
        }

        try {
            $result = $bookingService->runOrderCreateDiagnostic(
                connection: $connection,
                providerContext: $providerContext,
                passengerInput: $passengerInput,
                selectedOfferIndex: $offerIndex,
                selectedPublicOfferId: $publicOfferId,
                selectedSupplierTotal: $supplierTotal,
                currency: $currency,
                sourceDiagnosticPath: $sourcePath,
                executeOptionPnr: $executeOptionPnr,
                confirmPhrase: $confirmPhrase !== '' ? $confirmPhrase : null,
            );
        } catch (PiaNdcValidationException $exception) {
            $this->error($exception->safeMessage);

            return self::FAILURE;
        }

        $summary = $result['summary'];
        $this->line('connection_id='.($summary['connection_id'] ?? ''));
        $this->line('correlation_id='.($summary['correlation_id'] ?? ''));
        $this->line('dry_run='.(($summary['dry_run'] ?? true) ? 'true' : 'false'));
        $this->line('supplier_called='.(($summary['supplier_called'] ?? false) ? 'true' : 'false'));
        $this->line('selected_offer_id='.($summary['selected_public_offer_id'] ?? ''));
        $this->line('selected_supplier_total='.($summary['selected_supplier_total'] ?? ''));
        $this->line('currency='.($summary['currency'] ?? ''));
        $this->line('http_status='.($summary['http_status'] ?? ''));
        $this->line('success='.(($summary['success'] ?? false) ? 'true' : 'false'));
        $this->line('provider_error_code='.($summary['provider_error_code'] ?? ''));
        $this->line('provider_error_message='.($summary['provider_error_message'] ?? ''));
        $this->line('order_id='.($summary['order_id'] ?? ''));
        $this->line('pnr='.($summary['pnr'] ?? ''));
        $this->line('booking_reference='.($summary['booking_reference'] ?? ''));
        $this->line('airline_locator='.($summary['airline_locator'] ?? ''));
        $this->line('order_status='.($summary['order_status'] ?? ''));
        $this->line('payment_time_limit='.($summary['payment_time_limit'] ?? ''));
        $this->line('diagnostic_path='.($summary['diagnostic_path'] ?? ''));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{
     *     0: array<string, mixed>,
     *     1: string,
     *     2: int,
     *     3: ?string,
     *     4: ?float,
     *     5: ?string
     * }
     */
    private function resolveOfferInput(): array
    {
        $diagnosticPath = trim((string) $this->option('diagnostic-path'));
        $offerIndex = max(0, (int) $this->option('offer-index'));

        if ($diagnosticPath === '') {
            throw new PiaNdcValidationException('missing_input', 422, 'Provide --diagnostic-path to an AirShopping diagnostic folder.');
        }

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

        $ownerOverride = trim((string) $this->option('owner-code'));
        if ($ownerOverride !== '') {
            $providerContext['owner_code'] = $ownerOverride;
        }

        $fareBreakdown = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];

        return [
            $providerContext,
            $diagnosticPath,
            $offerIndex,
            isset($offer['offer_id']) ? (string) $offer['offer_id'] : null,
            isset($fareBreakdown['supplier_total']) ? (float) $fareBreakdown['supplier_total'] : null,
            isset($fareBreakdown['currency']) ? (string) $fareBreakdown['currency'] : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolvePassengerInput(): array
    {
        return [
            'given_name' => trim((string) $this->option('given-name')),
            'surname' => trim((string) $this->option('surname')),
            'title' => trim((string) $this->option('title')),
            'gender' => trim((string) $this->option('gender')),
            'dob' => trim((string) $this->option('dob')),
            'nationality' => trim((string) $this->option('nationality')),
            'passport_number' => trim((string) $this->option('passport-number')),
            'passport_expiry' => trim((string) $this->option('passport-expiry')),
            'email' => trim((string) $this->option('email')),
            'phone' => trim((string) $this->option('phone')),
        ];
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
