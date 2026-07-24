<?php

namespace App\Services\Suppliers\OneApi\Reservation;

use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Services\Suppliers\OneApi\Transport\OneApiSoapSecurityBuilder;
use XMLWriter;

/**
 * Builds vendor OTA_ReadRQ with exact wire spellings required by One API.
 */
class OneApiReadRequestBuilder
{
    public function __construct(
        private readonly OneApiConfigResolver $configResolver,
        private readonly OneApiSoapSecurityBuilder $securityBuilder,
    ) {}

    public function build(SupplierConnection $connection, string $pnr): string
    {
        $config = $this->configResolver->resolve($connection);
        $security = $this->securityBuilder->buildUsernameToken((string) $config['username'], (string) $config['password']);

        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('soapenv:Envelope');
        $writer->writeAttribute('xmlns:soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $writer->writeAttribute('xmlns:ota', 'http://www.opentravel.org/OTA/2003/05');
        $writer->startElement('soapenv:Header');
        $writer->writeRaw($security);
        $writer->endElement();
        $writer->startElement('soapenv:Body');
        $writer->startElement('ota:OTA_ReadRQ');
        $writer->startElement('ota:ReadRequests');
        $writer->startElement('ota:ReadRequest');
        $writer->startElement('ota:UniqueID');
        $writer->writeAttribute('Type', '14');
        $writer->writeAttribute('ID', $pnr);
        $writer->endElement();
        $writer->startElement('ota:Verification');
        $writer->writeElement('ota:LoadTravelerInfo', 'true');
        $writer->writeElement('ota:LoadAirItinery', 'true');
        $writer->writeElement('ota:LoadPriceInfoTotals', 'true');
        $writer->writeElement('ota:LoadFullFilment', 'true');
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }
}
