<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Data\FlightSearchRequestData;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Support\Phone\SupplierContactFormatter;
use DOMDocument;
use DOMElement;

/**
 * Builds SOAP XML request payloads for PIA Hitit Crane NDC 20.1 operations.
 */
class PiaNdcXmlBuilder
{
    private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    public const OFFER_PRICE_SHAPE_CURRENT = 'current_priced_offer_selected_offer';

    /** @var list<string> */
    public const OFFER_PRICE_PROBE_SHAPES = [
        'current_priced_offer_selected_offer',
        'selected_offer_without_priced_offer_wrapper',
        'selected_offer_with_offer_id_tag',
        'selected_offer_with_offer_item_id_tag',
        'priced_offer_with_offer_id_and_offer_item_id_tags',
        'current_shape_without_shopping_response_ref_id',
        'shopping_response_ref_before_selected_offer',
        'selected_offer_with_owner_inside_offer_item',
        'selected_offer_all_offer_items',
        'selected_offer_with_shopping_response_object',
    ];

    public const CANCEL_SHAPE_CURRENT = 'current_order_change_cancel_order';

    /** Shapes permitted for live supplier cancel preview (doOrderCancelPreview only). */
    public const CANCEL_EXECUTE_ALLOWED_SHAPES = [
        'hitit_cancel_preview_sample_exact',
        'hitit_cancel_preview_sample_exact_with_contact_info',
        'hitit_cancel_preview_sample_exact_with_orderref_owner_attr',
    ];

    /** Shapes blocked from live execute (all except {@see CANCEL_EXECUTE_ALLOWED_SHAPES}). */
    public const CANCEL_EXECUTE_BLOCKED_SHAPES = [
        'hitit_cancel_commit_sample_exact',
        'hitit_exact_from_sample_v2',
        'hitit_manual_view_only_exact',
        'hitit_manual_commit_exact',
        'current_order_change_cancel_order',
        'order_change_cancel_order_with_owner_inside_cancel',
        'order_change_cancel_order_only',
        'order_change_order_ref_top_level',
        'sample_exact_cancel_request',
        'order_reshop_cancel_preview',
        'order_cancel_rq_order_id',
        'order_cancel_rq_order_ref',
    ];

    /**
     * Official Hitit PIA-NDC 20.1 cancel samples (PIA_NDC_20.1_SCHEMA_UPGRADE_NEW SAMPLES.zip).
     *
     * @var list<string>
     */
    public const HITIT_OFFICIAL_CANCEL_SAMPLE_FILES = [
        'doOrderCancelPreview_OW_req.xml',
        'doOrderCancelPreview_OW_res.xml',
        'doOrderCancelCommit_OW_req.xml',
        'doOrderCancelCommit_OW_res.xml',
    ];

    /** @var list<string> Legacy IATA_OrderCancelRQ probe shapes (not Hitit §3.3 flow). */
    public const CANCEL_LEGACY_PROBE_SHAPES = [
        'order_cancel_rq_order_id',
        'order_cancel_rq_order_ref',
    ];

    /** Manual §3.3 sample filenames (authoritative names in HITIT_CRANENDC_20.1 manual). */
    public const HITIT_MANUAL_CANCEL_SAMPLE_FILES = [
        'DoOrderCancel_VIEW_ONLY_req.txt' => 'tests/Fixtures/pia-ndc/doOrderCancelPreview_OW_req.xml',
        'DoOrderCancel_COMMIT_req.txt' => 'tests/Fixtures/pia-ndc/doOrderCancelCommit_OW_req.xml',
        'DoOrderCancel_COMMIT_res.txt' => 'tests/Fixtures/pia-ndc/doOrderCancelCommit_OW_res.xml',
    ];

    /** @var list<string> */
    public const CANCEL_PROBE_SHAPES = [
        'hitit_cancel_preview_sample_exact',
        'hitit_cancel_preview_sample_exact_with_contact_info',
        'hitit_cancel_preview_sample_exact_with_orderref_owner_attr',
        'hitit_cancel_commit_sample_exact',
        'current_order_change_cancel_order',
        'order_change_cancel_order_with_owner_inside_cancel',
        'order_change_cancel_order_only',
        'order_change_order_ref_top_level',
        'sample_exact_cancel_request',
        'hitit_exact_from_sample_v2',
        'hitit_manual_view_only_exact',
        'hitit_manual_commit_exact',
        'order_reshop_cancel_preview',
        'order_cancel_rq_order_id',
        'order_cancel_rq_order_ref',
    ];

    /** @var list<string> */
    public const CANCEL_PROBE_OPERATIONS = [
        'cancel_commit',
        'cancel_preview',
        'order_change',
        'cancel',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildAirShoppingRequest(FlightSearchRequestData $request, array $config): string
    {
        $ns = $this->namespaceFor('IATA_AirShoppingRQ');
        $doc = $this->newEnvelope($ns, 'IATA_AirShoppingRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_AirShoppingRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build air shopping request.');
        }

        $messageDoc = $doc->createElement('MessageDoc');
        $messageDoc->appendChild($doc->createElement('RefVersionNumber', '20.1'));
        $root->appendChild($messageDoc);
        $root->appendChild($this->partyBlock($doc, $config));

        $req = $doc->createElement('Request');
        $flightRequest = $doc->createElement('FlightRequest');
        $flightRequest->appendChild($this->originDestCriteria($doc, $request->origin, $request->destination, $request->departure_date, $request->cabin ?? 'Y'));
        if ($request->return_date) {
            $flightRequest->appendChild($this->originDestCriteria($doc, $request->destination, $request->returnOrigin(), $request->return_date, $request->cabin ?? 'Y'));
        }
        $req->appendChild($flightRequest);

        $paxs = $doc->createElement('Paxs');
        $index = 1;
        foreach ($this->paxCounts($request) as $ptc => $count) {
            for ($i = 0; $i < $count; $i++) {
                $pax = $doc->createElement('Pax');
                $pax->appendChild($doc->createElement('PaxID', 'SH'.$index));
                $pax->appendChild($doc->createElement('PTC', $ptc));
                $paxs->appendChild($pax);
                $index++;
            }
        }
        $req->appendChild($paxs);

        $responseParams = $doc->createElement('ResponseParameters');
        $cur = $doc->createElement('CurParameter');
        $cur->appendChild($doc->createElement('RequestedCurCode', $config['currency']));
        $responseParams->appendChild($cur);
        $lang = $doc->createElement('LangUsage');
        $lang->appendChild($doc->createElement('LangCode', $config['language_code']));
        $responseParams->appendChild($lang);
        $req->appendChild($responseParams);
        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildGeneralParamsRequest(array $config): string
    {
        $ns = $this->namespaceFor('IATA_GeneralParamsRQ');
        $doc = $this->newEnvelope($ns, 'IATA_GeneralParamsRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_GeneralParamsRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build general params request.');
        }

        $messageDoc = $doc->createElement('MessageDoc');
        $messageDoc->appendChild($doc->createElement('RefVersionNumber', '20.1'));
        $root->appendChild($messageDoc);
        $root->appendChild($this->partyBlock($doc, $config));
        $root->appendChild($doc->createElement('Request'));

        return $doc->saveXML() ?: '';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildAirlineProfileRequest(array $config, ?string $origin = null): string
    {
        $ns = $this->namespaceFor('IATA_AirlineProfileRQ');
        $doc = $this->newEnvelope($ns, 'IATA_AirlineProfileRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_AirlineProfileRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build airline profile request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $payload = $doc->createElement('PayloadAttributes');
        $payload->appendChild($doc->createElement('PrimaryLangID', $config['language_code']));
        $root->appendChild($payload);

        $req = $doc->createElement('Request');
        $filter = $doc->createElement('AirlineProfileFilterCriteria');
        $filter->appendChild($doc->createElement('OwnerCode', (string) $config['owner_code']));
        if ($origin !== null && trim($origin) !== '') {
            $pos = $doc->createElement('POS');
            $location = $doc->createElement('Location');
            $location->appendChild($doc->createElement('IATA_LocationCode', strtoupper(trim($origin))));
            $pos->appendChild($location);
            $filter->appendChild($pos);
        }
        $req->appendChild($filter);
        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $providerContext
     */
    public function buildOfferPriceRequest(
        array $config,
        array $providerContext,
        string $shape = self::OFFER_PRICE_SHAPE_CURRENT,
    ): string {
        $shapeOptions = $this->resolveOfferPriceShapeOptions($shape);
        $this->assertOfferPriceContext($providerContext, $shapeOptions);
        $ns = $this->namespaceFor('IATA_OfferPriceRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OfferPriceRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OfferPriceRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build offer price request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $payload = $doc->createElement('PayloadAttributes');
        $payload->appendChild($doc->createElement('PrimaryLangID', $config['language_code']));
        $root->appendChild($payload);

        $req = $doc->createElement('Request');
        $dataLists = $doc->createElement('DataLists');
        $dataLists->appendChild($this->offerPricePaxList($doc, $providerContext));
        $req->appendChild($dataLists);

        $params = $doc->createElement('OfferPriceParameters');
        $cur = $doc->createElement('CurParameter');
        $cur->appendChild($doc->createElement('CurCode', $config['currency']));
        $params->appendChild($cur);
        $req->appendChild($params);

        $shoppingRef = trim((string) ($providerContext['shopping_response_ref_id'] ?? ''));
        if ($shapeOptions['shopping_response_placement'] === 'shopping_response_object' && $shoppingRef !== '') {
            $shoppingResponse = $doc->createElement('ShoppingResponse');
            $shoppingResponse->appendChild($doc->createElement('ShoppingResponseRefID', $shoppingRef));
            $req->appendChild($shoppingResponse);
        } elseif ($shapeOptions['shopping_response_placement'] === 'before_selected_offer' && $shoppingRef !== '') {
            $req->appendChild($doc->createElement('ShoppingResponseRefID', $shoppingRef));
        }

        $selectedOffer = $this->selectedOfferElement($doc, $providerContext, $config, $shapeOptions);
        if ($shapeOptions['wrapper'] === 'priced_offer') {
            $pricedOffer = $doc->createElement('PricedOffer');
            $pricedOffer->appendChild($selectedOffer);
            $req->appendChild($pricedOffer);
        } else {
            $req->appendChild($selectedOffer);
        }

        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOfferPriceShapeOptions(string $shape): array
    {
        $defaults = [
            'wrapper' => 'priced_offer',
            'offer_id_tag' => 'OfferRefID',
            'offer_item_id_tag' => 'OfferItemRefID',
            'include_shopping_response_ref_id' => true,
            'shopping_response_placement' => 'inside_selected_offer',
            'owner_code_on_item' => false,
            'all_offer_items' => true,
        ];

        $specific = match ($shape) {
            'selected_offer_without_priced_offer_wrapper' => ['wrapper' => 'none'],
            'selected_offer_with_offer_id_tag' => ['wrapper' => 'none', 'offer_id_tag' => 'OfferID'],
            'selected_offer_with_offer_item_id_tag' => ['wrapper' => 'none', 'offer_item_id_tag' => 'OfferItemID'],
            'priced_offer_with_offer_id_and_offer_item_id_tags' => [
                'offer_id_tag' => 'OfferID',
                'offer_item_id_tag' => 'OfferItemID',
            ],
            'current_shape_without_shopping_response_ref_id' => ['include_shopping_response_ref_id' => false],
            'shopping_response_ref_before_selected_offer' => [
                'wrapper' => 'none',
                'shopping_response_placement' => 'before_selected_offer',
                'include_shopping_response_ref_id' => false,
            ],
            'selected_offer_with_owner_inside_offer_item' => ['owner_code_on_item' => true],
            'selected_offer_all_offer_items' => ['all_offer_items' => true],
            'selected_offer_with_shopping_response_object' => [
                'wrapper' => 'none',
                'shopping_response_placement' => 'shopping_response_object',
                'include_shopping_response_ref_id' => false,
            ],
            default => [],
        };

        return array_merge($defaults, $specific);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $providerContext
     * @param  list<array<string, mixed>>  $passengers
     * @param  array<string, mixed>  $contact
     */
    public function buildOrderCreateRequest(array $config, array $providerContext, array $passengers, array $contact): string
    {
        $this->assertOrderCreateContext($providerContext);
        $ns = $this->namespaceFor('IATA_OrderCreateRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderCreateRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderCreateRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build order create request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $payload = $doc->createElement('PayloadAttributes');
        $payload->appendChild($doc->createElement('PrimaryLangID', $config['language_code']));
        $root->appendChild($payload);

        $req = $doc->createElement('Request');
        $createOrder = $doc->createElement('CreateOrder');
        $createOrder->appendChild($this->selectedOfferElement($doc, $providerContext, $config));
        $req->appendChild($createOrder);

        $dataLists = $doc->createElement('DataLists');
        $dataLists->appendChild($this->contactInfoList($doc, $contact));
        $dataLists->appendChild($this->paxList($doc, $passengers));
        $req->appendChild($dataLists);

        $params = $doc->createElement('OrderCreateParameters');
        $cur = $doc->createElement('CurParameter');
        $cur->appendChild($doc->createElement('CurCode', $config['currency']));
        $params->appendChild($cur);
        $req->appendChild($params);
        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildOrderRetrieveRequest(array $config, string $orderId, string $ownerCode): string
    {
        $ns = $this->namespaceFor('IATA_OrderRetrieveRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderRetrieveRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderRetrieveRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build order retrieve request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $payload = $doc->createElement('PayloadAttributes');
        $payload->appendChild($doc->createElement('PrimaryLangID', $config['language_code']));
        $root->appendChild($payload);

        $req = $doc->createElement('Request');
        $filter = $doc->createElement('OrderFilterCriteria');
        $order = $doc->createElement('Order');
        $order->appendChild($doc->createElement('OrderID', $orderId));
        $order->appendChild($doc->createElement('OwnerCode', $ownerCode));
        $filter->appendChild($order);
        $req->appendChild($filter);
        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildTicketPreviewRequest(array $config, string $orderId, string $ownerCode): string
    {
        return $this->buildOrderChangeShell($config, $orderId, $ownerCode, null);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array{amount: float, currency: string, ticket_id?: string}  $payment
     */
    public function buildTicketingOrderChangeRequest(array $config, string $orderId, string $ownerCode, array $payment): string
    {
        return $this->buildOrderChangeShell($config, $orderId, $ownerCode, $payment);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildCancelPreviewRequest(array $config, string $orderRefId): string
    {
        return $this->buildHititCancelPreviewSampleExact($config, $orderRefId);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildCancelCommitRequest(array $config, string $orderId, string $ownerCode): string
    {
        return $this->buildCancelDiagnosticRequest($config, $orderId, $ownerCode, self::CANCEL_SHAPE_CURRENT);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildCancelDiagnosticRequest(
        array $config,
        string $orderId,
        string $ownerCode,
        string $shape = self::CANCEL_SHAPE_CURRENT,
    ): string {
        if (! in_array($shape, self::CANCEL_PROBE_SHAPES, true)) {
            throw new PiaNdcValidationException('invalid_cancel_shape', 422, 'Unknown cancel shape: '.$shape);
        }

        return match ($shape) {
            'hitit_cancel_preview_sample_exact', 'hitit_manual_view_only_exact', 'order_reshop_cancel_preview' => $this->buildHititCancelPreviewSampleExact($config, $orderId),
            'hitit_cancel_preview_sample_exact_with_contact_info' => $this->buildHititCancelPreviewSampleExactWithContactInfo($config, $orderId),
            'hitit_cancel_preview_sample_exact_with_orderref_owner_attr' => $this->buildHititCancelPreviewSampleExactWithOrderRefOwnerAttr($config, $orderId, $ownerCode),
            'hitit_cancel_commit_sample_exact', 'hitit_manual_commit_exact', 'hitit_exact_from_sample_v2', 'sample_exact_cancel_request' => $this->buildHititCancelCommitSampleExact($config, $orderId, $ownerCode),
            'order_cancel_rq_order_id' => $this->buildOrderCancelRqRequest($config, $orderId, $ownerCode, 'order_id'),
            'order_cancel_rq_order_ref' => $this->buildOrderCancelRqRequest($config, $orderId, $ownerCode, 'order_ref'),
            default => $this->buildOrderChangeCancelShapeRequest($config, $orderId, $ownerCode, $shape),
        };
    }

    public function isLegacyCancelShape(string $shape): bool
    {
        return in_array($shape, self::CANCEL_LEGACY_PROBE_SHAPES, true);
    }

    public function defaultCancelOperationForShape(string $shape): string
    {
        return match ($shape) {
            'hitit_cancel_preview_sample_exact',
            'hitit_cancel_preview_sample_exact_with_contact_info',
            'hitit_cancel_preview_sample_exact_with_orderref_owner_attr',
            'order_reshop_cancel_preview',
            'hitit_manual_view_only_exact' => 'cancel_preview',
            'order_cancel_rq_order_id', 'order_cancel_rq_order_ref' => 'cancel',
            'hitit_cancel_commit_sample_exact', 'hitit_exact_from_sample_v2', 'hitit_manual_commit_exact', 'sample_exact_cancel_request' => 'cancel_commit',
            default => 'cancel_commit',
        };
    }

    /**
     * Map CLI --operation values to internal config operation keys.
     */
    public function resolveCancelOperationKey(string $operation): string
    {
        $normalized = strtolower(trim($operation));

        return match ($normalized) {
            'doordercancel' => 'cancel',
            'doordercancelpreview' => 'cancel_preview',
            'doorderchange' => 'order_change',
            'doordercancelcommit' => 'cancel_commit',
            'cancel', 'cancel_preview', 'order_change', 'cancel_commit' => $normalized,
            default => throw new PiaNdcValidationException(
                'invalid_cancel_operation',
                422,
                'Unknown cancel operation: '.$operation,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildOrderChangeCancelShapeRequest(
        array $config,
        string $orderId,
        string $ownerCode,
        string $shape,
    ): string {
        $ns = $this->namespaceFor('IATA_OrderChangeRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderChangeRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderChangeRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build cancel commit request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $payload = $doc->createElement('PayloadAttributes');
        $payload->appendChild($doc->createElement('PrimaryLangID', $config['language_code']));
        $root->appendChild($payload);

        $req = $doc->createElement('Request');

        if ($shape === 'order_change_order_ref_top_level') {
            $req->appendChild($doc->createElement('OrderRefID', $orderId));
            $req->appendChild($doc->createElement('OwnerCode', $ownerCode));
        }

        $change = $doc->createElement('ChangeOrder');
        $cancel = $doc->createElement('CancelOrder');
        $cancel->appendChild($doc->createElement('OrderRefID', $orderId));
        if ($shape === 'order_change_cancel_order_with_owner_inside_cancel') {
            $cancel->appendChild($doc->createElement('OwnerCode', $ownerCode));
        }
        $change->appendChild($cancel);
        $req->appendChild($change);

        if (! in_array($shape, ['order_change_cancel_order_only', 'order_change_order_ref_top_level'], true)) {
            $order = $doc->createElement('Order');
            $order->appendChild($doc->createElement('OrderID', $orderId));
            $order->appendChild($doc->createElement('OwnerCode', $ownerCode));
            $req->appendChild($order);
        }

        $params = $doc->createElement('OrderChangeParameters');
        $cur = $doc->createElement('CurParameter');
        $cur->appendChild($doc->createElement('CurCode', $config['currency']));
        $params->appendChild($cur);
        $req->appendChild($params);
        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    /**
     * Hypothetical IATA_OrderCancelRQ probe shape (no Hitit sample in repo).
     *
     * @param  array<string, mixed>  $config
     */
    private function buildOrderCancelRqRequest(
        array $config,
        string $orderId,
        string $ownerCode,
        string $refStyle,
    ): string {
        $ns = $this->namespaceFor('IATA_OrderCancelRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderCancelRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderCancelRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build order cancel request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $payload = $doc->createElement('PayloadAttributes');
        $payload->appendChild($doc->createElement('PrimaryLangID', $config['language_code']));
        $root->appendChild($payload);

        $req = $doc->createElement('Request');
        if ($refStyle === 'order_ref') {
            $req->appendChild($doc->createElement('OrderRefID', $orderId));
        } else {
            $order = $doc->createElement('Order');
            $order->appendChild($doc->createElement('OrderID', $orderId));
            $order->appendChild($doc->createElement('OwnerCode', $ownerCode));
            $req->appendChild($order);
        }
        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    public function compatibleCancelOperationsForShape(string $shape): array
    {
        return match ($shape) {
            'hitit_cancel_preview_sample_exact',
            'hitit_cancel_preview_sample_exact_with_contact_info',
            'hitit_cancel_preview_sample_exact_with_orderref_owner_attr',
            'order_reshop_cancel_preview',
            'hitit_manual_view_only_exact' => ['cancel_preview'],
            'order_cancel_rq_order_id', 'order_cancel_rq_order_ref' => ['cancel'],
            'hitit_cancel_commit_sample_exact', 'hitit_exact_from_sample_v2', 'hitit_manual_commit_exact', 'sample_exact_cancel_request' => ['cancel_commit'],
            default => ['cancel_commit', 'order_change'],
        };
    }

    /**
     * doOrderCancelPreview — IATA_OrderReshopRQ with Party ContactInfo (R11F variant).
     *
     * @param  array<string, mixed>  $config
     */
    private function buildHititCancelPreviewSampleExactWithContactInfo(array $config, string $orderRefId): string
    {
        return $this->buildHititCancelPreviewRequest($config, $orderRefId, withContactInfo: true);
    }

    /**
     * doOrderCancelPreview — IATA_OrderReshopRQ with ContactInfo and OrderRefID OwnerCode attribute (R11F variant).
     *
     * @param  array<string, mixed>  $config
     */
    private function buildHititCancelPreviewSampleExactWithOrderRefOwnerAttr(
        array $config,
        string $orderRefId,
        string $ownerCode,
    ): string {
        return $this->buildHititCancelPreviewRequest(
            $config,
            $orderRefId,
            withContactInfo: true,
            orderRefOwnerCodeAttr: $ownerCode,
        );
    }

    /**
     * doOrderCancelPreview — IATA_OrderReshopRQ (doOrderCancelPreview_OW_req.xml).
     *
     * @param  array<string, mixed>  $config
     */
    private function buildHititCancelPreviewSampleExact(array $config, string $orderRefId): string
    {
        return $this->buildHititCancelPreviewRequest($config, $orderRefId);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildHititCancelPreviewRequest(
        array $config,
        string $orderRefId,
        bool $withContactInfo = false,
        ?string $orderRefOwnerCodeAttr = null,
    ): string {
        $ns = $this->namespaceFor('IATA_OrderReshopRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderReshopRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderReshopRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build Hitit cancel preview sample request.');
        }

        $root->appendChild($withContactInfo
            ? $this->partyBlockWithContactInfo($doc, $config)
            : $this->partyBlock($doc, $config));
        $req = $doc->createElement('Request');
        $req->appendChild($this->cancelPreviewOrderRefElement($doc, $orderRefId, $orderRefOwnerCodeAttr));
        $params = $doc->createElement('ReshopParameters');
        $cur = $doc->createElement('CurParameter');
        $cur->appendChild($doc->createElement('RequestedCurCode', $config['currency']));
        $params->appendChild($cur);
        $device = $doc->createElement('Device');
        $device->appendChild($doc->createElement('DeviceOwnerTypeCode', 'SL'));
        $params->appendChild($device);
        $lang = $doc->createElement('LangUsage');
        $lang->appendChild($doc->createElement('LangCode', $config['language_code']));
        $params->appendChild($lang);
        $req->appendChild($params);
        $update = $doc->createElement('UpdateOrder');
        $cancel = $doc->createElement('CancelOrder');
        $cancel->appendChild($this->cancelPreviewOrderRefElement($doc, $orderRefId, $orderRefOwnerCodeAttr));
        $update->appendChild($cancel);
        $req->appendChild($update);
        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    private function cancelPreviewOrderRefElement(
        DOMDocument $doc,
        string $orderRefId,
        ?string $ownerCodeAttr,
    ): DOMElement {
        $orderRef = $doc->createElement('OrderRefID', $orderRefId);
        if ($ownerCodeAttr !== null && trim($ownerCodeAttr) !== '') {
            $orderRef->setAttribute('OwnerCode', trim($ownerCodeAttr));
        }

        return $orderRef;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function partyBlockWithContactInfo(DOMDocument $doc, array $config): DOMElement
    {
        $party = $doc->createElement('Party');
        $sender = $doc->createElement('Sender');
        $agency = $doc->createElement('TravelAgency');
        $agency->appendChild($doc->createElement('AgencyID', (string) $config['agency_id']));
        $contactInfo = $doc->createElement('ContactInfo');
        $emailAddress = $doc->createElement('EmailAddress');
        $emailAddress->appendChild($doc->createElement(
            'EmailAddressText',
            (string) ($config['agency_contact_email'] ?? 'ADMIN@JETPAKISTAN.COM'),
        ));
        $contactInfo->appendChild($emailAddress);
        $agency->appendChild($contactInfo);
        $agency->appendChild($doc->createElement('Name', (string) $config['agency_name']));
        $sender->appendChild($agency);
        $party->appendChild($sender);

        return $party;
    }

    /**
     * doOrderCancelCommit — IATA_OrderChangeRQ (doOrderCancelCommit_OW_req.xml).
     *
     * @param  array<string, mixed>  $config
     */
    private function buildHititCancelCommitSampleExact(array $config, string $orderId, string $ownerCode): string
    {
        $ns = $this->namespaceFor('IATA_OrderChangeRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderChangeRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderChangeRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build Hitit manual commit cancel request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $payload = $doc->createElement('PayloadAttributes');
        $payload->appendChild($doc->createElement('PrimaryLangID', $config['language_code']));
        $root->appendChild($payload);

        $req = $doc->createElement('Request');
        $change = $doc->createElement('ChangeOrder');
        $cancel = $doc->createElement('CancelOrder');
        $cancel->appendChild($doc->createElement('OrderRefID', $orderId));
        $change->appendChild($cancel);
        $req->appendChild($change);

        $order = $doc->createElement('Order');
        $order->appendChild($doc->createElement('OrderID', $orderId));
        $order->appendChild($doc->createElement('OwnerCode', $ownerCode));
        $req->appendChild($order);

        $params = $doc->createElement('OrderChangeParameters');
        $cur = $doc->createElement('CurParameter');
        $cur->appendChild($doc->createElement('CurCode', $config['currency']));
        $params->appendChild($cur);
        $req->appendChild($params);
        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    public function soapActionForCancelOperation(string $operationKey): string
    {
        return (string) config('suppliers.pia_ndc.operations.'.$operationKey.'.soap_action', $operationKey);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildVoidTicketRequest(array $config, string $orderId, string $ownerCode): string
    {
        return $this->buildOrderChangeShell($config, $orderId, $ownerCode, null);
    }

    /**
     * @param  array<string, mixed>  $booking
     */
    public function buildPassengersFromBooking(Booking $booking): array
    {
        $booking->loadMissing('passengers');
        $passengers = [];
        $counter = ['ADT' => 1, 'CHD' => 1, 'INF' => 1];
        foreach ($booking->passengers as $passenger) {
            $ptc = strtoupper((string) ($passenger->type ?? 'ADT'));
            if (! in_array($ptc, ['ADT', 'CHD', 'INF'], true)) {
                $ptc = 'ADT';
            }
            $idx = $counter[$ptc]++;
            $passengers[] = [
                'pax_id' => 'PAX-'.$ptc.$idx,
                'ptc' => $ptc,
                'title' => (string) ($passenger->title ?? ''),
                'given_name' => (string) ($passenger->first_name ?? ''),
                'surname' => (string) ($passenger->last_name ?? ''),
                'gender' => strtoupper(substr((string) ($passenger->gender ?? 'M'), 0, 1)),
                'birthdate' => (string) ($passenger->date_of_birth ?? ''),
                'contact_info_ref_id' => 'Contact-1',
            ];
        }

        return $passengers;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContactFromBooking(Booking $booking): array
    {
        $booking->loadMissing('contact');
        $contact = $booking->contact;
        $formatted = SupplierContactFormatter::fromBooking($booking);
        $xml = SupplierContactFormatter::toXmlContact($formatted);

        return [
            'contact_info_id' => 'Contact-1',
            'email' => (string) ($contact->email ?? $booking->contact_email ?? ''),
            'phone_country' => $xml['phone_country'],
            'phone_area' => $xml['phone_area'],
            'phone_number' => $xml['phone_number'],
            'ctcm_text' => $xml['ctcm_text'],
            'ctcb_text' => $xml['ctcb_text'],
            'contact_person_phone' => $xml['contact_person_phone'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildDiagnosticContact(array $input): array
    {
        $phoneRaw = trim((string) ($input['phone'] ?? ''));
        $countryHint = trim((string) ($input['phone_country_code'] ?? $input['phone_country'] ?? ''));
        $areaHint = trim((string) ($input['phone_area_code'] ?? $input['phone_area'] ?? $input['area_code'] ?? ''));
        $formatted = SupplierContactFormatter::format(
            $phoneRaw,
            $countryHint !== '' ? $countryHint : null,
            $areaHint !== '' ? $areaHint : null,
        );
        $xml = SupplierContactFormatter::toXmlContact($formatted);

        return [
            'contact_info_id' => 'Contact-1',
            'email' => trim((string) ($input['email'] ?? '')),
            'phone_country' => $xml['phone_country'],
            'phone_area' => $xml['phone_area'],
            'phone_number' => $xml['phone_number'],
            'ctcm_text' => $xml['ctcm_text'],
            'ctcb_text' => $xml['ctcb_text'],
            'contact_person_phone' => $xml['contact_person_phone'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    public function buildDiagnosticPassengers(array $input, string $paxId = 'ADTPax-1'): array
    {
        return [[
            'pax_id' => $paxId,
            'ptc' => 'ADT',
            'title' => strtoupper(trim((string) ($input['title'] ?? 'MR'))),
            'given_name' => trim((string) ($input['given_name'] ?? '')),
            'surname' => trim((string) ($input['surname'] ?? '')),
            'gender' => strtoupper(substr(trim((string) ($input['gender'] ?? 'M')), 0, 1)),
            'birthdate' => trim((string) ($input['dob'] ?? '')),
            'nationality' => strtoupper(trim((string) ($input['nationality'] ?? ''))),
            'passport_number' => trim((string) ($input['passport_number'] ?? '')),
            'passport_expiry' => trim((string) ($input['passport_expiry'] ?? '')),
            'contact_info_ref_id' => 'Contact-1',
        ]];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  ?array{amount: float, currency: string, ticket_id?: string}  $payment
     */
    private function buildOrderChangeShell(array $config, string $orderId, string $ownerCode, ?array $payment): string
    {
        $ns = $this->namespaceFor('IATA_OrderChangeRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderChangeRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderChangeRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new PiaNdcValidationException('xml_build_failed', 500, 'Failed to build order change request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $payload = $doc->createElement('PayloadAttributes');
        $payload->appendChild($doc->createElement('PrimaryLangID', $config['language_code']));
        $root->appendChild($payload);

        $req = $doc->createElement('Request');
        $order = $doc->createElement('Order');
        $order->appendChild($doc->createElement('OrderID', $orderId));
        $order->appendChild($doc->createElement('OwnerCode', $ownerCode));
        $req->appendChild($order);
        $params = $doc->createElement('OrderChangeParameters');
        $cur = $doc->createElement('CurParameter');
        $cur->appendChild($doc->createElement('CurCode', $config['currency']));
        $params->appendChild($cur);
        $req->appendChild($params);

        if ($payment !== null) {
            $paymentType = strtoupper((string) ($payment['type_code'] ?? $config['payment_type'] ?? 'MCO'));
            $ticketId = trim((string) ($payment['ticket_id'] ?? $config['mco_invoice_number'] ?? ''));
            $paymentFunctions = $doc->createElement('PaymentFunctions');
            $details = $doc->createElement('PaymentProcessingDetails');
            $amount = $doc->createElement('Amount', number_format((float) $payment['amount'], 2, '.', ''));
            $amount->setAttribute('CurCode', (string) ($payment['currency'] ?? $config['currency']));
            $details->appendChild($amount);
            $method = $doc->createElement('PaymentMethod');
            $docEl = $doc->createElement('AccountableDoc');
            $docEl->appendChild($doc->createElement('DocType', $paymentType));
            $docEl->appendChild($doc->createElement('TicketID', $ticketId));
            $method->appendChild($docEl);
            $details->appendChild($method);
            $details->appendChild($doc->createElement('PaymentRefID', 'PaymentInfo1'));
            $details->appendChild($doc->createElement('TypeCode', $paymentType));
            $paymentFunctions->appendChild($details);
            $req->appendChild($paymentFunctions);
        }

        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function partyBlock(DOMDocument $doc, array $config): DOMElement
    {
        $party = $doc->createElement('Party');
        $sender = $doc->createElement('Sender');
        $agency = $doc->createElement('TravelAgency');
        $agency->appendChild($doc->createElement('AgencyID', (string) $config['agency_id']));
        $agency->appendChild($doc->createElement('Name', (string) $config['agency_name']));
        $sender->appendChild($agency);
        $party->appendChild($sender);

        return $party;
    }

    private function originDestCriteria(DOMDocument $doc, string $origin, string $destination, string $date, string $cabin): DOMElement
    {
        $criteria = $doc->createElement('OriginDestCriteria');
        $dest = $doc->createElement('DestArrivalCriteria');
        $dest->appendChild($doc->createElement('IATA_LocationCode', strtoupper($destination)));
        $criteria->appendChild($dest);
        $originDep = $doc->createElement('OriginDepCriteria');
        $originDep->appendChild($doc->createElement('Date', $date));
        $originDep->appendChild($doc->createElement('IATA_LocationCode', strtoupper($origin)));
        $criteria->appendChild($originDep);
        $cabinEl = $doc->createElement('PreferredCabinType');
        $cabinEl->appendChild($doc->createElement('CabinTypeCode', $this->cabinTypeCode($cabin)));
        $criteria->appendChild($cabinEl);

        return $criteria;
    }

    /**
     * @return array<string, int>
     */
    private function paxCounts(FlightSearchRequestData $request): array
    {
        return [
            'ADT' => max(1, $request->adults),
            'CHD' => max(0, $request->children),
            'INF' => max(0, $request->infants),
        ];
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @param  array<string, mixed>  $shapeOptions
     */
    private function assertOfferPriceContext(array $providerContext, array $shapeOptions = []): void
    {
        if (trim((string) ($providerContext['offer_ref_id'] ?? '')) === '') {
            throw new PiaNdcValidationException('missing_provider_context', 422, 'Selected offer context is incomplete for offer price.');
        }

        $requiresShoppingRef = ($shapeOptions['include_shopping_response_ref_id'] ?? true)
            || in_array($shapeOptions['shopping_response_placement'] ?? 'inside_selected_offer', [
                'before_selected_offer',
                'shopping_response_object',
            ], true);

        if ($requiresShoppingRef && trim((string) ($providerContext['shopping_response_ref_id'] ?? '')) === '') {
            throw new PiaNdcValidationException('missing_provider_context', 422, 'Selected offer context is incomplete for offer price.');
        }
    }

    /**
     * @param  array<string, mixed>  $providerContext
     */
    private function assertOrderCreateContext(array $providerContext): void
    {
        $this->assertOfferPriceContext($providerContext);
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $shapeOptions
     */
    private function selectedOfferElement(
        DOMDocument $doc,
        array $providerContext,
        array $config,
        array $shapeOptions = [],
    ): DOMElement {
        $shapeOptions = array_merge([
            'offer_id_tag' => 'OfferRefID',
            'offer_item_id_tag' => 'OfferItemRefID',
            'include_shopping_response_ref_id' => true,
            'shopping_response_placement' => 'inside_selected_offer',
            'owner_code_on_item' => false,
            'all_offer_items' => true,
        ], $shapeOptions);

        $selectedOffer = $doc->createElement('SelectedOffer');
        $selectedOffer->appendChild($doc->createElement(
            (string) $shapeOptions['offer_id_tag'],
            (string) $providerContext['offer_ref_id'],
        ));
        $ownerCode = (string) ($providerContext['owner_code'] ?? $config['owner_code']);
        $selectedOffer->appendChild($doc->createElement('OwnerCode', $ownerCode));

        foreach ($this->selectedOfferItems($providerContext, (bool) $shapeOptions['all_offer_items']) as $item) {
            $offerItem = $doc->createElement('SelectedOfferItem');
            $offerItem->appendChild($doc->createElement(
                (string) $shapeOptions['offer_item_id_tag'],
                (string) $item['offer_item_ref_id'],
            ));
            $offerItem->appendChild($doc->createElement('PaxRefID', (string) $item['pax_ref_id']));
            if ($shapeOptions['owner_code_on_item']) {
                $offerItem->appendChild($doc->createElement('OwnerCode', $ownerCode));
            }
            $selectedOffer->appendChild($offerItem);
        }

        if (
            $shapeOptions['include_shopping_response_ref_id']
            && $shapeOptions['shopping_response_placement'] === 'inside_selected_offer'
        ) {
            $selectedOffer->appendChild($doc->createElement(
                'ShoppingResponseRefID',
                (string) $providerContext['shopping_response_ref_id'],
            ));
        }

        return $selectedOffer;
    }

    /**
     * @param  array<string, mixed>  $providerContext
     */
    private function offerPricePaxList(DOMDocument $doc, array $providerContext): DOMElement
    {
        $list = $doc->createElement('PaxList');
        $seen = [];
        foreach ($this->selectedOfferItems($providerContext) as $item) {
            $paxRef = trim((string) ($item['pax_ref_id'] ?? ''));
            if ($paxRef === '' || isset($seen[$paxRef])) {
                continue;
            }
            $seen[$paxRef] = true;
            $pax = $doc->createElement('Pax');
            $pax->appendChild($doc->createElement('PaxID', $paxRef));
            $pax->appendChild($doc->createElement('PTC', $this->ptcFromPaxRef($paxRef)));
            $list->appendChild($pax);
        }

        if ($seen === []) {
            $paxRef = trim((string) ($providerContext['pax_ref_id'] ?? 'ADTPax-1'));
            $pax = $doc->createElement('Pax');
            $pax->appendChild($doc->createElement('PaxID', $paxRef));
            $pax->appendChild($doc->createElement('PTC', $this->ptcFromPaxRef($paxRef)));
            $list->appendChild($pax);
        }

        return $list;
    }

    private function ptcFromPaxRef(string $paxRef): string
    {
        $upper = strtoupper($paxRef);

        return match (true) {
            str_contains($upper, 'CHD') => 'CHD',
            str_contains($upper, 'INF') => 'INF',
            default => 'ADT',
        };
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @return list<array{offer_item_ref_id: string, pax_ref_id: string}>
     */
    private function selectedOfferItems(array $providerContext, bool $allOfferItems = true): array
    {
        $items = is_array($providerContext['offer_item_refs'] ?? null) ? $providerContext['offer_item_refs'] : [];
        if ($items !== []) {
            return $allOfferItems ? $items : [reset($items)];
        }

        $offerItemRef = trim((string) ($providerContext['offer_item_ref_id'] ?? 'OfferItem-1'));
        $paxRef = trim((string) ($providerContext['pax_ref_id'] ?? 'ADTPax-1'));

        return [['offer_item_ref_id' => $offerItemRef, 'pax_ref_id' => $paxRef]];
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function contactInfoList(DOMDocument $doc, array $contact): DOMElement
    {
        $list = $doc->createElement('ContactInfoList');
        $info = $doc->createElement('ContactInfo');
        $info->appendChild($doc->createElement('ContactInfoID', (string) ($contact['contact_info_id'] ?? 'Contact-1')));
        $email = $doc->createElement('EmailAddress');
        $email->appendChild($doc->createElement('EmailAddressText', (string) ($contact['email'] ?? '')));
        $info->appendChild($email);
        $phone = $doc->createElement('Phone');
        $phone->appendChild($doc->createElement('CountryDialingCode', (string) ($contact['phone_country'] ?? '92')));
        $phone->appendChild($doc->createElement('AreaCodeNumber', (string) ($contact['phone_area'] ?? '')));
        $phone->appendChild($doc->createElement('PhoneNumber', (string) ($contact['phone_number'] ?? '')));
        $info->appendChild($phone);
        $list->appendChild($info);

        return $list;
    }

    /**
     * @param  list<array<string, mixed>>  $passengers
     */
    private function paxList(DOMDocument $doc, array $passengers): DOMElement
    {
        $list = $doc->createElement('PaxList');
        foreach ($passengers as $passenger) {
            $pax = $doc->createElement('Pax');
            if (($passenger['birthdate'] ?? '') !== '') {
                $pax->appendChild($doc->createElement('Birthdate', (string) $passenger['birthdate']));
            }
            if (($passenger['contact_info_ref_id'] ?? '') !== '') {
                $pax->appendChild($doc->createElement('ContactInfoRefID', (string) $passenger['contact_info_ref_id']));
            }
            if (($passenger['nationality'] ?? '') !== '') {
                $pax->appendChild($doc->createElement('CitizenshipCountryCode', (string) $passenger['nationality']));
            }
            if (($passenger['passport_number'] ?? '') !== '') {
                $pax->appendChild($this->identityDocElement($doc, $passenger));
            }
            $individual = $doc->createElement('Individual');
            $individual->appendChild($doc->createElement('GenderCode', (string) ($passenger['gender'] ?? 'M')));
            $individual->appendChild($doc->createElement('GivenName', strtoupper((string) ($passenger['given_name'] ?? ''))));
            $individual->appendChild($doc->createElement('IndividualID', 'IND-'.($passenger['pax_id'] ?? '1')));
            $individual->appendChild($doc->createElement('Surname', strtoupper((string) ($passenger['surname'] ?? ''))));
            if (($passenger['title'] ?? '') !== '') {
                $individual->appendChild($doc->createElement('TitleName', strtoupper((string) $passenger['title'])));
            }
            $pax->appendChild($individual);
            $pax->appendChild($doc->createElement('PaxID', (string) ($passenger['pax_id'] ?? 'PAX-ADT1')));
            $pax->appendChild($doc->createElement('PTC', (string) ($passenger['ptc'] ?? 'ADT')));
            $list->appendChild($pax);
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $passenger
     */
    private function identityDocElement(DOMDocument $doc, array $passenger): DOMElement
    {
        $identityDoc = $doc->createElement('IdentityDoc');
        if (($passenger['birthdate'] ?? '') !== '') {
            $identityDoc->appendChild($doc->createElement('Birthdate', (string) $passenger['birthdate']));
        }
        if (($passenger['nationality'] ?? '') !== '') {
            $identityDoc->appendChild($doc->createElement('CitizenshipCountryCode', (string) $passenger['nationality']));
        }
        if (($passenger['passport_expiry'] ?? '') !== '') {
            $identityDoc->appendChild($doc->createElement('ExpiryDate', (string) $passenger['passport_expiry']));
        }
        $identityDoc->appendChild($doc->createElement('GenderCode', (string) ($passenger['gender'] ?? 'M')));
        $identityDoc->appendChild($doc->createElement('GivenName', strtoupper((string) ($passenger['given_name'] ?? ''))));
        $identityDoc->appendChild($doc->createElement('IdentityDocID', (string) $passenger['passport_number']));
        $identityDoc->appendChild($doc->createElement('IdentityDocTypeCode', 'PASSPORT'));
        $identityDoc->appendChild($doc->createElement('IssuingCountryCode', (string) ($passenger['nationality'] ?? '')));
        $identityDoc->appendChild($doc->createElement('Surname', strtoupper((string) ($passenger['surname'] ?? ''))));

        return $identityDoc;
    }

    private function cabinTypeCode(string $cabin): string
    {
        $normalized = strtolower(trim($cabin));

        return match ($normalized) {
            'business', 'c', 'j' => 'C',
            'first', 'f' => 'F',
            default => 'Y',
        };
    }

    private function namespaceFor(string $message): string
    {
        return 'http://www.iata.org/IATA/2015/00/2020.1/'.$message;
    }

    private function newEnvelope(string $namespace, string $rootLocalName): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;
        $envelope = $doc->createElementNS(self::SOAP_NS, 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:soapenv', self::SOAP_NS);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', $namespace);
        $doc->appendChild($envelope);
        $envelope->appendChild($doc->createElementNS(self::SOAP_NS, 'soapenv:Header'));
        $body = $doc->createElementNS(self::SOAP_NS, 'soapenv:Body');
        $envelope->appendChild($body);
        $body->appendChild($doc->createElement($rootLocalName));

        return $doc;
    }
}
