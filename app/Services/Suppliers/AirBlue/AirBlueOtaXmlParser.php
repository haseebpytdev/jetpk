<?php

namespace App\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Parses Zapways OTA SOAP responses for AirBlue (v2.0 and v3.0).
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
                'seat_maps' => $this->parseSeatMaps($xpath),
                'ancillary_items' => $this->parseAncillaryItems($xpath),
                'seats' => $this->parseReservedSeats($xpath),
                'items' => $this->parseReservedItems($xpath),
                'transaction_history' => $this->parseTransactionHistory($xpath),
                'payment_success_only' => $this->parsePaymentSuccessOnly($xpath),
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
                'fare_type' => $segmentNode->attributes?->getNamedItem('FareType')?->nodeValue,
                'cabin_class' => $segmentNode->attributes?->getNamedItem('CabinClass')?->nodeValue,
                'rph' => $segmentNode->attributes?->getNamedItem('RPH')?->nodeValue,
                'fare_basis' => $this->firstText($xpath, './/*[local-name()="FareBasis"]/@Code', $segmentNode),
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
        $fareAmountType = $this->firstText($xpath, './/*[local-name()="ItinTotalFare"]//*[local-name()="TotalFare"]/@FareAmountType', $itineraryNode);

        return [
            'base' => $base !== '' ? (float) $base : 0.0,
            'taxes' => $taxes !== '' ? (float) $taxes : 0.0,
            'fees' => $fees !== '' ? (float) $fees : 0.0,
            'total' => $total !== '' ? (float) $total : 0.0,
            'currency' => $currency !== '' ? $currency : 'PKR',
            'fare_amount_type' => $fareAmountType !== '' ? $fareAmountType : null,
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
            $base = $this->firstText($xpath, './/*[local-name()="PassengerFare"]/*[local-name()="BaseFare"]/@Amount', $node);
            $baseCurrency = $this->firstText($xpath, './/*[local-name()="PassengerFare"]/*[local-name()="BaseFare"]/@CurrencyCode', $node);
            $taxes = $this->firstText($xpath, './/*[local-name()="PassengerFare"]/*[local-name()="Taxes"]/@Amount', $node);
            $taxCurrency = $this->firstText($xpath, './/*[local-name()="PassengerFare"]/*[local-name()="Taxes"]/@CurrencyCode', $node);
            $fareBasis = $this->firstText($xpath, './/*[local-name()="FareBasisCodes"]/*[local-name()="FareBasisCode"]', $node);
            $breakdowns[] = [
                'ptc' => $ptc,
                'quantity' => $qty !== '' ? (int) $qty : 1,
                'base' => $base !== '' ? (float) $base : 0.0,
                'base_currency' => $baseCurrency !== '' ? $baseCurrency : ($currency !== '' ? $currency : 'PKR'),
                'taxes' => $taxes !== '' ? (float) $taxes : 0.0,
                'tax_currency' => $taxCurrency !== '' ? $taxCurrency : ($currency !== '' ? $currency : 'PKR'),
                'total' => $total !== '' ? (float) $total : 0.0,
                'currency' => $currency !== '' ? $currency : 'PKR',
                'fare_basis' => $fareBasis !== '' ? $fareBasis : null,
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

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSeatMaps(DOMXPath $xpath): array
    {
        $maps = [];
        $seatMapNodes = $xpath->query('//*[local-name()="SeatMapResponses"]/*[local-name()="SeatMapResponse"]');
        if ($seatMapNodes === false) {
            return $maps;
        }

        foreach ($seatMapNodes as $mapNode) {
            if (! $mapNode instanceof DOMNode) {
                continue;
            }
            $rows = [];
            $rowNodes = $xpath->query('.//*[local-name()="SeatMapDetails"]/*[local-name()="CabinClass"]/*[local-name()="RowInfo"]', $mapNode);
            if ($rowNodes !== false) {
                foreach ($rowNodes as $rowNode) {
                    if (! $rowNode instanceof DOMNode) {
                        continue;
                    }
                    $rowNumber = $rowNode->attributes?->getNamedItem('RowNumber')?->nodeValue;
                    $seats = [];
                    $seatNodes = $xpath->query('.//*[local-name()="Seat"]', $rowNode);
                    if ($seatNodes !== false) {
                        foreach ($seatNodes as $seatNode) {
                            if (! $seatNode instanceof DOMNode) {
                                continue;
                            }
                            $seats[] = [
                                'seat_number' => $seatNode->attributes?->getNamedItem('SeatNumber')?->nodeValue,
                                'available_ind' => $seatNode->attributes?->getNamedItem('AvailableInd')?->nodeValue,
                                'occupied_ind' => $seatNode->attributes?->getNamedItem('OccupiedInd')?->nodeValue,
                                'cost' => $this->firstText($xpath, './/*[local-name()="Service"]/@Amount', $seatNode),
                                'currency' => $this->firstText($xpath, './/*[local-name()="Service"]/@CurrencyCode', $seatNode),
                                'characteristics' => $this->collectAttributeValues($xpath, './/*[local-name()="SeatCharacteristics"]/*', $seatNode, 'Code'),
                                'restrictions' => $this->collectAttributeValues($xpath, './/*[local-name()="Restrictions"]/*', $seatNode, 'Code'),
                            ];
                        }
                    }
                    $rows[] = [
                        'row_number' => $rowNumber,
                        'seats' => $seats,
                        'row_characteristics' => $this->collectAttributeValues($xpath, './/*[local-name()="RowCharacteristics"]/*', $rowNode, 'Code'),
                    ];
                }
            }

            $maps[] = [
                'flight_segment' => [
                    'departure_datetime' => $this->firstText($xpath, './/*[local-name()="FlightSegmentInfo"]/@DepartureDateTime', $mapNode),
                    'flight_number' => $this->firstText($xpath, './/*[local-name()="FlightSegmentInfo"]/@FlightNumber', $mapNode),
                    'fare_type' => $this->firstText($xpath, './/*[local-name()="FlightSegmentInfo"]/@FareType', $mapNode),
                    'cabin_class' => $this->firstText($xpath, './/*[local-name()="FlightSegmentInfo"]/@CabinClass', $mapNode),
                    'rbd' => $this->firstText($xpath, './/*[local-name()="FlightSegmentInfo"]/@ResBookDesigCode', $mapNode),
                ],
                'rows' => $rows,
            ];
        }

        return $maps;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseAncillaryItems(DOMXPath $xpath): array
    {
        $groups = [];
        $setNodes = $xpath->query('//*[local-name()="AncillaryItemSets"]/*[local-name()="AncillaryItemSet"]');
        if ($setNodes === false) {
            return $groups;
        }

        foreach ($setNodes as $setNode) {
            if (! $setNode instanceof DOMNode) {
                continue;
            }
            $items = [];
            $itemNodes = $xpath->query('.//*[local-name()="AncillaryItems"]/*[local-name()="AncillaryItem"]', $setNode);
            if ($itemNodes !== false) {
                foreach ($itemNodes as $itemNode) {
                    if (! $itemNode instanceof DOMNode) {
                        continue;
                    }
                    $items[] = [
                        'item_code' => $itemNode->attributes?->getNamedItem('ItemCode')?->nodeValue,
                        'item_title' => $itemNode->attributes?->getNamedItem('ItemTitle')?->nodeValue,
                        'description' => trim($itemNode->textContent ?? ''),
                        'available' => $itemNode->attributes?->getNamedItem('Available')?->nodeValue,
                        'charge_amount' => $itemNode->attributes?->getNamedItem('ChargeAmount')?->nodeValue,
                        'charge_currency' => $itemNode->attributes?->getNamedItem('ChargeCurrency')?->nodeValue,
                        'is_refundable' => $itemNode->attributes?->getNamedItem('IsRefundable')?->nodeValue,
                    ];
                }
            }

            $groups[] = [
                'group_code' => $setNode->attributes?->getNamedItem('GroupCode')?->nodeValue,
                'group_description' => $setNode->attributes?->getNamedItem('GroupDescription')?->nodeValue,
                'group_title' => $setNode->attributes?->getNamedItem('GroupTitle')?->nodeValue,
                'group_image_url' => $setNode->attributes?->getNamedItem('GroupImageURL')?->nodeValue,
                'group_level' => $setNode->attributes?->getNamedItem('GroupLevel')?->nodeValue,
                'multiple_choice' => $setNode->attributes?->getNamedItem('MultipleChoice')?->nodeValue,
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseReservedSeats(DOMXPath $xpath): array
    {
        $seats = [];
        $nodes = $xpath->query('//*[local-name()="SeatRequests"]/*[local-name()="SeatRequest"]');
        if ($nodes === false) {
            return $seats;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $seats[] = [
                'seat_number' => $node->attributes?->getNamedItem('SeatNumber')?->nodeValue,
                'row_number' => $node->attributes?->getNamedItem('RowNumber')?->nodeValue,
                'status' => $node->attributes?->getNamedItem('Status')?->nodeValue,
                'traveler_ref_number_rph' => $node->attributes?->getNamedItem('TravelerRefNumberRPHList')?->nodeValue,
                'flight_ref_number_rph' => $node->attributes?->getNamedItem('FlightRefNumberRPHList')?->nodeValue,
            ];
        }

        return $seats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseReservedItems(DOMXPath $xpath): array
    {
        $items = [];
        $nodes = $xpath->query('//*[local-name()="SpecialServiceRequests"]/*[local-name()="SpecialServiceRequest"]');
        if ($nodes === false) {
            return $items;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $items[] = [
                'item_code' => $node->attributes?->getNamedItem('ItemCode')?->nodeValue,
                'item_count' => $node->attributes?->getNamedItem('ItemCount')?->nodeValue,
                'status' => $node->attributes?->getNamedItem('Status')?->nodeValue,
                'traveler_ref_number_rph' => $node->attributes?->getNamedItem('TravelerRefNumberRPHList')?->nodeValue,
                'flight_ref_number_rph' => $node->attributes?->getNamedItem('FlightRefNumberRPHList')?->nodeValue,
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseTransactionHistory(DOMXPath $xpath): array
    {
        $history = [];
        $nodes = $xpath->query('//*[local-name()="ItinTotalFare"]/*[local-name()="TotalFare"]');
        if ($nodes === false) {
            return $history;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $amount = $node->attributes?->getNamedItem('Amount')?->nodeValue;
            if ($amount === null || $amount === '') {
                continue;
            }
            $history[] = [
                'amount' => (float) $amount,
                'currency' => $node->attributes?->getNamedItem('CurrencyCode')?->nodeValue,
                'fare_amount_type' => $node->attributes?->getNamedItem('FareAmountType')?->nodeValue,
            ];
        }

        return $history;
    }

    private function parsePaymentSuccessOnly(DOMXPath $xpath): bool
    {
        $success = $this->firstText($xpath, '//*[local-name()="Success"]');
        $hasTickets = $xpath->query('//*[local-name()="TicketItemInfo"]')?->length > 0;

        return $success !== '' && ! $hasTickets;
    }

    /**
     * @return list<string>
     */
    private function collectAttributeValues(DOMXPath $xpath, string $query, DOMNode $context, string $attribute): array
    {
        $values = [];
        $nodes = $xpath->query($query, $context);
        if ($nodes === false) {
            return $values;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $value = $node->attributes?->getNamedItem($attribute)?->nodeValue;
            if ($value !== null && $value !== '') {
                $values[] = (string) $value;
            }
        }

        return $values;
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
