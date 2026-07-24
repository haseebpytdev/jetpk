<?php

namespace Tests\Unit;

use App\Support\Sabre\Revalidation\SabreGdsRevalidationCanonicalSegmentSignature;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationResponseCandidateLinker;
use Tests\TestCase;

class SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest extends TestCase
{
    public function test_absent_operating_equals_explicit_same_as_marketing_in_signature(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $absent = [
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'marketing_carrier' => 'QR',
            'flight_number' => '629',
            'booking_class' => 'S',
        ];
        $explicit = array_merge($absent, ['operating_carrier' => 'QR']);
        $this->assertSame(
            $canonical->hashFromSegments([$absent]),
            $canonical->hashFromSegments([$explicit]),
        );
        $this->assertSame('absent', $canonical->operatingCarrierShapeCategory($absent));
        $this->assertSame('same_as_marketing', $canonical->operatingCarrierShapeCategory($explicit));
    }

    public function test_different_operating_carrier_still_changes_signature(): void
    {
        $canonical = app(SabreGdsRevalidationCanonicalSegmentSignature::class);
        $marketingOnly = [
            'marketing_carrier' => 'QR',
            'origin' => 'LHE',
            'destination' => 'DOH',
            'departure_at' => '11:35:00',
            'arrival_at' => '15:05:00',
            'flight_number' => '629',
            'booking_class' => 'S',
        ];
        $codeshare = array_merge($marketingOnly, ['operating_carrier' => 'BA']);
        $this->assertNotSame(
            $canonical->hashFromSegments([$marketingOnly]),
            $canonical->hashFromSegments([$codeshare]),
        );
        $this->assertSame('different_from_marketing', $canonical->operatingCarrierShapeCategory($codeshare));
    }

    public function test_schedule_desc_explicit_operating_matches_shop_absent_operating(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrLiveDraft();
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $analysis = $linker->analyze($this->qrLiveResponseWithExplicitOperating(), $selected);
        $this->assertSame(1, $analysis['exact_segment_signature_match_count']);
        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);
    }

    public function test_fare_component_desc_refs_populate_per_segment_fare_basis(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrLiveDraft();
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $response = $this->qrLiveResponseWithFareComponentDescRefs();
        $analysis = $linker->analyze($response, $selected);
        $this->assertSame(1, $analysis['fare_basis_compatible_match_count']);
        $this->assertSame(1, $analysis['unique_usable_linkage_match_count']);
        $this->assertTrue($analysis['usable_fare_linkage']);

        $candidate = $linker->normalizedCandidateSegmentsForDiagnostics(
            $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0],
            $response,
        );
        $this->assertSame('SLOW1', $candidate[0]['fare_basis_code'] ?? null);
        $this->assertSame('SLOW2', $candidate[1]['fare_basis_code'] ?? null);
    }

    public function test_one_fare_component_covering_two_segments_applies_both_indices(): void
    {
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $draft = $this->qrLiveDraft();
        $draft['segments'][0]['fare_basis_code'] = 'COMBO1';
        $draft['segments'][1]['fare_basis_code'] = 'COMBO1';
        $selected = $linker->buildSelectedContextFromDraft($draft);
        $response = $this->qrLiveResponseWithSingleFareComponentDesc('COMBO1');
        $analysis = $linker->analyze($response, $selected);
        $this->assertTrue($analysis['usable_fare_linkage']);
    }

    public function test_sanitized_31_candidate_fixture_still_selects_ordinal_2(): void
    {
        $fixturePath = base_path('tests/Fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json');
        $decoded = json_decode((string) file_get_contents($fixturePath), true);
        $linker = app(SabreGdsRevalidationResponseCandidateLinker::class);
        $analysis = $linker->analyze(
            $decoded['response'],
            $linker->buildSelectedContextFromDraft($decoded['api_draft']),
            31,
        );
        $this->assertSame(2, $analysis['selected_response_candidate_ordinal']);
        $this->assertTrue($analysis['usable_fare_linkage']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrLiveDraft(): array
    {
        return [
            'provider' => 'sabre',
            'validating_carrier' => 'QR',
            'fare' => ['amount' => 540.73, 'currency' => 'USD'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'departure_at' => '2026-09-01T11:35:00',
                    'arrival_at' => '2026-09-01T15:05:00',
                    'carrier' => 'QR',
                    'flight_number' => '629',
                    'booking_class' => 'S',
                    'fare_basis_code' => 'SLOW1',
                    'segment_cabin_code' => 'Y',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'departure_at' => '2026-09-01T23:10:00',
                    'arrival_at' => '2026-09-02T01:40:00',
                    'carrier' => 'QR',
                    'flight_number' => '1182',
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
    protected function qrLiveDescriptorTables(): array
    {
        return [
            'scheduleDescs' => [
                [
                    'ref' => 1,
                    'departure' => ['airport' => 'LHE', 'time' => '11:35:00'],
                    'arrival' => ['airport' => 'DOH', 'time' => '15:05:00'],
                    'carrier' => ['marketing' => 'QR', 'operating' => 'QR', 'marketingFlightNumber' => '629'],
                ],
                [
                    'ref' => 2,
                    'departure' => ['airport' => 'DOH', 'time' => '23:10:00'],
                    'arrival' => ['airport' => 'JED', 'time' => '01:40:00'],
                    'carrier' => ['marketing' => 'QR', 'operating' => 'QR', 'marketingFlightNumber' => '1182'],
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
    protected function qrLiveResponseWithExplicitOperating(): array
    {
        return [
            'groupedItineraryResponse' => array_merge($this->qrLiveDescriptorTables(), [
                'itineraryGroups' => [[
                    'itineraries' => [$this->qrMatchingItinerary(540.73)],
                ]],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrLiveResponseWithFareComponentDescRefs(): array
    {
        return [
            'groupedItineraryResponse' => array_merge($this->qrLiveDescriptorTables(), [
                'fareComponentDescs' => [
                    ['ref' => 10, 'fareBasisCode' => 'SLOW1'],
                    ['ref' => 11, 'fareBasisCode' => 'SLOW2'],
                ],
                'itineraryGroups' => [[
                    'itineraries' => [[
                        'legs' => [['ref' => 1], ['ref' => 2]],
                        'pricingInformation' => [[
                            'fare' => [
                                'totalFare' => ['totalPrice' => 540.73, 'currencyCode' => 'USD'],
                                'passengerInfoList' => [[
                                    'passengerInfo' => [
                                        'passengerType' => 'ADT',
                                        'fareComponents' => [
                                            [
                                                'ref' => 10,
                                                'segments' => [
                                                    ['segment' => ['id' => 1, 'bookingCode' => 'S', 'cabinCode' => 'Y']],
                                                ],
                                            ],
                                            [
                                                'ref' => 11,
                                                'segments' => [
                                                    ['segment' => ['id' => 2, 'bookingCode' => 'S', 'cabinCode' => 'Y']],
                                                ],
                                            ],
                                        ],
                                    ],
                                ]],
                            ],
                        ]],
                    ]],
                ]],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrLiveResponseWithSingleFareComponentDesc(string $fareBasis): array
    {
        return [
            'groupedItineraryResponse' => array_merge($this->qrLiveDescriptorTables(), [
                'fareComponentDescs' => [
                    ['ref' => 20, 'fareBasisCode' => $fareBasis],
                ],
                'itineraryGroups' => [[
                    'itineraries' => [[
                        'legs' => [['ref' => 1], ['ref' => 2]],
                        'pricingInformation' => [[
                            'fare' => [
                                'totalFare' => ['totalPrice' => 540.73, 'currencyCode' => 'USD'],
                                'passengerInfoList' => [[
                                    'passengerInfo' => [
                                        'fareComponents' => [[
                                            'ref' => 20,
                                            'segments' => [
                                                ['segment' => ['id' => 1, 'bookingCode' => 'S', 'cabinCode' => 'Y']],
                                                ['segment' => ['id' => 2, 'bookingCode' => 'S', 'cabinCode' => 'Y']],
                                            ],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ]],
                ]],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function qrMatchingItinerary(float $total): array
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
                                        'arrival' => ['locationCode' => 'DOH'],
                                        'bookingCode' => 'S',
                                        'fareBasisCode' => 'SLOW1',
                                        'cabinCode' => 'Y',
                                    ]],
                                    ['segment' => [
                                        'departure' => ['locationCode' => 'DOH'],
                                        'arrival' => ['locationCode' => 'JED'],
                                        'bookingCode' => 'S',
                                        'fareBasisCode' => 'SLOW2',
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
