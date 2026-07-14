<?php

namespace App\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Parses Zapways OTA v2.06 SOAP responses for AirBlue.
 */
class AirBlueOtaXmlParser
{
    /**
     * @return array{
     *     parsed: array<string, mixed>,
     *     raw_xml: string,
     *     soap_fault: ?array{code: string, message: string},
     *     errors: list<array{code: string, message: string, type: ?string}>,
     *     warnings: list<array{code: string, message: string, type: ?string}>
     * }
     */
    public function parse(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw new AirBlueXmlException('empty_xml', 502, 'Provider returned an empty response.');
        }

        $dom = new DOMDocument;
        if (@$dom->loadXML($xml) !== true) {
            throw new AirBlueXmlException('malformed_xml', 502, 'Provider returned an invalid XML response.');
        }

        $xpath = new DOMXPath($dom);
        $soapFault = $this->parseSoapFault($xpath);
        $errors = $this->parseOtaErrors($xpath);
        $warnings = [];

        return [
            'parsed' => [
                'priced_itineraries' => $this->parsePricedItineraries($xpath),
                'booking' => $this->parseBooking($xpath),
                'tickets' => $this->parseTickets($xpath),
                'pnr' => $this->firstText($xpath, '//*[local-name()="BookingReferenceID"]/@ID'),
                'instance' => $this->firstText($xpath, '//*[local-name()="BookingReferenceID"]/@Instance'),
            ],
            'raw_xml' => $xml,
            'soap_fault' => $soapFault,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return ?array{code: string, message: string}
     */
    private function parseSoapFault(DOMXPath $xpath): ?array
    {
        $code = $this->firstText($xpath, '//*[local-name()="Fault"]/*[local-name()="faultcode"]');
        $message = $this->firstText($xpath, '//*[local-name()="Fault"]/*[local-name()="faultstring"]');
        if ($code === '' && $message === '') {
            return null;
        }

        return ['code' => $code, 'message' => $message];
    }

    /**
     * @return list<array{code: string, message: string, type: ?string}>
     */
    private function parseOtaErrors(DOMXPath $xpath): array
    {
        $messages = [];
        $nodes = $xpath->query('//*[local-name()="Errors"]/*[local-name()="Error"]');
        if ($nodes === false) {
            return $messages;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $code = $node->attributes?->getNamedItem('Code')?->nodeValue ?? '';
            $message = trim($node->textContent ?? '');
            if ($code === '' && $message === '') {
                continue;
            }
            $messages[] = [
                'code' => (string) $code,
                'message' => $message,
                'type' => $node->attributes?->getNamedItem('Type')?->nodeValue,
            ];
        }

        return $messages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsePricedItineraries(DOMXPath $xpath): array
    {
        $itineraries = [];
        $nodes = $xpath->query('//*[local-name()="PricedItineraries"]/*[local-name()="PricedItinerary"]');
        if ($nodes === false) {
            return $itineraries;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $itineraries[] = [
                'origin_destination_ref' => $node->attributes?->getNamedItem('OriginDestinationRefNumber')?->nodeValue,
                'segments' => $this->parseFlightSegments($xpath, $node),
                'total_fare' => $this->parseItinTotalFare($xpath, $node),
                'fare_breakdowns' => $this->parseFareBreakdowns($xpath, $node),
            ];
        }

        return $itineraries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseFlightSegments(DOMXPath $xpath, DOMNode $itineraryNode): array
    {
        $segments = [];
        $segmentNodes = $xpath->query('.//*[local-name()="FlightSegment"]', $itineraryNode);
        if ($segmentNodes === false) {
            return $segments;
        }

        foreach ($segmentNodes as $segmentNode) {
            if (! $segmentNode instanceof DOMNode) {
                continue;
            }
            $segments[] = [
                'departure_datetime' => $segmentNode->attributes?->getNamedItem('DepartureDateTime')?->nodeValue,
                'arrival_datetime' => $segmentNode->attributes?->getNamedItem('ArrivalDateTime')?->nodeValue,
                'flight_number' => $segmentNode->attributes?->getNamedItem('FlightNumber')?->nodeValue,
                'rbd' => $segmentNode->attributes?->getNamedItem('ResBookDesigCode')?->nodeValue,
                'departure_airport' => $this->firstText($xpath, './/*[local-name()="DepartureAirport"]/@LocationCode', $segmentNode),
                'arrival_airport' => $this->firstText($xpath, './/*[local-name()="ArrivalAirport"]/@LocationCode', $segmentNode),
                'marketing_carrier' => $this->firstText($xpath, './/*[local-name()="MarketingAirline"]/@Code', $segmentNode),
                'operating_carrier' => $this->firstText($xpath, './/*[local-name()="OperatingAirline"]/@Code', $segmentNode),
                'equipment' => $this->firstText($xpath, './/*[local-name()="Equipment"]/@AirEquipType', $segmentNode),
            ];
        }

        return $segments;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseItinTotalFare(DOMXPath $xpath, DOMNode $itineraryNode): array
    {
        $base = $this->firstText($xpath, './/*[local-name()="ItinTotalFare"]//*[local-name()="BaseFare"]/@Amount', $itineraryNode);
        $taxes = $this->firstText($xpath, './/*[local-name()="ItinTotalFare"]//*[local-name()="Taxes"]/@Amount', $itineraryNode);
        $fees = $this->firstText($xpath, './/*[local-name()="ItinTotalFare"]//*[local-name()="Fees"]/@Amount', $itineraryNode);
        $total = $this->firstText($xpath, './/*[local-name()="ItinTotalFare"]//*[local-name()="TotalFare"]/@Amount', $itineraryNode);
        $currency = $this->firstText($xpath, './/*[local-name()="ItinTotalFare"]//*[local-name()="TotalFare"]/@CurrencyCode', $itineraryNode);

        return [
            'base' => $base !== '' ? (float) $base : 0.0,
            'taxes' => $taxes !== '' ? (float) $taxes : 0.0,
            'fees' => $fees !== '' ? (float) $fees : 0.0,
            'total' => $total !== '' ? (float) $total : 0.0,
            'currency' => $currency !== '' ? $currency : 'PKR',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseFareBreakdowns(DOMXPath $xpath, DOMNode $itineraryNode): array
    {
        $breakdowns = [];
        $nodes = $xpath->query('.//*[local-name()="PTC_FareBreakdown"]', $itineraryNode);
        if ($nodes === false) {
            return $breakdowns;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $ptc = $this->firstText($xpath, './/*[local-name()="PassengerTypeQuantity"]/@Code', $node);
            $qty = $this->firstText($xpath, './/*[local-name()="PassengerTypeQuantity"]/@Quantity', $node);
            $total = $this->firstText($xpath, './/*[local-name()="TotalFare"]/@Amount', $node);
            $currency = $this->firstText($xpath, './/*[local-name()="TotalFare"]/@CurrencyCode', $node);
            $breakdowns[] = [
                'ptc' => $ptc,
                'quantity' => $qty !== '' ? (int) $qty : 1,
                'total' => $total !== '' ? (float) $total : 0.0,
                'currency' => $currency !== '' ? $currency : 'PKR',
            ];
        }

        return $breakdowns;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseBooking(DOMXPath $xpath): array
    {
        return [
            'pnr' => $this->firstText($xpath, '//*[local-name()="BookingReferenceID"]/@ID'),
            'instance' => $this->firstText($xpath, '//*[local-name()="BookingReferenceID"]/@Instance'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseTickets(DOMXPath $xpath): array
    {
        $tickets = [];
        $nodes = $xpath->query('//*[local-name()="TicketItemInfo"]');
        if ($nodes === false) {
            return $tickets;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $tickets[] = [
                'ticket_number' => $node->attributes?->getNamedItem('TicketNumber')?->nodeValue,
                'pax_type' => $this->firstText($xpath, './/*[local-name()="PassengerName"]/@PassengerTypeCode', $node),
            ];
        }

        return $tickets;
    }

    private function firstText(DOMXPath $xpath, string $query, ?DOMNode $context = null): string
    {
        $nodes = $context !== null ? $xpath->query($query, $context) : $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }
        $node = $nodes->item(0);
        if ($node === null) {
            return '';
        }

        return trim($node->nodeValue ?? '');
    }
}
