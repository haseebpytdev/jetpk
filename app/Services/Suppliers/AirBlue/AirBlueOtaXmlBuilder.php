<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use Illuminate\Support\Str;

/**
 * Builds Zapways OTA SOAP request payloads for AirBlue (v2.0 and v3.0).
 */
class AirBlueOtaXmlBuilder
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function buildAirLowFareSearchRequest(FlightSearchRequestData $request, array $config): string
    {
        $segments = [];
        $segments[] = $this->originDestinationSegment($request->origin, $request->destination, $request->departure_date, 1);
        if ($request->return_date) {
            $segments[] = $this->originDestinationSegment($request->destination, $request->returnOrigin(), $request->return_date, 2);
        }

        if (count($segments) > 4) {
            throw new AirBlueValidationException('too_many_segments', 422, 'AirBlue OTA search supports at most 4 origin-destination segments.');
        }

        $totalPax = max(0, (int) $request->adults) + max(0, (int) $request->children) + max(0, (int) $request->infants);
        if ($totalPax > 6) {
            throw new AirBlueValidationException('too_many_passengers', 422, 'AirBlue OTA search supports at most 6 passengers.');
        }

        $paxXml = '';
        foreach ($this->paxCounts($request) as $ptc => $qty) {
            if ($qty > 0) {
                $paxXml .= '<ota:PassengerTypeQuantity Code="'.htmlspecialchars($ptc, ENT_XML1).'" Quantity="'.$qty.'" />';
            }
        }

        $odXml = implode('', $segments);
        $travelPrefsXml = $request->direct_only
            ? '<ota:TravelPreferences><ota:DirectFlightsOnly>true</ota:DirectFlightsOnly></ota:TravelPreferences>'
            : '';

        return $this->envelope(
            $config,
            'AirLowFareSearch',
            'airLowFareSearchRQ',
            <<<XML
                {$this->posBlock($config)}
                {$odXml}
                {$travelPrefsXml}
                <ota:TravelerInfoSummary>
                    <ota:AirTravelerAvail>
                        {$paxXml}
                    </ota:AirTravelerAvail>
                </ota:TravelerInfoSummary>
XML
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $providerContext
     * @param  list<array<string, mixed>>  $passengers
     * @param  array<string, mixed>  $contact
     */
    public function buildAirBookRequest(array $config, array $providerContext, array $passengers, array $contact): string
    {
        $itineraries = is_array($providerContext['priced_itineraries'] ?? null)
            ? $providerContext['priced_itineraries']
            : [];
        if ($itineraries === []) {
            throw new AirBlueValidationException('missing_offer_context', 422, 'AirBlue OTA booking requires priced itinerary context.');
        }

        $segmentsXml = '';
        foreach ($itineraries as $itinerary) {
            if (! is_array($itinerary)) {
                continue;
            }
            foreach (is_array($itinerary['segments'] ?? null) ? $itinerary['segments'] : [] as $segment) {
                if (! is_array($segment)) {
                    continue;
                }
                $segmentsXml .= $this->flightSegmentXml($segment, $config);
            }
        }

        $priceInfoXml = $this->priceInfoXml($itineraries);
        $travelersXml = '';
        $rph = 1;
        foreach ($passengers as $passenger) {
            if (! is_array($passenger)) {
                continue;
            }
            $travelersXml .= $this->travelerXml($passenger, $contact, $rph);
            $rph++;
        }

        return $this->envelope(
            $config,
            'AirBook',
            'airBookRQ',
            <<<XML
                {$this->posBlock($config)}
                <ota:AirItinerary>
                    <ota:OriginDestinationOptions>
                        <ota:OriginDestinationOption>
                            {$segmentsXml}
                        </ota:OriginDestinationOption>
                    </ota:OriginDestinationOptions>
                </ota:AirItinerary>
                {$priceInfoXml}
                <ota:TravelerInfo>
                    {$travelersXml}
                </ota:TravelerInfo>
XML
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $modifyContext
     */
    public function buildAirBookModifyRequest(array $config, array $modifyContext): string
    {
        $pnr = trim((string) ($modifyContext['pnr'] ?? ''));
        $instance = trim((string) ($modifyContext['instance'] ?? ''));
        if ($pnr === '') {
            throw new AirBlueValidationException('missing_modify_context', 422, 'AirBlue OTA modify requires PNR.');
        }

        $modificationType = trim((string) ($modifyContext['modification_type'] ?? '5'));
        if ($modificationType !== '5') {
            throw new AirBlueValidationException(
                'unsupported_modification_type',
                422,
                sprintf('AirBlue OTA modify type "%s" is not implemented for Zapways v3 wire contract.', $modificationType),
            );
        }

        if ($instance === '') {
            throw new AirBlueValidationException('missing_modify_context', 422, 'AirBlue OTA seat/ancillary modify requires instance.');
        }

        $specialReqDetails = $this->seatModifyXml($modifyContext).$this->ancillaryModifyXml($modifyContext);
        $body = $this->posBlock($config)
            .'<ota:AirBookModifyRQ ModificationType="'.htmlspecialchars($modificationType, ENT_XML1).'">'
            .'<ota:TravelerInfo>'
            .'<ota:SpecialReqDetails>'
            .$specialReqDetails
            .'</ota:SpecialReqDetails>'
            .'</ota:TravelerInfo>'
            .'</ota:AirBookModifyRQ>'
            .'<ota:AirReservation>'
            .$this->bookingReferenceIdXml($pnr, $instance)
            .'</ota:AirReservation>';

        return $this->envelope(
            $config,
            'AirBookModify',
            'airBookModifyRQ',
            $body,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildReadRequest(array $config, string $pnr, string $instance): string
    {
        return $this->envelope(
            $config,
            'Read',
            'readRQ',
            $this->posBlock($config).$this->uniqueIdXml($pnr, $instance),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $paymentContext
     */
    public function buildAirDemandTicketRequest(
        array $config,
        string $pnr,
        string $instance,
        array $paymentContext = [],
    ): string {
        $paymentXml = $this->paymentInfoXml($config, $paymentContext);

        return $this->envelope(
            $config,
            'AirDemandTicket',
            'airDemandTicketRQ',
            $this->posBlock($config)
            .'<ota:DemandTicketDetail>'
            .$this->bookingReferenceIdXml($pnr, $instance)
            .$paymentXml
            .'</ota:DemandTicketDetail>',
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildCancelRequest(array $config, string $pnr, string $instance): string
    {
        return $this->envelope(
            $config,
            'Cancel',
            'cancelRQ',
            $this->posBlock($config).$this->uniqueIdXml($pnr, $instance),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $seatMapContext
     */
    public function buildAirSeatMapRequest(array $config, array $seatMapContext): string
    {
        $this->assertV3Only($config, 'AirSeatMap');

        $pnr = trim((string) ($seatMapContext['pnr'] ?? ''));
        $instance = trim((string) ($seatMapContext['instance'] ?? ''));

        $seatMapRequestsXml = '';
        foreach (is_array($seatMapContext['flight_segments'] ?? null) ? $seatMapContext['flight_segments'] : [] as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $seatMapRequestsXml .= '<ota:SeatMapRequest>'.$this->flightSegmentInfoXml($segment).'</ota:SeatMapRequest>';
        }

        if ($seatMapRequestsXml === '') {
            throw new AirBlueValidationException('missing_seat_map_segments', 422, 'AirBlue seat map requires at least one flight segment.');
        }

        return $this->envelope(
            $config,
            'AirSeatMap',
            'airSeatMapRQ',
            $this->posBlock($config)
            .'<ota:SeatMapRequests>'
            .$seatMapRequestsXml
            .'</ota:SeatMapRequests>'
            .$this->bookingReferenceIdXml($pnr, $instance),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $ancillaryContext
     */
    public function buildAirAncillaryItemsRequest(array $config, array $ancillaryContext): string
    {
        $this->assertV3Only($config, 'AirAncillaryItems');

        $pnr = trim((string) ($ancillaryContext['pnr'] ?? ''));
        $instance = trim((string) ($ancillaryContext['instance'] ?? ''));

        $ancillaryRequestsXml = '';
        foreach (is_array($ancillaryContext['flight_segments'] ?? null) ? $ancillaryContext['flight_segments'] : [] as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $ancillaryRequestsXml .= '<ota:AncillaryItemRequest>'.$this->flightSegmentInfoXml($segment).'</ota:AncillaryItemRequest>';
        }

        if ($ancillaryRequestsXml === '') {
            throw new AirBlueValidationException('missing_ancillary_segments', 422, 'AirBlue ancillary items require at least one flight segment.');
        }

        return $this->envelope(
            $config,
            'AirAncillaryItems',
            'airAncillaryItemsRQ',
            $this->posBlock($config)
            .'<ota:AncillaryItemRequests>'
            .$ancillaryRequestsXml
            .'</ota:AncillaryItemRequests>'
            .$this->bookingReferenceIdXml($pnr, $instance),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $extraAttributes
     */
    private function envelope(
        array $config,
        string $operation,
        string $requestElement,
        string $innerBody,
        array $extraAttributes = [],
    ): string {
        $namespace = htmlspecialchars($this->namespaceUri($config), ENT_XML1);
        $echoToken = htmlspecialchars((string) Str::uuid(), ENT_XML1);
        $target = htmlspecialchars((string) $config['service_target'], ENT_XML1);
        $version = htmlspecialchars((string) $config['service_version'], ENT_XML1);
        $attrXml = '';
        foreach ($extraAttributes as $name => $value) {
            $attrXml .= ' '.htmlspecialchars((string) $name, ENT_XML1).'="'.htmlspecialchars((string) $value, ENT_XML1).'"';
        }

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:zap="{$namespace}" xmlns:ota="http://www.opentravel.org/OTA/2003/05">
    <soapenv:Header/>
    <soapenv:Body>
        <zap:{$operation}>
            <ota:{$requestElement} EchoToken="{$echoToken}" Target="{$target}" Version="{$version}"{$attrXml}>
{$innerBody}
            </ota:{$requestElement}>
        </zap:{$operation}>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function namespaceUri(array $config): string
    {
        $namespace = trim((string) ($config['namespace'] ?? ''));
        if ($namespace !== '') {
            return $namespace;
        }

        return AirBlueZapwaysProtocolVersion::fromCredentials([
            'protocol_version' => (string) ($config['protocol_version'] ?? '2.0'),
        ])->namespaceUri();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertV3Only(array $config, string $operation): void
    {
        $protocol = (string) ($config['protocol_version'] ?? AirBlueZapwaysProtocolVersion::V2->value);
        if ($protocol !== AirBlueZapwaysProtocolVersion::V3->value) {
            throw new AirBlueValidationException(
                'v3_operation_required',
                422,
                sprintf('AirBlue %s is only available on Zapways OTA v3.0.', $operation),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function posBlock(array $config): string
    {
        $ersp = htmlspecialchars($config['client_id'].'/'.$config['client_key'], ENT_XML1);
        $agentType = htmlspecialchars((string) $config['agent_type'], ENT_XML1);
        $agentId = htmlspecialchars((string) $config['agent_id'], ENT_XML1);
        $agentPassword = htmlspecialchars((string) $config['agent_password'], ENT_XML1);

        return <<<XML
                <ota:POS>
                    <ota:Source ERSP_UserID="{$ersp}">
                        <ota:RequestorID Type="{$agentType}" ID="{$agentId}" MessagePassword="{$agentPassword}" />
                    </ota:Source>
                </ota:POS>
XML;
    }

    /**
     * @param  array<string, mixed>  $segment
     * @param  array<string, mixed>  $config
     */
    private function flightSegmentXml(array $segment, array $config): string
    {
        $departure = htmlspecialchars((string) ($segment['departure_datetime'] ?? ''), ENT_XML1);
        $arrival = htmlspecialchars((string) ($segment['arrival_datetime'] ?? ''), ENT_XML1);
        $flightNumber = htmlspecialchars((string) ($segment['flight_number'] ?? ''), ENT_XML1);
        $rbd = htmlspecialchars((string) ($segment['rbd'] ?? 'Y'), ENT_XML1);
        $origin = htmlspecialchars(strtoupper((string) ($segment['departure_airport'] ?? '')), ENT_XML1);
        $destination = htmlspecialchars(strtoupper((string) ($segment['arrival_airport'] ?? '')), ENT_XML1);
        $carrier = htmlspecialchars(strtoupper((string) ($segment['marketing_carrier'] ?? 'PA')), ENT_XML1);
        $rph = htmlspecialchars((string) ($segment['rph'] ?? ''), ENT_XML1);
        $rphAttr = $rph !== '' ? ' RPH="'.$rph.'"' : '';
        $fareType = htmlspecialchars(trim((string) ($segment['fare_type'] ?? '')), ENT_XML1);
        $cabinClass = htmlspecialchars(trim((string) ($segment['cabin_class'] ?? '')), ENT_XML1);
        $fareTypeAttr = $fareType !== '' ? ' FareType="'.$fareType.'"' : '';
        $cabinClassAttr = $cabinClass !== '' ? ' CabinClass="'.$cabinClass.'"' : '';

        return <<<XML
                            <ota:FlightSegment DepartureDateTime="{$departure}" ArrivalDateTime="{$arrival}" FlightNumber="{$flightNumber}" ResBookDesigCode="{$rbd}"{$rphAttr}{$fareTypeAttr}{$cabinClassAttr}>
                                <ota:DepartureAirport LocationCode="{$origin}"/>
                                <ota:ArrivalAirport LocationCode="{$destination}"/>
                                <ota:MarketingAirline Code="{$carrier}"/>
                            </ota:FlightSegment>
XML;
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function flightSegmentInfoXml(array $segment): string
    {
        $departure = htmlspecialchars((string) ($segment['departure_datetime'] ?? ''), ENT_XML1);
        $flightNumber = htmlspecialchars((string) ($segment['flight_number'] ?? ''), ENT_XML1);
        $rbd = htmlspecialchars((string) ($segment['rbd'] ?? 'Y'), ENT_XML1);
        $origin = htmlspecialchars(strtoupper((string) ($segment['departure_airport'] ?? '')), ENT_XML1);
        $destination = htmlspecialchars(strtoupper((string) ($segment['arrival_airport'] ?? '')), ENT_XML1);
        $carrier = htmlspecialchars(strtoupper((string) ($segment['marketing_carrier'] ?? $segment['operating_carrier'] ?? 'PA')), ENT_XML1);
        $fareType = htmlspecialchars(trim((string) ($segment['fare_type'] ?? '')), ENT_XML1);
        $cabinClass = htmlspecialchars(trim((string) ($segment['cabin_class'] ?? '')), ENT_XML1);
        $fareTypeAttr = $fareType !== '' ? ' FareType="'.$fareType.'"' : '';
        $cabinClassAttr = $cabinClass !== '' ? ' CabinClass="'.$cabinClass.'"' : '';

        return <<<XML
                <ota:FlightSegmentInfo DepartureDateTime="{$departure}" FlightNumber="{$flightNumber}" ResBookDesigCode="{$rbd}"{$fareTypeAttr}{$cabinClassAttr}>
                    <ota:DepartureAirport LocationCode="{$origin}"/>
                    <ota:ArrivalAirport LocationCode="{$destination}"/>
                    <ota:OperatingAirline Code="{$carrier}" FlightNumber="{$flightNumber}"/>
                </ota:FlightSegmentInfo>
XML;
    }

    /**
     * @param  array<string, mixed>  $modifyContext
     */
    private function seatModifyXml(array $modifyContext): string
    {
        $requests = '';
        foreach (is_array($modifyContext['seat_changes'] ?? null) ? $modifyContext['seat_changes'] : [] as $change) {
            if (! is_array($change)) {
                continue;
            }
            $row = htmlspecialchars(trim((string) ($change['row_number'] ?? '')), ENT_XML1);
            $seat = htmlspecialchars(trim((string) ($change['seat_number'] ?? '')), ENT_XML1);
            $travelerRph = htmlspecialchars(trim((string) ($change['traveler_ref_number_rph'] ?? '')), ENT_XML1);
            $flightRph = htmlspecialchars(trim((string) ($change['flight_ref_number_rph'] ?? '')), ENT_XML1);
            if ($row === '' || $seat === '' || $travelerRph === '' || $flightRph === '') {
                throw new AirBlueValidationException('invalid_seat_modify', 422, 'Seat modify requires row, seat number, traveler RPH, and flight RPH.');
            }
            $requests .= '<ota:SeatRequest SeatNumber="'.$seat.'" RowNumber="'.$row.'" TravelerRefNumberRPHList="'.$travelerRph.'" FlightRefNumberRPHList="'.$flightRph.'"/>';
        }

        if ($requests === '') {
            return '';
        }

        return '<ota:SeatRequests>'.$requests.'</ota:SeatRequests>';
    }

    /**
     * @param  array<string, mixed>  $modifyContext
     */
    private function ancillaryModifyXml(array $modifyContext): string
    {
        $requests = '';
        foreach (is_array($modifyContext['item_changes'] ?? null) ? $modifyContext['item_changes'] : [] as $change) {
            if (! is_array($change)) {
                continue;
            }
            $itemCode = htmlspecialchars(trim((string) ($change['item_code'] ?? '')), ENT_XML1);
            $travelerRph = htmlspecialchars(trim((string) ($change['traveler_ref_number_rph'] ?? '')), ENT_XML1);
            $flightRph = htmlspecialchars(trim((string) ($change['flight_ref_number_rph'] ?? '')), ENT_XML1);
            $itemCount = (int) ($change['item_count'] ?? 1);
            if ($itemCode === '' || $travelerRph === '' || $flightRph === '') {
                throw new AirBlueValidationException('invalid_item_modify', 422, 'Ancillary modify requires item code, traveler RPH, and flight RPH.');
            }
            $attrs = ' ItemCode="'.$itemCode.'" TravelerRefNumberRPHList="'.$travelerRph.'" FlightRefNumberRPHList="'.$flightRph.'"';
            if ($itemCount === 0) {
                $attrs .= ' ItemCount="0"';
            }
            $requests .= '<ota:SpecialServiceRequest'.$attrs.'/>';
        }

        if ($requests === '') {
            return '';
        }

        return '<ota:SpecialServiceRequests>'.$requests.'</ota:SpecialServiceRequests>';
    }

    private function optionalInstanceAttribute(string $instance): string
    {
        $instance = trim($instance);

        return $instance !== '' ? ' Instance="'.htmlspecialchars($instance, ENT_XML1).'"' : '';
    }

    private function uniqueIdXml(string $pnr, string $instance): string
    {
        $pnr = htmlspecialchars(trim($pnr), ENT_XML1);
        $instanceAttr = $this->optionalInstanceAttribute($instance);

        return '<ota:UniqueID ID="'.$pnr.'"'.$instanceAttr.' Type="14"/>';
    }

    private function bookingReferenceIdXml(string $pnr, string $instance): string
    {
        $pnr = htmlspecialchars(trim($pnr), ENT_XML1);
        $instanceAttr = $this->optionalInstanceAttribute($instance);

        return '<ota:BookingReferenceID ID="'.$pnr.'"'.$instanceAttr.'/>';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $paymentContext
     */
    private function paymentInfoXml(array $config, array $paymentContext): string
    {
        $amount = $paymentContext['amount'] ?? null;
        if ($amount === null || $amount === '') {
            throw new AirBlueValidationException('missing_payment_amount', 422, 'AirBlue AirDemandTicket requires supplier-derived PaymentInfo amount.');
        }

        $currency = htmlspecialchars(
            strtoupper(trim((string) ($paymentContext['currency'] ?? $config['currency'] ?? 'PKR'))),
            ENT_XML1,
        );
        $paymentType = htmlspecialchars(trim((string) ($paymentContext['payment_type'] ?? 'Cash')), ENT_XML1);
        $amountAttr = htmlspecialchars(number_format((float) $amount, 2, '.', ''), ENT_XML1);

        return <<<XML
                    <ota:PaymentInfo PaymentType="{$paymentType}" CurrencyCode="{$currency}" Amount="{$amountAttr}"/>
XML;
    }

    /**
     * @param  list<array<string, mixed>>  $itineraries
     */
    private function priceInfoXml(array $itineraries): string
    {
        $breakdownXml = '';
        $totalBase = 0.0;
        $totalTaxes = 0.0;
        $totalAmount = 0.0;
        $currency = 'PKR';

        foreach ($itineraries as $itinerary) {
            if (! is_array($itinerary)) {
                continue;
            }
            $fare = is_array($itinerary['total_fare'] ?? null) ? $itinerary['total_fare'] : [];
            $totalBase += (float) ($fare['base'] ?? 0);
            $totalTaxes += (float) ($fare['taxes'] ?? 0) + (float) ($fare['fees'] ?? 0);
            $totalAmount += (float) ($fare['total'] ?? 0);
            $currency = (string) ($fare['currency'] ?? $currency);

            foreach (is_array($itinerary['fare_breakdowns'] ?? null) ? $itinerary['fare_breakdowns'] : [] as $breakdown) {
                if (! is_array($breakdown)) {
                    continue;
                }
                $breakdownXml .= $this->ptcFareBreakdownXml($breakdown);
            }
        }

        if ($breakdownXml === '') {
            throw new AirBlueValidationException(
                'missing_fare_breakdowns',
                422,
                'AirBlue OTA booking requires supplier PTC fare breakdowns from the selected search offer.',
            );
        }

        $base = htmlspecialchars(number_format($totalBase, 2, '.', ''), ENT_XML1);
        $taxes = htmlspecialchars(number_format($totalTaxes, 2, '.', ''), ENT_XML1);
        $total = htmlspecialchars(number_format($totalAmount, 2, '.', ''), ENT_XML1);
        $currencyAttr = htmlspecialchars($currency, ENT_XML1);

        return <<<XML
                <ota:PriceInfo>
                    <ota:ItinTotalFare>
                        <ota:BaseFare Amount="{$base}" CurrencyCode="{$currencyAttr}"/>
                        <ota:Taxes Amount="{$taxes}" CurrencyCode="{$currencyAttr}"/>
                        <ota:TotalFare Amount="{$total}" CurrencyCode="{$currencyAttr}"/>
                    </ota:ItinTotalFare>
                    <ota:PTC_FareBreakdowns>
{$breakdownXml}
                    </ota:PTC_FareBreakdowns>
                </ota:PriceInfo>
XML;
    }

    /**
     * @param  array<string, mixed>  $breakdown
     */
    private function ptcFareBreakdownXml(array $breakdown): string
    {
        $ptc = htmlspecialchars((string) ($breakdown['ptc'] ?? 'ADT'), ENT_XML1);
        $qty = max(1, (int) ($breakdown['quantity'] ?? 1));
        $currency = htmlspecialchars((string) ($breakdown['currency'] ?? 'PKR'), ENT_XML1);
        $baseCurrency = htmlspecialchars((string) ($breakdown['base_currency'] ?? $breakdown['currency'] ?? 'PKR'), ENT_XML1);
        $taxCurrency = htmlspecialchars((string) ($breakdown['tax_currency'] ?? $breakdown['currency'] ?? 'PKR'), ENT_XML1);
        $base = htmlspecialchars(number_format((float) ($breakdown['base'] ?? 0), 2, '.', ''), ENT_XML1);
        $taxes = htmlspecialchars(number_format((float) ($breakdown['taxes'] ?? 0), 2, '.', ''), ENT_XML1);
        $total = htmlspecialchars(number_format((float) ($breakdown['total'] ?? 0), 2, '.', ''), ENT_XML1);
        $fareBasis = trim((string) ($breakdown['fare_basis'] ?? ''));
        $fareBasisXml = $fareBasis !== ''
            ? '<ota:FareBasisCodes><ota:FareBasisCode>'.htmlspecialchars($fareBasis, ENT_XML1).'</ota:FareBasisCode></ota:FareBasisCodes>'
            : '';

        return <<<XML
                        <ota:PTC_FareBreakdown>
                            <ota:PassengerTypeQuantity Code="{$ptc}" Quantity="{$qty}"/>
                            <ota:PassengerFare>
                                <ota:BaseFare Amount="{$base}" CurrencyCode="{$baseCurrency}"/>
                                <ota:Taxes Amount="{$taxes}" CurrencyCode="{$taxCurrency}"/>
                                <ota:TotalFare Amount="{$total}" CurrencyCode="{$currency}"/>
                                {$fareBasisXml}
                            </ota:PassengerFare>
                        </ota:PTC_FareBreakdown>
XML;
    }

    /**
     * @param  array<string, mixed>  $passenger
     * @param  array<string, mixed>  $contact
     */
    private function travelerXml(array $passenger, array $contact, int $rph): string
    {
        $given = htmlspecialchars((string) ($passenger['given_name'] ?? ''), ENT_XML1);
        $surname = htmlspecialchars((string) ($passenger['surname'] ?? ''), ENT_XML1);
        $ptc = strtoupper((string) ($passenger['ptc'] ?? 'ADT'));
        $ptcAttr = htmlspecialchars($ptc, ENT_XML1);
        $email = htmlspecialchars((string) ($contact['email'] ?? ''), ENT_XML1);
        $phone = htmlspecialchars((string) ($contact['phone_number'] ?? ''), ENT_XML1);
        $birthdate = trim((string) ($passenger['birthdate'] ?? ''));
        $gender = strtoupper(trim((string) ($passenger['gender'] ?? '')));

        if (in_array($ptc, ['CHD', 'INF'], true) && $birthdate === '') {
            throw new AirBlueValidationException(
                'missing_birthdate',
                422,
                'AirBlue OTA booking requires birth date for child and infant passengers.',
            );
        }

        $attrs = ' PassengerTypeCode="'.$ptcAttr.'"';
        if ($birthdate !== '') {
            $attrs .= ' BirthDate="'.htmlspecialchars($birthdate, ENT_XML1).'"';
        }
        if (in_array($gender, ['M', 'F'], true)) {
            $attrs .= ' Gender="'.htmlspecialchars($gender, ENT_XML1).'"';
        }

        $emailXml = $email !== '' ? "<ota:Email>{$email}</ota:Email>" : '';
        $phoneXml = $phone !== '' ? '<ota:Telephone PhoneNumber="'.$phone.'"/>' : '';
        $documentXml = $this->travelerDocumentXml($passenger);

        return <<<XML
                    <ota:AirTraveler{$attrs}>
                        <ota:PersonName>
                            <ota:GivenName>{$given}</ota:GivenName>
                            <ota:Surname>{$surname}</ota:Surname>
                        </ota:PersonName>
                        {$documentXml}
                        {$phoneXml}
                        {$emailXml}
                        <ota:TravelerRefNumber RPH="{$rph}"/>
                    </ota:AirTraveler>
XML;
    }

    /**
     * @param  array<string, mixed>  $passenger
     */
    private function travelerDocumentXml(array $passenger): string
    {
        $docId = trim((string) ($passenger['document_number'] ?? $passenger['passport_number'] ?? ''));
        if ($docId === '') {
            return '';
        }

        $docIdAttr = htmlspecialchars($docId, ENT_XML1);
        $docType = htmlspecialchars(trim((string) ($passenger['document_type'] ?? '2')), ENT_XML1);
        $nationality = strtoupper(trim((string) ($passenger['nationality'] ?? '')));
        $issueCountry = htmlspecialchars(strtoupper(trim((string) ($passenger['document_issuing_country'] ?? $passenger['passport_issuing_country'] ?? $nationality))), ENT_XML1);
        $expireDate = trim((string) ($passenger['document_expiry'] ?? $passenger['passport_expiry_date'] ?? ''));
        $expireAttr = $expireDate !== '' ? ' ExpireDate="'.htmlspecialchars($expireDate, ENT_XML1).'"' : '';
        $issueAttr = $issueCountry !== '' ? ' DocIssueCountry="'.$issueCountry.'"' : '';
        $holderNationalityAttr = $nationality !== '' ? ' DocHolderNationality="'.htmlspecialchars($nationality, ENT_XML1).'"' : '';

        return <<<XML
                        <ota:Document DocID="{$docIdAttr}" DocType="{$docType}"{$issueAttr}{$expireAttr}{$holderNationalityAttr}/>
XML;
    }

    private function originDestinationSegment(string $origin, string $destination, string $date, int $rph): string
    {
        $origin = htmlspecialchars(strtoupper($origin), ENT_XML1);
        $destination = htmlspecialchars(strtoupper($destination), ENT_XML1);
        $departure = htmlspecialchars($date.'T00:00:00', ENT_XML1);

        return <<<XML
                <ota:OriginDestinationInformation RPH="{$rph}">
                    <ota:DepartureDateTime>{$departure}</ota:DepartureDateTime>
                    <ota:OriginLocation LocationCode="{$origin}" />
                    <ota:DestinationLocation LocationCode="{$destination}" />
                </ota:OriginDestinationInformation>
XML;
    }

    /**
     * @return array<string, int>
     */
    private function paxCounts(FlightSearchRequestData $request): array
    {
        return [
            'ADT' => max(0, (int) $request->adults),
            'CHD' => max(0, (int) $request->children),
            'INF' => max(0, (int) $request->infants),
        ];
    }
}
