<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AirBlueAuditDocsCommand extends Command
{
    protected $signature = 'airblue:audit-docs';

    protected $description = 'Print AirBlue 20.1 service matrix from integration reference concepts';

    /** @var list<array{operation: string, request: string, response: string, ota_service: string}> */
    private array $operationMatrix = [
        ['operation' => 'DoAirShopping', 'request' => 'IATA_AirShoppingRQ', 'response' => 'IATA_AirShoppingRS', 'ota_service' => 'Search'],
        ['operation' => 'DoOfferPrice', 'request' => 'IATA_OfferPriceRQ', 'response' => 'IATA_OfferPriceRS', 'ota_service' => 'Offer price (optional/no-op)'],
        ['operation' => 'DoOrderCreate', 'request' => 'IATA_OrderCreateRQ', 'response' => 'IATA_OrderViewRS', 'ota_service' => 'Option PNR (no payment)'],
        ['operation' => 'DoOrderRetrieve', 'request' => 'IATA_OrderRetrieveRQ', 'response' => 'IATA_OrderViewRS', 'ota_service' => 'Retrieve/sync'],
        ['operation' => 'DoTicketPreview', 'request' => 'IATA_OrderChangeRQ (order only)', 'response' => 'IATA_OrderViewRS', 'ota_service' => 'Ticket preview'],
        ['operation' => 'DoOrderChange', 'request' => 'IATA_OrderChangeRQ + payment', 'response' => 'IATA_OrderViewRS', 'ota_service' => 'Ticketing (MCO)'],
        ['operation' => 'DoOrderCancelPreview', 'request' => 'IATA_OrderReshopRQ', 'response' => 'IATA_OrderReshopRS', 'ota_service' => 'Cancel preview'],
        ['operation' => 'DoOrderCancelCommit', 'request' => 'IATA_OrderChangeRQ + ChangeOrder/CancelOrder', 'response' => 'IATA_OrderViewRS', 'ota_service' => 'Cancel commit'],
        ['operation' => 'DoVoidTicket', 'request' => 'IATA_OrderChangeRQ (order only)', 'response' => 'IATA_OrderViewRS', 'ota_service' => 'Void ticket'],
        ['operation' => 'DoReissuePreview', 'request' => 'IATA_OrderReshopRQ', 'response' => 'IATA_OrderReshopRS', 'ota_service' => 'Reissue preview'],
        ['operation' => 'DoReissueCommit', 'request' => 'IATA_OrderChangeRQ', 'response' => 'IATA_OrderViewRS', 'ota_service' => 'Reissue commit'],
        ['operation' => 'DoGeneralParams', 'request' => 'TBD manual', 'response' => 'TBD', 'ota_service' => 'Health/diagnostic'],
        ['operation' => 'DoAirlineProfile', 'request' => 'IATA_AirlineProfileRQ', 'response' => 'IATA_AirlineProfileRS', 'ota_service' => 'Profile/diagnostic'],
    ];

    public function handle(): int
    {
        $docsPath = base_path('docs/providers/airblue-ndc-service-matrix.md');
        $crossmatch = base_path('docs/providers/airblue-api-crossmatch.md');
        $this->line('reference_doc='.$docsPath);
        $this->line('crossmatch_doc='.$crossmatch);
        $this->line('doc_exists='.(is_file($docsPath) ? 'yes' : 'no'));
        $this->line('provider=airblue');
        $this->line('channels=crane_ndc,zapways_ota');
        $this->line('protocol=SOAP over HTTP');
        $this->line('ndc_version=IATA NDC 20.1');
        $this->line('service_name=CraneNDCService (Hitit)');

        $this->newLine();
        $this->info('SOAP operation matrix:');
        foreach ($this->operationMatrix as $row) {
            $this->line(sprintf(
                '%s | %s -> %s | %s',
                $row['operation'],
                $row['request'],
                $row['response'],
                $row['ota_service'],
            ));
        }

        $this->newLine();
        $this->info('Lifecycle rules:');
        $this->line('1. DoAirShopping → DoOrderCreate via ShoppingResponseRefID / OfferRefID');
        $this->line('2. Option PNR = DoOrderCreate without payment');
        $this->line('3. DoOrderRetrieve syncs PNR/order status and ticket numbers');
        $this->line('4. Ticketing: DoTicketPreview then DoOrderChange with PaymentFunctions');
        $this->line('5. Cancel: DoOrderCancelPreview then DoOrderCancelCommit');
        $this->line('6. DoVoidTicket reverts ticketed reservation to option');
        $this->line('7. Reissue: preview (OrderReshop) then commit (OrderChange)');

        $this->newLine();
        $this->warn('Unsafe patterns to avoid: credentials in env/debug XML, dd()/dump(), raw SOAP to users');

        return self::SUCCESS;
    }
}
