<?php

return [
    'suppliers' => [
        'sabre' => [
            'name' => 'Sabre',
            'type' => 'GDS / NDC aggregator',
            'status' => 'not_configured',
            'environment' => 'sandbox',
            'required_credentials' => [
                'SOAP/REST client ID & secret (EPR)',
                'PCC / pseudo city code',
                'IP allow-listing with Sabre',
            ],
            'notes' => 'Production Sabre access requires signed commercial agreements and certification.',
        ],
        'pia_ndc' => [
            'name' => 'PIA NDC',
            'type' => 'Hitit Crane NDC 20.1',
            'status' => 'not_configured',
            'environment' => 'sandbox',
            'required_credentials' => [
                'Username / password (HTTP headers)',
                'Agency ID and agency name',
                'Owner code and base URL',
            ],
            'notes' => 'PIA Hitit Crane NDC 20.1: air shopping, option PNR, ticket preview, ticketing, cancel, void, reissue.',
        ],
        'airblue' => [
            'name' => 'AirBlue',
            'type' => 'Crane NDC 20.1 + Zapways OTA v2.06',
            'status' => 'not_configured',
            'environment' => 'sandbox',
            'required_credentials' => [
                'API channel (Crane NDC or Zapways OTA)',
                'Channel-specific credentials and base URL',
            ],
            'notes' => 'AirBlue PA: Crane NDC lifecycle or Zapways OTA search/book/ticket/read/cancel per connection channel.',
        ],
        'iati' => [
            'name' => 'IATI',
            'type' => 'Flight API v2 aggregator',
            'status' => 'not_configured',
            'environment' => 'sandbox',
            'required_credentials' => [
                'Auth code + secret (Basic auth)',
                'Organization / agency ID',
                'Cert or Live environment',
            ],
            'notes' => 'IATI Flight API v2: search, fare confirmation, option/book, retrieve, cancel. Cert uses testapi.iati.com; Live uses api.iati.com.',
        ],
        'one_api' => [
            'name' => 'One API',
            'type' => 'FlyJinnah / Air Arabia hybrid REST+SOAP',
            'status' => 'not_configured',
            'environment' => 'staging',
            'required_credentials' => [
                'REST auth + search URLs',
                'Username, password, agent code',
                'SOAP URL (when supplied by vendor)',
            ],
            'notes' => 'REST search + SOAP price, bundles, ancillaries, book, read, hold payment. No cancel API in vendor docs.',
        ],
        'airline_direct' => [
            'name' => 'Airline Direct API',
            'type' => 'Generic NDC / proprietary airline API',
            'status' => 'not_configured',
            'environment' => 'live',
            'required_credentials' => [
                'OAuth client credentials or API token',
                'Office / agency identifiers per carrier',
            ],
            'notes' => 'Each carrier publishes different schemas and payload constraints.',
        ],
    ],
    'integration_notice' => 'Real API integration begins only after credential security and technical documentation review.',
];
