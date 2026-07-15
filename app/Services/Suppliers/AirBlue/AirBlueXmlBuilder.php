<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Models\Booking;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use App\Support\Phone\SupplierContactFormatter;
use DOMDocument;
use DOMElement;

/**
 * Builds SOAP XML request payloads for AirBlue Crane NDC 20.1 operations.
 */
class AirBlueXmlBuilder
{
    private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildAirShoppingRequest(FlightSearchRequestData $request, array $config): string
    {
        $ns = $this->namespaceFor('IATA_AirShoppingRQ');
        $doc = $this->newEnvelope($ns, 'IATA_AirShoppingRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_AirShoppingRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new AirBlueValidationException('xml_build_failed', 500, 'Failed to build air shopping request.');
        }

        $messageDoc = $doc->createElement('MessageDoc');
        $messageDoc->appendChild($doc->createElement('RefVersionNumber', '20.1'));
        $root->appendChild($messageDoc);
        $root->appendChild($this->partyBlock($doc, $config));

        $req = $doc->createElement('Request');
        $flightRequest = $doc->createElement('FlightRequest');
        $flightRequest->appendChild($this->originDestCriteria($doc, $request->origin, $request->destination, $request->departure_date, $request->cabin ?? 'Y'));
        if ($request->return_date) {
            $flightRequest->appendChild($this->originDestCriteria($doc, $request->destination, $request->origin, $request->return_date, $request->cabin ?? 'Y'));
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
            throw new AirBlueValidationException('xml_build_failed', 500, 'Failed to build order create request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $payload = $doc->createElement('PayloadAttributes');
        $payload->appendChild($doc->createElement('PrimaryLangID', $config['language_code']));
        $root->appendChild($payload);

        $req = $doc->createElement('Request');
        $createOrder = $doc->createElement('CreateOrder');
        $selectedOffer = $doc->createElement('SelectedOffer');
        $selectedOffer->appendChild($doc->createElement('OfferRefID', (string) $providerContext['offer_ref_id']));
        $selectedOffer->appendChild($doc->createElement('OwnerCode', (string) ($providerContext['owner_code'] ?? $config['owner_code'])));
        foreach ($this->selectedOfferItems($providerContext) as $item) {
            $offerItem = $doc->createElement('SelectedOfferItem');
            $offerItem->appendChild($doc->createElement('OfferItemRefID', (string) $item['offer_item_ref_id']));
            $offerItem->appendChild($doc->createElement('PaxRefID', (string) $item['pax_ref_id']));
            $selectedOffer->appendChild($offerItem);
        }
        $selectedOffer->appendChild($doc->createElement('ShoppingResponseRefID', (string) $providerContext['shopping_response_ref_id']));
        $createOrder->appendChild($selectedOffer);
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
            throw new AirBlueValidationException('xml_build_failed', 500, 'Failed to build order retrieve request.');
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
        $ns = $this->namespaceFor('IATA_OrderReshopRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderReshopRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderReshopRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new AirBlueValidationException('xml_build_failed', 500, 'Failed to build cancel preview request.');
        }

        $root->appendChild($this->partyBlock($doc, $config));
        $req = $doc->createElement('Request');
        $req->appendChild($doc->createElement('OrderRefID', $orderRefId));
        $params = $doc->createElement('ReshopParameters');
        $cur = $doc->createElement('CurParameter');
        $cur->appendChild($doc->createElement('RequestedCurCode', $config['currency']));
        $params->appendChild($cur);
        $lang = $doc->createElement('LangUsage');
        $lang->appendChild($doc->createElement('LangCode', $config['language_code']));
        $params->appendChild($lang);
        $req->appendChild($params);
        $update = $doc->createElement('UpdateOrder');
        $cancel = $doc->createElement('CancelOrder');
        $cancel->appendChild($doc->createElement('OrderRefID', $orderRefId));
        $update->appendChild($cancel);
        $req->appendChild($update);
        $root->appendChild($req);

        return $doc->saveXML() ?: '';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildCancelCommitRequest(array $config, string $orderId, string $ownerCode): string
    {
        $ns = $this->namespaceFor('IATA_OrderChangeRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderChangeRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderChangeRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new AirBlueValidationException('xml_build_failed', 500, 'Failed to build cancel commit request.');
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
     * @param  array<string, mixed>  $config
     * @param  ?array{amount: float, currency: string, ticket_id?: string}  $payment
     */
    private function buildOrderChangeShell(array $config, string $orderId, string $ownerCode, ?array $payment): string
    {
        $ns = $this->namespaceFor('IATA_OrderChangeRQ');
        $doc = $this->newEnvelope($ns, 'IATA_OrderChangeRQ');
        $root = $doc->documentElement->getElementsByTagName('IATA_OrderChangeRQ')->item(0);
        if (! $root instanceof DOMElement) {
            throw new AirBlueValidationException('xml_build_failed', 500, 'Failed to build order change request.');
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
            $paymentFunctions = $doc->createElement('PaymentFunctions');
            $details = $doc->createElement('PaymentProcessingDetails');
            $amount = $doc->createElement('Amount', number_format((float) $payment['amount'], 2, '.', ''));
            $amount->setAttribute('CurCode', (string) ($payment['currency'] ?? $config['currency']));
            $details->appendChild($amount);
            $method = $doc->createElement('PaymentMethod');
            $docEl = $doc->createElement('AccountableDoc');
            $docEl->appendChild($doc->createElement('DocType', 'MCO'));
            $docEl->appendChild($doc->createElement('TicketID', (string) ($payment['ticket_id'] ?? '4000012043')));
            $method->appendChild($docEl);
            $details->appendChild($method);
            $details->appendChild($doc->createElement('PaymentRefID', 'PaymentInfo1'));
            $details->appendChild($doc->createElement('TypeCode', 'MCO'));
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
     */
    private function assertOrderCreateContext(array $providerContext): void
    {
        foreach (['shopping_response_ref_id', 'offer_ref_id'] as $key) {
            if (trim((string) ($providerContext[$key] ?? '')) === '') {
                throw new AirBlueValidationException('missing_provider_context', 422, 'Selected offer context is incomplete. Please search again.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @return list<array{offer_item_ref_id: string, pax_ref_id: string}>
     */
    private function selectedOfferItems(array $providerContext): array
    {
        $items = is_array($providerContext['offer_item_refs'] ?? null) ? $providerContext['offer_item_refs'] : [];
        if ($items !== []) {
            return $items;
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
