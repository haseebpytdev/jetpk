<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSegmentSignature;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioExactOfferEvidence;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreRevalidationSegmentSignatureAndFareLinkageConsolidatedCorrectionPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_connecting_live_shaped_fixture_links_uniquely(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrConnectingDraft();
        $analysis = $linker->analyze($this->qrConnectingResponse(), $linker->buildSelectedContextFromDraft($draft));

        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);
        $this->assertSame(2, $analysis['selected_response_candidate_ordinal']);
    }

    public function test_pk_direct_response_shaped_fixture_links_uniquely(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->pkDirectDraft();
        $analysis = $linker->analyze($this->pkDirectResponse(), $linker->buildSelectedContextFromDraft($draft));

        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);
    }

    public function test_ey_connecting_response_shaped_fixture_links_uniquely(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->eyConnectingDraft();
        $analysis = $linker->analyze($this->eyConnectingResponse(), $linker->buildSelectedContextFromDraft($draft));

        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);
    }

    public function test_zero_padded_flight_numbers_normalize_consistently(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $this->assertSame('615', $canonical->normalizeFlightNumber('0615'));
        $this->assertSame('615', $canonical->normalizeFlightNumber('615'));

        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrConnectingDraft();
        $response = $this->qrConnectingResponse();
        data_set($response, 'groupedItineraryResponse.scheduleDescs.0.carrier.marketingFlightNumber', '0615');
        $analysis = $linker->analyze($response, $linker->buildSelectedContextFromDraft($draft));
        $this->assertTrue($analysis['usable_fare_linkage']);
    }

    public function test_equivalent_iso_datetime_formats_normalize_consistently(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $this->assertSame(
            $canonical->normalizeSignatureDateTime('2026-09-01T02:15:00'),
            $canonical->normalizeSignatureDateTime('2026-09-01T02:15:00+05:00'),
        );
        $this->assertSame(
            $canonical->comparableWallClock('2026-09-01T02:15:00'),
            $canonical->comparableWallClock('02:15:00'),
        );
    }

    public function test_different_flight_number_still_fails_closed(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrConnectingDraft();
        $draft['segments'][0]['flight_number'] = '9999';
        $analysis = $linker->analyze($this->qrConnectingResponse(), $linker->buildSelectedContextFromDraft($draft));
        $this->assertFalse($analysis['usable_fare_linkage']);
        $this->assertContains($analysis['linkage_failure_reason_code'], [
            SabreGdsRevalidationResponseCandidateLinker::REASON_NO_EXACT_SEGMENT_SIGNATURE_MATCH,
            SabreGdsRevalidationResponseCandidateLinker::REASON_NO_STRUCTURALLY_ELIGIBLE,
        ]);
    }

    public function test_ambiguous_candidates_still_fail_closed(): void
    {
        $itinerary = $this->qrMatchingItinerary(520.73);
        $response = [
            'groupedItineraryResponse' => array_merge($this->qrDescriptorTables(), [
                'itineraryGroups' => [[
                    'itineraries' => [$itinerary, $itinerary],
                ]],
            ]),
        ];
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $analysis = $linker->analyze($response, $linker->buildSelectedContextFromDraft($this->qrConnectingDraft()));
        $this->assertSame(2, $analysis['unique_usable_linkage_match_count']);
        $this->assertFalse($analysis['usable_fare_linkage']);
    }

    public function test_shop_evidence_and_draft_context_share_canonical_segment_signature(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $conn = \App\Models\SupplierConnection::query()->where('provider', \App\Enums\SupplierProvider::Sabre)->firstOrFail();
        $snap = [
            'validating_carrier' => 'QR',
            'segments' => $this->qrConnectingDraft()['segments'],
            'raw_payload' => [
                'sabre_shop_context' => ['itinerary_ref' => '2', 'pricing_information_index' => 0],
            ],
        ];
        $row = [
            'validating_carrier' => 'QR',
            'segment_count' => 2,
            'total_fare' => 520.73,
            'currency' => 'USD',
            'booking_classes_by_segment' => ['S', 'S'],
        ];
        $evidence = app(SabreGdsLiveScenarioExactOfferEvidence::class)->buildLinkageContext($conn, $snap, $row);
        $draftContext = app(SabreGdsRevalidationResponseCandidateLinker::class)->buildSelectedContextFromDraft($this->qrConnectingDraft());
        $this->assertSame($evidence['segment_signature'], $draftContext['segment_signature']);
    }

    public function test_failed_linkage_includes_safe_normalization_diagnostics(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrConnectingDraft();
        $draft['segments'][0]['flight_number'] = '9999';
        $analysis = $linker->analyze($this->qrConnectingResponse(), $linker->buildSelectedContextFromDraft($draft));
        $json = json_encode($analysis['linkage_normalization_diagnostics'] ?? [], JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertStringContainsString('mismatch_categories', $json);
        $this->assertStringNotContainsString('pseudo_city_code', strtolower($json));
    }

    public function test_sanitized_fixture_replay_contract_still_holds(): void
    {
        $fixturePath = base_path('tests/Fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json');
        $decoded = json_decode((string) file_get_contents($fixturePath), true);
        $this->assertIsArray($decoded);
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $decoded['api_draft'];
        $analysis = $linker->analyze($decoded['response'], $linker->buildSelectedContextFromDraft($draft), 31);
        $this->assertSame(31, $analysis['response_candidate_count']);
        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertSame(2, $analysis['selected_response_candidate_ordinal']);
        $this->assertTrue($analysis['usable_fare_linkage']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrConnectingDraft(): array
    {
        return [
            'provider' => 'sabre',
            'validating_carrier' => 'QR',
            'fare' => ['amount' => 520.73, 'currency' => 'USD'],
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pkDirectDraft(): array
    {
        return [
            'provider' => 'sabre',
            'validating_carrier' => 'PK',
            'fare' => ['amount' => 82.70, 'currency' => 'USD'],
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'KHI',
                'departure_at' => '2026-09-01T07:30:00',
                'arrival_at' => '2026-09-01T08:45:00',
                'carrier' => 'PK',
                'flight_number' => '301',
                'booking_class' => 'V',
                'fare_basis_code' => 'VOW1',
                'segment_cabin_code' => 'Y',
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function eyConnectingDraft(): array
    {
        return [
            'provider' => 'sabre',
            'validating_carrier' => 'EY',
            'fare' => ['amount' => 547.53, 'currency' => 'USD'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'AUH',
                    'departure_at' => '2026-09-01T03:10:00',
                    'arrival_at' => '2026-09-01T05:40:00',
                    'carrier' => 'EY',
                    'flight_number' => '242',
                    'booking_class' => 'Q',
                    'fare_basis_code' => 'QLOW1',
                    'segment_cabin_code' => 'Y',
                ],
                [
                    'origin' => 'AUH',
                    'destination' => 'JED',
                    'departure_at' => '2026-09-01T08:20:00',
                    'arrival_at' => '2026-09-01T10:35:00',
                    'carrier' => 'EY',
                    'flight_number' => '593',
                    'booking_class' => 'Q',
                    'fare_basis_code' => 'QLOW2',
                    'segment_cabin_code' => 'Y',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrConnectingResponse(): array
    {
        return [
            'groupedItineraryResponse' => array_merge($this->qrDescriptorTables(), [
                'itineraryGroups' => [[
                    'itineraries' => [
                        $this->qrMatchingItinerary(400.00, 'X', 'WRONG1', 'WRONG2'),
                        $this->qrMatchingItinerary(520.73),
                    ],
                ]],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pkDirectResponse(): array
    {
        return [
            'groupedItineraryResponse' => [
                'scheduleDescs' => [[
                    'ref' => 1,
                    'departure' => ['airport' => 'LHE', 'time' => '07:30:00'],
                    'arrival' => ['airport' => 'KHI', 'time' => '08:45:00'],
                    'carrier' => ['marketing' => 'PK', 'marketingFlightNumber' => '0301'],
                ]],
                'legDescs' => [['ref' => 1, 'schedules' => [['ref' => 1]]]],
                'itineraryGroups' => [[
                    'itineraries' => [$this->pkMatchingItinerary(82.70)],
                ]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function eyConnectingResponse(): array
    {
        return [
            'groupedItineraryResponse' => array_merge($this->eyDescriptorTables(), [
                'itineraryGroups' => [[
                    'itineraries' => [$this->eyMatchingItinerary(547.53)],
                ]],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrDescriptorTables(): array
    {
        return [
            'scheduleDescs' => [
                [
                    'ref' => 1,
                    'departure' => ['airport' => 'LHE', 'time' => '02:15:00'],
                    'arrival' => ['airport' => 'DOH', 'time' => '04:30:00'],
                    'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '0615'],
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
    protected function eyDescriptorTables(): array
    {
        return [
            'scheduleDescs' => [
                [
                    'ref' => 1,
                    'departure' => ['airport' => 'LHE', 'dateTime' => '2026-09-01T03:10:00'],
                    'arrival' => ['airport' => 'AUH', 'dateTime' => '2026-09-01T05:40:00'],
                    'carrier' => ['marketing' => ['code' => 'EY'], 'marketingFlightNumber' => 242],
                ],
                [
                    'ref' => 2,
                    'departure' => ['airport' => 'AUH', 'time' => '08:20:00'],
                    'arrival' => ['airport' => 'JED', 'time' => '10:35:00'],
                    'carrier' => ['marketing' => 'EY', 'marketingFlightNumber' => '0593'],
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
    protected function qrMatchingItinerary(
        float $total,
        string $bookingClass = 'S',
        string $fareBasisOne = 'SLOW1',
        string $fareBasisTwo = 'SLOW2',
    ): array {
        return [
            'legs' => [['ref' => 1], ['ref' => 2]],
            'pricingInformation' => [[
                'fare' => [
                    'totalFare' => ['totalPrice' => $total, 'currencyCode' => 'USD'],
                    'passengerInfoList' => [[
                        'passengerInfo' => [
                            'fareComponents' => [[
                                'segments' => [
                                    ['segment' => [
                                        'departure' => ['locationCode' => 'LHE'],
                                        'arrival' => ['locationCode' => 'DOH'],
                                        'bookingCode' => $bookingClass,
                                        'fareBasisCode' => $fareBasisOne,
                                        'cabinCode' => 'Y',
                                    ]],
                                    ['segment' => [
                                        'departure' => ['locationCode' => 'DOH'],
                                        'arrival' => ['locationCode' => 'JED'],
                                        'bookingCode' => $bookingClass,
                                        'fareBasisCode' => $fareBasisTwo,
                                        'cabinCode' => 'Y',
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pkMatchingItinerary(float $total): array
    {
        return [
            'legs' => [['ref' => 1]],
            'pricingInformation' => [[
                'fare' => [
                    'totalFare' => ['totalPrice' => $total, 'currencyCode' => 'USD'],
                    'passengerInfoList' => [[
                        'passengerInfo' => [
                            'fareComponents' => [[
                                'fareBasisCode' => 'VOW1',
                                'segments' => [[
                                    'segment' => [
                                        'departure' => ['locationCode' => 'LHE'],
                                        'arrival' => ['locationCode' => 'KHI'],
                                        'bookingCode' => 'V',
                                        'fareBasisCode' => 'VOW1',
                                        'cabinCode' => 'Y',
                                    ],
                                ]],
                            ]],
                        ],
                    ]],
                ],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function eyMatchingItinerary(float $total): array
    {
        return [
            'legs' => [['ref' => 1], ['ref' => 2]],
            'pricingInformation' => [[
                'fare' => [
                    'totalFare' => ['totalPrice' => $total, 'currencyCode' => 'USD'],
                    'passengerInfoList' => [[
                        'passengerInfo' => [
                            'fareComponents' => [[
                                'segments' => [
                                    ['segment' => [
                                        'departure' => ['locationCode' => 'LHE'],
                                        'arrival' => ['locationCode' => 'AUH'],
                                        'bookingCode' => 'Q',
                                        'fareBasisCode' => 'QLOW1',
                                        'cabinCode' => 'Y',
                                    ]],
                                    ['segment' => [
                                        'departure' => ['locationCode' => 'AUH'],
                                        'arrival' => ['locationCode' => 'JED'],
                                        'bookingCode' => 'Q',
                                        'fareBasisCode' => 'QLOW2',
                                        'cabinCode' => 'Y',
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
            ]],
        ];
    }
}
