<?php

namespace App\Services\Suppliers\OneApi\Pricing;

use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
use App\Services\Suppliers\OneApi\Transport\OneApiSoapSecurityBuilder;
use XMLWriter;

class OneApiAirPriceRequestBuilder
{
    public function __construct(
        private readonly OneApiConfigResolver $configResolver,
        private readonly OneApiSoapSecurityBuilder $securityBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $signedOffer
     * @param  list<list<array<string, mixed>>>  $ondGroups
     */
    public function buildInitialPrice(SupplierConnection $connection, array $signedOffer, array $ondGroups, string $directionInd): string
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
        $writer->startElement('ota:OTA_AirPriceRQ');
        $writer->startElement('ota:POS');
        $writer->startElement('ota:Source');
        $writer->startElement('ota:RequestorID');
        $writer->writeAttribute('Type', '4');
        $writer->writeAttribute('ID', (string) $config['username']);
        $writer->endElement();
        $writer->startElement('ota:BookingChannel');
        $writer->writeAttribute('Type', '12');
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->startElement('ota:AirItinerary');
        $writer->writeAttribute('DirectionInd', $directionInd);
        foreach ($ondGroups as $group) {
            $writer->startElement('ota:OriginDestinationOptions');
            $writer->startElement('ota:OriginDestinationOption');
            foreach ($group as $segment) {
                $writer->startElement('ota:FlightSegment');
                $writer->writeAttribute('DepartureDateTime', (string) ($segment['departure_local'] ?? ''));
                $writer->writeAttribute('ArrivalDateTime', (string) ($segment['arrival_local'] ?? ''));
                $writer->writeAttribute('FlightNumber', (string) ($segment['flight_number'] ?? ''));
                $writer->startElement('ota:DepartureAirport');
                $writer->writeAttribute('LocationCode', (string) ($segment['origin'] ?? ''));
                $writer->endElement();
                $writer->startElement('ota:ArrivalAirport');
                $writer->writeAttribute('LocationCode', (string) ($segment['destination'] ?? ''));
                $writer->endElement();
                $writer->startElement('ota:OperatingAirline');
                $writer->writeAttribute('Code', (string) ($segment['operating_carrier'] ?? ''));
                $writer->endElement();
                $writer->endElement();
            }
            $writer->endElement();
            $writer->endElement();
        }
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }
}
