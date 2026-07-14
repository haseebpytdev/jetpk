<?php

namespace App\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Namespace-aware SOAP/NDC XML parser — converts responses to normalized arrays.
 */
class AirBlueXmlParser
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
        $errors = $this->parseMessages($xpath, 'Error');
        $warnings = $this->parseMessages($xpath, 'Warning');

        return [
            'parsed' => [
                'shopping_response_ref_id' => $this->firstText($xpath, '//ShoppingResponseRefID'),
                'offers' => $this->parseOffers($xpath),
                'data_lists' => $this->parseDataLists($xpath),
                'order' => $this->parseOrder($xpath),
                'orders' => $this->parseOrders($xpath),
                'ticket_preview' => $this->parseTicketPreview($xpath),
                'cancel_preview' => $this->parseCancelPreview($xpath),
                'ticket_doc_infos' => $this->parseTicketDocInfos($xpath),
                'payment_time_limit' => $this->firstText($xpath, '//PaymentTimeLimitDateTime'),
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
    private function parseMessages(DOMXPath $xpath, string $localName): array
    {
        $nodes = $xpath->query('//*[local-name()="'.$localName.'"]');
        $messages = [];
        if ($nodes === false) {
            return $messages;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $messages[] = [
                'code' => $this->childText($node, 'Code'),
                'message' => $this->childText($node, 'DescText') ?: trim($node->textContent ?? ''),
                'type' => $this->childText($node, 'TypeCode') ?: null,
            ];
        }

        return $messages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseOffers(DOMXPath $xpath): array
    {
        $offers = [];
        $nodes = $xpath->query('//*[local-name()="CarrierOffers"]/*[local-name()="Offer"]|//*[local-name()="OffersGroup"]//*[local-name()="Offer"]|//*[local-name()="Response"]//*[local-name()="Offer"]');
        if ($nodes === false) {
            return $offers;
        }

        $seen = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $offerId = $this->childText($node, 'OfferID');
            if ($offerId === '' || isset($seen[$offerId])) {
                continue;
            }
            $seen[$offerId] = true;
            $offers[] = [
                'offer_id' => $offerId,
                'owner_code' => $this->childText($node, 'OwnerCode'),
                'offer_items' => $this->parseOfferItems($node),
                'total_price' => $this->parsePriceNode($this->firstChild($node, 'TotalPrice')),
                'journey_refs' => $this->childTexts($node, 'PaxJourneyRefID'),
            ];
        }

        return $offers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseOfferItems(DOMNode $offerNode): array
    {
        $items = [];
        foreach ($offerNode->childNodes as $child) {
            if (! $child instanceof DOMNode || $child->localName !== 'OfferItem') {
                continue;
            }
            $items[] = [
                'offer_item_id' => $this->childText($child, 'OfferItemID'),
                'pax_refs' => $this->childTexts($child, 'PaxRefID'),
                'price' => $this->parsePriceNode($this->firstChild($child, 'Price')),
                'fare_basis' => $this->firstDescendantText($child, 'FareBasisCode'),
                'rbd' => $this->firstDescendantText($child, 'RBD'),
                'cabin' => $this->firstDescendantText($child, 'CabinTypeCode'),
                'payment_time_limit' => $this->firstDescendantText($child, 'PaymentTimeLimitDateTime'),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDataLists(DOMXPath $xpath): array
    {
        return [
            'pax_segments' => $this->parsePaxSegments($xpath),
            'pax_journeys' => $this->parsePaxJourneys($xpath),
            'origin_dests' => $this->parseOriginDests($xpath),
            'pax_list' => $this->parsePaxList($xpath),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsePaxSegments(DOMXPath $xpath): array
    {
        $segments = [];
        $nodes = $xpath->query('//*[local-name()="PaxSegmentList"]/*[local-name()="PaxSegment"]');
        if ($nodes === false) {
            return $segments;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $segments[] = [
                'pax_segment_id' => $this->childText($node, 'PaxSegmentID'),
                'departure_airport' => $this->firstDescendantText($node, 'IATA_LocationCode', 'Dep'),
                'arrival_airport' => $this->lastDescendantText($node, 'IATA_LocationCode'),
                'departure_at' => $this->firstDescendantText($node, 'AircraftScheduledDateTime', 'Dep'),
                'arrival_at' => $this->firstDescendantText($node, 'AircraftScheduledDateTime', 'Arrival'),
                'carrier' => $this->firstDescendantText($node, 'CarrierDesigCode'),
                'flight_number' => $this->firstDescendantText($node, 'MarketingCarrierFlightNumberText'),
                'rbd' => $this->firstDescendantText($node, 'RBD'),
            ];
        }

        return $segments;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsePaxJourneys(DOMXPath $xpath): array
    {
        $journeys = [];
        $nodes = $xpath->query('//*[local-name()="PaxJourneyList"]/*[local-name()="PaxJourney"]');
        if ($nodes === false) {
            return $journeys;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $journeys[] = [
                'pax_journey_id' => $this->childText($node, 'PaxJourneyID'),
                'pax_segment_refs' => $this->childTexts($node, 'PaxSegmentRefID'),
            ];
        }

        return $journeys;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseOriginDests(DOMXPath $xpath): array
    {
        $list = [];
        $nodes = $xpath->query('//*[local-name()="OriginDestList"]/*[local-name()="OriginDest"]');
        if ($nodes === false) {
            return $list;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $list[] = [
                'origin_dest_id' => $this->childText($node, 'OriginDestID'),
                'origin' => $this->childText($node, 'OriginCode'),
                'destination' => $this->childText($node, 'DestCode'),
                'pax_journey_refs' => $this->childTexts($node, 'PaxJourneyRefID'),
            ];
        }

        return $list;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsePaxList(DOMXPath $xpath): array
    {
        $pax = [];
        $nodes = $xpath->query('//*[local-name()="PaxList"]/*[local-name()="Pax"]');
        if ($nodes === false) {
            return $pax;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $pax[] = [
                'pax_id' => $this->childText($node, 'PaxID'),
                'ptc' => $this->childText($node, 'PTC'),
            ];
        }

        return $pax;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function parseOrder(DOMXPath $xpath): ?array
    {
        $node = $xpath->query('//*[local-name()="Order"]')->item(0);
        if (! $node instanceof DOMNode) {
            return null;
        }

        return $this->orderFromNode($node, $xpath);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseOrders(DOMXPath $xpath): array
    {
        $orders = [];
        $nodes = $xpath->query('//*[local-name()="Order"]');
        if ($nodes === false) {
            return $orders;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $order = $this->orderFromNode($node, $xpath);
            if ($order !== null) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function orderFromNode(DOMNode $node, DOMXPath $xpath): ?array
    {
        $orderId = $this->childText($node, 'OrderID');
        if ($orderId === '') {
            return null;
        }

        return [
            'order_id' => $orderId,
            'owner_code' => $this->childText($node, 'OwnerCode'),
            'status' => $this->childText($node, 'StatusCode'),
            'total_price' => $this->parsePriceNode($this->firstChild($node, 'TotalPrice')),
            'payment_time_limit' => $this->firstText($xpath, '//PaymentTimeLimitDateTime'),
        ];
    }

    /**
     * @return ?array{amount: float, currency: string}
     */
    private function parseTicketPreview(DOMXPath $xpath): ?array
    {
        $amountNode = $xpath->query('//*[local-name()="Order"]//*[local-name()="TotalPrice"]//*[local-name()="TotalAmount"]')->item(0);
        if (! $amountNode instanceof DOMNode) {
            $amountNode = $xpath->query('//*[local-name()="TotalAmount"]')->item(0);
        }
        if (! $amountNode instanceof DOMNode) {
            return null;
        }

        return [
            'amount' => (float) trim($amountNode->textContent ?? '0'),
            'currency' => $amountNode->attributes?->getNamedItem('CurCode')?->nodeValue
                ?: $this->firstText($xpath, '//*[local-name()="CurCode"]')
                ?: 'PKR',
        ];
    }

    /**
     * @return ?array{penalty: ?float, refundable: ?float, currency: string}
     */
    private function parseCancelPreview(DOMXPath $xpath): ?array
    {
        $penalty = $this->firstText($xpath, '//*[local-name()="PenaltyAmount"]');
        $refund = $this->firstText($xpath, '//*[local-name()="RefundAmount"]');
        if ($penalty === '' && $refund === '') {
            return null;
        }

        return [
            'penalty' => $penalty !== '' ? (float) $penalty : null,
            'refundable' => $refund !== '' ? (float) $refund : null,
            'currency' => $this->firstText($xpath, '//*[local-name()="CurCode"]') ?: 'PKR',
        ];
    }

    /**
     * @return list<array{ticket_number: string, pax_ref: ?string}>
     */
    private function parseTicketDocInfos(DOMXPath $xpath): array
    {
        $tickets = [];
        $nodes = $xpath->query('//*[local-name()="TicketDocInfo"]');
        if ($nodes === false) {
            return $tickets;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $number = $this->firstDescendantText($node, 'TicketNumber');
            if ($number === '') {
                $number = $this->firstDescendantText($node, 'TicketID');
            }
            if ($number === '') {
                continue;
            }
            $tickets[] = [
                'ticket_number' => $number,
                'pax_ref' => $this->childText($node, 'PaxRefID') ?: null,
            ];
        }

        return $tickets;
    }

    /**
     * @return array{total: float, base: float, tax: float, currency: string}
     */
    private function parsePriceNode(?DOMNode $node): array
    {
        if (! $node instanceof DOMNode) {
            return ['total' => 0.0, 'base' => 0.0, 'tax' => 0.0, 'currency' => 'PKR'];
        }

        $total = (float) ($this->firstDescendantText($node, 'TotalAmount') ?: 0);
        $base = (float) ($this->firstDescendantText($node, 'BaseAmount') ?: 0);
        $tax = (float) ($this->firstDescendantText($node, 'TaxSummary') ?: 0);
        $currency = $this->firstDescendantAttribute($node, 'CurCode') ?: 'PKR';

        return ['total' => $total, 'base' => $base, 'tax' => $tax, 'currency' => $currency];
    }

    private function firstText(DOMXPath $xpath, string $query): string
    {
        $node = $xpath->query($query)->item(0);

        return $node instanceof DOMNode ? trim($node->textContent ?? '') : '';
    }

    private function childText(DOMNode $parent, string $localName): string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMNode && $child->localName === $localName) {
                return trim($child->textContent ?? '');
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function childTexts(DOMNode $parent, string $localName): array
    {
        $values = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMNode && $child->localName === $localName) {
                $value = trim($child->textContent ?? '');
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    private function firstChild(DOMNode $parent, string $localName): ?DOMNode
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMNode && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function firstDescendantText(DOMNode $node, string $localName, ?string $parentLocalName = null): string
    {
        foreach ($node->getElementsByTagName($localName) as $element) {
            if (! $element instanceof DOMNode) {
                continue;
            }
            if ($parentLocalName !== null) {
                $parent = $element->parentNode;
                if (! $parent instanceof DOMNode || $parent->localName !== $parentLocalName) {
                    continue;
                }
            }

            return trim($element->textContent ?? '');
        }

        return '';
    }

    private function lastDescendantText(DOMNode $node, string $localName): string
    {
        $value = '';
        foreach ($node->getElementsByTagName($localName) as $element) {
            if ($element instanceof DOMNode) {
                $value = trim($element->textContent ?? '');
            }
        }

        return $value;
    }

    private function firstDescendantAttribute(DOMNode $node, string $attribute): string
    {
        foreach ($node->getElementsByTagName('*') as $element) {
            if (! $element instanceof DOMNode || ! $element->attributes) {
                continue;
            }
            $attr = $element->attributes->getNamedItem($attribute);
            if ($attr !== null && trim($attr->nodeValue ?? '') !== '') {
                return trim($attr->nodeValue ?? '');
            }
        }

        return '';
    }
}
