<?php

namespace Tests\Unit\Services\Suppliers\Sabre;

use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use Tests\TestCase;

class SabreBookingClientUcHaltOnStatusTest extends TestCase
{
    public function test_digest_extracts_uc_flights_and_retry_blockers(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['content' => 'Unable to perform air booking step'],
                                        ['content' => 'EnhancedAirBookRQ: Specified HaltOnStatus Received - Processing Aborted'],
                                        ['content' => 'Flight SV739 returned status code UC'],
                                        ['content' => 'Flight SV568 returned status code UC'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $digest = $client->digestBookingResponseJsonForProbe($json);

        $this->assertSame('UC', $digest['airline_segment_status'] ?? null);
        $this->assertContains('SV739', (array) ($digest['affected_flight_numbers'] ?? []));
        $this->assertContains('SV568', (array) ($digest['affected_flight_numbers'] ?? []));
        $this->assertTrue($digest['halt_on_status_received'] ?? false);
        $this->assertSame('airline_segment_status_uc', $digest['probable_issue'] ?? null);
        $this->assertContains('choose_alternate_itinerary', (array) ($digest['retry_blocker_reasons'] ?? []));
    }

    public function test_http_200_uc_halt_on_status_returns_needs_review_without_pnr(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['content' => 'WARN.SP.HALT_ON_STATUS_RECEIVED'],
                                        ['content' => 'Flight SV739 returned status code UC'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $ref = new \ReflectionMethod(SabreBookingClient::class, 'normalizePassengerRecordsCpnrHttp200Response');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $out */
        $out = $ref->invoke($client, $json, 200, 120, ['booking_id' => 21]);

        $this->assertFalse($out['success']);
        $this->assertSame('needs_review', $out['status']);
        $this->assertNull($out['pnr']);
        $this->assertNull($out['record_locator']);
        $this->assertNull($out['provider_booking_id']);
        $this->assertSame('sabre_booking_application_error', $out['error_code']);
        $this->assertSame('sabre_passenger_records_halt_on_status_uc', $out['reason_code']);
        $this->assertStringContainsString('SV739', (string) ($out['safe_message'] ?? ''));
    }

    public function test_digest_classifies_nn_halt_separately_from_uc(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['content' => 'WARN.SP.HALT_ON_STATUS_RECEIVED'],
                                        ['content' => 'EnhancedAirBookRQ: Specified HaltOnStatus Received - Processing Aborted'],
                                        ['content' => 'Flight PK303 returned status code NN'],
                                        ['content' => 'Flight PK761 returned status code NN'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $digest = $client->digestBookingResponseJsonForProbe($json);

        $this->assertSame('NN', $digest['airline_segment_status'] ?? null);
        $this->assertTrue($digest['halt_on_status_received'] ?? false);
        $this->assertSame('airline_segment_status_nn_halt', $digest['probable_issue'] ?? null);
        $this->assertContains('PK303', (array) ($digest['affected_flight_numbers'] ?? []));
    }

    public function test_http_200_nn_halt_returns_needs_review_with_nn_reason_code(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['content' => 'WARN.SP.HALT_ON_STATUS_RECEIVED'],
                                        ['content' => 'Flight PK303 returned status code NN'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $ref = new \ReflectionMethod(SabreBookingClient::class, 'normalizePassengerRecordsCpnrHttp200Response');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $out */
        $out = $ref->invoke($client, $json, 200, 90, []);

        $this->assertFalse($out['success']);
        $this->assertSame('needs_review', $out['status']);
        $this->assertNull($out['pnr']);
        $this->assertSame('sabre_passenger_records_halt_on_status_nn', $out['reason_code']);
    }

    public function test_http_200_pnr_success_unchanged(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => ['status' => 'Complete'],
                'ItineraryRef' => ['ID' => 'ABCDEF'],
            ],
        ];

        $ref = new \ReflectionMethod(SabreBookingClient::class, 'normalizePassengerRecordsCpnrHttp200Response');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $out */
        $out = $ref->invoke($client, $json, 200, 50, []);

        $this->assertTrue($out['success']);
        $this->assertSame('pending_payment_or_ticketing', $out['status']);
        $this->assertSame('ABCDEF', $out['pnr']);
        $this->assertNull($out['error_code']);
    }

    public function test_http_200_allow_nn_operational_pnr_with_nn_status_is_unconfirmed_manual_review(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Complete',
                    'Warning' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [
                                ['content' => 'EnhancedAirBookRQ: Flight GF765 returned status code NN'],
                            ],
                        ]],
                    ]],
                ],
                'ItineraryRef' => ['ID' => 'QPXBOE'],
            ],
        ];

        $ref = new \ReflectionMethod(SabreBookingClient::class, 'normalizePassengerRecordsCpnrHttp200Response');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $out */
        $out = $ref->invoke($client, $json, 200, 50, ['allow_nn_cert_operational' => true]);

        $this->assertFalse($out['success']);
        $this->assertSame('needs_review', $out['status']);
        $this->assertSame('QPXBOE', $out['pnr']);
        $this->assertSame('sabre_passenger_records_pnr_unconfirmed_segment_nn', $out['reason_code']);
        $this->assertSame('sabre_booking_application_error', $out['error_code']);
    }

    public function test_http_200_check_flight_number_no_pnr_is_application_error(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [
                                ['code' => 'ERR.SP.PROVIDER_ERROR', 'content' => 'Unable to perform air booking step'],
                            ],
                        ]],
                    ]],
                    'Warning' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [
                                ['code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'content' => 'EnhancedAirBookRQ: CHECK FLIGHT NUMBER'],
                            ],
                        ]],
                    ]],
                ],
            ],
        ];

        $ref = new \ReflectionMethod(SabreBookingClient::class, 'normalizePassengerRecordsCpnrHttp200Response');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $out */
        $out = $ref->invoke($client, $json, 200, 100, ['booking_id' => 39]);

        $this->assertFalse($out['success']);
        $this->assertSame('needs_review', $out['status']);
        $this->assertNull($out['pnr']);
        $this->assertSame('sabre_booking_application_error', $out['error_code']);
        $this->assertSame('sabre_passenger_records_incomplete_no_pnr', $out['reason_code']);
        $this->assertStringContainsString('CHECK FLIGHT NUMBER', (string) ($out['safe_message'] ?? ''));
        $diag = is_array($out['booking_diagnostics'] ?? null) ? $out['booking_diagnostics'] : [];
        $this->assertFalse((bool) ($diag['pnr_present_in_response_body'] ?? true));
        $this->assertTrue((bool) ($diag['application_results_incomplete'] ?? false));
        $this->assertContains('ERR.SP.PROVIDER_ERROR', (array) ($diag['response_error_codes'] ?? []));
    }

    public function test_http_200_complete_no_pnr_with_provider_error_is_not_customer_success(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Complete',
                    'Error' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [
                                ['code' => 'ERR.SP.PROVIDER_ERROR', 'content' => 'Unable to perform air booking step'],
                            ],
                        ]],
                    ]],
                    'Warning' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [
                                ['code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'content' => 'EnhancedAirBookRQ: CHECK FLIGHT NUMBER'],
                            ],
                        ]],
                    ]],
                ],
            ],
        ];

        $ref = new \ReflectionMethod(SabreBookingClient::class, 'normalizePassengerRecordsCpnrHttp200Response');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $out */
        $out = $ref->invoke($client, $json, 200, 100, []);

        $this->assertFalse($out['success']);
        $this->assertSame('needs_review', $out['status']);
        $this->assertNull($out['pnr']);
        $this->assertSame('sabre_booking_application_error', $out['error_code']);
        $this->assertSame('sabre_passenger_records_application_booking_failure', $out['reason_code']);
    }

    public function test_http_200_not_processed_no_pnr_is_application_error(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'NotProcessed',
                    'Error' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [
                                ['code' => 'ERR.SP.PROVIDER_ERROR', 'content' => 'Unable to perform air booking step'],
                            ],
                        ]],
                    ]],
                ],
            ],
        ];

        $ref = new \ReflectionMethod(SabreBookingClient::class, 'normalizePassengerRecordsCpnrHttp200Response');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $out */
        $out = $ref->invoke($client, $json, 200, 100, []);

        $this->assertFalse($out['success']);
        $this->assertSame('sabre_booking_application_error', $out['error_code']);
        $this->assertSame('sabre_passenger_records_incomplete_no_pnr', $out['reason_code']);
    }

    public function test_http_200_pnr_with_host_warnings_remains_pending_ticketing(): void
    {
        $client = new SabreBookingClient(
            $this->createMock(SabreClient::class),
            app(SabreBookingPayloadBuilder::class),
        );

        $json = [
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Complete',
                    'Warning' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [
                                ['code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'content' => 'EnhancedAirBookRQ: informational only'],
                            ],
                        ]],
                    ]],
                ],
                'ItineraryRef' => ['ID' => 'QPXBOE'],
            ],
        ];

        $ref = new \ReflectionMethod(SabreBookingClient::class, 'normalizePassengerRecordsCpnrHttp200Response');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $out */
        $out = $ref->invoke($client, $json, 200, 50, []);

        $this->assertTrue($out['success']);
        $this->assertSame('pending_payment_or_ticketing', $out['status']);
        $this->assertSame('QPXBOE', $out['pnr']);
        $this->assertNull($out['error_code']);
        $this->assertSame('sabre_booking_success', $out['reason_code']);
    }
}
