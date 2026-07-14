<?php

namespace Tests\Unit\Support\Sabre;

use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use App\Support\Security\SensitiveDataRedactor;
use Tests\TestCase;

class SabrePassengerRecordsApplicationResultDigestTest extends TestCase
{
    public function test_digest_extracts_application_results_status_errors_warnings_and_messages(): void
    {
        $digest = app(SabrePassengerRecordsApplicationResultDigest::class)->digest([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [
                        [
                            'type' => 'Application',
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['code' => 'ERR.SP.PROVIDER_ERROR', 'content' => 'Unable to perform air booking step'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'Warning' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'content' => 'Host module warning token'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'Message' => [
                        ['code' => 'INFO.RECORD', 'content' => 'Processing note only'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Incomplete', $digest['application_status']);
        $this->assertSame('incomplete_no_locator', $digest['status']);
        $this->assertFalse($digest['has_record_locator']);
        $this->assertGreaterThan(0, $digest['error_count']);
        $this->assertGreaterThan(0, $digest['warning_count']);
        $this->assertGreaterThan(0, $digest['message_count']);
        $this->assertContains('CreatePassengerNameRecordRS', $digest['raw_keys_sample']);
        $this->assertContains('status', $digest['application_results_keys_sample']);
    }

    public function test_digest_preserves_safe_warn_sws_code_and_message(): void
    {
        $digest = app(SabrePassengerRecordsApplicationResultDigest::class)->digest([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Warning' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        [
                                            'code' => 'WARN.SWS.CLIENT.VALIDATION_FAILED',
                                            'content' => 'EnhancedAirBookRQ: CommandPricing@RPH must be combined with SegmentSelect@RPH',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $warning = $digest['warnings'][0] ?? [];
        $this->assertSame('WARN.SWS.CLIENT.VALIDATION_FAILED', $warning['code'] ?? null);
        $this->assertStringContainsString('CommandPricing@RPH must be combined with SegmentSelect@RPH', (string) ($warning['message'] ?? ''));
        $this->assertNotSame('[redacted]', $warning['message'] ?? null);

        $slice = SensitiveDataRedactor::sanitizeSupplierSummary(
            app(SabrePassengerRecordsApplicationResultDigest::class)->attemptSafeSummarySlice($digest),
        );
        $savedWarning = is_array($slice['safe_application_warnings'][0] ?? null) ? $slice['safe_application_warnings'][0] : [];
        $this->assertSame('WARN.SWS.CLIENT.VALIDATION_FAILED', $savedWarning['code'] ?? null);
        $this->assertStringContainsString('SegmentSelect@RPH', (string) ($savedWarning['message'] ?? ''));
    }

    public function test_digest_redacts_pii_email_phone_pcc_and_locator_tokens_in_messages(): void
    {
        $digest = app(SabrePassengerRecordsApplicationResultDigest::class)->digest([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Warning' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        [
                                            'code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE',
                                            'content' => 'Contact secret@example.com PCC AB12 locator ABC123 token bearer-secret',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $encoded = json_encode($digest, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('secret@example.com', $encoded);
        $warning = $digest['warnings'][0] ?? [];
        $this->assertSame('WARN.SWS.HOST.ERROR_IN_RESPONSE', $warning['code'] ?? null);
        $this->assertStringContainsString('[REDACTED_EMAIL]', (string) ($warning['message'] ?? ''));
        $this->assertStringContainsString('[REDACTED_PCC]', (string) ($warning['message'] ?? ''));
    }

    public function test_inspect_reclassifies_segmentselect_pairing_warning(): void
    {
        $service = app(SabrePassengerRecordsApplicationResultDigest::class);
        $digest = $service->digest([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Warning' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        [
                                            'code' => 'WARN.SWS.CLIENT.VALIDATION_FAILED',
                                            'content' => 'EnhancedAirBookRQ: CommandPricing@RPH must be combined with SegmentSelect@RPH',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $hostSlice = (new \ReflectionMethod($service, 'resolveHostClassificationSlice'))->invoke(
            $service,
            $digest,
            ['safe_reason_code' => SabreHostErrorClassifier::REASON_APPLICATION_INCOMPLETE_NO_LOCATOR],
            $digest['errors'] ?? [],
            $digest['warnings'] ?? [],
        );

        $this->assertSame(
            SabreHostErrorClassifier::REASON_COMMANDPRICING_SEGMENTSELECT_PAIRING_REQUIRED,
            $hostSlice['safe_reason_code'] ?? null,
        );
        $this->assertTrue($hostSlice['host_classification_reclassified_from_digest'] ?? false);
    }

    public function test_rehydrate_attempt_208_style_redacted_warnings_from_messages(): void
    {
        $service = app(SabrePassengerRecordsApplicationResultDigest::class);
        $rows = $service->rehydrateApplicationRowsFromAttemptSafeSummary([
            'safe_application_warnings' => [['type' => 'warning', 'message' => '[redacted]']],
            'response_error_codes' => ['WARN.SWS.CLIENT.VALIDATION_FAILED'],
            'response_error_messages' => ['EnhancedAirBookRQ: mixed interline not bookable for carrier combination'],
        ], 'warnings');

        $this->assertNotEmpty($rows);
        $this->assertSame('WARN.SWS.CLIENT.VALIDATION_FAILED', $rows[0]['code'] ?? null);
        $this->assertStringContainsString('interline', strtolower((string) ($rows[0]['message'] ?? '')));
    }

    public function test_digest_redacts_pii_like_payload_keys_and_values(): void
    {
        $digest = app(SabrePassengerRecordsApplicationResultDigest::class)->digest([
            'passengers' => [['email' => 'secret@example.com', 'givenName' => 'Hidden']],
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'NotProcessed',
                    'Error' => [
                        ['message' => 'Contact secret@example.com for help'],
                    ],
                ],
            ],
        ]);

        $encoded = json_encode($digest);
        $this->assertStringNotContainsString('secret@example.com', $encoded);
        $this->assertStringNotContainsString('Hidden', $encoded);
        $this->assertStringNotContainsString('passengers', $encoded);
        $this->assertSame('NotProcessed', $digest['application_status']);
        $this->assertTrue(app(SabrePassengerRecordsApplicationResultDigest::class)->shouldPersistForIncompleteNoLocator($digest));
    }

    public function test_convenience_meta_maps_first_error_and_status(): void
    {
        $service = app(SabrePassengerRecordsApplicationResultDigest::class);
        $digest = $service->digest([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['code' => 'ERR.SP.PROVIDER_ERROR', 'content' => 'Air booking step failed'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $meta = $service->convenienceMetaFromDigest($digest);
        $this->assertSame('Incomplete', $meta['sabre_booking_application_status']);
        $this->assertSame('ERR.SP.PROVIDER_ERROR', $meta['sabre_booking_application_error_code']);
        $this->assertSame('Air booking step failed', $meta['sabre_booking_application_error']);
        $this->assertSame('manual_review', $meta['supplier_booking_status']);
    }

    public function test_command_summary_reports_digest_availability(): void
    {
        $service = app(SabrePassengerRecordsApplicationResultDigest::class);
        $empty = $service->commandSummaryFromDigest(null);
        $this->assertFalse($empty['application_error_digest_available']);

        $digest = $service->digest([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['code' => 'ERR.SP.PROVIDER_ERROR', 'content' => 'Step failed'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $summary = $service->commandSummaryFromDigest($digest);
        $this->assertTrue($summary['application_error_digest_available']);
        $this->assertSame('Incomplete', $summary['sabre_application_status']);
        $this->assertSame('ERR.SP.PROVIDER_ERROR', $summary['sabre_application_first_error_code']);
    }

    public function test_attempt_safe_summary_slice_includes_structured_excerpts_without_raw_payload(): void
    {
        $service = app(SabrePassengerRecordsApplicationResultDigest::class);
        $digest = $service->digest([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['code' => 'ERR.SP.PROVIDER_ERROR', 'content' => 'Unable to perform air booking step'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'Warning' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'content' => 'Host module warning token'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'Success' => [
                        ['code' => 'INFO.OK', 'message' => 'Partial step acknowledged'],
                    ],
                ],
            ],
        ]);

        $slice = $service->attemptSafeSummarySlice($digest);
        $this->assertSame('Incomplete', $slice['safe_application_status']);
        $this->assertIsArray($slice['safe_validation_excerpts_structured']);
        $this->assertNotEmpty($slice['safe_validation_excerpts_structured']);
        $this->assertIsArray($slice['safe_validation_excerpts']);
        $this->assertNotEmpty($slice['safe_application_errors']);

        $encoded = json_encode($slice, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('CreatePassengerNameRecordRS', $encoded);
        $this->assertStringNotContainsString('request_body', $encoded);
    }

    public function test_host_classification_context_from_digest_maps_application_status(): void
    {
        $service = app(SabrePassengerRecordsApplicationResultDigest::class);
        $digest = $service->digest([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'NotProcessed',
                    'Error' => [
                        ['message' => 'No locator returned'],
                    ],
                ],
            ],
        ]);

        $context = $service->hostClassificationContextFromDigest($digest, [
            'error_code' => 'sabre_booking_application_error',
            'reason_code' => 'sabre_passenger_records_incomplete_no_pnr',
        ]);

        $this->assertSame('NotProcessed', $context['application_status']);
        $this->assertSame('incomplete_no_locator', $context['application_digest_status']);
        $this->assertContains('No locator returned', $context['response_error_messages']);
    }
}
