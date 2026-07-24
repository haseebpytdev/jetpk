<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreGdsRevalidationService;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationApplicationMessageDiagnostics;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationOutcomeMapper;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreRevalidationBfmApplicationWarningAndResponseLinkageCorrectionPhaseTest extends TestCase
{
    use RefreshDatabase;
    public function test_informational_statistics_warning_does_not_block_revalidation(): void
    {
        $diagnostics = app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->analyze([
            'groupedItineraryResponse' => [
                'statistics' => [
                    'itineraryCount' => 31,
                    'messages' => [[
                        'type' => 'INFO',
                        'severity' => 'INFO',
                        'code' => 'PROCESS',
                        'text' => 'Completed processing.',
                    ]],
                ],
                'itineraryGroups' => [[
                    'itineraries' => [$this->matchingItinerary('match-1', 520.83)],
                ]],
            ],
        ]);

        $this->assertTrue($diagnostics['informational_warning_present'] ?? false);
        $this->assertFalse($diagnostics['blocking_application_error_present'] ?? true);
        $this->assertFalse($diagnostics['blocking_application_warning_present'] ?? true);
        $this->assertFalse(app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->hasBlockingMessages($diagnostics));
    }

    public function test_blocking_application_error_still_fails_closed(): void
    {
        $diagnostics = app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->analyze([
            'errors' => [[
                'code' => 'APP.ERROR',
                'severity' => 'ERROR',
                'message' => 'Fatal application error',
            ]],
        ]);

        $this->assertTrue(app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->hasBlockingMessages($diagnostics));
        $this->assertTrue($diagnostics['blocking_application_error_present']);
    }

    public function test_blocking_warning_still_fails_closed(): void
    {
        $diagnostics = app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->analyze([
            'groupedItineraryResponse' => [
                'messages' => [[
                    'type' => 'WARNING',
                    'severity' => 'WARNING',
                    'code' => 'WARN',
                    'text' => 'NO FARES FOR REQUESTED ITINERARY',
                ]],
            ],
        ]);

        $this->assertTrue($diagnostics['blocking_application_warning_present']);
        $this->assertTrue(app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->hasBlockingMessages($diagnostics));
    }

    public function test_unique_exact_candidate_linkage_selects_non_zero_ordinal(): void
    {
        $response = $this->multiCandidateResponse();
        $draft = $this->qrConnectingDraft();
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $analysis = $linker->analyze($response, $linker->buildSelectedContextFromDraft($draft));

        $this->assertSame(3, $analysis['response_candidate_count']);
        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertSame(2, $analysis['selected_response_candidate_ordinal']);
        $this->assertTrue($analysis['usable_fare_linkage']);
        $this->assertArrayNotHasKey('linkage_failure_reason_code', $analysis);
    }

    public function test_zero_matches_fail_closed_with_reason(): void
    {
        $response = $this->multiCandidateResponse();
        $draft = $this->qrConnectingDraft();
        $draft['segments'][0]['arrival_at'] = '2026-09-01T05:30:00';
        $analysis = app(SabreGdsRevalidationResponseCandidateLinker::class)->analyze(
            $response,
            app(SabreGdsRevalidationResponseCandidateLinker::class)->buildSelectedContextFromDraft($draft),
        );

        $this->assertSame(0, $analysis['unique_usable_linkage_match_count']);
        $this->assertFalse($analysis['usable_fare_linkage']);
        $this->assertSame(
            SabreGdsRevalidationResponseCandidateLinker::REASON_NO_EXACT_SEGMENT_SIGNATURE_MATCH,
            $analysis['linkage_failure_reason_code'],
        );
    }

    public function test_multiple_exact_matches_fail_as_ambiguous(): void
    {
        $itinerary = $this->matchingItinerary('dup-a', 520.83);
        $response = [
            'groupedItineraryResponse' => array_merge($this->descriptorTables(), [
                'itineraryGroups' => [[
                    'itineraries' => [
                        $itinerary,
                        $itinerary,
                    ],
                ]],
            ]),
        ];
        $analysis = app(SabreGdsRevalidationResponseCandidateLinker::class)->analyze(
            $response,
            app(SabreGdsRevalidationResponseCandidateLinker::class)->buildSelectedContextFromDraft($this->qrConnectingDraft()),
        );

        $this->assertSame(2, $analysis['unique_usable_linkage_match_count']);
        $this->assertFalse($analysis['usable_fare_linkage']);
        $this->assertSame(
            SabreGdsRevalidationResponseCandidateLinker::REASON_AMBIGUOUS_EXACT_ITINERARY_MATCH,
            $analysis['linkage_failure_reason_code'],
        );
    }

    public function test_route_only_match_is_insufficient(): void
    {
        $wrongFlight = $this->matchingItinerary('route-only', 520.83);
        data_set($wrongFlight, 'pricingInformation.0.fare.passengerInfoList.0.passengerInfo.fareComponents.0.segments.0.segment.bookingCode', 'X');
        data_set($wrongFlight, 'pricingInformation.0.fare.passengerInfoList.0.passengerInfo.fareComponents.0.segments.1.segment.bookingCode', 'X');
        $response = [
            'groupedItineraryResponse' => array_merge($this->descriptorTables(), [
                'itineraryGroups' => [[
                    'itineraries' => [$wrongFlight],
                ]],
            ]),
        ];
        $analysis = app(SabreGdsRevalidationResponseCandidateLinker::class)->analyze(
            $response,
            app(SabreGdsRevalidationResponseCandidateLinker::class)->buildSelectedContextFromDraft($this->qrConnectingDraft()),
        );

        $this->assertFalse($analysis['usable_fare_linkage']);
        $this->assertSame(
            SabreGdsRevalidationResponseCandidateLinker::REASON_NO_EXACT_SEGMENT_SIGNATURE_MATCH,
            $analysis['linkage_failure_reason_code'],
        );
    }

    public function test_cheapest_candidate_is_not_selected(): void
    {
        $response = $this->multiCandidateResponse();
        $analysis = app(SabreGdsRevalidationResponseCandidateLinker::class)->analyze(
            $response,
            app(SabreGdsRevalidationResponseCandidateLinker::class)->buildSelectedContextFromDraft($this->qrConnectingDraft()),
        );

        $this->assertSame(2, $analysis['selected_response_candidate_ordinal']);
        $linkage = app(SabreGdsRevalidationResponseCandidateLinker::class)->extractLinkageForSelectedCandidate($response, $analysis);
        $this->assertSame(520.83, $linkage['revalidated_total']);
        $this->assertNotSame(400.00, $linkage['revalidated_total']);
    }

    public function test_http_200_informational_warning_with_unique_linkage_can_succeed_end_to_end(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $conn = $this->seedSabreConnection();
        Http::fake([
            'https://api.cert.platform.sabre.com/v2/auth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'https://api.cert.platform.sabre.com/v4/shop/flights/revalidate' => Http::response(
                $this->multiCandidateResponse(true),
                200,
            ),
        ]);

        $out = app(SabreGdsRevalidationService::class)->revalidateDraft(
            $this->qrConnectingDraft(),
            $conn,
            'bfm_revalidate_v1',
            null,
            null,
            '/v4/shop/flights/revalidate',
        );

        $this->assertTrue($out['success']);
        $this->assertTrue($out['informational_warning_present'] ?? false);
        $this->assertFalse($out['blocking_application_warning_present'] ?? true);
        $this->assertTrue($out['usable_fare_linkage']);
        $this->assertSame(520.83, $out['fare_comparison']['fresh_total']);
        $this->assertSame('USD', $out['fare_comparison']['fresh_currency']);
        Http::assertSentCount(2);
    }

    public function test_mapper_does_not_classify_informational_warning_as_supplier_application_error(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => true,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_ok',
            'revalidation_failure_class' => 'application_informational',
            'application_message_diagnostics' => [
                'application_warnings_present' => true,
                'informational_warning_present' => true,
                'blocking_application_error_present' => false,
                'blocking_application_warning_present' => false,
            ],
            'response_linkage_diagnostics' => [
                'usable_fare_linkage' => true,
                'pricing_complete' => true,
                'response_candidate_count' => 31,
                'unique_usable_linkage_match_count' => 1,
            ],
            'linkage_digest' => [
                'per_segment_fare_basis_complete' => true,
                'has_revalidated_fare' => true,
                'has_revalidated_currency' => true,
            ],
            'response_structure' => [
                'top_level_keys' => 'groupedItineraryResponse',
                'key_paths' => '',
                'empty_body' => 'false',
                'json_valid' => 'true',
                'candidate_fields' => 'totalFare',
                'candidate_count' => '31',
            ],
        ], true, true);

        $mapper = new SabreGdsLiveScenarioRevalidationOutcomeMapper;
        $evidence = $mapper->mapToScenarioEvidence($outcome, [
            'selected_total' => 520.83,
            'selected_currency' => 'USD',
        ]);
        $this->assertTrue($evidence['revalidation_success']);
        $this->assertTrue($evidence['freshness_satisfied']);
        $this->assertSame(
            SabreGdsLiveScenarioRevalidationOutcomeMapper::REASON_SUCCESS,
            $evidence['revalidation_reason_code'],
        );
        $this->assertFalse($outcome['blocking_application_warning_present'] ?? true);
    }

    public function test_diagnostics_exclude_raw_supplier_identifiers(): void
    {
        $diagnostics = app(SabreGdsRevalidationApplicationMessageDiagnostics::class)->analyze([
            'groupedItineraryResponse' => [
                'messages' => [[
                    'type' => 'SERVER',
                    'code' => 'sabre_GCC14-ISELL-REFTXN-12345',
                    'text' => '27131',
                ]],
                'transactionId' => 'sabre_GCC14-ISELL-REFTXN-12345',
            ],
        ]);
        $json = json_encode($diagnostics, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('sabre_GCC14-ISELL-REFTXN-12345', $json);
        $this->assertStringNotContainsString('ISELL', $json);
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrConnectingDraft(): array
    {
        return [
            'provider' => 'sabre',
            'validating_carrier' => 'QR',
            'fare' => ['amount' => 520.83, 'currency' => 'USD'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'departure_at' => '2026-09-01T02:15:00',
                    'arrival_at' => '2026-09-01T04:30:00',
                    'carrier' => 'QR',
                    'flight_number' => '615',
                    'booking_class' => 'S',
                    'fare_basis_code' => 'SLOW1',
                    'segment_cabin_code' => 'Y',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'departure_at' => '2026-09-01T06:00:00',
                    'arrival_at' => '2026-09-01T08:15:00',
                    'carrier' => 'QR',
                    'flight_number' => '1184',
                    'booking_class' => 'S',
                    'fare_basis_code' => 'SLOW2',
                    'segment_cabin_code' => 'Y',
                ],
            ],
            'passengers' => [['type' => 'ADT', 'first_name' => 'A', 'last_name' => 'B']],
            '_sabre_shop_identifiers' => ['pseudo_city_code' => 'TEST'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function multiCandidateResponse(bool $withInformationalStatistics = false): array
    {
        $gir = array_merge($this->descriptorTables(), [
            'itineraryGroups' => [[
                'itineraries' => [
                    $this->matchingItinerary('cheap-wrong', 400.00, 'X', 'WRONG1', 'WRONG2'),
                    $this->matchingItinerary('match-1', 520.83),
                    $this->matchingItinerary('route-only', 510.00, 'X', 'SLOW1', 'SLOW2'),
                ],
            ]],
        ]);
        if ($withInformationalStatistics) {
            $gir['statistics'] = [
                'itineraryCount' => 31,
                'messages' => [[
                    'type' => 'INFO',
                    'severity' => 'INFO',
                    'code' => 'PROCESS',
                    'text' => 'Completed processing.',
                ]],
            ];
        }

        return ['groupedItineraryResponse' => $gir];
    }

    /**
     * @return array<string, mixed>
     */
    protected function descriptorTables(): array
    {
        return [
            'scheduleDescs' => [
                [
                    'ref' => 1,
                    'departure' => ['airport' => 'LHE', 'time' => '02:15:00'],
                    'arrival' => ['airport' => 'DOH', 'time' => '04:30:00'],
                    'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '615'],
                ],
                [
                    'ref' => 2,
                    'departure' => ['airport' => 'DOH', 'time' => '06:00:00'],
                    'arrival' => ['airport' => 'JED', 'time' => '08:15:00'],
                    'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '1184'],
                ],
            ],
            'legDescs' => [
                ['ref' => 1, 'schedules' => [['ref' => 1]]],
                ['ref' => 2, 'schedules' => [['ref' => 2]]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function matchingItinerary(
        string $label,
        float $total,
        string $bookingClass = 'S',
        string $fareBasisOne = 'SLOW1',
        string $fareBasisTwo = 'SLOW2',
    ): array {
        return [
            'id' => $label,
            'legs' => [['ref' => 1], ['ref' => 2]],
            'pricingInformation' => [[
                'fare' => [
                    'validatingCarrierCode' => 'QR',
                    'totalFare' => ['totalPrice' => $total, 'currencyCode' => 'USD'],
                    'passengerInfoList' => [[
                        'passengerInfo' => [
                            'fareComponents' => [[
                                'segments' => [
                                    ['segment' => ['bookingCode' => $bookingClass, 'fareBasisCode' => $fareBasisOne, 'cabinCode' => 'Y']],
                                    ['segment' => ['bookingCode' => $bookingClass, 'fareBasisCode' => $fareBasisTwo, 'cabinCode' => 'Y']],
                                ],
                            ]],
                        ],
                    ]],
                ],
            ]],
        ];
    }

    protected function seedSabreConnection(): \App\Models\SupplierConnection
    {
        $agency = \App\Models\Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = \App\Models\SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', \App\Enums\SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://api.cert.platform.sabre.com';
        $conn->credentials = ['client_id' => 'cid', 'client_secret' => 'sec', 'pcc' => 'TEST'];
        $conn->save();

        return $conn;
    }
}
