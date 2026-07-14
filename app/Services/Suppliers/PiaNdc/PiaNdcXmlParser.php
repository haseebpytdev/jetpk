<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcXmlException;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Namespace-aware SOAP/NDC XML parser — converts responses to normalized arrays.
 */
class PiaNdcXmlParser
{
    /** @var list<string> */
    public const AIR_SHOPPING_DEBUG_NODE_LOCAL_NAMES = [
        'Error',
        'Warning',
        'Offer',
        'OfferItem',
        'PricedOffer',
        'DataLists',
        'PaxSegment',
        'FlightSegment',
        'TotalPrice',
        'BaseAmount',
        'TaxSummary',
    ];

    /**
     * Count XML nodes by local-name, ignoring namespace prefixes.
     *
     * @param  list<string>|null  $localNames
     * @return array<string, int>
     */
    public function countNodesByLocalName(string $xml, ?array $localNames = null): array
    {
        $localNames = $localNames ?? self::AIR_SHOPPING_DEBUG_NODE_LOCAL_NAMES;
        $counts = array_fill_keys($localNames, 0);
        $xml = trim($xml);
        if ($xml === '') {
            return $counts;
        }

        $dom = new DOMDocument;
        if (@$dom->loadXML($xml) !== true) {
            return $counts;
        }

        $xpath = new DOMXPath($dom);
        foreach ($localNames as $localName) {
            $nodes = $xpath->query('//*[local-name()="'.$localName.'"]');
            $counts[$localName] = $nodes !== false ? $nodes->length : 0;
        }

        return $counts;
    }

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
            throw new PiaNdcXmlException('empty_xml', 502, 'Provider returned an empty response.');
        }

        $dom = new DOMDocument;
        if (@$dom->loadXML($xml) !== true) {
            throw new PiaNdcXmlException('malformed_xml', 502, 'Provider returned an invalid XML response.');
        }

        $xpath = new DOMXPath($dom);
        $soapFault = $this->parseSoapFault($xpath);
        $errors = $this->parseMessages($xpath, 'Error');
        $warnings = $this->parseMessages($xpath, 'Warning');

        return [
            'parsed' => [
                'shopping_response_ref_id' => $this->parseShoppingResponseRefId($xpath),
                'offers' => $this->parseOffers($xpath),
                'priced_offers' => $this->parsePricedOffers($xpath),
                'offer_price_summary' => $this->parseOfferPriceSummary($xpath),
                'data_lists' => $this->parseDataLists($xpath),
                'order' => $this->parseOrder($xpath),
                'orders' => $this->parseOrders($xpath),
                'booking_refs' => $this->parseBookingRefs($xpath),
                'ticket_preview' => $this->parseTicketPreview($xpath),
                'cancel_preview' => $this->parseCancelPreview($xpath),
                'ticket_doc_infos' => $this->parseTicketDocInfos($xpath),
                'payment_time_limit' => $this->firstText($xpath, '//PaymentTimeLimitDateTime'),
                'general_params' => $this->parseGeneralParams($xpath),
                'airline_profile' => $this->parseAirlineProfile($xpath),
                'order_service_statuses' => $this->parseOrderServiceStatuses($xpath),
                'order_item_statuses' => $this->parseOrderItemStatuses($xpath),
                'segment_operating_statuses' => $this->parseSegmentOperatingStatuses($xpath),
            ],
            'raw_xml' => $xml,
            'soap_fault' => $soapFault,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * IATA_AirShoppingRS > Response > ShoppingResponse > ShoppingResponseRefID (namespace-agnostic).
     */
    private function parseShoppingResponseRefId(DOMXPath $xpath): string
    {
        $scoped = $this->firstText(
            $xpath,
            '//*[local-name()="Response"]/*[local-name()="ShoppingResponse"]/*[local-name()="ShoppingResponseRefID"]',
        );
        if ($scoped !== '') {
            return $scoped;
        }

        return $this->firstText($xpath, '//*[local-name()="ShoppingResponseRefID"]');
    }

    /**
     * @return list<string>
     */
    private function parseOrderServiceStatuses(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//*[local-name()="Order"]/*[local-name()="OrderItem"]/*[local-name()="Service"]/*[local-name()="StatusCode"]');
        if ($nodes === false) {
            return [];
        }

        $statuses = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $status = trim($node->textContent ?? '');
            if ($status !== '' && ! in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    /**
     * @return list<string>
     */
    private function parseOrderItemStatuses(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//*[local-name()="Order"]/*[local-name()="OrderItem"]/*[local-name()="StatusCode"]');
        if ($nodes === false) {
            return [];
        }

        $statuses = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $status = trim($node->textContent ?? '');
            if ($status !== '' && ! in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    /**
     * @return list<string>
     */
    private function parseSegmentOperatingStatuses(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//*[local-name()="Order"]//*[local-name()="OperatingCarrierInfo"]/*[local-name()="StatusCode"]');
        if ($nodes === false) {
            return [];
        }

        $statuses = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $status = trim($node->textContent ?? '');
            if ($status !== '' && ! in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
        }

        return $statuses;
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
     * @return array{
     *     allowed_currencies: list<string>,
     *     trip_types: list<string>,
     *     cabin_classes: list<string>,
     *     pax_types: list<string>
     * }
     */
    private function parseGeneralParams(DOMXPath $xpath): array
    {
        return [
            'allowed_currencies' => $this->collectUniqueTexts($xpath, '//*[local-name()="AllowedCurCode"]|//*[local-name()="RequestedCurCode"]|//*[local-name()="CurCode"]'),
            'trip_types' => $this->collectUniqueTexts($xpath, '//*[local-name()="TripPurposeCode"]|//*[local-name()="TripTypeCode"]'),
            'cabin_classes' => $this->collectUniqueTexts($xpath, '//*[local-name()="CabinTypeCode"]'),
            'pax_types' => $this->collectUniqueTexts($xpath, '//*[local-name()="PTC"]'),
        ];
    }

    /**
     * @return array{
     *     routes: list<array{origin: string, destination: string}>,
     *     owner_codes: list<string>
     * }
     */
    private function parseAirlineProfile(DOMXPath $xpath): array
    {
        $routes = [];
        $nodes = $xpath->query('//*[local-name()="OriginDest"]|//*[local-name()="OriginDestCriteria"]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }
                $origin = $this->firstDescendantText($node, 'IATA_LocationCode', 'OriginDepCriteria')
                    ?: $this->firstDescendantText($node, 'OriginCode');
                $destination = $this->firstDescendantText($node, 'IATA_LocationCode', 'DestArrivalCriteria')
                    ?: $this->firstDescendantText($node, 'DestCode');
                if ($origin !== '' || $destination !== '') {
                    $routes[] = ['origin' => $origin, 'destination' => $destination];
                }
            }
        }

        return [
            'routes' => $routes,
            'owner_codes' => $this->collectUniqueTexts($xpath, '//*[local-name()="OwnerCode"]'),
        ];
    }

    /**
     * @return list<string>
     */
    private function collectUniqueTexts(DOMXPath $xpath, string $query): array
    {
        $nodes = $xpath->query($query);
        $values = [];
        if ($nodes === false) {
            return $values;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $value = trim($node->textContent ?? '');
            if ($value !== '' && ! in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseOffers(DOMXPath $xpath): array
    {
        $offers = [];
        $nodes = $xpath->query(
            '//*[local-name()="Response"]//*[local-name()="OffersGroup"]//*[local-name()="CarrierOffers"]/*[local-name()="Offer"]'
            .'|//*[local-name()="CarrierOffers"]/*[local-name()="Offer"]',
        );
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
            $offerItems = $this->parseOfferItems($node);
            $totalPrice = $this->parsePriceNode($this->firstChild($node, 'TotalPrice'));
            if (($totalPrice['total'] ?? 0.0) <= 0.0) {
                $totalPrice = $this->resolveOfferTotalPriceFallback($offerItems, $totalPrice);
            }
            $offers[] = [
                'offer_id' => $offerId,
                'owner_code' => $this->childText($node, 'OwnerCode'),
                'offer_items' => $offerItems,
                'total_price' => $totalPrice,
                'journey_refs' => $this->parseOfferJourneyRefs($node),
                'price_class_text' => $this->firstDescendantText($node, 'PriceClassText')
                    ?: $this->firstDescendantText($node, 'CabinTypeName', 'JourneyPriceClass'),
            ];
        }

        return $offers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsePricedOffers(DOMXPath $xpath): array
    {
        $offers = [];
        $nodes = $xpath->query(
            '//*[local-name()="Response"]//*[local-name()="PricedOffer"]/*[local-name()="Offer"]'
            .'|//*[local-name()="PricedOffer"]/*[local-name()="Offer"]'
            .'|//*[local-name()="Response"]//*[local-name()="PricedOffer"][*[local-name()="OfferID"]]',
        );
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
            $offerItems = $this->parseOfferItems($node);
            $totalPrice = $this->parsePriceNode($this->firstChild($node, 'TotalPrice'));
            if (($totalPrice['total'] ?? 0.0) <= 0.0) {
                $totalPrice = $this->resolveOfferTotalPriceFallback($offerItems, $totalPrice);
            }
            $totalPriceNode = $this->firstChild($node, 'TotalPrice');
            if ($totalPriceNode instanceof DOMNode) {
                $feeData = $this->parseFeeLines($totalPriceNode);
                $totalPrice['fees'] = $feeData['fees'];
                $totalPrice['fee_amount_total'] = $feeData['fee_amount_total'];
                $totalPrice['fee_descriptions'] = $feeData['fee_descriptions'];
            }
            $offers[] = [
                'offer_id' => $offerId,
                'owner_code' => $this->childText($node, 'OwnerCode'),
                'offer_items' => $offerItems,
                'total_price' => $totalPrice,
                'journey_refs' => $this->parseOfferJourneyRefs($node),
                'price_class_text' => $this->firstDescendantText($node, 'PriceClassText')
                    ?: $this->firstDescendantText($node, 'CabinTypeName', 'JourneyPriceClass'),
            ];
        }

        return $offers;
    }

    /**
     * @return array{
     *     total: float,
     *     base: float,
     *     tax: float,
     *     currency: string,
     *     priced_offer_ref_id: string,
     *     payment_time_limit: string,
     *     fee_amount_total: float,
     *     fee_descriptions: list<string>,
     *     fees: list<array{amount: float, desc_text: string, currency: string}>
     * }
     */
    private function parseOfferPriceSummary(DOMXPath $xpath): array
    {
        $summary = [
            'total' => 0.0,
            'base' => 0.0,
            'tax' => 0.0,
            'currency' => 'PKR',
            'priced_offer_ref_id' => '',
            'payment_time_limit' => '',
            'fee_amount_total' => 0.0,
            'fee_descriptions' => [],
            'fees' => [],
        ];

        $pricedOffers = $this->parsePricedOffers($xpath);
        if ($pricedOffers !== []) {
            $first = $pricedOffers[0];
            $price = is_array($first['total_price'] ?? null) ? $first['total_price'] : [];
            $summary['total'] = (float) ($price['total'] ?? 0);
            $summary['base'] = (float) ($price['base'] ?? 0);
            $summary['tax'] = (float) ($price['tax'] ?? 0);
            $summary['currency'] = (string) ($price['currency'] ?? 'PKR');
            $summary['priced_offer_ref_id'] = (string) ($first['offer_id'] ?? '');
            $firstItem = is_array($first['offer_items'][0] ?? null) ? $first['offer_items'][0] : [];
            $summary['payment_time_limit'] = (string) ($firstItem['payment_time_limit'] ?? '');
            $feeData = $this->extractFeeDataFromPrice(is_array($price) ? $price : []);
            $summary['fees'] = $feeData['fees'];
            $summary['fee_amount_total'] = $feeData['fee_amount_total'];
            $summary['fee_descriptions'] = $feeData['fee_descriptions'];

            return $summary;
        }

        $pricedOfferNode = $xpath->query('//*[local-name()="Response"]//*[local-name()="PricedOffer"]')->item(0);
        if (! $pricedOfferNode instanceof DOMNode) {
            $pricedOfferNode = $xpath->query('//*[local-name()="PricedOffer"]')->item(0);
        }
        if ($pricedOfferNode instanceof DOMNode) {
            $totalPriceNode = $this->firstDescendantNode($pricedOfferNode, 'TotalPrice')
                ?: $this->firstChild($pricedOfferNode, 'TotalPrice');
            if ($totalPriceNode instanceof DOMNode) {
                $price = $this->parsePriceNode($totalPriceNode);
                $feeData = $this->parseFeeLines($totalPriceNode);
                $summary['total'] = (float) ($price['total'] ?? 0);
                $summary['base'] = (float) ($price['base'] ?? 0);
                $summary['tax'] = (float) ($price['tax'] ?? 0);
                $summary['currency'] = (string) ($price['currency'] ?? 'PKR');
                $summary['fees'] = $feeData['fees'];
                $summary['fee_amount_total'] = $feeData['fee_amount_total'];
                $summary['fee_descriptions'] = $feeData['fee_descriptions'];
            }
        }

        if ($summary['total'] <= 0.0) {
            $amountNode = $xpath->query(
                '//*[local-name()="Response"]//*[local-name()="PricedOffer"]//*[local-name()="TotalAmount"]',
            )->item(0);
            if (! $amountNode instanceof DOMNode) {
                $amountNode = $xpath->query('//*[local-name()="PricedOffer"]//*[local-name()="TotalAmount"]')->item(0);
            }
            if ($amountNode instanceof DOMNode) {
                $summary['total'] = (float) trim($amountNode->textContent ?? '0');
                $summary['currency'] = $this->nodeCurCode($amountNode) ?? $summary['currency'];
            }
        }

        if ($summary['fees'] === [] && $pricedOfferNode instanceof DOMNode) {
            $feeData = $this->parseFeeLines($pricedOfferNode);
            $summary['fees'] = $feeData['fees'];
            $summary['fee_amount_total'] = $feeData['fee_amount_total'];
            $summary['fee_descriptions'] = $feeData['fee_descriptions'];
        }

        $offerId = $this->firstText($xpath, '//*[local-name()="PricedOffer"]/*[local-name()="OfferID"]');
        if ($offerId === '') {
            $offerId = $this->firstText($xpath, '//*[local-name()="PricedOffer"]//*[local-name()="OfferID"]');
        }
        $summary['priced_offer_ref_id'] = $offerId;
        $summary['payment_time_limit'] = $this->firstText($xpath, '//*[local-name()="PricedOffer"]//*[local-name()="PaymentTimeLimitDateTime"]');

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $price
     * @return array{
     *     fees: list<array{amount: float, desc_text: string, currency: string}>,
     *     fee_amount_total: float,
     *     fee_descriptions: list<string>
     * }
     */
    private function extractFeeDataFromPrice(array $price): array
    {
        $fees = is_array($price['fees'] ?? null) ? $price['fees'] : [];

        return $this->summarizeFeeLines($fees);
    }

    /**
     * @return array{
     *     fees: list<array{amount: float, desc_text: string, currency: string}>,
     *     fee_amount_total: float,
     *     fee_descriptions: list<string>
     * }
     */
    private function parseFeeLines(DOMNode $scopeNode): array
    {
        $fees = [];
        $document = $scopeNode->ownerDocument;
        if ($document === null) {
            return $this->summarizeFeeLines($fees);
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('.//*[local-name()="Fee"]', $scopeNode);
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }
                $amountNode = null;
                foreach ($node->getElementsByTagName('Amount') as $element) {
                    if ($element instanceof DOMNode) {
                        $amountNode = $element;
                        break;
                    }
                }
                $amount = $amountNode instanceof DOMNode ? (float) trim($amountNode->textContent ?? '0') : 0.0;
                $currency = $amountNode instanceof DOMNode
                    ? ($this->nodeCurCode($amountNode) ?? 'PKR')
                    : 'PKR';
                $desc = $this->firstDescendantText($node, 'DescText');
                $fees[] = [
                    'amount' => $amount,
                    'desc_text' => $desc,
                    'currency' => $currency,
                ];
            }
        }

        return $this->summarizeFeeLines($fees);
    }

    /**
     * @param  list<array{amount: float, desc_text: string, currency: string}>  $fees
     * @return array{
     *     fees: list<array{amount: float, desc_text: string, currency: string}>,
     *     fee_amount_total: float,
     *     fee_descriptions: list<string>
     * }
     */
    private function summarizeFeeLines(array $fees): array
    {
        $total = 0.0;
        $descriptions = [];
        foreach ($fees as $fee) {
            if (! is_array($fee)) {
                continue;
            }
            $total += (float) ($fee['amount'] ?? 0);
            $desc = trim((string) ($fee['desc_text'] ?? ''));
            if ($desc !== '' && ! in_array($desc, $descriptions, true)) {
                $descriptions[] = $desc;
            }
        }

        return [
            'fees' => $fees,
            'fee_amount_total' => $total,
            'fee_descriptions' => $descriptions,
        ];
    }

    /**
     * @return list<string>
     */
    private function parseOfferJourneyRefs(DOMNode $offerNode): array
    {
        $refs = [];
        $document = $offerNode->ownerDocument;
        if ($document === null) {
            return $refs;
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(
            './/*[local-name()="JourneyOverview"]//*[local-name()="PaxJourneyRefID"]|.//*[local-name()="PaxJourneyRefID"]',
            $offerNode,
        );
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }
                $value = trim($node->textContent ?? '');
                if ($value !== '' && ! in_array($value, $refs, true)) {
                    $refs[] = $value;
                }
            }
        }

        return $refs;
    }

    /**
     * @param  list<array<string, mixed>>  $offerItems
     * @param  array{total: float, base: float, tax: float, currency: string}  $fallback
     * @return array{total: float, base: float, tax: float, currency: string}
     */
    private function resolveOfferTotalPriceFallback(array $offerItems, array $fallback): array
    {
        $total = 0.0;
        $base = 0.0;
        $tax = 0.0;
        $currency = $fallback['currency'] ?? 'PKR';

        foreach ($offerItems as $item) {
            if (! is_array($item) || ! ($item['mandatory'] ?? true)) {
                continue;
            }
            $price = is_array($item['price'] ?? null) ? $item['price'] : [];
            $total += (float) ($price['total'] ?? 0);
            $base += (float) ($price['base'] ?? 0);
            $tax += (float) ($price['tax'] ?? 0);
            if (($price['currency'] ?? '') !== '') {
                $currency = (string) $price['currency'];
            }
        }

        if ($total <= 0.0) {
            return $fallback;
        }

        return [
            'total' => $total,
            'base' => $base,
            'tax' => $tax,
            'currency' => $currency,
        ];
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
            $mandatory = strtolower($this->childText($child, 'MandatoryInd'));
            $items[] = [
                'offer_item_id' => $this->childText($child, 'OfferItemID'),
                'pax_refs' => $this->childTexts($child, 'PaxRefID'),
                'price' => $this->parsePriceNode($this->firstChild($child, 'Price')),
                'fare_basis' => $this->firstDescendantText($child, 'FareBasisCode'),
                'fare_type_code' => $this->firstDescendantText($child, 'FareTypeCode'),
                'rbd' => $this->firstDescendantText($child, 'RBD_Code')
                    ?: $this->firstDescendantText($child, 'RBD'),
                'cabin' => $this->firstDescendantText($child, 'CabinTypeName')
                    ?: $this->firstDescendantText($child, 'CabinTypeCode'),
                'payment_time_limit' => $this->firstDescendantText($child, 'PaymentTimeLimitDateTime'),
                'service_definition_ref_ids' => $this->parseServiceDefinitionRefIds($child),
                'mandatory' => $mandatory === '' || $mandatory === 'true',
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function parseServiceDefinitionRefIds(DOMNode $offerItemNode): array
    {
        $refs = [];
        foreach ($offerItemNode->getElementsByTagName('ServiceDefinitionRefID') as $element) {
            if (! $element instanceof DOMNode) {
                continue;
            }
            $value = trim($element->textContent ?? '');
            if ($value !== '' && ! in_array($value, $refs, true)) {
                $refs[] = $value;
            }
        }

        return $refs;
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
            'baggage_allowances' => $this->parseBaggageAllowances($xpath),
            'service_definitions' => $this->parseServiceDefinitions($xpath),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseBaggageAllowances(DOMXPath $xpath): array
    {
        $allowances = [];
        $nodes = $xpath->query('//*[local-name()="BaggageAllowanceList"]/*[local-name()="BaggageAllowance"]');
        if ($nodes === false) {
            return $allowances;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $weightNode = null;
            foreach ($node->getElementsByTagName('MaximumWeightMeasure') as $element) {
                if ($element instanceof DOMNode) {
                    $weightNode = $element;
                    break;
                }
            }
            $allowances[] = [
                'baggage_allowance_id' => $this->childText($node, 'BaggageAllowanceID'),
                'type_code' => $this->childText($node, 'TypeCode'),
                'maximum_weight' => $weightNode instanceof DOMNode ? trim($weightNode->textContent ?? '') : '',
                'unit_code' => $weightNode instanceof DOMNode
                    ? trim($weightNode->attributes?->getNamedItem('UnitCode')?->nodeValue ?? 'KG')
                    : 'KG',
            ];
        }

        return $allowances;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseServiceDefinitions(DOMXPath $xpath): array
    {
        $definitions = [];
        $nodes = $xpath->query('//*[local-name()="ServiceDefinitionList"]/*[local-name()="ServiceDefinition"]');
        if ($nodes === false) {
            return $definitions;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $definitions[] = [
                'service_definition_id' => $this->childText($node, 'ServiceDefinitionID'),
                'name' => $this->childText($node, 'Name'),
                'service_code' => $this->childText($node, 'ServiceCode'),
                'baggage_allowance_ref_id' => $this->firstDescendantText($node, 'BaggageAllowanceRefID'),
            ];
        }

        return $definitions;
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
            $marketingCarrier = $this->descendantTextUnderParent($node, 'CarrierDesigCode', 'MarketingCarrierInfo');
            $operatingCarrier = $this->descendantTextUnderParent($node, 'CarrierDesigCode', 'OperatingCarrierInfo');
            $segments[] = [
                'pax_segment_id' => $this->childText($node, 'PaxSegmentID'),
                'departure_airport' => $this->firstDescendantText($node, 'IATA_LocationCode', 'Dep'),
                'arrival_airport' => $this->firstDescendantText($node, 'IATA_LocationCode', 'Arrival'),
                'departure_at' => $this->firstDescendantText($node, 'AircraftScheduledDateTime', 'Dep'),
                'arrival_at' => $this->firstDescendantText($node, 'AircraftScheduledDateTime', 'Arrival'),
                'carrier' => $marketingCarrier ?: $operatingCarrier,
                'marketing_carrier' => $marketingCarrier,
                'operating_carrier' => $operatingCarrier,
                'flight_number' => $this->descendantTextUnderParent($node, 'MarketingCarrierFlightNumberText', 'MarketingCarrierInfo')
                    ?: $this->descendantTextUnderParent($node, 'OperatingCarrierFlightNumberText', 'OperatingCarrierInfo'),
                'operating_flight_number' => $this->descendantTextUnderParent($node, 'OperatingCarrierFlightNumberText', 'OperatingCarrierInfo'),
                'duration' => $this->childText($node, 'Duration'),
                'aircraft_type' => $this->firstDescendantText($node, 'CarrierAircraftTypeName'),
                'segment_type_code' => $this->childText($node, 'SegmentTypeCode'),
                'rbd' => $this->firstDescendantText($node, 'RBD_Code') ?: $this->firstDescendantText($node, 'RBD'),
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
            'payment_time_limit' => $this->firstDescendantText($node, 'PaymentTimeLimitDateTime'),
        ];
    }

    /**
     * @return list<array{booking_id: string, airline_desig_code: string}>
     */
    private function parseBookingRefs(DOMXPath $xpath): array
    {
        $refs = [];
        $nodes = $xpath->query('//*[local-name()="BookingRef"]');
        if ($nodes === false) {
            return $refs;
        }

        $seen = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $bookingId = $this->firstDescendantText($node, 'BookingID');
            if ($bookingId === '' || isset($seen[$bookingId])) {
                continue;
            }
            $seen[$bookingId] = true;
            $refs[] = [
                'booking_id' => $bookingId,
                'airline_desig_code' => $this->firstDescendantText($node, 'AirlineDesigCode'),
            ];
        }

        return $refs;
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
            $couponStatusCodes = [];
            $couponNodes = $xpath->query('.//*[local-name()="Coupon"]/*[local-name()="CouponStatusCode"]', $node);
            if ($couponNodes !== false) {
                foreach ($couponNodes as $couponNode) {
                    if (! $couponNode instanceof DOMNode) {
                        continue;
                    }
                    $status = strtoupper(trim($couponNode->textContent ?? ''));
                    if ($status !== '' && ! in_array($status, $couponStatusCodes, true)) {
                        $couponStatusCodes[] = $status;
                    }
                }
            }
            $tickets[] = array_filter([
                'ticket_number' => $number,
                'pax_ref' => $this->childText($node, 'PaxRefID') ?: null,
                'coupon_status_codes' => $couponStatusCodes !== [] ? $couponStatusCodes : null,
            ], fn ($value) => $value !== null);
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

        $totalNode = $this->lastChild($node, 'TotalAmount');
        $equivNode = $this->firstChild($node, 'EquivAmount');
        $baseNode = $this->firstChild($node, 'BaseAmount');
        $taxSummaryNode = $this->firstChild($node, 'TaxSummary');
        $taxNode = $taxSummaryNode instanceof DOMNode
            ? $this->firstChild($taxSummaryNode, 'TotalTaxAmount')
            : null;

        $total = $totalNode instanceof DOMNode ? (float) trim($totalNode->textContent ?? '0') : 0.0;
        $tax = $taxNode instanceof DOMNode ? (float) trim($taxNode->textContent ?? '0') : 0.0;
        $currency = $this->nodeCurCode($totalNode)
            ?? $this->nodeCurCode($equivNode)
            ?? $this->nodeCurCode($baseNode)
            ?? 'PKR';

        if ($equivNode instanceof DOMNode && trim($equivNode->textContent ?? '') !== '') {
            $base = (float) trim($equivNode->textContent ?? '0');
        } elseif ($baseNode instanceof DOMNode) {
            $base = (float) trim($baseNode->textContent ?? '0');
        } else {
            $base = 0.0;
        }

        return ['total' => $total, 'base' => $base, 'tax' => $tax, 'currency' => $currency];
    }

    private function firstDescendantNode(DOMNode $node, string $localName): ?DOMNode
    {
        foreach ($node->getElementsByTagName($localName) as $element) {
            if ($element instanceof DOMNode) {
                return $element;
            }
        }

        return null;
    }

    private function nodeCurCode(?DOMNode $node): ?string
    {
        if (! $node instanceof DOMNode) {
            return null;
        }
        $value = trim($node->attributes?->getNamedItem('CurCode')?->nodeValue ?? '');

        return $value !== '' ? $value : null;
    }

    private function descendantTextUnderParent(DOMNode $node, string $localName, string $parentLocalName): string
    {
        foreach ($node->getElementsByTagName($localName) as $element) {
            if (! $element instanceof DOMNode) {
                continue;
            }
            $parent = $element->parentNode;
            while ($parent instanceof DOMNode) {
                if ($parent->localName === $parentLocalName) {
                    return trim($element->textContent ?? '');
                }
                $parent = $parent->parentNode;
            }
        }

        return '';
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

    private function lastChild(DOMNode $parent, string $localName): ?DOMNode
    {
        $found = null;
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMNode && $child->localName === $localName) {
                $found = $child;
            }
        }

        return $found;
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
