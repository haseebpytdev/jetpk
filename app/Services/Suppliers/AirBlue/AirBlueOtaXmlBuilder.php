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
<<<<<<< HEAD
            $segments[] = $this->originDestinationSegment($request->destination, $request->returnOrigin(), $request->return_date, 2);
=======
            $segments[] = $this->originDestinationSegment($request->destination, $request->origin, $request->return_date, 2);
>>>>>>> jetpk/main
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

<<<<<<< HEAD
        $travelPrefsXml = '';
        if ($request->direct_only) {
            $travelPrefsXml = '<ota:TravelPreferences><ota:DirectFlightsOnly>true</ota:DirectFlightsOnly></ota:TravelPreferences>';
        }

=======
>>>>>>> jetpk/main
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
<<<<<<< HEAD
                {$travelPrefsXml}
=======
>>>>>>> jetpk/main
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
     * @param  array<string, mixed>  $passenger
     * @param  array<string, mixed>  $contact
     */
    private function travelerXml(array $passenger, array $contact, int $rph): string
    {
        $given = htmlspecialchars((string) ($passenger['given_name'] ?? ''), ENT_XML1);
        $surname = htmlspecialchars((string) ($passenger['surname'] ?? ''), ENT_XML1);
        $ptc = htmlspecialchars((string) ($passenger['ptc'] ?? 'ADT'), ENT_XML1);
        $email = htmlspecialchars((string) ($contact['email'] ?? ''), ENT_XML1);
        $phone = htmlspecialchars((string) ($contact['phone_number'] ?? ''), ENT_XML1);

        $emailXml = $email !== '' ? "<ota:Email>{$email}</ota:Email>" : '';
        $phoneXml = $phone !== '' ? '<ota:Telephone PhoneNumber="'.$phone.'"/>' : '';

        return <<<XML
                    <ota:AirTraveler PassengerTypeCode="{$ptc}">
                        <ota:PersonName>
                            <ota:GivenName>{$given}</ota:GivenName>
                            <ota:Surname>{$surname}</ota:Surname>
                        </ota:PersonName>
                        {$phoneXml}
                        {$emailXml}
                        <ota:TravelerRefNumber RPH="{$rph}"/>
                    </ota:AirTraveler>
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
