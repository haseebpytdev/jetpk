<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use Illuminate\Support\Str;

/**
 * Builds Zapways OTA v2.06 SOAP request payloads for AirBlue.
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

        $paxXml = '';
        foreach ($this->paxCounts($request) as $ptc => $qty) {
            if ($qty > 0) {
                $paxXml .= '<ota:PassengerTypeQuantity Code="'.htmlspecialchars($ptc, ENT_XML1).'" Quantity="'.$qty.'" />';
            }
        }

        $odXml = '';
        foreach ($segments as $segment) {
            $odXml .= $segment;
        }

        $echoToken = (string) Str::uuid();
        $target = htmlspecialchars((string) $config['service_target'], ENT_XML1);
        $version = htmlspecialchars((string) $config['service_version'], ENT_XML1);
        $ersp = htmlspecialchars($config['client_id'].'/'.$config['client_key'], ENT_XML1);
        $agentType = htmlspecialchars((string) $config['agent_type'], ENT_XML1);
        $agentId = htmlspecialchars((string) $config['agent_id'], ENT_XML1);
        $agentPassword = htmlspecialchars((string) $config['agent_password'], ENT_XML1);

        $travelPrefsXml = '';
        if ($request->direct_only) {
            $travelPrefsXml = '<ota:TravelPreferences><ota:DirectFlightsOnly>true</ota:DirectFlightsOnly></ota:TravelPreferences>';
        }

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:zap="http://zapways.com/air/ota/2.0" xmlns:ota="http://www.opentravel.org/OTA/2003/05">
    <soapenv:Header/>
    <soapenv:Body>
        <zap:AirLowFareSearch>
            <ota:airLowFareSearchRQ EchoToken="{$echoToken}" Target="{$target}" Version="{$version}">
                <ota:POS>
                    <ota:Source ERSP_UserID="{$ersp}">
                        <ota:RequestorID Type="{$agentType}" ID="{$agentId}" MessagePassword="{$agentPassword}" />
                    </ota:Source>
                </ota:POS>
                {$odXml}
                {$travelPrefsXml}
                <ota:TravelerInfoSummary>
                    <ota:AirTravelerAvail>
                        {$paxXml}
                    </ota:AirTravelerAvail>
                </ota:TravelerInfoSummary>
            </ota:airLowFareSearchRQ>
        </zap:AirLowFareSearch>
    </soapenv:Body>
</soapenv:Envelope>
XML;
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
                $segmentsXml .= $this->flightSegmentXml($segment);
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

        $pos = $this->posBlock($config);
        $echoToken = htmlspecialchars((string) Str::uuid(), ENT_XML1);
        $target = htmlspecialchars((string) $config['service_target'], ENT_XML1);
        $version = htmlspecialchars((string) $config['service_version'], ENT_XML1);

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:zap="http://zapways.com/air/ota/2.0" xmlns:ota="http://www.opentravel.org/OTA/2003/05">
    <soapenv:Header/>
    <soapenv:Body>
        <zap:AirBook>
            <ota:OTA_AirBookRQ EchoToken="{$echoToken}" Target="{$target}" Version="{$version}">
                {$pos}
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
            </ota:OTA_AirBookRQ>
        </zap:AirBook>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $modifyContext
     */
    public function buildAirBookModifyRequest(array $config, array $modifyContext): string
    {
        $pnr = htmlspecialchars(trim((string) ($modifyContext['pnr'] ?? '')), ENT_XML1);
        $instance = htmlspecialchars(trim((string) ($modifyContext['instance'] ?? '')), ENT_XML1);
        if ($pnr === '' || $instance === '') {
            throw new AirBlueValidationException('missing_modify_context', 422, 'AirBlue OTA modify requires PNR and instance.');
        }

        $pos = $this->posBlock($config);
        $echoToken = htmlspecialchars((string) Str::uuid(), ENT_XML1);
        $target = htmlspecialchars((string) $config['service_target'], ENT_XML1);
        $version = htmlspecialchars((string) $config['service_version'], ENT_XML1);

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:zap="http://zapways.com/air/ota/2.0" xmlns:ota="http://www.opentravel.org/OTA/2003/05">
    <soapenv:Header/>
    <soapenv:Body>
        <zap:AirBookModify>
            <ota:OTA_AirBookModifyRQ EchoToken="{$echoToken}" Target="{$target}" Version="{$version}">
                {$pos}
                <ota:UniqueID ID="{$pnr}" Instance="{$instance}" Type="14"/>
            </ota:OTA_AirBookModifyRQ>
        </zap:AirBookModify>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildReadRequest(array $config, string $pnr, string $instance): string
    {
        $pos = $this->posBlock($config);
        $echoToken = htmlspecialchars((string) Str::uuid(), ENT_XML1);
        $target = htmlspecialchars((string) $config['service_target'], ENT_XML1);
        $version = htmlspecialchars((string) $config['service_version'], ENT_XML1);
        $pnr = htmlspecialchars(trim($pnr), ENT_XML1);
        $instance = htmlspecialchars(trim($instance), ENT_XML1);

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:zap="http://zapways.com/air/ota/2.0" xmlns:ota="http://www.opentravel.org/OTA/2003/05">
    <soapenv:Header/>
    <soapenv:Body>
        <zap:Read>
            <ota:OTA_ReadRQ EchoToken="{$echoToken}" Target="{$target}" Version="{$version}">
                {$pos}
                <ota:ReadRequests>
                    <ota:ReadRequest>
                        <ota:UniqueID ID="{$pnr}" Instance="{$instance}" Type="14"/>
                    </ota:ReadRequest>
                </ota:ReadRequests>
            </ota:OTA_ReadRQ>
        </zap:Read>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildAirDemandTicketRequest(array $config, string $pnr, string $instance): string
    {
        $pos = $this->posBlock($config);
        $echoToken = htmlspecialchars((string) Str::uuid(), ENT_XML1);
        $target = htmlspecialchars((string) $config['service_target'], ENT_XML1);
        $version = htmlspecialchars((string) $config['service_version'], ENT_XML1);
        $pnr = htmlspecialchars(trim($pnr), ENT_XML1);
        $instance = htmlspecialchars(trim($instance), ENT_XML1);

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:zap="http://zapways.com/air/ota/2.0" xmlns:ota="http://www.opentravel.org/OTA/2003/05">
    <soapenv:Header/>
    <soapenv:Body>
        <zap:AirDemandTicket>
            <ota:OTA_AirDemandTicketRQ EchoToken="{$echoToken}" Target="{$target}" Version="{$version}">
                {$pos}
                <ota:DemandTicketDetail>
                    <ota:BookingReferenceID ID="{$pnr}" Instance="{$instance}"/>
                </ota:DemandTicketDetail>
            </ota:OTA_AirDemandTicketRQ>
        </zap:AirDemandTicket>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function buildCancelRequest(array $config, string $pnr, string $instance): string
    {
        $pos = $this->posBlock($config);
        $echoToken = htmlspecialchars((string) Str::uuid(), ENT_XML1);
        $target = htmlspecialchars((string) $config['service_target'], ENT_XML1);
        $version = htmlspecialchars((string) $config['service_version'], ENT_XML1);
        $pnr = htmlspecialchars(trim($pnr), ENT_XML1);
        $instance = htmlspecialchars(trim($instance), ENT_XML1);

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:zap="http://zapways.com/air/ota/2.0" xmlns:ota="http://www.opentravel.org/OTA/2003/05">
    <soapenv:Header/>
    <soapenv:Body>
        <zap:Cancel>
            <ota:OTA_CancelRQ EchoToken="{$echoToken}" Target="{$target}" Version="{$version}">
                {$pos}
                <ota:UniqueID ID="{$pnr}" Instance="{$instance}" Type="14"/>
            </ota:OTA_CancelRQ>
        </zap:Cancel>
    </soapenv:Body>
</soapenv:Envelope>
XML;
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
     */
    private function flightSegmentXml(array $segment): string
    {
        $departure = htmlspecialchars((string) ($segment['departure_datetime'] ?? ''), ENT_XML1);
        $arrival = htmlspecialchars((string) ($segment['arrival_datetime'] ?? ''), ENT_XML1);
        $flightNumber = htmlspecialchars((string) ($segment['flight_number'] ?? ''), ENT_XML1);
        $rbd = htmlspecialchars((string) ($segment['rbd'] ?? 'Y'), ENT_XML1);
        $origin = htmlspecialchars(strtoupper((string) ($segment['departure_airport'] ?? '')), ENT_XML1);
        $destination = htmlspecialchars(strtoupper((string) ($segment['arrival_airport'] ?? '')), ENT_XML1);
        $carrier = htmlspecialchars(strtoupper((string) ($segment['marketing_carrier'] ?? 'PA')), ENT_XML1);

        return <<<XML
                            <ota:FlightSegment DepartureDateTime="{$departure}" ArrivalDateTime="{$arrival}" FlightNumber="{$flightNumber}" ResBookDesigCode="{$rbd}">
                                <ota:DepartureAirport LocationCode="{$origin}"/>
                                <ota:ArrivalAirport LocationCode="{$destination}"/>
                                <ota:MarketingAirline Code="{$carrier}"/>
                            </ota:FlightSegment>
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
        $issueCountry = htmlspecialchars(strtoupper(trim((string) ($passenger['document_issuing_country'] ?? $passenger['passport_issuing_country'] ?? $passenger['nationality'] ?? ''))), ENT_XML1);
        $expireDate = trim((string) ($passenger['document_expiry'] ?? $passenger['passport_expiry_date'] ?? ''));
        $expireAttr = $expireDate !== '' ? ' ExpireDate="'.htmlspecialchars($expireDate, ENT_XML1).'"' : '';
        $issueAttr = $issueCountry !== '' ? ' DocIssueCountry="'.$issueCountry.'"' : '';

        return <<<XML
                        <ota:Document DocID="{$docIdAttr}" DocType="{$docType}"{$issueAttr}{$expireAttr}/>
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
