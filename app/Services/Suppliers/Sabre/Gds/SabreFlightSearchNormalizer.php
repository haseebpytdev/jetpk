<?php

namespace App\Services\Suppliers\Sabre\Gds;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\FlightSearchRequestData;
use App\Data\FlightSegmentData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\FlightSearch\BaggageDisplayNormalizer;
use App\Support\FlightSearch\SabreMarketEndpointEquivalence;
use App\Support\Suppliers\SabreItineraryTimingValidator;
use App\Support\Suppliers\SabreSegmentChronologyRepair;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Normalizes Sabre Bargain Finder / shop grouped responses into {@see NormalizedFlightOfferData}.
 * Stores safe `raw_payload.sabre_fare_excerpt` and compact Sabre shop context/reference tokens for revalidation/Trip Orders booking.
 * B1/B2A: extra `pricingInformation` rows may appear in {@code branded_fares} with readiness metadata ({@code selectable} stays false until B2B/C).
 * BF4: IATI-style brand resolution follows {@code fareComponents[].ref} into {@code fareComponentDescs[].brand} (optional {@code fareBrandDescs} lookup).
 * {@see brandedFaresProbeDiagnostics()} emits aggregate {@code sabre.branded_fares_probe} metadata (no raw payload).
 * Round-trip shop rows may use {@see buildRoundTripSegmentsFromWholeItineraryRouteChains} when leg-wrapper assembly fails continuity.
 */
class SabreFlightSearchNormalizer
{
    /** Max schedule edges for round-trip whole-itinerary route-chain DFS (live safety). */
    protected const MAX_RT_WHOLE_ITINERARY_SCHEDULE_EDGES = 12;

    /** @var array<string, int> */
    protected array $durationSourceHistogram = [
        'segment_timeline' => 0,
        'leg_elapsed_time' => 0,
        'calculated' => 0,
        'unavailable' => 0,
    ];

    protected int $diagDateAdjustedSegmentCount = 0;

    protected int $diagMissingSegmentTimeCount = 0;

    protected int $diagUnreliableLayoverConnectionCount = 0;

    /** @var array<string, int|string> */
    protected array $lastDisplayDiagnostics = [
        'date_adjusted_segment_count' => 0,
        'missing_segment_time_count' => 0,
        'raw_decimal_layover_suppressed_count' => 0,
        'itinerary_duration_source' => 'unavailable',
    ];

    /**
     * Descriptor resolution context for the current {@see normalize()} call only.
     *
     * @var array<string, array{rows: list<array<string, mixed>>, lookup: array<int, array<string, mixed>>, explicit: bool}>|null
     */
    protected ?array $descriptorResolutionCtx = null;

    /**
     * Full GIR descriptor lists for the current {@see normalize()} call (server-only archive slices).
     *
     * @var array{scheduleDescs: list<array<string, mixed>>, legDescs: list<array<string, mixed>>, fareComponentDescs: list<array<string, mixed>>}|null
     */
    protected ?array $currentGirDescriptorLists = null;

    protected int $descriptorRejectProbeEmitted = 0;

    /**
     * Snapshot from the last {@see normalize()} call (safe counts only).
     *
     * @return array<string, int|string>
     */
    public function getDisplayDiagnostics(): array
    {
        return $this->lastDisplayDiagnostics;
    }

    protected function finalizeDisplayDiagnosticsSnapshot(): void
    {
        $primary = 'unavailable';
        if (($this->durationSourceHistogram['segment_timeline'] ?? 0) > 0) {
            $primary = 'segment_timeline';
        } elseif ($this->durationSourceHistogram['leg_elapsed_time'] > 0) {
            $primary = 'leg_elapsed_time';
        } elseif ($this->durationSourceHistogram['calculated'] > 0) {
            $primary = 'calculated';
        }

        $this->lastDisplayDiagnostics = [
            'date_adjusted_segment_count' => $this->diagDateAdjustedSegmentCount,
            'missing_segment_time_count' => $this->diagMissingSegmentTimeCount,
            'raw_decimal_layover_suppressed_count' => $this->diagUnreliableLayoverConnectionCount,
            'itinerary_duration_source' => $primary,
        ];
    }

    protected function resetDisplayDiagnostics(): void
    {
        $this->durationSourceHistogram = [
            'segment_timeline' => 0,
            'leg_elapsed_time' => 0,
            'calculated' => 0,
            'unavailable' => 0,
        ];
        $this->diagDateAdjustedSegmentCount = 0;
        $this->diagMissingSegmentTimeCount = 0;
        $this->diagUnreliableLayoverConnectionCount = 0;
        $this->lastDisplayDiagnostics = [
            'date_adjusted_segment_count' => 0,
            'missing_segment_time_count' => 0,
            'raw_decimal_layover_suppressed_count' => 0,
            'itinerary_duration_source' => 'unavailable',
        ];
        $this->descriptorResolutionCtx = null;
        $this->currentGirDescriptorLists = null;
        $this->descriptorRejectProbeEmitted = 0;
    }

    protected function registerOfferDurationSource(string $source): void
    {
        $this->durationSourceHistogram[$source] = ($this->durationSourceHistogram[$source] ?? 0) + 1;
    }

    /**
     * Safe counts for diagnostics only (no raw payload). Includes BFM v4 structure metrics.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function inventorySummary(array $response): array
    {
        $root = data_get($response, 'groupedItineraryResponse');
        $groups = data_get($response, 'groupedItineraryResponse.itineraryGroups');
        $responseHasGrouped = is_array($root);
        $groupCount = is_array($groups) ? count($groups) : 0;
        $itineraryCount = 0;
        $pricingInformationCount = 0;
        $fareTotalPresentCount = 0;
        $scheduleRefCount = 0;

        $scheduleDescs = $this->listDescs($response, 'scheduleDescs');
        $legDescs = $this->listDescs($response, 'legDescs');

        foreach ($legDescs as $leg) {
            if (! is_array($leg)) {
                continue;
            }
            $schedules = $leg['schedules'] ?? [];
            if (is_array($schedules)) {
                $scheduleRefCount += count($schedules);
            }
        }

        if (is_array($groups)) {
            foreach ($groups as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $itineraries = data_get($group, 'itineraries');
                if (! is_array($itineraries)) {
                    continue;
                }
                $itineraryCount += count($itineraries);
                foreach ($itineraries as $itinerary) {
                    if (! is_array($itinerary)) {
                        continue;
                    }
                    $pi = $itinerary['pricingInformation'] ?? [];
                    if (! is_array($pi)) {
                        continue;
                    }
                    $pricingInformationCount += count($pi);
                    foreach ($pi as $block) {
                        $fare = is_array($block) ? ($block['fare'] ?? null) : null;
                        if (is_array($fare) && $this->extractFareBreakdownFromFare($fare)['supplier_total'] > 0) {
                            $fareTotalPresentCount++;
                        }
                    }
                }
            }
        }

        $fareComponentDescs = $this->listDescs($response, 'fareComponentDescs');
        $brandFeatureDescs = $this->listDescs($response, 'brandFeatureDescs');
        $fareBrandDescs = $this->listDescs($response, 'fareBrandDescs');

        return [
            'response_has_grouped_itinerary' => $responseHasGrouped,
            'has_grouped_itinerary_response' => $responseHasGrouped,
            'itinerary_group_count' => $groupCount,
            'itinerary_count' => $itineraryCount,
            'schedule_desc_count' => count($scheduleDescs),
            'leg_desc_count' => count($legDescs),
            'pricing_information_count' => $pricingInformationCount,
            'fare_total_present_count' => $fareTotalPresentCount,
            'schedule_ref_count' => $scheduleRefCount,
            'fare_component_desc_count' => count($fareComponentDescs),
            'fare_component_descs_with_brand_count' => $this->countFareComponentDescsWithBrand($fareComponentDescs),
            'brand_feature_desc_count' => count($brandFeatureDescs),
            'fare_brand_desc_count' => count($fareBrandDescs),
        ];
    }

    /**
     * Aggregate branded-fare mapping probe for one Sabre shop response (metadata only; no raw payload).
     *
     * @param  array<string, mixed>  $response
     * @param  list<NormalizedFlightOfferData>  $offers
     * @return array<string, mixed>
     */
    public function brandedFaresProbeDiagnostics(array $response, array $offers): array
    {
        $gir = data_get($response, 'groupedItineraryResponse');
        if (! is_array($gir)) {
            return array_merge($this->emptyBrandedFaresProbeDiagnosticsShell(count($offers)), [
                'offers_with_branded_fares' => $this->countOffersWithBrandedFares($offers),
            ]);
        }

        $this->primeGirDescriptorContextFromResponse($response);
        $fareComponentDescs = $this->listDescs($response, 'fareComponentDescs');
        $brandFeatureDescs = $this->listDescs($response, 'brandFeatureDescs');
        $fareBrandDescs = $this->listDescs($response, 'fareBrandDescs');

        $itineraries = $this->collectItineraries($gir);
        $histogram = [
            'too_few_pi_rows' => 0,
            'no_brand_fields' => 0,
            'zero_price' => 0,
            'dedupe_collapsed' => 0,
            'options_lt_2' => 0,
        ];
        $piRowCount = 0;
        $itinerariesPiCount0 = 0;
        $itinerariesPiCount1 = 0;
        $itinerariesPiCount2 = 0;
        $itinerariesPiCount3Plus = 0;
        $piRowsWithPositiveTotal = 0;
        $piRowsWithBrandName = 0;
        $piRowsWithBrandCode = 0;
        $piRowsWithBrandNameOrCode = 0;
        $piRowsWithInlineBrandName = 0;
        $piRowsWithInlineBrandCode = 0;
        $piRowsWithDescriptorBrandName = 0;
        $piRowsWithDescriptorBrandCode = 0;
        $itinerariesDistinctBrandNames2Plus = 0;
        $samples = [];

        foreach ($itineraries as $idx => $itinerary) {
            if (! is_array($itinerary)) {
                continue;
            }
            $row = $this->diagnoseBrandedFareMappingForItinerary($itinerary);
            $piCount = (int) ($row['pi_count'] ?? 0);
            $piRowCount += $piCount;
            if ($piCount === 0) {
                $itinerariesPiCount0++;
            } elseif ($piCount === 1) {
                $itinerariesPiCount1++;
            } elseif ($piCount === 2) {
                $itinerariesPiCount2++;
            } else {
                $itinerariesPiCount3Plus++;
            }

            $piRowsWithPositiveTotal += (int) ($row['pi_rows_with_positive_total'] ?? 0);
            $piRowsWithBrandName += (int) ($row['pi_rows_with_brand_name'] ?? 0);
            $piRowsWithBrandCode += (int) ($row['pi_rows_with_brand_code'] ?? 0);
            $piRowsWithBrandNameOrCode += (int) ($row['pi_rows_with_brand_name_or_code'] ?? 0);
            $piRowsWithInlineBrandName += (int) ($row['pi_rows_with_inline_brand_name'] ?? 0);
            $piRowsWithInlineBrandCode += (int) ($row['pi_rows_with_inline_brand_code'] ?? 0);
            $piRowsWithDescriptorBrandName += (int) ($row['pi_rows_with_descriptor_brand_name'] ?? 0);
            $piRowsWithDescriptorBrandCode += (int) ($row['pi_rows_with_descriptor_brand_code'] ?? 0);

            if ((int) ($row['distinct_brand_names_count'] ?? 0) >= 2) {
                $itinerariesDistinctBrandNames2Plus++;
            }

            if (($row['would_map_branded_fares'] ?? false) !== true) {
                $reason = (string) ($row['skip_reason'] ?? 'options_lt_2');
                if (! array_key_exists($reason, $histogram)) {
                    $reason = 'options_lt_2';
                }
                $histogram[$reason]++;
            }

            if (count($samples) < 5) {
                $samples[] = [
                    'itinerary_index' => $idx,
                    'pi_count' => $piCount,
                    'brand_name_count' => (int) ($row['brand_name_count'] ?? 0),
                    'brand_code_count' => (int) ($row['brand_code_count'] ?? 0),
                    'distinct_brand_names_count' => (int) ($row['distinct_brand_names_count'] ?? 0),
                    'would_map_branded_fares' => (bool) ($row['would_map_branded_fares'] ?? false),
                    'skip_reason' => $row['skip_reason'] ?? null,
                ];
            }
        }

        return [
            'itinerary_count' => count($itineraries),
            'normalized_offer_count' => count($offers),
            'pi_row_count' => $piRowCount,
            'itineraries_pi_count_0' => $itinerariesPiCount0,
            'itineraries_pi_count_1' => $itinerariesPiCount1,
            'itineraries_pi_count_2' => $itinerariesPiCount2,
            'itineraries_pi_count_3_plus' => $itinerariesPiCount3Plus,
            'pi_rows_with_positive_total' => $piRowsWithPositiveTotal,
            'pi_rows_with_brand_name' => $piRowsWithBrandName,
            'pi_rows_with_brand_code' => $piRowsWithBrandCode,
            'pi_rows_with_brand_name_or_code' => $piRowsWithBrandNameOrCode,
            'pi_rows_with_inline_brand_name' => $piRowsWithInlineBrandName,
            'pi_rows_with_inline_brand_code' => $piRowsWithInlineBrandCode,
            'pi_rows_with_descriptor_brand_name' => $piRowsWithDescriptorBrandName,
            'pi_rows_with_descriptor_brand_code' => $piRowsWithDescriptorBrandCode,
            'fare_component_desc_count' => count($fareComponentDescs),
            'fare_component_descs_with_brand_count' => $this->countFareComponentDescsWithBrand($fareComponentDescs),
            'brand_feature_desc_count' => count($brandFeatureDescs),
            'fare_brand_desc_count' => count($fareBrandDescs),
            'descriptor_brand_sample_keys' => $this->collectDescriptorBrandSampleKeys($fareComponentDescs),
            'itineraries_distinct_brand_names_2_plus' => $itinerariesDistinctBrandNames2Plus,
            'offers_with_branded_fares' => $this->countOffersWithBrandedFares($offers),
            'skip_reason_histogram' => $histogram,
            'itinerary_samples' => $samples,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyBrandedFaresProbeDiagnosticsShell(int $normalizedOfferCount = 0): array
    {
        return [
            'itinerary_count' => 0,
            'normalized_offer_count' => $normalizedOfferCount,
            'pi_row_count' => 0,
            'itineraries_pi_count_0' => 0,
            'itineraries_pi_count_1' => 0,
            'itineraries_pi_count_2' => 0,
            'itineraries_pi_count_3_plus' => 0,
            'pi_rows_with_positive_total' => 0,
            'pi_rows_with_brand_name' => 0,
            'pi_rows_with_brand_code' => 0,
            'pi_rows_with_brand_name_or_code' => 0,
            'pi_rows_with_inline_brand_name' => 0,
            'pi_rows_with_inline_brand_code' => 0,
            'pi_rows_with_descriptor_brand_name' => 0,
            'pi_rows_with_descriptor_brand_code' => 0,
            'fare_component_desc_count' => 0,
            'fare_component_descs_with_brand_count' => 0,
            'brand_feature_desc_count' => 0,
            'fare_brand_desc_count' => 0,
            'descriptor_brand_sample_keys' => [],
            'itineraries_distinct_brand_names_2_plus' => 0,
            'offers_with_branded_fares' => 0,
            'skip_reason_histogram' => [
                'too_few_pi_rows' => 0,
                'no_brand_fields' => 0,
                'zero_price' => 0,
                'dedupe_collapsed' => 0,
                'options_lt_2' => 0,
            ],
            'itinerary_samples' => [],
        ];
    }

    /**
     * @param  list<NormalizedFlightOfferData>  $offers
     */
    protected function countOffersWithBrandedFares(array $offers): int
    {
        $count = 0;
        foreach ($offers as $offer) {
            if ($offer->branded_fares !== []) {
                $count++;
            }
        }

        return $count;
    }

    /** @var list<string> Parser paths for ATPCO/Sabre brand fields (metadata only; no response scrape). */
    public const BRAND_FIELD_PATHS_OBSERVED = [
        'pricingInformation[].fare.passengerInfoList[].passengerInfo.fareComponents[].brandCode',
        'pricingInformation[].fare.passengerInfoList[].passengerInfo.fareComponents[].fareFamilyCode',
        'pricingInformation[].fare.passengerInfoList[].passengerInfo.fareComponents[].fareFamily',
        'pricingInformation[].fare.passengerInfoList[].passengerInfo.fareComponents[].fareFamilyName',
        'pricingInformation[].fare.passengerInfoList[].passengerInfo.fareComponents[].ref',
        'groupedItineraryResponse.fareComponentDescs[].brand.brandName',
        'groupedItineraryResponse.fareComponentDescs[].brand.code',
        'groupedItineraryResponse.fareComponentDescs[].brand.id',
        'groupedItineraryResponse.fareComponentDescs[].brand.priceClassDescriptionRef',
        'groupedItineraryResponse.fareBrandDescs[].brandName',
        'groupedItineraryResponse.fareBrandDescs[].code',
    ];

    /**
     * Post-normalization branded-fare outcome counts for inspect/diagnostics (safe aggregates only).
     *
     * @param  list<NormalizedFlightOfferData>  $offers
     * @return array{
     *   offers_with_fare_family: int,
     *   normalized_offers_with_fare_family_count: int,
     *   branded_fares_option_count: int,
     *   branded_fares_options_count: int,
     *   offers_with_branded_fares: int,
     *   offers_with_brand_code: int,
     *   distinct_brand_codes_count: int
     * }
     */
    public function brandedFaresOutcomeCounts(array $offers): array
    {
        $offersWithFareFamily = 0;
        $brandedFaresOptionCount = 0;
        $offersWithBrandCode = 0;
        $distinctBrandCodes = [];

        foreach ($offers as $offer) {
            $fareFamily = trim((string) ($offer->fare_family ?? ''));
            if ($fareFamily !== '') {
                $offersWithFareFamily++;
            }
            $brandedFaresOptionCount += count($offer->branded_fares);
            $offerHasBrandCode = false;
            foreach ($offer->branded_fares as $brandedFare) {
                if (! is_array($brandedFare)) {
                    continue;
                }
                $code = trim((string) ($brandedFare['brand_code'] ?? $brandedFare['supplier_brand_code'] ?? ''));
                if ($code !== '') {
                    $offerHasBrandCode = true;
                    $distinctBrandCodes[strtolower($code)] = true;
                }
            }
            if ($offerHasBrandCode) {
                $offersWithBrandCode++;
            }
        }

        return [
            'offers_with_fare_family' => $offersWithFareFamily,
            'normalized_offers_with_fare_family_count' => $offersWithFareFamily,
            'branded_fares_option_count' => $brandedFaresOptionCount,
            'branded_fares_options_count' => $brandedFaresOptionCount,
            'offers_with_branded_fares' => $this->countOffersWithBrandedFares($offers),
            'offers_with_brand_code' => $offersWithBrandCode,
            'distinct_brand_codes_count' => count($distinctBrandCodes),
        ];
    }

    /**
     * Simulate branded-fare mapping for one raw itinerary (metadata only; mirrors {@see buildBrandedFaresFromItinerary}).
     *
     * @param  array<string, mixed>  $itinerary
     * @return array{
     *   pi_count: int,
     *   brand_name_count: int,
     *   brand_code_count: int,
     *   distinct_brand_names_count: int,
     *   pi_rows_with_positive_total: int,
     *   pi_rows_with_brand_name: int,
     *   pi_rows_with_brand_code: int,
     *   pi_rows_with_brand_name_or_code: int,
     *   would_map_branded_fares: bool,
     *   skip_reason: ?string
     * }
     */
    protected function diagnoseBrandedFareMappingForItinerary(array $itinerary): array
    {
        $pricingInfo = is_array($itinerary['pricingInformation'] ?? null)
            ? array_values($itinerary['pricingInformation'])
            : [];
        $piCount = count($pricingInfo);

        $brandNameCount = 0;
        $brandCodeCount = 0;
        $distinctBrandNames = [];
        $piRowsWithPositiveTotal = 0;
        $piRowsWithBrandName = 0;
        $piRowsWithBrandCode = 0;
        $piRowsWithBrandNameOrCode = 0;
        $piRowsWithInlineBrandName = 0;
        $piRowsWithInlineBrandCode = 0;
        $piRowsWithDescriptorBrandName = 0;
        $piRowsWithDescriptorBrandCode = 0;

        foreach ($pricingInfo as $pricingRow) {
            if (! is_array($pricingRow)) {
                continue;
            }
            $fareNode = is_array($pricingRow['fare'] ?? null) ? $pricingRow['fare'] : [];
            if ($fareNode === []) {
                continue;
            }

            $fareBreak = $this->extractFareBreakdownFromFare($fareNode);
            $total = (float) ($fareBreak['supplier_total'] ?? 0);
            if ($total > 0) {
                $piRowsWithPositiveTotal++;
            }

            $brandFields = $this->resolveBrandFieldsFromFareNode($fareNode);
            $name = $brandFields['name'] !== '' ? $brandFields['name'] : null;
            $brandCode = $brandFields['code'] !== '' ? $brandFields['code'] : null;
            $hasName = $name !== null && $name !== '';
            $hasCode = $brandCode !== null && $brandCode !== '';
            $hasInlineName = ($brandFields['inline_name'] ?? '') !== '';
            $hasInlineCode = ($brandFields['inline_code'] ?? '') !== '';
            $hasDescriptorName = ($brandFields['descriptor_name'] ?? '') !== '';
            $hasDescriptorCode = ($brandFields['descriptor_code'] ?? '') !== '';
            if ($hasName) {
                $piRowsWithBrandName++;
                $brandNameCount++;
                $distinctBrandNames[strtolower((string) $name)] = true;
            }
            if ($hasCode) {
                $piRowsWithBrandCode++;
                $brandCodeCount++;
            }
            if ($hasName || $hasCode) {
                $piRowsWithBrandNameOrCode++;
            }
            if ($hasInlineName) {
                $piRowsWithInlineBrandName++;
            }
            if ($hasInlineCode) {
                $piRowsWithInlineBrandCode++;
            }
            if ($hasDescriptorName) {
                $piRowsWithDescriptorBrandName++;
            }
            if ($hasDescriptorCode) {
                $piRowsWithDescriptorBrandCode++;
            }
        }

        $base = [
            'pi_count' => $piCount,
            'brand_name_count' => $brandNameCount,
            'brand_code_count' => $brandCodeCount,
            'distinct_brand_names_count' => count($distinctBrandNames),
            'pi_rows_with_positive_total' => $piRowsWithPositiveTotal,
            'pi_rows_with_brand_name' => $piRowsWithBrandName,
            'pi_rows_with_brand_code' => $piRowsWithBrandCode,
            'pi_rows_with_brand_name_or_code' => $piRowsWithBrandNameOrCode,
            'pi_rows_with_inline_brand_name' => $piRowsWithInlineBrandName,
            'pi_rows_with_inline_brand_code' => $piRowsWithInlineBrandCode,
            'pi_rows_with_descriptor_brand_name' => $piRowsWithDescriptorBrandName,
            'pi_rows_with_descriptor_brand_code' => $piRowsWithDescriptorBrandCode,
        ];

        if ($piCount < 2) {
            return array_merge($base, [
                'would_map_branded_fares' => false,
                'skip_reason' => 'too_few_pi_rows',
            ]);
        }

        $options = [];
        $seen = [];
        $skippedZeroPrice = 0;
        $skippedNoBrand = 0;
        $skippedDedupe = 0;
        $viableBeforeDedupe = 0;

        foreach ($pricingInfo as $piIndex => $pricingRow) {
            if (! is_array($pricingRow)) {
                continue;
            }
            $fareNode = is_array($pricingRow['fare'] ?? null) ? $pricingRow['fare'] : [];
            if ($fareNode === []) {
                continue;
            }

            $fareBreak = $this->extractFareBreakdownFromFare($fareNode);
            $total = (float) ($fareBreak['supplier_total'] ?? 0);
            if ($total <= 0) {
                $skippedZeroPrice++;

                continue;
            }

            $name = $this->extractFareFamily($fareNode);
            $brandCode = $this->extractPrimaryBrandCode($fareNode);
            if (($name === null || $name === '') && ($brandCode === null || $brandCode === '')) {
                $skippedNoBrand++;

                continue;
            }

            $viableBeforeDedupe++;
            $piScalars = $this->extractPricingInformationLinkageScalars($pricingRow, $fareNode);
            $dedupeKey = strtolower($brandCode ?? '').'|'.(int) round($total).'|'.$piScalars['pricing_information_ref'].'|'.$piIndex;
            if (isset($seen[$dedupeKey])) {
                $skippedDedupe++;

                continue;
            }
            $seen[$dedupeKey] = true;
            $options[] = true;
        }

        if (count($options) >= 2) {
            return array_merge($base, [
                'would_map_branded_fares' => true,
                'skip_reason' => null,
            ]);
        }

        $skipReason = 'options_lt_2';
        if ($skippedDedupe > 0 && $viableBeforeDedupe >= 2) {
            $skipReason = 'dedupe_collapsed';
        } elseif ($skippedNoBrand > 0 && $skippedZeroPrice === 0 && $skippedDedupe === 0 && $viableBeforeDedupe === 0) {
            $skipReason = 'no_brand_fields';
        } elseif ($skippedZeroPrice > 0 && $skippedNoBrand === 0 && $skippedDedupe === 0 && $viableBeforeDedupe === 0) {
            $skipReason = 'zero_price';
        } elseif ($skippedNoBrand > 0 && $skippedZeroPrice === 0 && $skippedDedupe === 0) {
            $skipReason = 'no_brand_fields';
        } elseif ($skippedZeroPrice > 0 && $skippedNoBrand === 0 && $skippedDedupe === 0) {
            $skipReason = 'zero_price';
        }

        return array_merge($base, [
            'would_map_branded_fares' => false,
            'skip_reason' => $skipReason,
        ]);
    }

    /**
     * Post-normalization quality metrics (safe counts only).
     *
     * @param  list<NormalizedFlightOfferData>  $offers
     * @return array<string, int>
     */
    public function normalizationOutcomeDiagnostics(array $offers): array
    {
        $zeroPrice = 0;
        $missingSegment = 0;
        $missingCarrier = 0;

        foreach ($offers as $o) {
            $total = $o->fare_breakdown->supplier_total;
            if ($total <= 0) {
                $zeroPrice++;
            }
            if ($o->segments === []) {
                $missingSegment++;
            }
            $code = strtoupper(trim($o->airline_code));
            if ($code === '' || $code === 'XX') {
                $missingCarrier++;
            }
        }

        return [
            'normalized_offer_count' => count($offers),
            'zero_price_offer_count' => $zeroPrice,
            'missing_segment_offer_count' => $missingSegment,
            'missing_carrier_offer_count' => $missingCarrier,
        ];
    }

    /**
     * Safe route/segment metrics after normalization (no raw payload).
     *
     * @param  list<NormalizedFlightOfferData>  $offers
     * @return array<string, int|string>
     */
    public function batchRouteDiagnostics(?FlightSearchRequestData $searchRequest, array $offers): array
    {
        if ($offers === []) {
            return [
                'itinerary_segment_count' => 0,
                'offer_origin' => '',
                'offer_destination' => '',
                'first_segment_origin' => '',
                'last_segment_destination' => '',
                'route_mismatch_count' => 0,
            ];
        }

        $firstOffer = $offers[0];
        $n = count($firstOffer->segments);
        $firstSeg = $firstOffer->segments[0] ?? [];
        $lastSeg = $n > 0 ? $firstOffer->segments[$n - 1] : [];

        $reqOrigin = $searchRequest !== null ? strtoupper(trim($searchRequest->origin)) : '';
        $reqDest = $searchRequest !== null ? strtoupper(trim($searchRequest->destination)) : '';
        $isRoundTrip = $searchRequest !== null && $searchRequest->trip_type === 'round_trip';
        $mismatch = 0;
        foreach ($offers as $o) {
            $c = count($o->segments);
            if ($c === 0) {
                continue;
            }
            $lastD = strtoupper(trim((string) ($o->segments[$c - 1]['destination'] ?? '')));
            if ($lastD === '') {
                continue;
            }
            if ($isRoundTrip) {
                if ($reqOrigin !== '' && $lastD !== $reqOrigin) {
                    $mismatch++;
                }
            } elseif ($reqDest !== '' && $lastD !== $reqDest) {
                $mismatch++;
            }
        }

        $maxSeg = 0;
        foreach ($offers as $o) {
            $maxSeg = max($maxSeg, count($o->segments));
        }

        return [
            'itinerary_segment_count' => $maxSeg,
            'offer_origin' => strtoupper(trim($firstOffer->origin)),
            'offer_destination' => strtoupper(trim($firstOffer->destination)),
            'first_segment_origin' => strtoupper(trim((string) ($firstSeg['origin'] ?? ''))),
            'last_segment_destination' => strtoupper(trim((string) ($lastSeg['destination'] ?? ''))),
            'route_mismatch_count' => $mismatch,
        ];
    }

    /**
     * Key-only digest of top-level grouped response (max depth), for local inspect tooling.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function groupedResponseKeyDigest(array $response, int $maxDepth = 3): array
    {
        $root = $response['groupedItineraryResponse'] ?? null;

        return [
            'top_level_keys' => array_keys($response),
            'grouped_itinerary_response_keys' => is_array($root) ? array_keys($root) : [],
            'grouped_itinerary_nested' => is_array($root) ? $this->keyTree($root, $maxDepth) : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<NormalizedFlightOfferData>
     */
    public function normalize(array $response, SupplierConnection $connection, ?FlightSearchRequestData $searchRequest = null): array
    {
        $this->resetDisplayDiagnostics();

        $gir = data_get($response, 'groupedItineraryResponse');
        if (! is_array($gir)) {
            return [];
        }

        $scheduleDescs = $this->listDescs($response, 'scheduleDescs');
        $legDescs = $this->listDescs($response, 'legDescs');
        $baggageAllowanceDescs = $this->listDescs($response, 'baggageAllowanceDescs');
        $fareComponentDescs = $this->listDescs($response, 'fareComponentDescs');
        $this->primeGirDescriptorContextFromResponse($response);
        $this->currentGirDescriptorLists = [
            'scheduleDescs' => $scheduleDescs,
            'legDescs' => $legDescs,
            'fareComponentDescs' => $fareComponentDescs,
        ];

        $itineraries = $this->collectItineraries($gir);
        $normalized = [];

        foreach ($itineraries as $itinerary) {
            if (! is_array($itinerary)) {
                continue;
            }
            $one = $this->normalizeOneItineraryWithDiagnostics($itinerary, $connection, $searchRequest);
            if ($one['offer'] !== null) {
                $normalized[] = $one['offer'];
            }
        }

        $this->finalizeDisplayDiagnosticsSnapshot();

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function snapshotPresabreSegmentRows(array $segmentModels): array
    {
        $out = [];
        foreach ($segmentModels as $m) {
            if (! $m instanceof FlightSegmentData) {
                continue;
            }
            $out[] = [
                'origin' => strtoupper(trim($m->origin)),
                'destination' => strtoupper(trim($m->destination)),
                'departure_at' => $m->departure_at,
                'arrival_at' => $m->arrival_at,
                'elapsed_minutes' => max(0, $m->duration_minutes),
                'marketing' => strtoupper(trim((string) ($m->airline_code ?? ''))),
                'operating' => strtoupper(trim((string) ($m->operating_airline_code ?? ''))),
                'flight_number' => trim((string) ($m->flight_number ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   offer: ?NormalizedFlightOfferData,
     *   reject_code: ?string,
     *   reject_meta: array<string, mixed>,
     *   presabre_segments: list<array<string, mixed>>
     * }
     */
    protected function normalizeOneItineraryWithDiagnostics(
        array $itinerary,
        SupplierConnection $connection,
        ?FlightSearchRequestData $searchRequest,
    ): array {
        $segmentModels = $this->buildSegmentsForItinerary($itinerary, $searchRequest);
        if ($segmentModels === []) {
            $segmentModels = $this->buildSegmentsLegacyInline($itinerary, $searchRequest);
        }
        $presabreSnapshot = $this->snapshotPresabreSegmentRows($segmentModels);

        $orderMeta = $this->resolveSegmentOrderWithOptionalReverse($segmentModels, $searchRequest);
        /** @var list<FlightSegmentData> $workingSegments */
        $workingSegments = $orderMeta['working_segments'];
        $segmentOrderCorrected = $orderMeta['segment_order_corrected'];
        $routeInner = $this->segmentModelsRouteContinuity($workingSegments);

        $rawReference = (string) (data_get($itinerary, 'id') ?? data_get($itinerary, 'pricingInformation.0.offerItemId') ?? '');
        $offerDiagnosticId = hash('sha256', implode('|', [
            SupplierProvider::Sabre->value,
            $connection->id,
            $rawReference,
            'rt_fallback_diag',
        ]));

        $rtFallbackApplied = false;
        $fallbackPack = $this->maybeRebuildRoundTripSegmentsViaWholeItineraryRouteChains(
            $itinerary,
            $searchRequest,
            $workingSegments,
            $routeInner,
            $offerDiagnosticId,
        );
        if ($fallbackPack !== null) {
            $rtFallbackApplied = true;
            $segmentModels = $fallbackPack['segment_models'];
            $presabreSnapshot = $this->snapshotPresabreSegmentRows($segmentModels);
            $orderMeta = $this->resolveSegmentOrderWithOptionalReverse($segmentModels, $searchRequest);
            $workingSegments = $orderMeta['working_segments'];
            $segmentOrderCorrected = $orderMeta['segment_order_corrected'];
            $routeInner = $this->segmentModelsRouteContinuity($workingSegments);
        }
        $first = $workingSegments[0] ?? null;
        $last = $workingSegments === [] ? null : $workingSegments[array_key_last($workingSegments)];

        $origin = strtoupper(trim((string) ($first?->origin ?? '')));
        if ($origin === '') {
            $origin = strtoupper(trim((string) data_get($itinerary, 'legs.0.origin', '')));
        }
        $destination = strtoupper(trim((string) ($last?->destination ?? '')));
        if ($destination === '') {
            $destination = strtoupper(trim((string) data_get($itinerary, 'legs.0.destination', '')));
        }

        $rejectOfferId = hash('sha256', implode('|', [
            SupplierProvider::Sabre->value,
            $connection->id,
            $rawReference,
            'reject_candidate',
        ]));

        $searchEndpointsOk = $this->matchesSearchEndpoints($searchRequest, $origin, $destination, $workingSegments);
        $multiCitySliceEndpointsOk = $searchRequest !== null
            && trim($searchRequest->trip_type) === 'multi_city'
            && $searchEndpointsOk;

        $orderDiag = [
            'original_route_continuity_ok' => $orderMeta['original_route_continuity_ok'],
            'reversed_route_continuity_ok' => $orderMeta['reversed_route_continuity_ok'],
            'segment_order_corrected' => $segmentOrderCorrected,
            'original_segment_routes_sample' => $orderMeta['original_segment_routes_sample'],
            'corrected_segment_routes_sample' => $orderMeta['corrected_segment_routes_sample'],
        ];

        if ($workingSegments === [] || $first === null || $last === null) {
            $ctx = array_merge([
                'segment_count' => 0,
                'offer_origin' => $origin,
                'offer_destination' => $destination,
                'first_segment_origin' => '',
                'last_segment_destination' => '',
                'failed_link_count' => 0,
                'route_continuity_ok' => false,
            ], $orderDiag);
            $this->maybeMergeDescriptorRefRejectProbe($ctx, $itinerary, $segmentModels, $routeInner);
            $this->logSabreOfferRejected($rejectOfferId, 'route_continuity_failed', $ctx);

            return ['offer' => null, 'reject_code' => 'route_continuity_failed', 'reject_meta' => $ctx, 'presabre_segments' => $presabreSnapshot];
        }

        if (! $routeInner['route_continuity_ok'] && ! $multiCitySliceEndpointsOk) {
            $ctx = array_merge($routeInner, [
                'offer_origin' => $origin,
                'offer_destination' => $destination,
                'failed_link_count' => $routeInner['out_of_order_segment_count'],
                'route_continuity_ok' => false,
            ], $orderDiag);
            $this->maybeMergeDescriptorRefRejectProbe($ctx, $itinerary, $segmentModels, $routeInner);
            $this->logSabreOfferRejected($rejectOfferId, 'route_continuity_failed', $ctx);

            return ['offer' => null, 'reject_code' => 'route_continuity_failed', 'reject_meta' => $ctx, 'presabre_segments' => $presabreSnapshot];
        }

        $requestDepYmd = $searchRequest !== null ? trim((string) $searchRequest->departure_date) : null;
        $requestReturnYmd = $searchRequest !== null && $searchRequest->return_date !== null
            ? trim((string) $searchRequest->return_date)
            : null;
        $repairPack = SabreSegmentChronologyRepair::repair(
            $workingSegments,
            $requestDepYmd,
            $segmentOrderCorrected,
            $requestReturnYmd !== '' ? $requestReturnYmd : null,
            $searchRequest?->origin,
            $searchRequest?->destination,
        );
        /** @var list<FlightSegmentData> $workingSegments */
        $workingSegments = $repairPack['segments'];
        $repairDiag = $repairPack['diagnostics'];
        $first = $workingSegments[0] ?? null;
        $last = $workingSegments === [] ? null : $workingSegments[array_key_last($workingSegments)];

        $timeInner = SabreItineraryTimingValidator::analyzeFlightSegmentModels($workingSegments);
        if (! $timeInner['ok'] && ! $multiCitySliceEndpointsOk) {
            if ($rtFallbackApplied) {
                $this->logRtRouteChainFallbackRejected(
                    $offerDiagnosticId,
                    'datetime_failed_after_fallback',
                    $searchRequest,
                    count($workingSegments),
                    null,
                    $this->sampleSegmentRoutes($workingSegments, 12),
                );
            }
            $ctx = array_merge($routeInner, $timeInner, $repairDiag, [
                'offer_origin' => $origin,
                'offer_destination' => $destination,
                'failed_link_count' => $routeInner['out_of_order_segment_count'],
                'route_continuity_ok' => true,
                'segment_datetime_continuity_ok' => false,
                'failed_time_link_count' => $timeInner['failed_time_link_count'],
                'invalid_segment_duration_count' => $timeInner['invalid_segment_duration_count'],
            ], $orderDiag);
            $this->maybeMergeDescriptorRefRejectProbe($ctx, $itinerary, $segmentModels, $routeInner);
            $this->logSabreOfferRejected($rejectOfferId, 'segment_datetime_continuity_failed', $ctx);

            return ['offer' => null, 'reject_code' => 'segment_datetime_continuity_failed', 'reject_meta' => $ctx, 'presabre_segments' => $presabreSnapshot];
        }

        if (! $searchEndpointsOk) {
            $ctx = [
                'segment_count' => $routeInner['segment_count'],
                'offer_origin' => $origin,
                'offer_destination' => $destination,
                'first_segment_origin' => $routeInner['first_segment_origin'],
                'last_segment_destination' => $routeInner['last_segment_destination'],
                'requested_origin' => $searchRequest !== null ? strtoupper(trim($searchRequest->origin)) : '',
                'requested_destination' => $searchRequest !== null ? strtoupper(trim($searchRequest->destination)) : '',
                'trip_type' => $searchRequest !== null ? trim($searchRequest->trip_type) : '',
                'failed_link_count' => 0,
                'route_continuity_ok' => true,
                'xnb_dxb_equivalent_considered' => SabreMarketEndpointEquivalence::areEquivalent($origin, (string) ($searchRequest?->origin ?? ''))
                    || SabreMarketEndpointEquivalence::areEquivalent($destination, (string) ($searchRequest?->destination ?? '')),
                'reversed_route_detected' => ($orderMeta['segment_order_corrected'] ?? false) === true,
                'selected_offer_context_preserved' => false,
            ];
            $this->logSabreOfferRejected($rejectOfferId, 'search_endpoint_mismatch', $ctx);

            return ['offer' => null, 'reject_code' => 'search_endpoint_mismatch', 'reject_meta' => $ctx, 'presabre_segments' => $presabreSnapshot];
        }

        $this->diagUnreliableLayoverConnectionCount += $this->countUnreliableLayovers($workingSegments);

        $fareNode = $this->firstFareNode($itinerary);

        $segments = [];
        foreach ($workingSegments as $sm) {
            $row = $sm->toArray();
            $isoSegDur = $this->durationMinutesFromIso($sm->departure_at, $sm->arrival_at);
            if ($isoSegDur > 0) {
                $row['duration_minutes'] = $isoSegDur;
            }
            $segments[] = $row;
        }
        $segments = $this->mergeFareBookingMetadataIntoSegmentRows($segments, $fareNode);

        $departureAt = $first?->departure_at ?? '';
        $arrivalAt = $last?->arrival_at ?? '';

        $legElapsed = $this->itineraryLegsElapsedMinutesSum($itinerary);

        $sumSegmentMinutes = 0;
        foreach ($workingSegments as $sm) {
            $isoLeg = $this->durationMinutesFromIso($sm->departure_at, $sm->arrival_at);
            $sumSegmentMinutes += $isoLeg > 0 ? $isoLeg : max(0, $sm->duration_minutes);
        }

        $timelineMinutes = ($departureAt !== '' && $arrivalAt !== '')
            ? $this->durationMinutesFromIso($departureAt, $arrivalAt)
            : 0;

        $aggregateMinutes = 0;
        $aggregateSource = 'unavailable';
        if ($legElapsed > 0) {
            $aggregateMinutes = $legElapsed;
            $aggregateSource = 'leg_elapsed_time';
        } elseif ($sumSegmentMinutes > 0) {
            $aggregateMinutes = $sumSegmentMinutes;
            $aggregateSource = 'calculated';
        }

        $durationMismatch = false;
        $durationMismatchMinutes = 0;
        if ($timelineMinutes > 0) {
            $durationMinutes = $timelineMinutes;
            $durationSource = 'segment_timeline';
            if ($aggregateMinutes > 0 && abs($aggregateMinutes - $timelineMinutes) > 15) {
                $durationMismatch = true;
                $durationMismatchMinutes = abs($aggregateMinutes - $timelineMinutes);
            }
        } elseif ($aggregateMinutes > 0) {
            $durationMinutes = $aggregateMinutes;
            $durationSource = $aggregateSource;
        } else {
            $durationMinutes = 0;
            $durationSource = 'unavailable';
        }
        if ($durationMinutes <= 0) {
            $durationSource = 'unavailable';
        }
        $this->registerOfferDurationSource($durationSource);

        $stops = max(0, count($workingSegments) - 1);

        $fareBreak = $this->extractFareBreakdownFromFare($fareNode);
        $validatingCarrier = strtoupper(trim((string) ($fareNode['validatingCarrierCode'] ?? '')));

        $carrierDisplay = NormalizedFlightOfferData::deriveMultiSegmentCarrierDisplay(
            $segments,
            $validatingCarrier !== '' ? $validatingCarrier : null,
            $validatingCarrier !== '' ? $validatingCarrier : strtoupper(trim((string) ($first?->airline_code ?? ''))),
        );
        $airlineCode = $carrierDisplay['primary_display_carrier'];
        $flightNumber = $carrierDisplay['headline_flight_number'];
        $airlineName = $carrierDisplay['headline_airline_name'];
        if ($airlineName === '' || $airlineName === 'XX') {
            $airlineName = $airlineCode !== '' && $airlineCode !== 'XX' ? $airlineCode : ($validatingCarrier !== '' ? $validatingCarrier : 'XX');
        }

        $sabreCabin = $this->extractCabinCodeFromFare($fareNode);
        $cabin = $sabreCabin !== ''
            ? $sabreCabin
            : strtolower(trim($searchRequest?->cabin ?? 'economy'));
        $refundable = $this->extractRefundable($fareNode);
        $baggage = $this->extractBaggage($fareNode);
        $fareFamily = $this->extractFareFamily($fareNode);

        $total = $fareBreak['supplier_total'];
        $currency = $fareBreak['currency'];
        $baseFare = $fareBreak['base_fare'];
        $taxes = $fareBreak['taxes'];
        $fareExcerpt = $this->buildSabreFareExcerptSnapshot($fareNode);

        $offerId = hash('sha256', implode('|', [
            SupplierProvider::Sabre->value,
            $connection->id,
            $rawReference,
            $airlineCode.($flightNumber ?? ''),
            $departureAt,
            (string) $total,
            $currency,
        ]));

        $fareBasisCodes = $this->collectFareBasisCodesFromFareNode($fareNode);
        $shopIdentifiers = $this->extractSabreShopIdentifiersSnapshot($itinerary, $fareNode, $fareBasisCodes);
        $shopContext = $this->syncShopContextLinkageFromIdentifiers(
            $this->buildSabreShopContextSnapshot(
                $itinerary,
                $fareNode,
                $searchRequest,
                $segments,
                $carrierDisplay,
                $validatingCarrier,
                $fareBasisCodes,
            ),
            $shopIdentifiers,
        );

        $girArchive = $this->buildSabreBfmGirArchiveSlice($itinerary, $fareNode, $segments);

        $rawPayload = [
            'itinerary_id' => $rawReference,
            'airline_code' => $airlineCode,
            'flight_number' => $flightNumber,
            'departure_at' => $departureAt,
            'arrival_at' => $arrivalAt,
            'sabre_shop_identifiers' => $shopIdentifiers,
            'sabre_shop_context' => $shopContext,
            'sabre_bfm_gir_archive' => $girArchive !== [] ? $girArchive : null,
            'sabre_fare_excerpt' => $fareExcerpt,
            'fare' => [
                'base' => $baseFare,
                'tax' => $taxes,
                'total' => $total,
                'currency' => $currency,
            ],
            'duration_consistency' => [
                'duration_mismatch_detected' => $durationMismatch,
                'aggregate_duration_minutes' => $aggregateMinutes,
                'calculated_timeline_duration_minutes' => $timelineMinutes,
                'duration_mismatch_minutes' => $durationMismatchMinutes,
                'selected_duration_source' => $durationSource,
            ],
        ];
        if ($segmentOrderCorrected) {
            $rawPayload['sabre_segment_order'] = $orderDiag;
        }

        $brandCode = $this->extractPrimaryBrandCode($fareNode);
        $readinessProbe = [
            'offer_id' => $offerId,
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $connection->id,
            'validating_carrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
            'segments' => $segments,
            'fare_breakdown' => $fareBreak,
            'raw_payload' => [
                'sabre_shop_context' => $shopContext,
                'sabre_shop_identifiers' => $shopIdentifiers,
            ],
        ];
        $readiness = (new SabreStoredPricingContextDigest)->assessReadiness($readinessProbe);
        $rawPayload['sabre_booking_context'] = $this->buildSabreBookingContextHandoff(
            $connection->id,
            $segments,
            $shopContext,
            $validatingCarrier,
            $fareBasisCodes,
            is_string($brandCode) && trim($brandCode) !== '' ? trim($brandCode) : null,
            $readiness,
        );
        $rawPayload = $this->mergeSabrePricingLinkageScalarsIntoRawPayload($rawPayload, $shopContext, $shopIdentifiers, $segments, $carrierDisplay);

        $brandedFares = $this->buildBrandedFaresFromItinerary($itinerary, $validatingCarrier, $offerId);
        if ($brandedFares !== []) {
            $rawPayload['branded_fare_option_count'] = count($brandedFares);
            $this->logSabreBrandedFaresMapped($offerId, $brandedFares);
        }

        $offer = new NormalizedFlightOfferData(
            offer_id: $offerId,
            supplier_provider: SupplierProvider::Sabre->value,
            supplier_connection_id: $connection->id,
            airline_code: $airlineCode !== '' ? $airlineCode : 'XX',
            airline_name: $airlineName,
            flight_number: $flightNumber,
            origin: $origin,
            destination: $destination,
            departure_at: $departureAt,
            arrival_at: $arrivalAt,
            duration_minutes: $durationMinutes,
            stops: $stops,
            cabin: $cabin,
            fare_family: $fareFamily,
            refundable: $refundable,
            seats_left: null,
            segments: $segments,
            baggage: $baggage,
            fare_breakdown: new FareBreakdownData(
                base_fare: $baseFare,
                taxes: $taxes,
                supplier_fees: max(0, $total - ($baseFare + $taxes)),
                supplier_total: $total,
                currency: $currency,
                passenger_pricing: is_array($fareBreak['passenger_pricing'] ?? null) ? $fareBreak['passenger_pricing'] : null,
                passenger_pricing_available: (bool) ($fareBreak['passenger_pricing_available'] ?? false),
                passenger_counts: $this->buildPassengerCountsFromSearchRequest($searchRequest),
                fare_basis_codes: $fareBasisCodes,
                display_base_fare: isset($fareBreak['display_base_fare']) ? (float) $fareBreak['display_base_fare'] : null,
                display_taxes: isset($fareBreak['display_taxes']) ? (float) $fareBreak['display_taxes'] : null,
                raw_base_fare: isset($fareBreak['raw_base_fare']) ? (float) $fareBreak['raw_base_fare'] : null,
                base_fare_display_source: isset($fareBreak['base_fare_display_source'])
                    ? (string) $fareBreak['base_fare_display_source']
                    : null,
                breakdown_reconciled: (bool) ($fareBreak['breakdown_reconciled'] ?? false),
            ),
            raw_reference: $rawReference !== '' ? $rawReference : null,
            raw_payload: $rawPayload,
            marketing_carrier_chain: $carrierDisplay['marketing_carrier_chain'],
            operating_carrier_chain: $carrierDisplay['operating_carrier_chain'],
            validating_carrier: $validatingCarrier !== '' ? $validatingCarrier : null,
            primary_display_carrier: $carrierDisplay['primary_display_carrier'],
            mixed_carrier: $carrierDisplay['mixed_carrier'],
            all_airline_codes: $carrierDisplay['all_airline_codes'],
            branded_fares: $brandedFares,
        );

        return ['offer' => $offer, 'reject_code' => null, 'reject_meta' => [], 'presabre_segments' => $presabreSnapshot];
    }

    /**
     * Map additional BFM pricingInformation rows into branded_fares for fare-family UI (B1 display; B2A readiness).
     * Parent offer pricing still uses {@see firstFareNode()} (index 0). Options are exposed only when
     * at least two distinct supplier pricing rows survive deduplication — a single row does not open the modal.
     * {@code selectable} stays false until B2B/C prove revalidation and booking for a selected row.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildBrandedFaresFromItinerary(array $itinerary, string $validatingCarrier, string $offerId = ''): array
    {
        $digest = new SabreStoredPricingContextDigest;
        $pricingInfo = is_array($itinerary['pricingInformation'] ?? null)
            ? array_values($itinerary['pricingInformation'])
            : [];
        if (count($pricingInfo) < 2) {
            return [];
        }

        $options = [];
        $seen = [];
        foreach ($pricingInfo as $piIndex => $pricingRow) {
            if (! is_array($pricingRow)) {
                continue;
            }
            $fareNode = is_array($pricingRow['fare'] ?? null) ? $pricingRow['fare'] : [];
            if ($fareNode === []) {
                continue;
            }

            $fareBreak = $this->extractFareBreakdownFromFare($fareNode);
            $total = (float) ($fareBreak['supplier_total'] ?? 0);
            if ($total <= 0) {
                continue;
            }

            $name = $this->extractFareFamily($fareNode);
            $brandCode = $this->extractPrimaryBrandCode($fareNode);
            if (($name === null || $name === '') && ($brandCode === null || $brandCode === '')) {
                continue;
            }
            if ($name === null || $name === '') {
                $name = $brandCode;
            }

            $piScalars = $this->extractPricingInformationLinkageScalars($pricingRow, $fareNode);
            $dedupeKey = strtolower($brandCode ?? '').'|'.(int) round($total).'|'.$piScalars['pricing_information_ref'].'|'.$piIndex;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $baggage = $this->extractBaggage($fareNode);
            $cabin = $this->extractCabinCodeFromFare($fareNode);
            $refundable = $this->extractRefundable($fareNode);
            $fareBasisCodes = $this->collectFareBasisCodesFromFareNode($fareNode);
            $segmentSlices = $this->collectBrandedFareSegmentBookingSlices($fareNode);
            $itinRef = $this->firstSafeScalar($itinerary, ['id', 'ref']);
            $fareComponentRefs = $this->collectFareComponentRefsFromFareNode($fareNode, false);

            $option = [
                'name' => $name,
                'supplier_brand_code' => $brandCode,
                'brand_code' => $brandCode,
                'price_total' => $total,
                'currency' => (string) ($fareBreak['currency'] ?? ''),
                'cabin' => $cabin !== '' ? $cabin : null,
                'baggage_summary' => $baggage->summary,
                'check_in_summary' => $baggage->checked,
                'carry_on_summary' => $baggage->cabin,
                'refundable' => $refundable,
                'refundable_display' => $refundable ? 'Refundable' : 'Non-refundable',
                'fare_basis_codes' => $fareBasisCodes,
                'pricing_information_index' => $piIndex,
                'pricing_information_ref' => $piScalars['pricing_information_ref'] !== '' ? $piScalars['pricing_information_ref'] : null,
                'pricing_information_id' => $piScalars['pricing_information_id'] !== '' ? $piScalars['pricing_information_id'] : null,
                'offer_ref' => $piScalars['offer_ref'] !== '' ? $piScalars['offer_ref'] : null,
                'supplier_offer_id' => $piScalars['offer_id'] !== '' ? $piScalars['offer_id'] : null,
                'validating_carrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
                'selectable' => false,
                'fare_basis_codes_by_segment' => $segmentSlices['fare_basis_codes_by_segment'],
                'booking_classes_by_segment' => $segmentSlices['booking_classes_by_segment'],
                'cabin_by_segment' => $segmentSlices['cabin_by_segment'],
                'segment_slice_count' => $segmentSlices['segment_slice_count'],
                'linkage_summary' => $this->buildBrandedFareLinkageSummary(
                    $piIndex,
                    $piScalars,
                    $itinRef,
                    count($fareComponentRefs),
                ),
            ];

            $options[] = array_merge($option, $digest->assessBrandedFareOptionReadiness($option));
        }

        if (count($options) < 2) {
            return [];
        }

        $cheapest = null;
        foreach ($options as $opt) {
            $p = (float) ($opt['price_total'] ?? 0);
            if ($p > 0 && ($cheapest === null || $p < $cheapest)) {
                $cheapest = $p;
            }
        }
        if ($cheapest !== null) {
            foreach ($options as $idx => $opt) {
                $p = (float) ($opt['price_total'] ?? 0);
                $options[$idx]['is_cheapest'] = $p > 0 && abs($p - $cheapest) < 0.01;
            }
        }

        return $options;
    }

    /**
     * Safe metadata-only log after branded fare rows are attached (B2A).
     *
     * @param  list<array<string, mixed>>  $options
     */
    protected function logSabreBrandedFaresMapped(string $offerId, array $options): void
    {
        $readyReval = 0;
        $readyBooking = 0;
        $reasonCounts = [];
        foreach ($options as $opt) {
            if (! is_array($opt)) {
                continue;
            }
            if (($opt['ready_for_revalidation'] ?? false) === true) {
                $readyReval++;
            }
            if (($opt['ready_for_booking_payload'] ?? false) === true) {
                $readyBooking++;
            }
            foreach (is_array($opt['readiness_reasons'] ?? null) ? $opt['readiness_reasons'] : [] as $reason) {
                $code = substr(trim((string) $reason), 0, 64);
                if ($code === '') {
                    continue;
                }
                $reasonCounts[$code] = ($reasonCounts[$code] ?? 0) + 1;
            }
        }

        Log::info('sabre.branded_fares_mapped', [
            'offer_id' => substr(trim($offerId), 0, 64),
            'option_count' => count($options),
            'ready_for_revalidation_count' => $readyReval,
            'ready_for_booking_payload_count' => $readyBooking,
            'readiness_reason_counts' => $reasonCounts,
        ]);
    }

    /**
     * Per-segment sell hints from a priced fare node (no raw BFM JSON).
     *
     * @param  array<string, mixed>  $fareNode
     * @return array{
     *   fare_basis_codes_by_segment: list<string>,
     *   booking_classes_by_segment: list<string>,
     *   cabin_by_segment: list<string>,
     *   segment_slice_count: int
     * }
     */
    protected function collectBrandedFareSegmentBookingSlices(array $fareNode): array
    {
        $metaRows = $this->flattenFareComponentBookingSegments($fareNode);
        $fareBasis = [];
        $bookingClasses = [];
        $cabins = [];
        foreach ($metaRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fb = strtoupper(trim((string) ($row['fare_basis_code'] ?? '')));
            if ($fb !== '') {
                $fareBasis[] = substr($fb, 0, 24);
            }
            $bc = strtoupper(trim((string) ($row['booking_class'] ?? '')));
            if ($bc !== '') {
                $bookingClasses[] = substr($bc, 0, 8);
            }
            $cab = strtolower(trim((string) ($row['segment_cabin_code'] ?? '')));
            if ($cab !== '') {
                $cabins[] = substr($cab, 0, 32);
            }
        }

        return [
            'fare_basis_codes_by_segment' => $fareBasis,
            'booking_classes_by_segment' => $bookingClasses,
            'cabin_by_segment' => $cabins,
            'segment_slice_count' => count($metaRows),
        ];
    }

    /**
     * Boolean/count linkage summary for a branded fare row (no ref token values).
     *
     * @param  array{
     *   pricing_information_ref: string,
     *   pricing_information_id: string,
     *   pricing_subsource: string,
     *   fare_source: string,
     *   offer_ref: string,
     *   offer_id: string,
     *   order_ref: string
     * }  $piScalars
     * @return array<string, mixed>
     */
    protected function buildBrandedFareLinkageSummary(
        int $piIndex,
        array $piScalars,
        string $itineraryRef,
        int $fareComponentRefCount,
    ): array {
        $hasPricingRef = trim($piScalars['pricing_information_ref']) !== '';
        $hasPricingId = trim($piScalars['pricing_information_id']) !== '';
        $hasOfferRef = trim($piScalars['offer_ref']) !== '';
        $hasOfferId = trim($piScalars['offer_id']) !== '';

        return [
            'pricing_information_index' => $piIndex,
            'has_pricing_information_ref' => $hasPricingRef,
            'has_pricing_information_id' => $hasPricingId,
            'has_offer_ref' => $hasOfferRef,
            'has_offer_id' => $hasOfferId,
            'itinerary_ref_present' => trim($itineraryRef) !== '',
            'fare_component_ref_count' => min(48, max(0, $fareComponentRefCount)),
            'explicit_pricing_ref_present' => $hasPricingRef,
            'stable_offer_linkage_present' => $hasOfferRef || $hasOfferId,
        ];
    }

    /**
     * Primary ATPCO/Sabre brand code from priced fare components when present (inline, then fareComponentDescs brand).
     */
    protected function extractPrimaryBrandCode(array $fare): ?string
    {
        $list = data_get($fare, 'passengerInfoList.0.passengerInfo.fareComponents');
        if (! is_array($list)) {
            $resolved = $this->resolveBrandFieldsFromFareNode($fare);

            return $resolved['code'] !== '' ? $resolved['code'] : null;
        }
        foreach ($list as $fc) {
            if (! is_array($fc)) {
                continue;
            }
            $brand = trim((string) ($fc['brandCode'] ?? $fc['fareFamilyCode'] ?? $fc['fareFamily'] ?? $fc['fareFamilyName'] ?? ''));
            if ($brand !== '') {
                return $brand;
            }
        }

        $resolved = $this->resolveBrandFieldsFromFareNode($fare);

        return $resolved['code'] !== '' ? $resolved['code'] : null;
    }

    /**
     * Resolve inline and descriptor-based brand fields from one fare component (IATI-style GIR parity).
     *
     * @param  array<string, mixed>  $fc
     * @return array{
     *   inline_code: string,
     *   inline_name: string,
     *   descriptor_code: string,
     *   descriptor_name: string,
     *   code: string,
     *   name: string,
     *   meta: array<string, mixed>
     * }
     */
    protected function resolveBrandFieldsFromFareComponent(array $fc): array
    {
        $inlineCode = $this->extractInlineBrandCodeFromFareComponent($fc);
        $inlineName = $this->extractInlineBrandNameFromFareComponent($fc);
        $descriptorCode = '';
        $descriptorName = '';
        $meta = [];

        $ref = $this->resolveFareComponentDescRefFromFareComponent($fc);
        if ($ref !== null) {
            $desc = $this->resolveDescriptorKey('fare_component', $ref);
            if (is_array($desc) && is_array($desc['brand'] ?? null) && $desc['brand'] !== []) {
                $componentBrand = $desc['brand'];
                $descriptorCode = $this->pickBrandScalar($componentBrand, ['code', 'brandCode']);
                $descriptorName = $this->pickBrandScalar($componentBrand, ['brandName', 'name']);

                $programCode = $this->pickBrandScalar($componentBrand, ['programCode', 'programName']);
                if ($programCode !== '') {
                    $meta['program_code'] = substr($programCode, 0, 32);
                }

                $pcRef = $this->pickBrandScalar($componentBrand, [
                    'priceClassDescriptionRef', 'priceClassRef', 'priceClassID', 'priceClassId',
                ]);
                if ($pcRef !== '') {
                    $meta['price_class_description_ref'] = $pcRef;
                }

                $brandId = $componentBrand['id'] ?? null;
                if (is_numeric($brandId)) {
                    $brandDesc = $this->resolveDescriptorKey('fare_brand', (int) $brandId);
                    if (is_array($brandDesc)) {
                        $fareBrandName = $this->pickBrandScalar($brandDesc, ['brandName', 'name']);
                        $fareBrandCode = $this->pickBrandScalar($brandDesc, ['code', 'brandCode']);
                        if ($descriptorName === '' && $fareBrandName !== '') {
                            $descriptorName = $fareBrandName;
                        }
                        if ($descriptorCode === '' && $fareBrandCode !== '') {
                            $descriptorCode = $fareBrandCode;
                        }
                        $meta['brand_source'] = 'fare_brand_descs';
                    }
                }

                if (($descriptorName !== '' || $descriptorCode !== '') && ! isset($meta['brand_source'])) {
                    $meta['brand_source'] = 'fare_component_desc';
                }

                $featureRefs = $this->collectBrandFeatureRefsFromBrandNode($componentBrand);
                if ($featureRefs !== []) {
                    $meta['brand_feature_ref_count'] = count($featureRefs);
                }
            }
        }

        $code = $inlineCode ?? '';
        if ($code === '' && $descriptorCode !== '') {
            $code = $descriptorCode;
        }
        $name = $inlineName ?? '';
        if ($name === '' && $descriptorName !== '') {
            $name = $descriptorName;
        }
        if ($name === '' && $code !== '') {
            $name = $code;
        }

        return [
            'inline_code' => $inlineCode ?? '',
            'inline_name' => $inlineName ?? '',
            'descriptor_code' => $descriptorCode,
            'descriptor_name' => $descriptorName,
            'code' => $code,
            'name' => $name,
            'meta' => $meta,
        ];
    }

    /**
     * Walk priced fare components (prefer ADT) and merge brand fields for one fare node.
     *
     * @param  array<string, mixed>  $fareNode
     * @return array{
     *   inline_code: string,
     *   inline_name: string,
     *   descriptor_code: string,
     *   descriptor_name: string,
     *   code: string,
     *   name: string,
     *   meta: array<string, mixed>
     * }
     */
    protected function resolveBrandFieldsFromFareNode(array $fareNode): array
    {
        $empty = [
            'inline_code' => '',
            'inline_name' => '',
            'descriptor_code' => '',
            'descriptor_name' => '',
            'code' => '',
            'name' => '',
            'meta' => [],
        ];

        $components = $this->collectFareComponentsFromFareNode($fareNode);
        if ($components === []) {
            return $empty;
        }

        $merged = $empty;
        foreach ($components as $fc) {
            if (! is_array($fc)) {
                continue;
            }
            $row = $this->resolveBrandFieldsFromFareComponent($fc);
            if ($merged['inline_code'] === '' && $row['inline_code'] !== '') {
                $merged['inline_code'] = $row['inline_code'];
            }
            if ($merged['inline_name'] === '' && $row['inline_name'] !== '') {
                $merged['inline_name'] = $row['inline_name'];
            }
            if ($merged['descriptor_code'] === '' && $row['descriptor_code'] !== '') {
                $merged['descriptor_code'] = $row['descriptor_code'];
            }
            if ($merged['descriptor_name'] === '' && $row['descriptor_name'] !== '') {
                $merged['descriptor_name'] = $row['descriptor_name'];
            }
            if ($merged['code'] === '' && $row['code'] !== '') {
                $merged['code'] = $row['code'];
            }
            if ($merged['name'] === '' && $row['name'] !== '') {
                $merged['name'] = $row['name'];
            }
            if ($merged['meta'] === [] && $row['meta'] !== []) {
                $merged['meta'] = $row['meta'];
            }
            if ($merged['code'] !== '' && $merged['name'] !== '') {
                break;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $fc
     */
    protected function extractInlineBrandCodeFromFareComponent(array $fc): ?string
    {
        $brand = trim((string) ($fc['brandCode'] ?? $fc['fareFamilyCode'] ?? $fc['fareFamily'] ?? $fc['fareFamilyName'] ?? ''));

        return $brand !== '' ? $brand : null;
    }

    /**
     * @param  array<string, mixed>  $fc
     */
    protected function extractInlineBrandNameFromFareComponent(array $fc): ?string
    {
        foreach (['fareFamilyName', 'fareFamily', 'name', 'brandCode', 'fareFamilyCode'] as $k) {
            $v = trim((string) ($fc[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fc
     */
    protected function resolveFareComponentDescRefFromFareComponent(array $fc): ?int
    {
        foreach (['ref', 'fareComponentDescRef', 'fareComponentDescNumber', 'fareComponentDescIndex'] as $rk) {
            if (isset($fc[$rk]) && is_numeric($fc[$rk])) {
                return (int) $fc[$rk];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $brandNode
     * @param  list<string>  $keys
     */
    protected function pickBrandScalar(array $brandNode, array $keys): string
    {
        foreach ($keys as $k) {
            $v = trim((string) ($brandNode[$k] ?? ''));
            if ($v !== '') {
                return substr($v, 0, 120);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $brandNode
     * @return list<int>
     */
    protected function collectBrandFeatureRefsFromBrandNode(array $brandNode): array
    {
        $refs = [];
        foreach (['brandFeatureRefs', 'brandFeatures', 'featureRefs', 'features'] as $listKey) {
            $list = $brandNode[$listKey] ?? null;
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $item) {
                if (is_numeric($item)) {
                    $refs[] = (int) $item;
                } elseif (is_array($item)) {
                    foreach (['ref', 'id'] as $rk) {
                        if (isset($item[$rk]) && is_numeric($item[$rk])) {
                            $refs[] = (int) $item[$rk];
                            break;
                        }
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($refs, static fn (int $v): bool => $v >= 0)));
    }

    /**
     * @param  array<string, mixed>  $fareNode
     * @return list<array<string, mixed>>
     */
    protected function collectFareComponentsFromFareNode(array $fareNode): array
    {
        $list = $fareNode['passengerInfoList'] ?? null;
        $preferAdt = [];
        $fallback = [];
        if (is_array($list)) {
            foreach ($list as $wrap) {
                if (! is_array($wrap)) {
                    continue;
                }
                $pi = $wrap['passengerInfo'] ?? null;
                if (! is_array($pi)) {
                    continue;
                }
                $fcs = is_array($pi['fareComponents'] ?? null) ? array_values($pi['fareComponents']) : [];
                if ($fcs === []) {
                    continue;
                }
                $pt = strtoupper(trim((string) ($pi['passengerType'] ?? $pi['passengerTypeCode'] ?? '')));
                if ($pt === 'ADT' || $pt === 'ADULT' || $pt === '') {
                    $preferAdt = $fcs;
                    break;
                }
                if ($fallback === []) {
                    $fallback = $fcs;
                }
            }
        }

        $picked = $preferAdt !== [] ? $preferAdt : $fallback;
        foreach ($fareNode['fareComponents'] ?? [] as $fc) {
            if (is_array($fc)) {
                $picked[] = $fc;
            }
        }

        return array_values(array_filter($picked, fn ($row): bool => is_array($row)));
    }

    /**
     * @param  list<array<string, mixed>>  $fareComponentDescs
     */
    protected function countFareComponentDescsWithBrand(array $fareComponentDescs): int
    {
        $count = 0;
        foreach ($fareComponentDescs as $row) {
            if (! is_array($row)) {
                continue;
            }
            $brand = $row['brand'] ?? null;
            if (is_array($brand) && $brand !== []) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $fareComponentDescs
     * @return list<string>
     */
    protected function collectDescriptorBrandSampleKeys(array $fareComponentDescs): array
    {
        $keys = [];
        foreach ($fareComponentDescs as $row) {
            if (! is_array($row)) {
                continue;
            }
            $brand = $row['brand'] ?? null;
            if (! is_array($brand)) {
                continue;
            }
            foreach (array_keys($brand) as $k) {
                if (is_string($k) && $k !== '') {
                    $keys[$k] = true;
                }
            }
        }

        $out = array_keys($keys);
        sort($out);

        return array_slice($out, 0, 24);
    }

    /**
     * Safe, capped Sabre shop / pricing reference tokens for Trip Orders booking (no raw shop JSON).
     *
     * @param  array<string, mixed>  $itinerary
     * @param  array<string, mixed>  $fareNode
     * @param  list<string>  $fareBasisCodes
     * @return array<string, string>
     */
    protected function extractSabreShopIdentifiersSnapshot(array $itinerary, array $fareNode, array $fareBasisCodes = []): array
    {
        $out = [];
        $id = trim((string) ($itinerary['id'] ?? ''));
        if ($id !== '') {
            $out['itinerary_id'] = substr($id, 0, 120);
        }
        foreach (['source', 'pricingSource'] as $k) {
            $v = trim((string) ($itinerary[$k] ?? ''));
            if ($v !== '') {
                $out['itinerary_'.$k] = substr($v, 0, 64);
            }
        }
        $pi = $itinerary['pricingInformation'] ?? null;
        if (is_array($pi)) {
            foreach (array_slice($pi, 0, 3) as $idx => $pRow) {
                if (! is_array($pRow)) {
                    continue;
                }
                $pfx = 'pricing_'.$idx.'_';
                foreach ([
                    'id', 'ref', 'offerItemId', 'offerItemRef', 'offerItemReference', 'offerItemID',
                    'fareReference', 'fareRef', 'priceQuoteReference', 'priceQuoteRef',
                    'pricingSubSource', 'pricingSubsource', 'pricingInformationRef', 'pricingRef',
                    'offerReference', 'offerRef', 'offerId', 'offerID',
                    'revalidated', 'soldOut',
                ] as $k) {
                    $v = $pRow[$k] ?? null;
                    if (is_string($v) && trim($v) !== '') {
                        $out[$pfx.$k] = substr(trim($v), 0, 120);
                    } elseif (is_scalar($v) && $v !== '') {
                        $out[$pfx.$k] = substr((string) $v, 0, 32);
                    }
                }
                $fare = is_array($pRow['fare'] ?? null) ? $pRow['fare'] : [];
                foreach (['fareConstruction', 'accountCode', 'lastTicketDate', 'source'] as $fk) {
                    $v = trim((string) ($fare[$fk] ?? ''));
                    if ($v !== '') {
                        $out[$pfx.'fare_'.$fk] = substr($v, 0, 120);
                    }
                }
                foreach (['offer', 'order', 'offerItem'] as $nestedKey) {
                    $node = is_array($pRow[$nestedKey] ?? null) ? $pRow[$nestedKey] : [];
                    if ($node === []) {
                        continue;
                    }
                    foreach (['id', 'ref'] as $nk) {
                        $v = $node[$nk] ?? null;
                        if (is_string($v) && trim($v) !== '') {
                            $out[$pfx.$nestedKey.'_'.$nk] = substr(trim($v), 0, 120);
                        } elseif (is_scalar($v) && $v !== '') {
                            $out[$pfx.$nestedKey.'_'.$nk] = substr((string) $v, 0, 32);
                        }
                    }
                }
            }
        }
        $vcc = trim((string) ($fareNode['validatingCarrierCode'] ?? ''));
        if ($vcc !== '') {
            $out['validating_carrier_code'] = substr($vcc, 0, 8);
        }

        $bfmFareBasisCodes = $fareBasisCodes !== [] ? $fareBasisCodes : $this->collectFareBasisCodesFromFareNode($fareNode);
        if ($bfmFareBasisCodes !== []) {
            $out['fare_basis_first'] = substr(strtoupper($bfmFareBasisCodes[0]), 0, 24);
            $out['fare_basis_codes_csv'] = substr(implode(',', array_slice($bfmFareBasisCodes, 0, 8)), 0, 120);
        }

        return $out;
    }

    /**
     * Compact, safe BFM context needed by /v4/shop/flights/revalidate and Trip Orders booking.
     *
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $carrierDisplay
     * @param  list<string>  $fareBasisCodes
     * @return array<string, mixed>
     */
    protected function buildSabreShopContextSnapshot(
        array $itinerary,
        array $fareNode,
        ?FlightSearchRequestData $searchRequest,
        array $segments,
        array $carrierDisplay,
        string $validatingCarrier,
        array $fareBasisCodes,
    ): array {
        $pricingInfo = is_array($itinerary['pricingInformation'] ?? null) ? array_values($itinerary['pricingInformation']) : [];
        $firstPricing = is_array($pricingInfo[0] ?? null) ? $pricingInfo[0] : [];
        $firstPricingFare = is_array($firstPricing['fare'] ?? null) ? $firstPricing['fare'] : [];
        $legRefs = $this->collectNumericRefs(is_array($itinerary['legs'] ?? null) ? $itinerary['legs'] : []);
        $scheduleRefs = $this->collectScheduleRefsForLegs($legRefs);
        $fareComponentRefs = $this->collectFareComponentRefsFromFareNode($fareNode, false);
        $fareComponentDescRefs = $this->collectFareComponentRefsFromFareNode($fareNode, true);
        $baggageRefs = $this->collectBaggageRefsFromFareNode($fareNode);
        $bookingClasses = array_values(array_filter(array_map(
            static fn (array $s): string => strtoupper(trim((string) ($s['booking_class'] ?? ''))),
            $segments
        ), static fn (string $v): bool => $v !== ''));

        $requested = [];
        if ($searchRequest !== null) {
            $requested = array_filter([
                'origin' => strtoupper(trim($searchRequest->origin)),
                'destination' => strtoupper(trim($searchRequest->destination)),
                'departure_date' => trim($searchRequest->departure_date),
                'return_date' => $searchRequest->return_date !== null ? trim($searchRequest->return_date) : null,
                'trip_type' => trim($searchRequest->trip_type),
                'cabin' => trim($searchRequest->cabin),
                'passenger_counts' => [
                    'adults' => max(0, (int) $searchRequest->adults),
                    'children' => max(0, (int) $searchRequest->children),
                    'infants' => max(0, (int) $searchRequest->infants),
                ],
            ], static fn ($v): bool => $v !== null && $v !== '');
        }

        $piScalars = $this->extractPricingInformationLinkageScalars($firstPricing, $firstPricingFare);
        $itinRef = $this->firstSafeScalar($itinerary, ['id', 'ref']);
        $pricingRef = $piScalars['pricing_information_ref'] !== ''
            ? $piScalars['pricing_information_ref']
            : $this->firstSafeScalar($firstPricing, [
                'ref', 'pricingInformationRef', 'pricingRef',
            ]);
        $context = [
            'itinerary_group_index' => (int) ($itinerary['_ota_itinerary_group_index'] ?? 0),
            'itinerary_index' => (int) ($itinerary['_ota_itinerary_index'] ?? 0),
            'itinerary_ref' => $itinRef !== '' ? $itinRef : $this->safeScalar((string) ($itinerary['id'] ?? '')),
            'itinerary_pricing_index' => 0,
            'pricing_information_index' => 0,
            'pricing_information_ref' => $pricingRef,
            'pricing_information_id' => $piScalars['pricing_information_id'],
            'pricing_subsource' => $piScalars['pricing_subsource'],
            'fare_source' => $piScalars['fare_source'],
            'offer_ref' => $piScalars['offer_ref'],
            'offer_id' => $piScalars['offer_id'],
            'order_ref' => $piScalars['order_ref'],
            'leg_refs' => $legRefs,
            'schedule_refs' => $scheduleRefs,
            'fare_component_refs' => $fareComponentRefs,
            'fare_component_desc_refs' => $fareComponentDescRefs,
            'baggage_refs' => $baggageRefs,
            'validating_carrier' => $validatingCarrier !== '' ? substr($validatingCarrier, 0, 8) : $this->safeScalar((string) ($fareNode['validatingCarrierCode'] ?? ''), 8),
            'carrier_chain' => array_values(array_filter(array_map(
                static fn ($v): string => strtoupper(trim((string) $v)),
                $carrierDisplay['marketing_carrier_chain'] ?? []
            ), static fn (string $v): bool => $v !== '')),
            'booking_class' => $bookingClasses,
            'fare_basis_codes' => array_slice($fareBasisCodes, 0, 12),
            'requested' => $requested,
            'shop_request_signature' => substr(hash('sha256', json_encode($requested, JSON_UNESCAPED_SLASHES) ?: ''), 0, 16),
            'shop_endpoint_path' => (string) config('suppliers.sabre.shop_path', '/v4/offers/shop'),
        ];

        return $this->compactSafeContext($context);
    }

    /**
     * P2b: Promote safe scalar pricing/offer tokens from {@code sabre_shop_identifiers} into {@code sabre_shop_context}
     * when the context row omitted them (no raw Sabre JSON stored).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $identifiers
     * @return array<string, mixed>
     */
    public function syncShopContextLinkageFromIdentifiers(array $context, array $identifiers): array
    {
        if ($identifiers === []) {
            return $context;
        }

        $idx = (int) ($context['pricing_information_index'] ?? 0);
        $pfx = 'pricing_'.$idx.'_';

        $this->fillContextScalarFromIdentifierKeys($context, $identifiers, 'pricing_information_ref', [
            $pfx.'ref',
            $pfx.'pricingInformationRef',
            $pfx.'pricingRef',
            $pfx.'offerItemId',
            $pfx.'offerItemRef',
            $pfx.'offerItemReference',
            $pfx.'fareReference',
            $pfx.'fareRef',
            $pfx.'priceQuoteReference',
            $pfx.'priceQuoteRef',
        ]);
        $this->fillContextScalarFromIdentifierKeys($context, $identifiers, 'pricing_information_id', [
            $pfx.'id',
        ]);
        $this->fillContextScalarFromIdentifierKeys($context, $identifiers, 'offer_ref', [
            $pfx.'offer_ref',
            $pfx.'offerRef',
            $pfx.'offerItemRef',
            $pfx.'offerItem_ref',
            $pfx.'offer_item_ref',
        ]);
        $this->fillContextScalarFromIdentifierKeys($context, $identifiers, 'offer_id', [
            $pfx.'offer_id',
            $pfx.'offerId',
            $pfx.'offerItemId',
            $pfx.'offer_item_id',
            $pfx.'offerItem_id',
        ]);
        $this->fillContextScalarFromIdentifierKeys($context, $identifiers, 'order_ref', [
            $pfx.'order_ref',
            $pfx.'orderRef',
            $pfx.'order_id',
        ]);
        $this->fillContextScalarFromIdentifierKeys($context, $identifiers, 'pricing_subsource', [
            $pfx.'pricingSubsource',
            $pfx.'pricingSubSource',
        ]);
        $this->fillContextScalarFromIdentifierKeys($context, $identifiers, 'fare_source', [
            $pfx.'fare_source',
        ]);
        if (trim((string) ($context['offer_ref'] ?? '')) === ''
            && trim((string) ($context['offer_id'] ?? '')) !== '') {
            $context['offer_ref'] = substr(trim((string) $context['offer_id']), 0, 120);
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $identifiers
     * @param  list<string>  $identifierKeys
     */
    protected function fillContextScalarFromIdentifierKeys(
        array &$context,
        array $identifiers,
        string $contextKey,
        array $identifierKeys,
    ): void {
        if (trim((string) ($context[$contextKey] ?? '')) !== '') {
            return;
        }
        foreach ($identifierKeys as $idKey) {
            if (! array_key_exists($idKey, $identifiers)) {
                continue;
            }
            $v = $identifiers[$idKey];
            if (! is_string($v) && ! is_numeric($v)) {
                continue;
            }
            $s = $this->safeScalar((string) $v);
            if ($s !== '') {
                $context[$contextKey] = $s;

                return;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<int|string>
     */
    protected function collectNumericRefs(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['ref', 'id'] as $k) {
                if (isset($row[$k]) && (is_numeric($row[$k]) || is_string($row[$k]))) {
                    $v = is_numeric($row[$k]) ? (int) $row[$k] : $this->safeScalar((string) $row[$k]);
                    if ($v !== '' && ! in_array($v, $out, true)) {
                        $out[] = $v;
                    }
                }
            }
        }

        return array_slice($out, 0, 24);
    }

    /**
     * @param  list<int|string>  $legRefs
     * @return list<int|string>
     */
    protected function collectScheduleRefsForLegs(array $legRefs): array
    {
        $out = [];
        foreach ($legRefs as $legRef) {
            if (! is_numeric($legRef)) {
                continue;
            }
            $leg = $this->resolveDescriptorKey('leg', (int) $legRef);
            $schedules = is_array($leg['schedules'] ?? null) ? $leg['schedules'] : [];
            foreach ($this->collectNumericRefs($schedules) as $ref) {
                if (! in_array($ref, $out, true)) {
                    $out[] = $ref;
                }
            }
        }

        return array_slice($out, 0, 48);
    }

    /**
     * @return list<int|string>
     */
    protected function collectFareComponentRefsFromFareNode(array $fareNode, bool $descOnly): array
    {
        $keys = $descOnly
            ? ['fareComponentDescRef', 'fareComponentDescNumber', 'fareComponentDescIndex', 'ref']
            : ['ref', 'fareComponentRef', 'fareComponentNumber', 'fareComponentIndex'];
        $out = [];
        foreach ($fareNode['passengerInfoList'] ?? [] as $wrap) {
            $pi = is_array($wrap) && is_array($wrap['passengerInfo'] ?? null) ? $wrap['passengerInfo'] : [];
            foreach ($pi['fareComponents'] ?? [] as $fc) {
                if (! is_array($fc)) {
                    continue;
                }
                foreach ($keys as $k) {
                    if (! isset($fc[$k]) || (! is_numeric($fc[$k]) && ! is_string($fc[$k]))) {
                        continue;
                    }
                    $v = is_numeric($fc[$k]) ? (int) $fc[$k] : $this->safeScalar((string) $fc[$k]);
                    if ($v !== '' && ! in_array($v, $out, true)) {
                        $out[] = $v;
                    }
                }
            }
        }

        return array_slice($out, 0, 48);
    }

    /**
     * @return list<int|string>
     */
    protected function collectBaggageRefsFromFareNode(array $fareNode): array
    {
        $out = [];
        foreach ($fareNode['passengerInfoList'] ?? [] as $wrap) {
            $pi = is_array($wrap) && is_array($wrap['passengerInfo'] ?? null) ? $wrap['passengerInfo'] : [];
            foreach (array_merge($pi['baggageInformation'] ?? [], $pi['fareComponents'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['ref', 'baggageAllowanceRef', 'baggageAllowanceNumber', 'allowanceRef'] as $k) {
                    $candidate = $row[$k] ?? data_get($row, 'allowance.'.$k);
                    if (! is_numeric($candidate) && ! is_string($candidate)) {
                        continue;
                    }
                    $v = is_numeric($candidate) ? (int) $candidate : $this->safeScalar((string) $candidate);
                    if ($v !== '' && ! in_array($v, $out, true)) {
                        $out[] = $v;
                    }
                }
            }
        }

        return array_slice($out, 0, 48);
    }

    /**
     * @param  list<string>  $keys
     */
    protected function firstSafeScalar(array $row, array $keys): string
    {
        foreach ($keys as $k) {
            $v = $row[$k] ?? null;
            if ((is_string($v) || is_numeric($v)) && trim((string) $v) !== '') {
                return $this->safeScalar((string) $v);
            }
        }

        return '';
    }

    /**
     * Scalar pricing/offer linkage tokens from the first BFM {@code pricingInformation} row (no raw payload storage).
     *
     * @param  array<string, mixed>  $pricingRow
     * @param  array<string, mixed>  $fareFromRow  {@code $pricingRow['fare']} when already extracted
     * @return array{
     *   pricing_information_ref: string,
     *   pricing_information_id: string,
     *   pricing_subsource: string,
     *   fare_source: string,
     *   offer_ref: string,
     *   offer_id: string,
     *   order_ref: string
     * }
     */
    protected function extractPricingInformationLinkageScalars(array $pricingRow, array $fareFromRow = []): array
    {
        $out = [
            'pricing_information_ref' => '',
            'pricing_information_id' => '',
            'pricing_subsource' => '',
            'fare_source' => '',
            'offer_ref' => '',
            'offer_id' => '',
            'order_ref' => '',
        ];
        if ($pricingRow === []) {
            return $out;
        }
        $fare = $fareFromRow !== [] ? $fareFromRow : (is_array($pricingRow['fare'] ?? null) ? $pricingRow['fare'] : []);

        $piRef = $this->firstSafeScalar($pricingRow, ['ref', 'pricingInformationRef', 'pricingRef']);
        $piId = $this->firstSafeScalar($pricingRow, ['id']);
        $out['pricing_information_id'] = $piId;
        // Preserve explicit ref tokens only; do not promote `id` or other identifiers into `pricing_information_ref`
        // (indexes are stored separately on `sabre_shop_context` for diagnostics / experimental payloads).
        $out['pricing_information_ref'] = $piRef !== '' ? $piRef : $this->firstSafeScalar($pricingRow, [
            'offerItemId', 'offerItemRef', 'offerItemReference', 'fareReference', 'fareRef',
            'priceQuoteReference', 'priceQuoteRef',
        ]);

        $out['pricing_subsource'] = $this->firstSafeScalar($pricingRow, ['pricingSubsource', 'pricingSubSource']);
        $out['fare_source'] = $this->firstSafeScalar($fare, ['source']);

        foreach (['offer', 'order', 'offerItem'] as $wrapKey) {
            $node = is_array($pricingRow[$wrapKey] ?? null) ? $pricingRow[$wrapKey] : [];
            if ($node === []) {
                continue;
            }
            $nid = $this->firstSafeScalar($node, ['id']);
            $nref = $this->firstSafeScalar($node, ['ref']);
            if ($wrapKey === 'offer') {
                $out['offer_id'] = $out['offer_id'] !== '' ? $out['offer_id'] : $nid;
                $out['offer_ref'] = $out['offer_ref'] !== '' ? $out['offer_ref'] : $nref;
            }
            if ($wrapKey === 'order') {
                $out['order_ref'] = $out['order_ref'] !== '' ? $out['order_ref'] : ($nref !== '' ? $nref : $nid);
            }
            if ($wrapKey === 'offerItem') {
                $out['offer_id'] = $out['offer_id'] !== '' ? $out['offer_id'] : $nid;
                $out['offer_ref'] = $out['offer_ref'] !== '' ? $out['offer_ref'] : ($nref !== '' ? $nref : $this->firstSafeScalar($node, ['offerItemId', 'offerItemRef']));
            }
        }

        if ($out['offer_id'] === '' || $out['offer_ref'] === '') {
            $oid = $this->firstSafeScalar($pricingRow, ['offerId', 'offer_id', 'offerID']);
            $oref = $this->firstSafeScalar($pricingRow, ['offerRef', 'offer_ref']);
            if ($out['offer_id'] === '' && $oid !== '') {
                $out['offer_id'] = $oid;
            }
            if ($out['offer_ref'] === '' && $oref !== '') {
                $out['offer_ref'] = $oref;
            }
        }

        return $out;
    }

    protected function safeScalar(string $value, int $max = 120): string
    {
        return substr(trim($value), 0, max(1, $max));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function compactSafeContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            if (is_string($v)) {
                $safe = $this->safeScalar($v);
                if ($safe !== '') {
                    $out[$k] = $safe;
                }
            } elseif (is_numeric($v) || is_bool($v)) {
                $out[$k] = $v;
            } elseif (is_array($v)) {
                $nested = [];
                foreach ($v as $nk => $nv) {
                    if (is_string($nv) || is_numeric($nv) || is_bool($nv)) {
                        $sv = is_string($nv) ? $this->safeScalar($nv) : $nv;
                        if ($sv !== '') {
                            $nested[$nk] = $sv;
                        }
                    } elseif (is_array($nv)) {
                        $cleanList = array_values(array_filter(array_map(function ($item) {
                            if (is_string($item)) {
                                return $this->safeScalar($item, 48);
                            }

                            return is_numeric($item) || is_bool($item) ? $item : null;
                        }, $nv), static fn ($item): bool => $item !== null && $item !== ''));
                        if ($cleanList !== []) {
                            $nested[$nk] = array_slice($cleanList, 0, 24);
                        }
                    }
                }
                if ($nested !== []) {
                    $out[$k] = array_is_list($v) ? array_slice(array_values($nested), 0, 48) : $nested;
                }
            }
        }

        return $out;
    }

    /**
     * BFM grouped-response fare basis hints from passengerInfoList[].fareComponents[].fareBasisCode (preferring ADT),
     * with descriptor fallback (fareComponentDescs) when only refs are present. Used to populate
     * raw_payload.sabre_shop_identifiers for Trip Orders booking linkage.
     *
     * @param  array<string, mixed>  $fareNode  First priced fare from itinerary
     * @return list<string>
     */
    protected function collectFareBasisCodesFromFareNode(array $fareNode): array
    {
        $list = $fareNode['passengerInfoList'] ?? null;
        $preferAdt = [];
        $fallback = [];
        if (is_array($list)) {
            foreach ($list as $wrap) {
                if (! is_array($wrap)) {
                    continue;
                }
                $pi = $wrap['passengerInfo'] ?? null;
                if (! is_array($pi)) {
                    continue;
                }
                $codes = $this->collectFareBasisCodesFromPassengerInfo($pi);
                if ($codes === []) {
                    continue;
                }
                $pt = strtoupper(trim((string) ($pi['passengerType'] ?? $pi['passengerTypeCode'] ?? '')));
                if ($pt === 'ADT' || $pt === 'ADULT' || $pt === '') {
                    $preferAdt = $codes;
                    break;
                }
                if ($fallback === []) {
                    $fallback = $codes;
                }
            }
        }
        $picked = $preferAdt !== [] ? $preferAdt : $fallback;

        foreach ($fareNode['fareComponents'] ?? [] as $fc) {
            if (is_array($fc)) {
                $picked = array_merge($picked, $this->collectFareBasisCodesFromFareComponent($fc));
            }
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $v): string => strtoupper(substr(trim($v), 0, 32)),
            $picked
        ), static fn (string $v): bool => $v !== '')));
    }

    /**
     * @param  array<string, mixed>  $passengerInfo
     * @return list<string>
     */
    protected function collectFareBasisCodesFromPassengerInfo(array $passengerInfo): array
    {
        $codes = [];
        foreach ($passengerInfo['fareComponents'] ?? [] as $fc) {
            if (is_array($fc)) {
                $codes = array_merge($codes, $this->collectFareBasisCodesFromFareComponent($fc));
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  array<string, mixed>  $fc
     * @return list<string>
     */
    protected function collectFareBasisCodesFromFareComponent(array $fc): array
    {
        $codes = [];
        foreach (['fareBasisCode', 'fareBasis'] as $k) {
            $code = trim((string) ($fc[$k] ?? ''));
            if ($code !== '') {
                $codes[] = strtoupper($code);
            }
        }
        foreach (['ref', 'fareComponentDescRef', 'fareComponentDescNumber', 'fareComponentDescIndex'] as $rk) {
            if (! isset($fc[$rk]) || ! is_numeric($fc[$rk])) {
                continue;
            }
            $desc = $this->resolveDescriptorKey('fare_component', (int) $fc[$rk]);
            if (! is_array($desc)) {
                continue;
            }
            foreach (['fareBasisCode', 'fareBasis'] as $dk) {
                $code = trim((string) ($desc[$dk] ?? ''));
                if ($code !== '') {
                    $codes[] = strtoupper($code);
                }
            }
        }
        foreach ($fc['segments'] ?? [] as $segWrap) {
            $seg = is_array($segWrap['segment'] ?? null) ? $segWrap['segment'] : (is_array($segWrap) ? $segWrap : []);
            if ($seg === []) {
                continue;
            }
            foreach (['fareBasisCode', 'fareBasis'] as $sk) {
                $code = trim((string) ($seg[$sk] ?? ''));
                if ($code !== '') {
                    $codes[] = strtoupper($code);
                }
            }
            foreach (['bookingCode', 'resBookDesigCode', 'classOfService'] as $sk) {
                $code = trim((string) ($seg[$sk] ?? data_get($seg, 'segment.'.$sk) ?? ''));
                if ($code !== '') {
                    $codes[] = strtoupper($code);
                    break;
                }
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }

    /**
     * Local inspect: safe per-itinerary digests with pre-repair segment timeline and normalizer outcome.
     *
     * @param  array<string, mixed>  $response
     * @return array{summary: array<string, mixed>, itineraries: list<array<string, mixed>>}
     */
    public function inspectRawItineraryDigests(
        array $response,
        SupplierConnection $connection,
        ?FlightSearchRequestData $searchRequest,
    ): array {
        $this->resetDisplayDiagnostics();
        $gir = data_get($response, 'groupedItineraryResponse');
        if (! is_array($gir)) {
            return [
                'summary' => $this->buildInspectSummaryShell(0, 0, 0, 0, 0, 0, [], [], [], 0),
                'itineraries' => [],
            ];
        }

        $scheduleDescs = $this->listDescs($response, 'scheduleDescs');
        $legDescs = $this->listDescs($response, 'legDescs');
        $fareComponentDescs = $this->listDescs($response, 'fareComponentDescs');
        $this->primeGirDescriptorContextFromResponse($response);

        $itineraries = $this->collectItineraries($gir);
        $groups = $gir['itineraryGroups'] ?? null;
        $itineraryGroupCount = is_array($groups) ? count($groups) : 0;
        $pricingInformationCount = 0;
        foreach ($itineraries as $itin) {
            $pi = $itin['pricingInformation'] ?? null;
            if (is_array($pi)) {
                $pricingInformationCount += count($pi);
            }
        }

        $rawHist = [];
        $acceptedHist = [];
        $rejectedHist = [];
        $rows = [];
        $accepted = 0;
        $rejected = 0;
        $idx = 0;
        foreach ($itineraries as $itinerary) {
            if (! is_array($itinerary)) {
                $idx++;

                continue;
            }
            $one = $this->normalizeOneItineraryWithDiagnostics($itinerary, $connection, $searchRequest);
            $row = $this->composeRawItineraryInspectRow($idx, $itinerary, $one);
            $rows[] = $row;
            foreach ($row['raw_marketing_carriers'] ?? [] as $c) {
                $cc = strtoupper(trim((string) $c));
                if ($cc !== '') {
                    $rawHist[$cc] = ($rawHist[$cc] ?? 0) + 1;
                }
            }
            if ($one['offer'] !== null) {
                $accepted++;
                foreach ($this->marketingCarriersFromOffer($one['offer']) as $c) {
                    $acceptedHist[$c] = ($acceptedHist[$c] ?? 0) + 1;
                }
            } else {
                $rejected++;
                foreach ($row['raw_marketing_carriers'] ?? [] as $c) {
                    $cc = strtoupper(trim((string) $c));
                    if ($cc !== '') {
                        $rejectedHist[$cc] = ($rejectedHist[$cc] ?? 0) + 1;
                    }
                }
            }
            $idx++;
        }

        $this->finalizeDisplayDiagnosticsSnapshot();

        $summary = $this->buildInspectSummaryShell(
            $itineraryGroupCount,
            count($itineraries),
            count($scheduleDescs),
            count($legDescs),
            $pricingInformationCount,
            $accepted,
            $rawHist,
            $acceptedHist,
            $rejectedHist,
            $rejected
        );

        return ['summary' => $summary, 'itineraries' => $rows];
    }

    /**
     * @param  array<string, int>  $rawHist
     * @param  array<string, int>  $acceptedHist
     * @param  array<string, int>  $rejectedHist
     * @return array<string, mixed>
     */
    protected function buildInspectSummaryShell(
        int $itineraryGroupCount,
        int $itineraryCount,
        int $scheduleDescCount,
        int $legDescCount,
        int $pricingInformationCount,
        int $acceptedCount = 0,
        array $rawHist = [],
        array $acceptedHist = [],
        array $rejectedHist = [],
        int $rejectedCount = 0,
    ): array {
        return [
            'itinerary_group_count' => $itineraryGroupCount,
            'itinerary_count' => $itineraryCount,
            'schedule_desc_count' => $scheduleDescCount,
            'leg_desc_count' => $legDescCount,
            'pricing_information_count' => $pricingInformationCount,
            'normalized_accepted_count' => $acceptedCount,
            'normalized_rejected_count' => $rejectedCount,
            'raw_carrier_histogram' => $rawHist,
            'accepted_carrier_histogram' => $acceptedHist,
            'rejected_carrier_histogram' => $rejectedHist,
        ];
    }

    /**
     * @param  array<string, mixed>  $one  Result of normalizeOneItineraryWithDiagnostics
     * @return array<string, mixed>
     */
    protected function composeRawItineraryInspectRow(int $rawItineraryIndex, array $itinerary, array $one): array
    {
        $pres = $one['presabre_segments'] ?? [];
        $marketingChain = [];
        $operatingChain = [];
        $routeParts = [];
        $flightNums = [];
        $rawDeps = [];
        $rawArrs = [];
        $elapsedSeg = [];
        foreach ($pres as $seg) {
            $m = strtoupper(trim((string) ($seg['marketing'] ?? '')));
            if ($m !== '') {
                $marketingChain[] = $m;
            }
            $op = strtoupper(trim((string) ($seg['operating'] ?? '')));
            if ($op !== '') {
                $operatingChain[] = $op;
            }
            $fn = trim((string) ($seg['flight_number'] ?? ''));
            if ($m !== '' && $fn !== '') {
                $flightNums[] = $m.$fn;
            } elseif ($fn !== '') {
                $flightNums[] = $fn;
            }
            $rawDeps[] = (string) ($seg['departure_at'] ?? '');
            $rawArrs[] = (string) ($seg['arrival_at'] ?? '');
            $elapsedSeg[] = (int) ($seg['elapsed_minutes'] ?? 0);
        }
        if ($pres !== []) {
            $routeParts[] = strtoupper(trim((string) ($pres[0]['origin'] ?? '')));
            foreach ($pres as $seg) {
                $routeParts[] = strtoupper(trim((string) ($seg['destination'] ?? '')));
            }
        }
        $routeDisplay = implode('-', $routeParts);
        $fareNode = $this->firstFareNode($itinerary);
        $fareBreak = $this->extractFareBreakdownFromFare($fareNode);
        $fareExcerpt = $this->buildSabreFareExcerptSnapshot($fareNode);
        $validating = strtoupper(trim((string) ($fareNode['validatingCarrierCode'] ?? '')));
        $baggage = $this->extractBaggage($fareNode);
        $bagSummary = trim((string) ($baggage->summary ?? ''));
        $legElapsedSum = $this->itineraryLegsElapsedMinutesSum($itinerary);

        $offer = $one['offer'] ?? null;
        $status = $offer instanceof NormalizedFlightOfferData ? 'accepted' : 'rejected';
        if ($offer === null && ($one['reject_code'] ?? null) === null) {
            $status = 'unknown';
        }

        $base = [
            'raw_itinerary_index' => $rawItineraryIndex,
            'itinerary_ref' => (string) (data_get($itinerary, 'id') ?? data_get($itinerary, 'pricingInformation.0.offerItemId') ?? ''),
            'carrier_chain' => implode('+', $marketingChain),
            'raw_marketing_carriers' => $marketingChain,
            'raw_operating_carriers' => $operatingChain,
            'operating_chain' => implode('+', $operatingChain),
            'validating_carrier' => $validating,
            'route_chain' => $routeDisplay,
            'flight_numbers' => implode('+', $flightNums),
            'segment_count' => count($pres),
            'raw_departure_at' => $rawDeps,
            'raw_arrival_at' => $rawArrs,
            'elapsed_minutes_per_segment' => $elapsedSeg,
            'total_elapsed_minutes_leg_sum' => $legElapsedSum,
            'total_fare' => $fareBreak['supplier_total'],
            'fare_currency' => $fareBreak['currency'],
            'sabre_fare_excerpt' => $fareExcerpt,
            'baggage_summary' => $bagSummary !== '' ? $bagSummary : null,
            'normalizer_status' => $status,
            'reject_reason' => $one['reject_code'] ?? null,
            'reject_meta_safe' => $this->sanitizeInspectRejectMeta($one['reject_meta'] ?? []),
            'normalized_offer_id' => $offer instanceof NormalizedFlightOfferData ? $offer->offer_id : null,
            'normalized_total' => $offer instanceof NormalizedFlightOfferData ? $offer->fare_breakdown->supplier_total : null,
            'normalized_currency' => $offer instanceof NormalizedFlightOfferData ? $offer->fare_breakdown->currency : null,
            'normalized_base_fare' => $offer instanceof NormalizedFlightOfferData ? $offer->fare_breakdown->base_fare : null,
            'normalized_taxes' => $offer instanceof NormalizedFlightOfferData ? $offer->fare_breakdown->taxes : null,
        ];

        return array_merge($base, $this->normalizedOfferInspectDisplayFragment($offer));
    }

    /**
     * Safe Sabre shop money snapshot from the priced fare node (same sources as fare breakdown totals).
     *
     * @param  array<string, mixed>  $fare
     * @return array<string, mixed>
     */
    protected function buildSabreFareExcerptSnapshot(array $fare): array
    {
        $out = [
            'total_price_field' => 'totalFare.totalPrice',
            'total_price' => null,
            'currency' => null,
            'equiv_fare_amount' => null,
            'base_fare_amount' => null,
            'total_tax_amount' => null,
        ];
        $totalFareNode = $fare['totalFare'] ?? null;
        if (! is_array($totalFareNode)) {
            return $out;
        }
        $cur = strtoupper(trim((string) ($totalFareNode['currency']
            ?? $totalFareNode['currencyCode']
            ?? '')));
        if ($cur !== '') {
            $out['currency'] = $cur;
        }
        $tp = $this->parseAmount($totalFareNode['totalPrice'] ?? $totalFareNode['totalFare'] ?? null);
        if ($tp > 0) {
            $out['total_price'] = round($tp, 2);
        }
        $eq = $this->parseAmount($totalFareNode['equivFareAmount'] ?? null);
        if ($eq > 0) {
            $out['equiv_fare_amount'] = round($eq, 2);
        }
        $bf = $this->parseAmount($totalFareNode['baseFareAmount'] ?? $totalFareNode['baseFare'] ?? null);
        if ($bf > 0) {
            $out['base_fare_amount'] = round($bf, 2);
        }
        $tx = $this->parseAmount($totalFareNode['totalTaxAmount'] ?? $totalFareNode['taxAmount'] ?? null);
        if ($tx > 0) {
            $out['total_tax_amount'] = round($tx, 2);
        }

        return $out;
    }

    /**
     * Safe display-only fields for accepted offers (no raw Sabre payload).
     */
    protected function normalizedOfferInspectDisplayFragment(mixed $offer): array
    {
        if (! $offer instanceof NormalizedFlightOfferData) {
            return [
                'normalized_route_chain' => null,
                'normalized_carrier_chain' => null,
                'normalized_flight_numbers' => null,
                'primary_display_carrier' => null,
                'mixed_carrier' => null,
                'all_airline_codes' => null,
            ];
        }
        $segs = $offer->segments;
        $routeParts = [];
        if ($segs !== []) {
            $first = $segs[0] ?? null;
            if (is_array($first)) {
                $routeParts[] = strtoupper(trim((string) ($first['origin'] ?? '')));
            }
            foreach ($segs as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                $routeParts[] = strtoupper(trim((string) ($seg['destination'] ?? '')));
            }
        }

        return [
            'normalized_route_chain' => implode('-', array_values(array_filter($routeParts, fn (string $p): bool => $p !== ''))),
            'normalized_carrier_chain' => implode('+', $offer->marketing_carrier_chain),
            'normalized_flight_numbers' => (string) ($offer->flight_number ?? ''),
            'primary_display_carrier' => $offer->primary_display_carrier !== ''
                ? $offer->primary_display_carrier
                : $offer->airline_code,
            'mixed_carrier' => $offer->mixed_carrier,
            'all_airline_codes' => $offer->all_airline_codes,
        ];
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    protected function marketingCarriersFromOffer(NormalizedFlightOfferData $offer): array
    {
        $out = [];
        foreach ($offer->segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $c = strtoupper(trim((string) ($seg['airline_code'] ?? '')));
            if ($c !== '') {
                $out[] = $c;
            }
        }
        if ($out === []) {
            $c = strtoupper(trim($offer->airline_code));
            if ($c !== '') {
                $out[] = $c;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function sanitizeInspectRejectMeta(array $meta): array
    {
        $allow = [
            'segment_count', 'offer_origin', 'offer_destination', 'first_segment_origin', 'last_segment_destination',
            'failed_link_count', 'route_continuity_ok', 'segment_datetime_continuity_ok', 'failed_time_link_count',
            'invalid_segment_duration_count', 'requested_origin', 'requested_destination', 'segment_order_corrected',
            'original_route_continuity_ok', 'reversed_route_continuity_ok', 'out_of_order_segment_count',
        ];
        $out = [];
        foreach ($allow as $k) {
            if (array_key_exists($k, $meta)) {
                $out[$k] = $meta[$k];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $gir
     * @return list<array<string, mixed>>
     */
    protected function collectItineraries(array $gir): array
    {
        $out = [];
        $groups = $gir['itineraryGroups'] ?? null;
        if (! is_array($groups)) {
            return [];
        }
        foreach ($groups as $groupIndex => $group) {
            if (! is_array($group)) {
                continue;
            }
            $itineraries = $group['itineraries'] ?? null;
            if (! is_array($itineraries)) {
                continue;
            }
            foreach ($itineraries as $itineraryIndex => $it) {
                if (is_array($it)) {
                    $it['_ota_itinerary_group_index'] = $groupIndex;
                    $it['_ota_itinerary_index'] = $itineraryIndex;
                    $out[] = $it;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    protected function listDescs(array $response, string $key): array
    {
        $list = data_get($response, 'groupedItineraryResponse.'.$key);
        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter($list, fn ($row): bool => is_array($row)));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function primeGirDescriptorContextFromResponse(array $response): void
    {
        $scheduleDescs = $this->listDescs($response, 'scheduleDescs');
        $legDescs = $this->listDescs($response, 'legDescs');
        $baggageAllowanceDescs = $this->listDescs($response, 'baggageAllowanceDescs');
        $fareComponentDescs = $this->listDescs($response, 'fareComponentDescs');
        $brandFeatureDescs = $this->listDescs($response, 'brandFeatureDescs');
        $fareBrandDescs = $this->listDescs($response, 'fareBrandDescs');
        $priceClassDescriptions = $this->listDescs($response, 'priceClassDescriptions');

        $this->primeDescriptorResolutionContext(
            $scheduleDescs,
            $legDescs,
            $baggageAllowanceDescs,
            $fareComponentDescs,
            $brandFeatureDescs,
            $fareBrandDescs,
            $priceClassDescriptions,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $scheduleDescs
     * @param  list<array<string, mixed>>  $legDescs
     * @param  list<array<string, mixed>>  $baggageAllowanceDescs
     * @param  list<array<string, mixed>>  $fareComponentDescs
     * @param  list<array<string, mixed>>  $brandFeatureDescs
     * @param  list<array<string, mixed>>  $fareBrandDescs
     * @param  list<array<string, mixed>>  $priceClassDescriptions
     */
    protected function primeDescriptorResolutionContext(
        array $scheduleDescs,
        array $legDescs,
        array $baggageAllowanceDescs,
        array $fareComponentDescs,
        array $brandFeatureDescs = [],
        array $fareBrandDescs = [],
        array $priceClassDescriptions = [],
    ): void {
        $this->descriptorResolutionCtx = [
            'schedule' => $this->makeDescriptorResolutionSlice($scheduleDescs),
            'leg' => $this->makeDescriptorResolutionSlice($legDescs),
            'baggage' => $this->makeDescriptorResolutionSlice($baggageAllowanceDescs),
            'fare_component' => $this->makeDescriptorResolutionSlice($fareComponentDescs),
            'brand_feature' => $this->makeDescriptorResolutionSlice($brandFeatureDescs),
            'fare_brand' => $this->makeDescriptorResolutionSlice($fareBrandDescs),
            'price_class' => $this->makeDescriptorResolutionSlice($priceClassDescriptions),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $descList
     * @return array{rows: list<array<string, mixed>>, lookup: array<int, array<string, mixed>>, explicit: bool}
     */
    protected function makeDescriptorResolutionSlice(array $descList): array
    {
        $rows = array_values(array_filter($descList, fn ($row): bool => is_array($row)));
        $lookup = [];
        $explicit = false;
        foreach ($rows as $item) {
            foreach (['id', 'ref'] as $k) {
                if (! array_key_exists($k, $item)) {
                    continue;
                }
                $v = $item[$k];
                if (! is_numeric($v)) {
                    continue;
                }
                $explicit = true;
                $lookup[(int) $v] = $item;
            }
        }

        return [
            'rows' => $rows,
            'lookup' => $lookup,
            'explicit' => $explicit,
        ];
    }

    /**
     * Resolve a Sabre descriptor row by numeric id/ref for the current {@see normalize()} context.
     *
     * When any descriptor row exposes numeric `id` or `ref`, array-index / ref-1 fallback is disabled
     * so unresolved refs never pick the wrong row by position.
     *
     * @param  'schedule'|'leg'|'baggage'|'fare_component'|'brand_feature'|'fare_brand'|'price_class'  $kind
     */
    protected function resolveDescriptorKey(string $kind, int $ref): ?array
    {
        if ($ref < 0) {
            return null;
        }
        $slice = $this->descriptorResolutionCtx[$kind] ?? null;
        if ($slice === null) {
            return null;
        }
        /** @var array<int, array<string, mixed>> $lookup */
        $lookup = $slice['lookup'];
        /** @var list<array<string, mixed>> $rows */
        $rows = $slice['rows'];
        $explicit = $slice['explicit'];

        if (isset($lookup[$ref])) {
            return $lookup[$ref];
        }
        if ($explicit) {
            return null;
        }
        if (isset($rows[$ref]) && is_array($rows[$ref])) {
            return $rows[$ref];
        }
        $z = $ref - 1;
        if (isset($rows[$z]) && is_array($rows[$z])) {
            return $rows[$z];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lookup
     * @return list<int>
     */
    protected function sampleDescriptorLookupKeys(array $lookup, int $limit): array
    {
        $keys = array_keys($lookup);
        sort($keys, SORT_NUMERIC);

        return array_values(array_slice($keys, 0, $limit));
    }

    /**
     * Safe descriptor-resolution diagnostics (no raw Sabre payload).
     *
     * @param  array<string, mixed>  $itinerary
     * @param  list<FlightSegmentData>  $segmentModels
     * @param  array<string, mixed>  $routeInner
     * @return array<string, mixed>
     */
    protected function buildDescriptorRefProbe(array $itinerary, array $segmentModels, array $routeInner): array
    {
        $legLookup = $this->descriptorResolutionCtx['leg']['lookup'] ?? [];
        $schedLookup = $this->descriptorResolutionCtx['schedule']['lookup'] ?? [];

        $itineraryLegRefs = [];
        $scheduleRefsInLegOrder = [];
        $resolvedScheduleIds = [];
        $resolvedSegmentRoutes = [];

        $legs = $itinerary['legs'] ?? null;
        if (is_array($legs)) {
            foreach ($legs as $legWrap) {
                if (! is_array($legWrap)) {
                    continue;
                }
                $lr = $this->legDescriptorRefFromLegWrap($legWrap);
                $itineraryLegRefs[] = $lr >= 0 ? $lr : null;

                $legDesc = $lr >= 0 ? $this->resolveDescriptorKey('leg', $lr) : null;
                $schedules = null;
                if (is_array($legDesc)) {
                    $schedules = $legDesc['schedules'] ?? null;
                }
                if (! is_array($schedules) || $schedules === []) {
                    $schedules = $legWrap['schedules'] ?? null;
                }
                if (! is_array($schedules)) {
                    continue;
                }
                foreach ($schedules as $schedWrap) {
                    if (! is_array($schedWrap)) {
                        continue;
                    }
                    $sref = $this->scheduleRefFromLegScheduleWrap($schedWrap);
                    $scheduleRefsInLegOrder[] = $sref;
                    $schedRow = $sref >= 0 ? $this->resolveDescriptorKey('schedule', $sref) : null;
                    if (! is_array($schedRow)) {
                        $resolvedScheduleIds[] = null;
                    } else {
                        $sid = $schedRow['id'] ?? $schedRow['ref'] ?? null;
                        $resolvedScheduleIds[] = is_numeric($sid) ? (int) $sid : null;
                    }
                }
            }
        }

        foreach ($segmentModels as $sm) {
            if ($sm instanceof FlightSegmentData) {
                $resolvedSegmentRoutes[] = $sm->origin.'→'.$sm->destination;
            }
        }

        $segCount = (int) ($routeInner['segment_count'] ?? 0);
        $continuity = $segCount > 0 && (bool) ($routeInner['route_continuity_ok'] ?? false);

        return [
            'itinerary_leg_refs' => $itineraryLegRefs,
            'leg_desc_ids_available_sample' => $this->sampleDescriptorLookupKeys($legLookup, 12),
            'schedule_refs_in_leg_order' => $scheduleRefsInLegOrder,
            'schedule_desc_ids_available_sample' => $this->sampleDescriptorLookupKeys($schedLookup, 12),
            'resolved_schedule_ids' => $resolvedScheduleIds,
            'resolved_segment_routes' => $resolvedSegmentRoutes,
            'route_continuity_ok' => $continuity,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<FlightSegmentData>  $segmentModels
     * @param  array<string, mixed>  $routeInner
     */
    protected function maybeMergeDescriptorRefRejectProbe(array &$context, array $itinerary, array $segmentModels, array $routeInner): void
    {
        if ($this->descriptorRejectProbeEmitted >= 3) {
            return;
        }
        if ($this->descriptorResolutionCtx === null) {
            return;
        }
        $this->descriptorRejectProbeEmitted++;
        $context['descriptor_ref_probe'] = $this->buildDescriptorRefProbe($itinerary, $segmentModels, $routeInner);
    }

    /**
     * @param  array<string, mixed>  $legWrap
     */
    protected function legDescriptorRefFromLegWrap(array $legWrap): int
    {
        foreach (['id', 'ref'] as $k) {
            if (isset($legWrap[$k]) && is_numeric($legWrap[$k])) {
                return (int) $legWrap[$k];
            }
        }

        return -1;
    }

    protected function compareIsoSortKeys(string $ka, string $kb): int
    {
        if ($ka === '' && $kb !== '') {
            return 1;
        }
        if ($kb === '' && $ka !== '') {
            return -1;
        }

        return strcmp($ka, $kb);
    }

    /**
     * Departure instant for ordering resolved schedule descriptors within a leg.
     * Uses the search anchor date only (not prior-segment chaining); segment ISO strings
     * are still composed in {@see segmentFromScheduleDesc} using advancing state.
     */
    protected function scheduleDepartureSortKeyForOrdering(array $schedule, ?string $dateHintYmd): string
    {
        $depNode = $this->mergeScheduleEndpointFields($schedule, 'departure');
        $rawDep = $this->stringifyScheduleDateTime($depNode);

        return $this->composeScheduleEndpointIso($schedule, 'departure', $rawDep, $dateHintYmd);
    }

    /**
     * Stable tie-break when two schedules share the same departure sort key.
     */
    protected function scheduleSortTieBreakSignature(array $schedule): string
    {
        $depNode = $this->mergeScheduleEndpointFields($schedule, 'departure');
        $arrNode = $this->mergeScheduleEndpointFields($schedule, 'arrival');
        $o = $this->stringifyAirportCode($depNode['airport'] ?? null)
            ?: $this->stringifyAirportCode($depNode['airportCode'] ?? null);
        $d = $this->stringifyAirportCode($arrNode['airport'] ?? null)
            ?: $this->stringifyAirportCode($arrNode['airportCode'] ?? null);
        $fn = trim((string) (data_get($schedule, 'carrier.marketingFlightNumber')
            ?? data_get($schedule, 'carrier.flightNumber')
            ?? ''));

        return strtoupper($o).'|'.strtoupper($d).'|'.$fn;
    }

    /**
     * When every schedule has a full calendar departure, Sabre leg schedule declaration order is
     * authoritative; sorting by departure instant can move a mis-dated segment before an earlier
     * leg and break airport continuity. Time-only (clock-only) departures still need ordering.
     *
     * @param  list<array<string, mixed>>  $legSchedules
     */
    protected function legSchedulesNeedDepartureChronologySort(array $legSchedules): bool
    {
        foreach ($legSchedules as $sched) {
            if (! is_array($sched)) {
                continue;
            }
            $depNode = $this->mergeScheduleEndpointFields($sched, 'departure');
            $rawDep = $this->stringifyScheduleDateTime($depNode);
            if ($rawDep === '') {
                return true;
            }
            if (! $this->rawHasCalendarDate($rawDep)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stable identity for a resolved schedule row (dedupe only; does not drop unresolved refs).
     */
    protected function scheduleIdentitySignature(array $schedule): string
    {
        $ref = (string) ($schedule['ref'] ?? $schedule['id'] ?? '');
        $o = $this->scheduleEndpointAirportCode($schedule, 'departure');
        $d = $this->scheduleEndpointAirportCode($schedule, 'arrival');
        $fn = trim((string) (data_get($schedule, 'carrier.marketingFlightNumber')
            ?? data_get($schedule, 'carrier.flightNumber')
            ?? ''));

        return $ref.'|'.$o.'|'.$d.'|'.$fn;
    }

    /**
     * When leg-based RT assembly fails continuity, rebuild from all itinerary schedule edges.
     *
     * @param  array<string, mixed>  $itinerary
     * @param  list<FlightSegmentData>  $workingSegments
     * @param  array<string, mixed>  $routeInner
     * @return array{segment_models: list<FlightSegmentData>}|null
     */
    protected function maybeRebuildRoundTripSegmentsViaWholeItineraryRouteChains(
        array $itinerary,
        ?FlightSearchRequestData $searchRequest,
        array $workingSegments,
        array $routeInner,
        string $offerDiagnosticId,
    ): ?array {
        if ($searchRequest === null || trim($searchRequest->trip_type) !== 'round_trip') {
            return null;
        }
        if (($routeInner['route_continuity_ok'] ?? false) && $workingSegments !== []) {
            return null;
        }

        $reqO = strtoupper(trim($searchRequest->origin));
        $reqD = strtoupper(trim($searchRequest->destination));
        $legWraps = $itinerary['legs'] ?? null;
        $legWraps = is_array($legWraps)
            ? array_values(array_filter($legWraps, static fn (mixed $w): bool => is_array($w)))
            : [];
        $legRefCount = count($legWraps);
        $scheduleRefCount = $this->countScheduleRefsInLegWraps($legWraps);

        $collectFailReason = null;
        $edges = $this->collectWholeItineraryScheduleEdges($legWraps, $collectFailReason);
        $edgeCount = is_array($edges) ? count($edges) : 0;
        $routeSample = $this->sampleRouteEdgesFromEdgeList(is_array($edges) ? $edges : [], 12);
        if ($routeSample === [] && $workingSegments !== []) {
            $routeSample = $this->sampleSegmentRoutes($workingSegments, 12);
        }

        $this->logRtRouteChainFallbackAttempted(
            $offerDiagnosticId,
            $reqO,
            $reqD,
            $edgeCount,
            $routeSample,
            $scheduleRefCount,
            $legRefCount,
        );

        $rejectDiag = [
            'edge_count' => $edgeCount,
            'candidate_count' => null,
            'route_edges_sample' => $routeSample,
        ];
        $built = $this->buildRoundTripSegmentsFromWholeItineraryRouteChains(
            $itinerary,
            $searchRequest,
            $rejectDiag,
            $edges,
            $collectFailReason,
        );
        if ($built === null) {
            $this->logRtRouteChainFallbackRejected(
                $offerDiagnosticId,
                (string) ($rejectDiag['reason'] ?? 'no_partition'),
                $searchRequest,
                (int) ($rejectDiag['edge_count'] ?? $edgeCount),
                isset($rejectDiag['candidate_count']) ? (int) $rejectDiag['candidate_count'] : null,
                is_array($rejectDiag['route_edges_sample'] ?? null)
                    ? $rejectDiag['route_edges_sample']
                    : $routeSample,
                $rejectDiag,
            );

            return null;
        }

        $this->logRoundTripWholeItineraryRouteChainFallbackApplied(
            $built['segments'],
            $built['outbound_count'],
            $built['return_count'],
        );

        return ['segment_models' => $built['segments']];
    }

    /**
     * Partition all itinerary schedule edges into unique outbound (reqO→reqD) and return (reqD→reqO) chains.
     *
     * @param  array<string, mixed>  $itinerary
     * @return array{segments: list<FlightSegmentData>, outbound_count: int, return_count: int}|null
     */
    /**
     * @param  array<string, mixed>  $rejectDiag
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>|null  $preCollectedEdges
     */
    protected function buildRoundTripSegmentsFromWholeItineraryRouteChains(
        array $itinerary,
        ?FlightSearchRequestData $searchRequest,
        array &$rejectDiag = [],
        ?array $preCollectedEdges = null,
        ?string $preCollectFailReason = null,
    ): ?array {
        if ($searchRequest === null || trim($searchRequest->trip_type) !== 'round_trip') {
            $rejectDiag['reason'] = 'not_round_trip';

            return null;
        }

        $reqO = strtoupper(trim($searchRequest->origin));
        $reqD = strtoupper(trim($searchRequest->destination));
        if ($reqO === '' || $reqD === '' || $reqO === $reqD) {
            $rejectDiag['reason'] = 'no_request_origin_destination';
            $rejectDiag['edge_count'] = 0;

            return null;
        }

        $legWraps = $itinerary['legs'] ?? null;
        if (! is_array($legWraps) || $legWraps === []) {
            $rejectDiag['reason'] = 'no_partition';
            $rejectDiag['edge_count'] = 0;

            return null;
        }

        $collectFailReason = $preCollectFailReason;
        $edges = $preCollectedEdges ?? $this->collectWholeItineraryScheduleEdges($legWraps, $collectFailReason);
        if ($edges === null) {
            $rejectDiag['reason'] = $collectFailReason ?? 'unresolved_schedule_ref';
            $rejectDiag['edge_count'] = 0;
            $rejectDiag['route_edges_sample'] = [];

            return null;
        }

        $edgeCount = count($edges);
        $rejectDiag['edge_count'] = $edgeCount;
        $rejectDiag['route_edges_sample'] = $this->sampleRouteEdgesFromEdgeList($edges, 12);
        if ($edgeCount === 0) {
            $rejectDiag['reason'] = 'no_partition';

            return null;
        }
        if ($edgeCount > self::MAX_RT_WHOLE_ITINERARY_SCHEDULE_EDGES) {
            $rejectDiag['reason'] = 'too_many_edges';

            return null;
        }

        $candidateCount = 0;
        $partitionEndpointCheck = null;
        $partition = $this->findUniqueRoundTripOutboundReturnPartition(
            $edges,
            $reqO,
            $reqD,
            $candidateCount,
            $partitionEndpointCheck,
        );
        $rejectDiag['candidate_count'] = $candidateCount;
        if ($partition === null) {
            if ($candidateCount > 1) {
                $rejectDiag['reason'] = 'ambiguous_partition';
            } elseif ($candidateCount === 1) {
                $rejectDiag['reason'] = 'invalid_endpoints';
                if (is_array($partitionEndpointCheck)) {
                    $rejectDiag = array_merge($rejectDiag, $partitionEndpointCheck);
                }
            } elseif ($this->wholeItineraryEdgeGraphHasDisconnectedComponent($edges, $reqO)) {
                $rejectDiag['reason'] = 'unused_edges';
            } else {
                $rejectDiag['reason'] = 'no_partition';
            }

            return null;
        }

        $departYmd = $this->validYmd($searchRequest->departure_date);
        $returnYmd = $this->validYmd($searchRequest->return_date);

        $outboundCount = count($partition['outbound']);
        $returnCount = count($partition['return']);
        if ($outboundCount < 1 || $returnCount < 1) {
            $rejectDiag['reason'] = 'invalid_endpoints';
            $rejectDiag = array_merge(
                $rejectDiag,
                $this->validateRoundTripFallbackEdgePartition($partition['outbound'], $partition['return'], $reqO, $reqD),
            );

            return null;
        }

        $segments = [];
        $outboundScheds = array_map(static fn (array $e): array => $e['sched'], $partition['outbound']);
        $returnScheds = array_map(static fn (array $e): array => $e['sched'], $partition['return']);

        // Partition order is already a continuous route chain; do not chronology-sort (would break hub turns).
        $state = ['prev_arrival' => null];
        foreach ($outboundScheds as $sched) {
            $segments[] = $this->segmentFromScheduleDesc($sched, $departYmd, $state);
        }

        $state = ['prev_arrival' => null];
        foreach ($returnScheds as $sched) {
            $segments[] = $this->segmentFromScheduleDesc($sched, $returnYmd, $state);
        }

        $journeyCheck = $this->validateRoundTripFallbackSegmentJourney($segments, $outboundCount, $reqO, $reqD);
        if (! $journeyCheck['ok']) {
            $rejectDiag['reason'] = 'invalid_endpoints';
            $rejectDiag = array_merge($rejectDiag, $journeyCheck);

            return null;
        }

        return [
            'segments' => $segments,
            'outbound_count' => $outboundCount,
            'return_count' => $returnCount,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $legWraps
     * @return list<array{sched: array<string, mixed>, origin: string, dest: string}>|null
     */
    protected function collectWholeItineraryScheduleEdges(array $legWraps, ?string &$failReason = null): ?array
    {
        $failReason = null;
        $edges = [];
        $seenSig = [];

        foreach ($legWraps as $legWrap) {
            if (! is_array($legWrap)) {
                continue;
            }
            $legRef = $this->legDescriptorRefFromLegWrap($legWrap);
            $legDesc = $legRef >= 0 ? $this->resolveDescriptorKey('leg', $legRef) : null;
            $schedules = null;
            if (is_array($legDesc)) {
                $schedules = $legDesc['schedules'] ?? null;
            }
            if (! is_array($schedules) || $schedules === []) {
                $schedules = $legWrap['schedules'] ?? null;
            }
            if (! is_array($schedules)) {
                continue;
            }
            foreach ($schedules as $schedWrap) {
                if (! is_array($schedWrap)) {
                    continue;
                }
                $sref = $this->scheduleRefFromLegScheduleWrap($schedWrap);
                $sched = $this->resolveDescriptorKey('schedule', $sref);
                if ($sched === null) {
                    $failReason = 'unresolved_schedule_ref';

                    return null;
                }
                $sig = $this->scheduleIdentitySignature($sched);
                if (isset($seenSig[$sig])) {
                    $failReason = 'duplicate_schedule_identity';

                    return null;
                }
                $seenSig[$sig] = true;
                $origin = $this->scheduleEndpointAirportCode($sched, 'departure');
                $dest = $this->scheduleEndpointAirportCode($sched, 'arrival');
                if ($origin === '' || $dest === '') {
                    $failReason = 'unresolved_schedule_ref';

                    return null;
                }
                $edges[] = [
                    'sched' => $sched,
                    'origin' => $origin,
                    'dest' => $dest,
                ];
            }
        }

        return $edges;
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $edges
     * @return array{outbound: list<array{sched: array<string, mixed>, origin: string, dest: string}>, return: list<array{sched: array<string, mixed>, origin: string, dest: string}>}|null
     */
    protected function findUniqueRoundTripOutboundReturnPartition(
        array $edges,
        string $reqO,
        string $reqD,
        ?int &$candidateCount = null,
        ?array &$failedEndpointCheck = null,
    ): ?array {
        $failedEndpointCheck = null;
        $solutions = [];
        $seenKeys = [];
        $n = count($edges);
        $this->dfsRoundTripOutboundPartition(
            $edges,
            $reqO,
            $reqD,
            $reqO,
            [],
            array_fill(0, $n, false),
            $solutions,
            $seenKeys,
        );

        $candidateCount = count($solutions);

        if (count($solutions) !== 1) {
            return null;
        }

        $solution = $solutions[0];
        $check = $this->validateRoundTripFallbackEdgePartition(
            $solution['outbound'],
            $solution['return'],
            $reqO,
            $reqD,
        );
        if (! $check['ok']) {
            $failedEndpointCheck = $check;

            return null;
        }

        return $solution;
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $outbound
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $return
     * @return array{
     *   ok: bool,
     *   outbound_route_sample: list<string>,
     *   return_route_sample: list<string>,
     *   combined_route_sample: list<string>,
     *   endpoint_check: array<string, string>
     * }
     */
    protected function validateRoundTripFallbackEdgePartition(
        array $outbound,
        array $return,
        string $reqO,
        string $reqD,
    ): array {
        $reqO = strtoupper(trim($reqO));
        $reqD = strtoupper(trim($reqD));
        $outSample = $this->sampleRouteEdgesFromEdgeList($outbound, 12);
        $retSample = $this->sampleRouteEdgesFromEdgeList($return, 12);
        $combined = array_merge($outSample, $retSample);

        $outFirst = $outbound[0]['origin'] ?? '';
        $outLast = $outbound === [] ? '' : ($outbound[array_key_last($outbound)]['dest'] ?? '');
        $retFirst = $return[0]['origin'] ?? '';
        $retLast = $return === [] ? '' : ($return[array_key_last($return)]['dest'] ?? '');

        $endpointCheck = [
            'outbound_first' => $outFirst,
            'outbound_last' => $outLast,
            'return_first' => $retFirst,
            'return_last' => $retLast,
            'combined_first' => $outFirst,
            'combined_last' => $retLast,
            'requested_origin' => $reqO,
            'requested_destination' => $reqD,
        ];

        $ok = count($outbound) >= 1
            && count($return) >= 1
            && $outFirst === $reqO
            && $outLast === $reqD
            && $retFirst === $reqD
            && $retLast === $reqO
            && $this->scheduleEdgeChainRouteContinuous($outbound)
            && $this->scheduleEdgeChainRouteContinuous($return);

        return [
            'ok' => $ok,
            'outbound_route_sample' => $outSample,
            'return_route_sample' => $retSample,
            'combined_route_sample' => array_slice($combined, 0, 12),
            'endpoint_check' => $endpointCheck,
        ];
    }

    /**
     * Validates fallback-built segments: per-direction continuity only (turnaround at reqD is not a layover link).
     *
     * @param  list<FlightSegmentData>  $segments
     * @return array{
     *   ok: bool,
     *   outbound_route_sample: list<string>,
     *   return_route_sample: list<string>,
     *   combined_route_sample: list<string>,
     *   endpoint_check: array<string, string>
     * }
     */
    protected function validateRoundTripFallbackSegmentJourney(
        array $segments,
        int $outboundCount,
        string $reqO,
        string $reqD,
    ): array {
        $reqO = strtoupper(trim($reqO));
        $reqD = strtoupper(trim($reqD));
        $n = count($segments);
        $outboundCount = max(0, min($outboundCount, $n));
        $returnCount = $n - $outboundCount;

        $outSample = [];
        $retSample = [];
        $combined = [];
        foreach ($segments as $i => $sm) {
            if (! $sm instanceof FlightSegmentData) {
                continue;
            }
            $route = strtoupper(trim($sm->origin)).'→'.strtoupper(trim($sm->destination));
            $combined[] = $route;
            if ($i < $outboundCount) {
                $outSample[] = $route;
            } else {
                $retSample[] = $route;
            }
        }

        $outFirst = $outboundCount > 0 && isset($segments[0]) && $segments[0] instanceof FlightSegmentData
            ? strtoupper(trim($segments[0]->origin)) : '';
        $outLast = $outboundCount > 0 && isset($segments[$outboundCount - 1]) && $segments[$outboundCount - 1] instanceof FlightSegmentData
            ? strtoupper(trim($segments[$outboundCount - 1]->destination)) : '';
        $retFirst = $returnCount > 0 && isset($segments[$outboundCount]) && $segments[$outboundCount] instanceof FlightSegmentData
            ? strtoupper(trim($segments[$outboundCount]->origin)) : '';
        $last = $n > 0 ? $segments[$n - 1] : null;
        $retLast = $last instanceof FlightSegmentData ? strtoupper(trim($last->destination)) : '';

        $endpointCheck = [
            'outbound_first' => $outFirst,
            'outbound_last' => $outLast,
            'return_first' => $retFirst,
            'return_last' => $retLast,
            'combined_first' => $outFirst,
            'combined_last' => $retLast,
            'requested_origin' => $reqO,
            'requested_destination' => $reqD,
        ];

        $ok = $outboundCount >= 1
            && $returnCount >= 1
            && $outFirst === $reqO
            && $outLast === $reqD
            && $retFirst === $reqD
            && $retLast === $reqO
            && $this->segmentModelsSliceRouteContinuous($segments, 0, $outboundCount)
            && $this->segmentModelsSliceRouteContinuous($segments, $outboundCount, $returnCount)
            && $this->itineraryTouchesAirport($segments, $reqD);

        return [
            'ok' => $ok,
            'outbound_route_sample' => array_slice($outSample, 0, 12),
            'return_route_sample' => array_slice($retSample, 0, 12),
            'combined_route_sample' => array_slice($combined, 0, 12),
            'endpoint_check' => $endpointCheck,
        ];
    }

    /**
     * @param  list<FlightSegmentData>  $segments
     */
    protected function segmentModelsSliceRouteContinuous(array $segments, int $start, int $length): bool
    {
        if ($length <= 1) {
            return $length === 1;
        }
        $end = $start + $length - 1;
        if ($end >= count($segments)) {
            return false;
        }
        for ($i = $start; $i < $end; $i++) {
            $a = $segments[$i];
            $b = $segments[$i + 1];
            if (! $a instanceof FlightSegmentData || ! $b instanceof FlightSegmentData) {
                return false;
            }
            if (strtoupper(trim($a->destination)) !== strtoupper(trim($b->origin))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $edges
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $outChain
     * @param  list<bool>  $used
     * @param  list<array{outbound: list<array{sched: array<string, mixed>, origin: string, dest: string}>, return: list<array{sched: array<string, mixed>, origin: string, dest: string}>}>  $solutions
     * @param  array<string, true>  $seenKeys
     */
    protected function dfsRoundTripOutboundPartition(
        array $edges,
        string $at,
        string $reqD,
        string $reqO,
        array $outChain,
        array $used,
        array &$solutions,
        array &$seenKeys,
    ): void {
        $n = count($edges);

        if ($at === $reqD && $outChain !== []) {
            $returnChains = $this->findAllReturnChainsUsingRemainingEdges($edges, $used, $reqD, $reqO);
            foreach ($returnChains as $retChain) {
                $outKey = implode('>', array_map(static fn (array $e): string => $e['origin'].'→'.$e['dest'], $outChain));
                $retKey = implode('>', array_map(static fn (array $e): string => $e['origin'].'→'.$e['dest'], $retChain));
                $key = $outKey.'||'.$retKey;
                if (isset($seenKeys[$key])) {
                    continue;
                }
                $seenKeys[$key] = true;
                $solutions[] = [
                    'outbound' => $outChain,
                    'return' => $retChain,
                ];
            }

            return;
        }

        for ($i = 0; $i < $n; $i++) {
            if ($used[$i]) {
                continue;
            }
            $edge = $edges[$i];
            if ($edge['origin'] !== $at) {
                continue;
            }
            $used[$i] = true;
            $this->dfsRoundTripOutboundPartition(
                $edges,
                $edge['dest'],
                $reqD,
                $reqO,
                [...$outChain, $edge],
                $used,
                $solutions,
                $seenKeys,
            );
            $used[$i] = false;
        }
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $edges
     * @param  list<bool>  $outboundUsed
     * @return list<list<array{sched: array<string, mixed>, origin: string, dest: string}>>
     */
    protected function findAllReturnChainsUsingRemainingEdges(
        array $edges,
        array $outboundUsed,
        string $reqD,
        string $reqO,
    ): array {
        $remaining = [];
        foreach ($edges as $i => $edge) {
            if (! $outboundUsed[$i]) {
                $remaining[] = $edge;
            }
        }
        $nRem = count($remaining);
        if ($nRem === 0) {
            return [];
        }

        $found = [];
        $this->dfsReturnChainPartition($remaining, $reqD, $reqO, [], array_fill(0, $nRem, false), $found);

        return $found;
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $remaining
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $chain
     * @param  list<bool>  $used
     * @param  list<list<array{sched: array<string, mixed>, origin: string, dest: string}>>  $found
     */
    protected function dfsReturnChainPartition(
        array $remaining,
        string $at,
        string $reqO,
        array $chain,
        array $used,
        array &$found,
    ): void {
        $nRem = count($remaining);
        if ($at === $reqO && count($chain) === $nRem) {
            $found[] = $chain;

            return;
        }
        if (count($chain) === $nRem) {
            return;
        }

        for ($i = 0; $i < $nRem; $i++) {
            if ($used[$i]) {
                continue;
            }
            $edge = $remaining[$i];
            if ($edge['origin'] !== $at) {
                continue;
            }
            $used[$i] = true;
            $this->dfsReturnChainPartition(
                $remaining,
                $edge['dest'],
                $reqO,
                [...$chain, $edge],
                $used,
                $found,
            );
            $used[$i] = false;
        }
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $chain
     */
    protected function scheduleEdgeChainRouteContinuous(array $chain): bool
    {
        $n = count($chain);
        if ($n <= 1) {
            return true;
        }
        for ($i = 0; $i < $n - 1; $i++) {
            if ($chain[$i]['dest'] !== $chain[$i + 1]['origin']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $edges
     */
    protected function sampleRouteEdgesFromEdgeList(array $edges, int $limit = 12): array
    {
        $out = [];
        foreach (array_slice($edges, 0, max(0, $limit)) as $edge) {
            $out[] = $edge['origin'].'→'.$edge['dest'];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $legWraps
     */
    protected function countScheduleRefsInLegWraps(array $legWraps): int
    {
        $count = 0;
        foreach ($legWraps as $legWrap) {
            if (! is_array($legWrap)) {
                continue;
            }
            $legRef = $this->legDescriptorRefFromLegWrap($legWrap);
            $legDesc = $legRef >= 0 ? $this->resolveDescriptorKey('leg', $legRef) : null;
            $schedules = null;
            if (is_array($legDesc)) {
                $schedules = $legDesc['schedules'] ?? null;
            }
            if (! is_array($schedules) || $schedules === []) {
                $schedules = $legWrap['schedules'] ?? null;
            }
            if (! is_array($schedules)) {
                continue;
            }
            foreach ($schedules as $schedWrap) {
                if (is_array($schedWrap)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $edges
     */
    protected function wholeItineraryEdgeGraphHasDisconnectedComponent(array $edges, string $reqO): bool
    {
        if ($edges === []) {
            return false;
        }

        $adj = [];
        foreach ($edges as $edge) {
            $o = $edge['origin'];
            $d = $edge['dest'];
            $adj[$o][] = $d;
            $adj[$d][] = $o;
        }

        $visited = [];
        $stack = [$reqO];
        while ($stack !== []) {
            $airport = array_pop($stack);
            if (isset($visited[$airport])) {
                continue;
            }
            $visited[$airport] = true;
            foreach ($adj[$airport] ?? [] as $next) {
                if (! isset($visited[$next])) {
                    $stack[] = $next;
                }
            }
        }

        foreach ($edges as $edge) {
            if (! isset($visited[$edge['origin']]) || ! isset($visited[$edge['dest']])) {
                return true;
            }
        }

        return false;
    }

    protected function logRtRouteChainFallbackAttempted(
        string $offerId,
        string $reqOrigin,
        string $reqDestination,
        int $edgeCount,
        array $routeEdgesSample,
        int $scheduleRefCount,
        int $itineraryLegRefCount,
    ): void {
        Log::info('sabre.normalizer.rt_route_chain_fallback_attempted', [
            'provider' => 'sabre',
            'offer_id' => $offerId,
            'req_origin' => $reqOrigin,
            'req_destination' => $reqDestination,
            'edge_count' => $edgeCount,
            'route_edges_sample' => array_slice($routeEdgesSample, 0, 12),
            'schedule_ref_count' => $scheduleRefCount,
            'itinerary_leg_ref_count' => $itineraryLegRefCount,
        ]);
    }

    /**
     * @param  list<string>  $routeEdgesSample
     * @param  array<string, mixed>  $rejectDiag
     */
    protected function logRtRouteChainFallbackRejected(
        string $offerId,
        string $reason,
        ?FlightSearchRequestData $searchRequest,
        int $edgeCount,
        ?int $candidateCount,
        array $routeEdgesSample,
        array $rejectDiag = [],
    ): void {
        $payload = [
            'provider' => 'sabre',
            'offer_id' => $offerId,
            'reason' => $reason,
            'req_origin' => $searchRequest !== null ? strtoupper(trim($searchRequest->origin)) : '',
            'req_destination' => $searchRequest !== null ? strtoupper(trim($searchRequest->destination)) : '',
            'edge_count' => $edgeCount,
            'route_edges_sample' => array_slice($routeEdgesSample, 0, 12),
        ];
        if ($candidateCount !== null) {
            $payload['candidate_count'] = $candidateCount;
        }
        foreach (['outbound_route_sample', 'return_route_sample', 'combined_route_sample', 'endpoint_check'] as $k) {
            if (isset($rejectDiag[$k]) && is_array($rejectDiag[$k]) && $rejectDiag[$k] !== []) {
                $payload[$k] = $rejectDiag[$k];
            }
        }

        Log::warning('sabre.normalizer.rt_route_chain_fallback_rejected', $payload);
    }

    /**
     * @param  list<FlightSegmentData>  $segments
     */
    protected function logRoundTripWholeItineraryRouteChainFallbackApplied(
        array $segments,
        int $outboundCount,
        int $returnCount,
    ): void {
        $sample = [];
        foreach (array_slice($segments, 0, 8) as $sm) {
            if ($sm instanceof FlightSegmentData) {
                $sample[] = strtoupper(trim($sm->origin)).'→'.strtoupper(trim($sm->destination));
            }
        }

        Log::info('sabre.normalizer.rt_route_chain_fallback_applied', [
            'provider' => 'sabre',
            'segment_count' => count($segments),
            'outbound_count' => $outboundCount,
            'return_count' => $returnCount,
            'route_chain_sample' => $sample,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $legSchedules
     */
    protected function resolvedSchedulesRouteContinuous(array $legSchedules): bool
    {
        $n = count($legSchedules);
        if ($n <= 1) {
            return true;
        }
        for ($i = 0; $i < $n - 1; $i++) {
            $d = $this->scheduleEndpointAirportCode($legSchedules[$i], 'arrival');
            $nextO = $this->scheduleEndpointAirportCode($legSchedules[$i + 1], 'departure');
            if ($d === '' || $nextO === '' || $d !== $nextO) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reorder schedules inside one leg into a continuous airport chain when Sabre declaration order is wrong.
     * Prefers a unique chain starting at $expectedLegStart; otherwise a unique chain overall; else keeps API order.
     *
     * @param  list<array<string, mixed>>  $legSchedules
     * @return list<array<string, mixed>>
     */
    protected function orderResolvedSchedulesForLegRouteChain(array $legSchedules, ?string $expectedLegStart): array
    {
        $legSchedules = array_values($legSchedules);
        if (count($legSchedules) <= 1) {
            return $legSchedules;
        }
        if ($this->resolvedSchedulesRouteContinuous($legSchedules)) {
            return $legSchedules;
        }

        $edges = [];
        $seenSig = [];
        foreach ($legSchedules as $sched) {
            if (! is_array($sched)) {
                continue;
            }
            $origin = $this->scheduleEndpointAirportCode($sched, 'departure');
            $dest = $this->scheduleEndpointAirportCode($sched, 'arrival');
            if ($origin === '' || $dest === '') {
                return $legSchedules;
            }
            $sig = $this->scheduleIdentitySignature($sched);
            if (isset($seenSig[$sig])) {
                return $legSchedules;
            }
            $seenSig[$sig] = true;
            $edges[] = [
                'sched' => $sched,
                'origin' => $origin,
                'dest' => $dest,
            ];
        }
        if (count($edges) <= 1) {
            return $legSchedules;
        }

        $chains = $this->findResolvedScheduleRouteChains($edges);
        if ($chains === []) {
            return $legSchedules;
        }

        $expected = $expectedLegStart !== null ? strtoupper(trim($expectedLegStart)) : '';
        $candidates = $chains;
        if ($expected !== '') {
            $filtered = [];
            foreach ($chains as $chain) {
                $first = $chain[0]['origin'] ?? '';
                if ($first === $expected) {
                    $filtered[] = $chain;
                }
            }
            if (count($filtered) === 1) {
                $candidates = $filtered;
            } elseif (count($filtered) > 1) {
                $candidates = $filtered;
            } else {
                return $legSchedules;
            }
        }

        if (count($candidates) !== 1) {
            return $legSchedules;
        }

        $ordered = [];
        foreach ($candidates[0] as $edge) {
            $ordered[] = $edge['sched'];
        }

        return $ordered;
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $edges
     * @return list<list<array{sched: array<string, mixed>, origin: string, dest: string}>>
     */
    protected function findResolvedScheduleRouteChains(array $edges): array
    {
        $n = count($edges);
        if ($n === 0) {
            return [];
        }
        if ($n > 8) {
            return [];
        }

        $starts = [];
        foreach ($edges as $edge) {
            $starts[$edge['origin']] = true;
        }

        $found = [];
        $seenKeys = [];

        foreach (array_keys($starts) as $startAirport) {
            $this->collectScheduleRouteChainsFrom(
                $edges,
                $startAirport,
                [],
                array_fill(0, $n, false),
                $found,
                $seenKeys,
            );
        }

        return $found;
    }

    /**
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $edges
     * @param  list<array{sched: array<string, mixed>, origin: string, dest: string}>  $chain
     * @param  list<bool>  $used
     * @param  list<list<array{sched: array<string, mixed>, origin: string, dest: string}>>  $found
     * @param  array<string, true>  $seenKeys
     */
    protected function collectScheduleRouteChainsFrom(
        array $edges,
        string $currentAirport,
        array $chain,
        array $used,
        array &$found,
        array &$seenKeys,
    ): void {
        $n = count($edges);
        if (count($chain) === $n) {
            $key = implode('>', array_map(
                fn (array $e): string => $e['origin'].'→'.$e['dest'],
                $chain
            ));
            if (! isset($seenKeys[$key])) {
                $seenKeys[$key] = true;
                $found[] = $chain;
            }

            return;
        }

        for ($i = 0; $i < $n; $i++) {
            if ($used[$i]) {
                continue;
            }
            $edge = $edges[$i];
            if ($chain === [] || $edge['origin'] === $currentAirport) {
                $nextUsed = $used;
                $nextUsed[$i] = true;
                $nextChain = [...$chain, $edge];
                $this->collectScheduleRouteChainsFrom(
                    $edges,
                    $edge['dest'],
                    $nextChain,
                    $nextUsed,
                    $found,
                    $seenKeys,
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $legSchedules
     * @return list<array<string, mixed>>
     */
    protected function sortResolvedSchedulesForLegChronology(array $legSchedules, ?string $anchorYmd): array
    {
        if (count($legSchedules) <= 1) {
            return $legSchedules;
        }
        if (! $this->legSchedulesNeedDepartureChronologySort($legSchedules)) {
            return array_values($legSchedules);
        }
        $hint = $this->validYmd($anchorYmd);
        usort($legSchedules, function (array $a, array $b) use ($hint): int {
            $ka = $this->scheduleDepartureSortKeyForOrdering($a, $hint);
            $kb = $this->scheduleDepartureSortKeyForOrdering($b, $hint);
            $c = $this->compareIsoSortKeys($ka, $kb);
            if ($c !== 0) {
                return $c;
            }

            return strcmp($this->scheduleSortTieBreakSignature($a), $this->scheduleSortTieBreakSignature($b));
        });

        return array_values($legSchedules);
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @return list<FlightSegmentData>
     */
    protected function buildSegmentsForItinerary(
        array $itinerary,
        ?FlightSearchRequestData $searchRequest,
    ): array {
        $segments = [];
        $itineraryLegs = $itinerary['legs'] ?? null;
        if (! is_array($itineraryLegs) || $itineraryLegs === []) {
            return [];
        }

        $legEntries = $this->itineraryLegEntriesForSearchRequest($itineraryLegs, $searchRequest);

        foreach ($legEntries as $legEntry) {
            $legWrap = $legEntry['wrap'];
            $expectedLegStart = $legEntry['expected_leg_start'];
            $legAnchorYmd = $this->validYmd($legEntry['leg_anchor_ymd'] ?? null);
            $state = [
                'prev_arrival' => null,
            ];
            if (! is_array($legWrap)) {
                continue;
            }

            $legRef = $this->legDescriptorRefFromLegWrap($legWrap);
            $legDesc = $legRef >= 0 ? $this->resolveDescriptorKey('leg', $legRef) : null;
            $schedules = null;
            if (is_array($legDesc)) {
                $schedules = $legDesc['schedules'] ?? null;
            }
            if (! is_array($schedules) || $schedules === []) {
                $schedules = $legWrap['schedules'] ?? null;
            }
            if (! is_array($schedules)) {
                continue;
            }
            $legSchedules = [];
            foreach ($schedules as $schedWrap) {
                if (! is_array($schedWrap)) {
                    continue;
                }
                $sref = $this->scheduleRefFromLegScheduleWrap($schedWrap);
                $sched = $this->resolveDescriptorKey('schedule', $sref);
                if ($sched === null) {
                    continue;
                }
                $legSchedules[] = $sched;
            }
            $legSchedules = $this->orderResolvedSchedulesForLegRouteChain($legSchedules, $expectedLegStart);
            foreach ($this->sortResolvedSchedulesForLegChronology($legSchedules, $legAnchorYmd) as $sched) {
                $segments[] = $this->segmentFromScheduleDesc($sched, $legAnchorYmd, $state);
            }
        }

        return $segments;
    }

    /**
     * @return array{wrap: array<string, mixed>, expected_leg_start: ?string, leg_anchor_ymd: ?string, leg_role: string}
     */
    protected function makeLegEntry(array $wrap, ?string $expectedLegStart, ?string $legAnchorYmd, string $legRole): array
    {
        $anchor = $this->validYmd($legAnchorYmd);

        return [
            'wrap' => $wrap,
            'expected_leg_start' => $expectedLegStart,
            'leg_anchor_ymd' => $anchor,
            'leg_role' => $legRole,
        ];
    }

    /**
     * Itinerary legs in travel order with optional expected first airport per leg (for in-leg route-chain ordering).
     *
     * @param  list<array<string, mixed>>  $legWraps
     * @return list<array{wrap: array<string, mixed>, expected_leg_start: ?string, leg_anchor_ymd: ?string, leg_role: string}>
     */
    protected function itineraryLegEntriesForSearchRequest(array $legWraps, ?FlightSearchRequestData $searchRequest): array
    {
        $legWraps = array_values(array_filter($legWraps, static fn (mixed $w): bool => is_array($w)));
        if ($legWraps === []) {
            return [];
        }

        $reqO = $searchRequest !== null ? strtoupper(trim($searchRequest->origin)) : '';
        $reqD = $searchRequest !== null ? strtoupper(trim($searchRequest->destination)) : '';
        $tripType = $searchRequest !== null ? trim($searchRequest->trip_type) : '';

        if ($tripType === 'round_trip' && count($legWraps) >= 2 && $reqO !== '' && $reqD !== '') {
            $anchorYmd = $this->validYmd($searchRequest->departure_date);
            $returnYmd = $this->validYmd($searchRequest->return_date);

            $meta = [];
            foreach ($legWraps as $idx => $legWrap) {
                $summary = $this->legWrapTravelEndpointSummary($legWrap, $anchorYmd);
                if ($summary === null) {
                    return $this->plainLegEntries($legWraps, $reqO, $tripType, $searchRequest);
                }
                $meta[] = ['idx' => $idx, 'wrap' => $legWrap, ...$summary];
            }

            $outboundIdx = [];
            $returnIdx = [];
            foreach ($meta as $row) {
                $start = $row['origin'];
                $end = $row['destination'];
                if ($start === $reqO) {
                    $outboundIdx[] = $row['idx'];
                }
                if ($start === $reqD && $end === $reqO) {
                    $returnIdx[] = $row['idx'];
                } elseif ($start === $reqD && $returnIdx === []) {
                    $returnIdx[] = $row['idx'];
                }
            }

            if (count($outboundIdx) === 1 && count($returnIdx) === 1 && $outboundIdx[0] !== $returnIdx[0]) {
                $entries = [
                    $this->makeLegEntry($legWraps[$outboundIdx[0]], $reqO, $anchorYmd, 'outbound'),
                    $this->makeLegEntry($legWraps[$returnIdx[0]], $reqD, $returnYmd, 'return'),
                ];
                $placed = [$outboundIdx[0] => true, $returnIdx[0] => true];
                $remaining = [];
                foreach ($meta as $row) {
                    if (isset($placed[$row['idx']])) {
                        continue;
                    }
                    $remaining[] = $row;
                }
                if ($remaining !== []) {
                    usort($remaining, function (array $a, array $b) use ($returnYmd, $anchorYmd): int {
                        $ka = $this->legDepartureSortPreferenceKey($a, $anchorYmd, $returnYmd);
                        $kb = $this->legDepartureSortPreferenceKey($b, $anchorYmd, $returnYmd);

                        return $this->compareIsoSortKeys($ka, $kb);
                    });
                    foreach ($remaining as $row) {
                        $entries[] = $this->makeLegEntry($legWraps[$row['idx']], null, null, 'unknown');
                    }
                }

                return $entries;
            }
        }

        return $this->plainLegEntries($legWraps, $reqO, $tripType, $searchRequest);
    }

    /**
     * @param  list<array<string, mixed>>  $legWraps
     * @return list<array{wrap: array<string, mixed>, expected_leg_start: ?string, leg_anchor_ymd: ?string, leg_role: string}>
     */
    protected function plainLegEntries(array $legWraps, string $reqO, string $tripType, ?FlightSearchRequestData $searchRequest = null): array
    {
        $departAnchor = $this->validYmd($searchRequest?->departure_date);
        $entries = [];
        foreach ($legWraps as $idx => $wrap) {
            $expected = null;
            $anchor = null;
            $role = 'unknown';
            if ($tripType === 'one_way' && $reqO !== '' && $idx === 0) {
                $expected = $reqO;
                $anchor = $departAnchor;
                $role = 'outbound';
            }
            $entries[] = $this->makeLegEntry($wrap, $expected, $anchor, $role);
        }

        return $entries;
    }

    /**
     * First/last airport and departure sort key for a leg wrap (resolved schedule descriptors only).
     *
     * @param  array<string, mixed>  $legWrap
     * @return array{origin: string, destination: string, first_departure_sort_key: string}|null
     */
    protected function legWrapTravelEndpointSummary(array $legWrap, ?string $anchorYmd): ?array
    {
        $legRef = $this->legDescriptorRefFromLegWrap($legWrap);
        $legDesc = $legRef >= 0 ? $this->resolveDescriptorKey('leg', $legRef) : null;
        $schedules = null;
        if (is_array($legDesc)) {
            $schedules = $legDesc['schedules'] ?? null;
        }
        if (! is_array($schedules) || $schedules === []) {
            $schedules = $legWrap['schedules'] ?? null;
        }
        if (! is_array($schedules) || $schedules === []) {
            return null;
        }

        $legSchedules = [];
        foreach ($schedules as $schedWrap) {
            if (! is_array($schedWrap)) {
                continue;
            }
            $sref = $this->scheduleRefFromLegScheduleWrap($schedWrap);
            $sched = $this->resolveDescriptorKey('schedule', $sref);
            if ($sched !== null) {
                $legSchedules[] = $sched;
            }
        }
        if ($legSchedules === []) {
            return null;
        }

        $ordered = $this->orderResolvedSchedulesForLegRouteChain($legSchedules, null);
        $ordered = $this->sortResolvedSchedulesForLegChronology($ordered, $anchorYmd);
        $first = $ordered[0];
        $last = $ordered[array_key_last($ordered)];
        $origin = $this->scheduleEndpointAirportCode($first, 'departure');
        $destination = $this->scheduleEndpointAirportCode($last, 'arrival');
        if ($origin === '' || $destination === '') {
            return null;
        }

        return [
            'origin' => $origin,
            'destination' => $destination,
            'first_departure_sort_key' => $this->scheduleDepartureSortKeyForOrdering($first, $anchorYmd),
        ];
    }

    /**
     * @param  array{origin: string, destination: string, first_departure_sort_key: string}  $legSummary
     */
    protected function legDepartureSortPreferenceKey(array $legSummary, ?string $departYmd, ?string $returnYmd): string
    {
        $key = (string) ($legSummary['first_departure_sort_key'] ?? '');
        if ($key !== '') {
            return $key;
        }

        return $departYmd ?? $returnYmd ?? '';
    }

    protected function scheduleEndpointAirportCode(array $schedule, string $endpoint): string
    {
        $node = $this->mergeScheduleEndpointFields($schedule, $endpoint);

        return $this->stringifyAirportCode($node['airport'] ?? null)
            ?: $this->stringifyAirportCode($node['airportCode'] ?? null);
    }

    /**
     * Schedule reference on a leg descriptor's schedule slot (BFM v4).
     *
     * @param  array<string, mixed>  $schedWrap
     */
    protected function scheduleRefFromLegScheduleWrap(array $schedWrap): int
    {
        foreach (['ref', 'scheduleRef', 'id'] as $k) {
            if (isset($schedWrap[$k]) && is_numeric($schedWrap[$k])) {
                return (int) $schedWrap[$k];
            }
        }
        $nested = $schedWrap['schedule'] ?? null;
        if (is_array($nested)) {
            foreach (['ref', 'scheduleRef', 'id'] as $k) {
                if (isset($nested[$k]) && is_numeric($nested[$k])) {
                    return (int) $nested[$k];
                }
            }
        }

        return -1;
    }

    /**
     * Route continuity on built segment models (for safe logging).
     *
     * @param  list<FlightSegmentData>  $segmentModels
     * @return array{segment_count: int, first_segment_origin: string, last_segment_destination: string, route_continuity_ok: bool, out_of_order_segment_count: int, connection_gaps: list<string>}
     */
    protected function segmentModelsRouteContinuity(array $segmentModels): array
    {
        $n = count($segmentModels);
        if ($n === 0) {
            return [
                'segment_count' => 0,
                'first_segment_origin' => '',
                'last_segment_destination' => '',
                'route_continuity_ok' => true,
                'out_of_order_segment_count' => 0,
                'connection_gaps' => [],
            ];
        }

        $firstO = strtoupper(trim($segmentModels[0]->origin));
        $lastD = strtoupper(trim($segmentModels[$n - 1]->destination));
        $gaps = 0;
        $gapCodes = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $d = strtoupper(trim($segmentModels[$i]->destination));
            $nextO = strtoupper(trim($segmentModels[$i + 1]->origin));
            if ($d !== '' && $nextO !== '' && $d !== $nextO) {
                $gaps++;
                $gapCodes[] = $d.'|'.$nextO;
            }
        }

        return [
            'segment_count' => $n,
            'first_segment_origin' => $firstO,
            'last_segment_destination' => $lastD,
            'route_continuity_ok' => $gaps === 0,
            'out_of_order_segment_count' => $gaps,
            'connection_gaps' => $gapCodes,
        ];
    }

    /**
     * Validates offer endpoints against the search request. One-way requires first origin and
     * last destination to match; round-trip requires return to origin and the away point to appear.
     *
     * @param  list<FlightSegmentData>  $workingSegments
     */
    protected function matchesSearchEndpoints(
        ?FlightSearchRequestData $searchRequest,
        string $offerOrigin,
        string $offerDestination,
        array $workingSegments,
    ): bool {
        if ($searchRequest === null) {
            return true;
        }

        $reqO = strtoupper(trim($searchRequest->origin));
        $reqD = strtoupper(trim($searchRequest->destination));
        $tripType = trim($searchRequest->trip_type);

        if ($tripType === 'round_trip') {
            if ($reqO !== '' && $offerOrigin !== '' && ! SabreMarketEndpointEquivalence::endpointMatchesRequested($offerOrigin, $reqO)) {
                return false;
            }
            if ($reqO !== '' && $offerDestination !== '' && ! SabreMarketEndpointEquivalence::endpointMatchesRequested($offerDestination, $reqO)) {
                return false;
            }

            return $reqD === '' || SabreMarketEndpointEquivalence::itineraryTouchesRequestedMarket($workingSegments, $reqD);
        }

        if ($tripType === 'multi_city') {
            $legs = $searchRequest->segments ?? null;
            if (is_array($legs) && count($legs) >= 2) {
                return $this->matchesMultiCitySearchEndpoints($workingSegments, $legs);
            }
        }

        return ($reqO === '' || SabreMarketEndpointEquivalence::endpointMatchesRequested($offerOrigin, $reqO))
            && ($reqD === '' || SabreMarketEndpointEquivalence::endpointMatchesRequested($offerDestination, $reqD));
    }

    /**
     * Validates a flat itinerary against ordered multi-city legs (connections allowed within each leg).
     *
     * @param  list<FlightSegmentData>  $workingSegments
     * @param  list<array<string, mixed>>  $requestedLegs
     */
    protected function matchesMultiCitySearchEndpoints(array $workingSegments, array $requestedLegs): bool
    {
        $legs = [];
        foreach ($requestedLegs as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $o = strtoupper(trim((string) ($raw['origin'] ?? '')));
            $d = strtoupper(trim((string) ($raw['destination'] ?? '')));
            if ($o !== '' && $d !== '') {
                $legs[] = ['origin' => $o, 'destination' => $d];
            }
        }
        if (count($legs) < 2 || $workingSegments === []) {
            return false;
        }

        $n = count($workingSegments);
        $startIdx = 0;

        foreach ($legs as $legIdx => $leg) {
            if ($startIdx >= $n) {
                return false;
            }

            $legO = $leg['origin'];
            $legD = $leg['destination'];
            $scanFrom = $startIdx;

            if ($legIdx === 0) {
                $first = $workingSegments[0];
                if (! $first instanceof FlightSegmentData || strtoupper(trim($first->origin)) !== $legO) {
                    return false;
                }
                $scanFrom = 0;
            } else {
                $seg = $workingSegments[$startIdx];
                if (! $seg instanceof FlightSegmentData || strtoupper(trim($seg->origin)) !== $legO) {
                    return false;
                }
            }

            $splitAt = null;
            for ($i = $scanFrom; $i < $n; $i++) {
                $sm = $workingSegments[$i];
                if ($sm instanceof FlightSegmentData && strtoupper(trim($sm->destination)) === $legD) {
                    $splitAt = $i;
                    break;
                }
            }
            if ($splitAt === null) {
                return false;
            }

            for ($i = $scanFrom; $i < $splitAt; $i++) {
                $a = $workingSegments[$i];
                $b = $workingSegments[$i + 1];
                if (! $a instanceof FlightSegmentData || ! $b instanceof FlightSegmentData) {
                    return false;
                }
                if (strtoupper(trim($a->destination)) !== strtoupper(trim($b->origin))) {
                    return false;
                }
            }

            $startIdx = $splitAt + 1;
        }

        return $startIdx === $n;
    }

    /**
     * @param  list<FlightSegmentData>  $workingSegments
     */
    protected function itineraryTouchesAirport(array $workingSegments, string $airportCode): bool
    {
        $airport = strtoupper(trim($airportCode));
        if ($airport === '') {
            return true;
        }
        foreach ($workingSegments as $sm) {
            if (! $sm instanceof FlightSegmentData) {
                continue;
            }
            if (strtoupper(trim($sm->origin)) === $airport || strtoupper(trim($sm->destination)) === $airport) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<FlightSegmentData>  $segmentModels
     * @return list<string>
     */
    protected function sampleSegmentRoutes(array $segmentModels, int $limit = 6): array
    {
        $out = [];
        foreach (array_slice($segmentModels, 0, max(0, $limit)) as $sm) {
            if ($sm instanceof FlightSegmentData) {
                $out[] = strtoupper(trim($sm->origin)).'→'.strtoupper(trim($sm->destination));
            }
        }

        return $out;
    }

    /**
     * When leg-level chronological sorting yields a globally reversed multi-segment chain, continuity
     * fails while the reverse order is a valid LHE→…→DXB style journey. Try reversal only when it
     * restores strict continuity and matches requested endpoints (no blind reverse).
     *
     * @param  list<FlightSegmentData>  $segmentModels
     * @return array{
     *     working_segments: list<FlightSegmentData>,
     *     segment_order_corrected: bool,
     *     original_route_continuity_ok: bool,
     *     reversed_route_continuity_ok: bool|null,
     *     original_segment_routes_sample: list<string>,
     *     corrected_segment_routes_sample: list<string>
     * }
     */
    protected function resolveSegmentOrderWithOptionalReverse(
        array $segmentModels,
        ?FlightSearchRequestData $searchRequest,
    ): array {
        $limit = 6;
        $originalSample = $this->sampleSegmentRoutes($segmentModels, $limit);
        $routeOrig = $this->segmentModelsRouteContinuity($segmentModels);
        $originalOk = $routeOrig['route_continuity_ok'];
        $reversedOk = null;
        $orderCorrected = false;
        $working = $segmentModels;

        if (! $originalOk && count($segmentModels) >= 2 && $searchRequest !== null) {
            $reqO = strtoupper(trim($searchRequest->origin));
            $reqD = strtoupper(trim($searchRequest->destination));
            if ($reqO !== '' && $reqD !== '') {
                /** @var list<FlightSegmentData> $reversed */
                $reversed = array_values(array_reverse($segmentModels));
                $routeRev = $this->segmentModelsRouteContinuity($reversed);
                $reversedOk = $routeRev['route_continuity_ok'];
                $rFirst = strtoupper(trim($reversed[0]->origin));
                $rLast = strtoupper(trim($reversed[array_key_last($reversed)]->destination));
                $tripType = trim($searchRequest->trip_type);
                if ($reversedOk === true && $tripType === 'round_trip') {
                    if ($rFirst === $reqO && $rLast === $reqO && $this->itineraryTouchesAirport($reversed, $reqD)) {
                        $working = $reversed;
                        $orderCorrected = true;
                    }
                } elseif ($reversedOk === true && $rFirst === $reqO && $rLast === $reqD) {
                    $working = $reversed;
                    $orderCorrected = true;
                }
            }
        }

        $correctedSample = $originalSample;
        if ($orderCorrected) {
            $correctedSample = $this->sampleSegmentRoutes($working, $limit);
        } elseif ($reversedOk !== null && count($segmentModels) >= 2) {
            $correctedSample = $this->sampleSegmentRoutes(array_values(array_reverse($segmentModels)), $limit);
        }

        return [
            'working_segments' => $working,
            'segment_order_corrected' => $orderCorrected,
            'original_route_continuity_ok' => $originalOk,
            'reversed_route_continuity_ok' => $reversedOk,
            'original_segment_routes_sample' => $originalSample,
            'corrected_segment_routes_sample' => $correctedSample,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logSabreOfferRejected(string $offerId, string $rejectReason, array $context): void
    {
        Log::warning('sabre.normalizer.offer_rejected', array_merge([
            'provider' => 'sabre',
            'offer_id' => $offerId,
            'reject_reason' => $rejectReason,
        ], $context));
    }

    /**
     * @param  array<string, mixed>  $itinerary
     */
    protected function itineraryLegsElapsedMinutesSum(array $itinerary): int
    {
        $legs = $itinerary['legs'] ?? null;
        if (! is_array($legs)) {
            return 0;
        }
        $sum = 0;
        foreach ($legs as $legWrap) {
            if (! is_array($legWrap)) {
                continue;
            }
            $lr = $this->legDescriptorRefFromLegWrap($legWrap);
            if ($lr < 0) {
                continue;
            }
            $legDesc = $this->resolveDescriptorKey('leg', $lr);
            if (is_array($legDesc)) {
                $sum += max(0, (int) ($legDesc['elapsedTime'] ?? 0));
            }
        }

        return $sum;
    }

    /**
     * @return list<FlightSegmentData>
     */
    protected function buildSegmentsLegacyInline(array $itinerary, ?FlightSearchRequestData $searchRequest = null): array
    {
        $inline = $itinerary['scheduleDescs'] ?? null;
        if (! is_array($inline)) {
            return [];
        }
        $segments = [];
        $anchorYmd = $this->validYmd($searchRequest?->departure_date);
        $state = [
            'prev_arrival' => null,
        ];
        $rows = [];
        foreach ($inline as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        foreach ($this->sortResolvedSchedulesForLegChronology($rows, $anchorYmd) as $row) {
            $segments[] = $this->segmentFromScheduleDesc($row, $anchorYmd, $state);
        }

        return $segments;
    }

    /**
     * @param  array{prev_arrival: ?string}  $state
     */
    protected function segmentFromScheduleDesc(array $schedule, ?string $anchorYmd, array &$state): FlightSegmentData
    {
        $depNode = $this->mergeScheduleEndpointFields($schedule, 'departure');
        $arrNode = $this->mergeScheduleEndpointFields($schedule, 'arrival');

        $origin = $this->stringifyAirportCode($depNode['airport'] ?? null)
            ?: $this->stringifyAirportCode($depNode['airportCode'] ?? null);
        $destination = $this->stringifyAirportCode($arrNode['airport'] ?? null)
            ?: $this->stringifyAirportCode($arrNode['airportCode'] ?? null);

        $rawDep = $this->stringifyScheduleDateTime($depNode);
        $rawArr = $this->stringifyScheduleDateTime($arrNode);

        $depDateHint = $state['prev_arrival'] !== null
            ? substr((string) $state['prev_arrival'], 0, 10)
            : $anchorYmd;

        $departureAt = $this->composeScheduleEndpointIso($schedule, 'departure', $rawDep, $depDateHint);
        $arrivalDateHint = $departureAt !== '' ? substr($departureAt, 0, 10) : $depDateHint;
        $arrivalAt = $this->composeScheduleEndpointIso($schedule, 'arrival', $rawArr, $arrivalDateHint);

        $segmentAdjusted = false;
        if ($this->readDateAdjustmentDays($schedule, 'departure') !== 0
            || $this->readDateAdjustmentDays($schedule, 'arrival') !== 0) {
            $segmentAdjusted = true;
        }
        if ($rawDep !== '' && $departureAt !== '' && ! $this->rawHasCalendarDate($rawDep)) {
            $segmentAdjusted = true;
        }
        if ($rawArr !== '' && $arrivalAt !== '' && ! $this->rawHasCalendarDate($rawArr)) {
            $segmentAdjusted = true;
        }
        if ($segmentAdjusted) {
            $this->diagDateAdjustedSegmentCount++;
        }

        if ($departureAt === '' || $arrivalAt === '') {
            $this->diagMissingSegmentTimeCount += ($departureAt === '' ? 1 : 0) + ($arrivalAt === '' ? 1 : 0);
        }

        $airlineCode = (string) (data_get($schedule, 'carrier.marketing')
            ?? data_get($schedule, 'carrier.marketingAirline')
            ?? '');
        if (is_array(data_get($schedule, 'carrier.marketing'))) {
            $airlineCode = (string) (data_get($schedule, 'carrier.marketing.code')
                ?? data_get($schedule, 'carrier.marketing.airlineCode')
                ?? $airlineCode);
        }
        if (trim($airlineCode) === '') {
            $airlineCode = (string) (data_get($schedule, 'carrier.operating') ?? '');
        }
        $airlineCode = strtoupper(trim($airlineCode));

        $marketingName = trim((string) (data_get($schedule, 'carrier.marketingAirlineName')
            ?? data_get($schedule, 'carrier.marketing.name')
            ?? ''));
        if ($marketingName === '') {
            $m = data_get($schedule, 'carrier.marketing');
            if (is_string($m)) {
                $marketingName = trim($m);
            }
        }

        $flightNumber = (string) (data_get($schedule, 'carrier.marketingFlightNumber')
            ?? data_get($schedule, 'carrier.flightNumber')
            ?? '');
        $flightNumber = trim($flightNumber);

        $opRaw = data_get($schedule, 'carrier.operating');
        $operatingCode = is_string($opRaw)
            ? strtoupper(trim($opRaw))
            : $this->stringifyAirportCode($opRaw);
        if ($operatingCode === $airlineCode) {
            $operatingCode = '';
        }
        $operatingName = trim((string) (data_get($schedule, 'carrier.operatingAirlineName')
            ?? data_get($schedule, 'carrier.operating.name')
            ?? ''));

        $elapsedSchedule = $this->readElapsedMinutesFromSchedule($schedule);

        // Sabre block time (minutes): when present for a typical short/medium leg, force canonical arrival
        // so a mis-tagged +1 day on arrival does not steal layover time into segment wall duration (S29).
        if ($elapsedSchedule > 0 && $elapsedSchedule <= 600 && $departureAt !== '') {
            try {
                $arrivalAt = (new DateTimeImmutable($departureAt))->modify('+'.$elapsedSchedule.' minutes')->format('Y-m-d\TH:i:s');
            } catch (\Throwable) {
                // keep composed arrival
            }
        }

        $elapsed = max(0, $elapsedSchedule);
        if ($elapsed <= 0 && $departureAt !== '' && $arrivalAt !== '') {
            $wall = $this->durationMinutesFromIso($departureAt, $arrivalAt);
            // Never copy multi-day wall clock into segment duration when Sabre omitted elapsed (PK/KHI bug).
            if ($wall > 0 && $wall <= 1320) {
                $elapsed = $wall;
            }
        }

        if ($elapsed > 0 && $departureAt !== '' && $arrivalAt !== '' && $departureAt === $arrivalAt) {
            try {
                $arrivalAt = (new DateTimeImmutable($departureAt))->modify('+'.$elapsed.' minutes')->format('Y-m-d\TH:i:s');
            } catch (\Throwable) {
                // keep Sabre values
            }
        }

        if ($arrivalAt !== '') {
            $state['prev_arrival'] = $arrivalAt;
        }

        return new FlightSegmentData(
            origin: $origin,
            destination: $destination,
            departure_at: $departureAt,
            arrival_at: $arrivalAt,
            flight_number: $flightNumber !== '' ? $flightNumber : null,
            airline_code: $airlineCode !== '' ? $airlineCode : null,
            airline_name: $marketingName !== '' ? $marketingName : null,
            duration_minutes: max(0, $elapsed),
            operating_airline_code: $operatingCode !== '' ? $operatingCode : null,
            operating_airline_name: $operatingName !== '' ? $operatingName : null,
        );
    }

    /**
     * Merge nested departure/arrival nodes with schedule-level Sabre fields when endpoints omit time/airport.
     *
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    protected function mergeScheduleEndpointFields(array $schedule, string $endpoint): array
    {
        $node = data_get($schedule, $endpoint);
        $out = is_array($node) ? $node : [];

        if ($endpoint === 'departure') {
            if (($out['airport'] ?? '') === '' && ($out['airportCode'] ?? '') === '') {
                foreach (['departureAirport', 'departureAirportCode', 'originAirport', 'fromAirport'] as $k) {
                    $v = data_get($schedule, $k);
                    if (is_string($v) && trim($v) !== '') {
                        $out['airport'] = trim($v);
                        break;
                    }
                }
            }
            if (($out['time'] ?? '') === '' && ($out['dateTime'] ?? '') === '') {
                foreach (['departureTime', 'departureLocalTime', 'depTime'] as $k) {
                    $v = data_get($schedule, $k);
                    if (is_string($v) || is_numeric($v)) {
                        $t = trim((string) $v);
                        if ($t !== '') {
                            $out['time'] = $t;
                            break;
                        }
                    }
                }
            }
            if (($out['date'] ?? '') === '' && ($out['dateTime'] ?? '') === '') {
                foreach (['departureDate', 'depDate'] as $k) {
                    $v = data_get($schedule, $k);
                    if (is_string($v) && trim($v) !== '') {
                        $out['date'] = trim($v);
                        break;
                    }
                }
            }
        } else {
            if (($out['airport'] ?? '') === '' && ($out['airportCode'] ?? '') === '') {
                foreach (['arrivalAirport', 'arrivalAirportCode', 'destinationAirport', 'toAirport'] as $k) {
                    $v = data_get($schedule, $k);
                    if (is_string($v) && trim($v) !== '') {
                        $out['airport'] = trim($v);
                        break;
                    }
                }
            }
            if (($out['time'] ?? '') === '' && ($out['dateTime'] ?? '') === '') {
                foreach (['arrivalTime', 'arrivalLocalTime', 'arrTime'] as $k) {
                    $v = data_get($schedule, $k);
                    if (is_string($v) || is_numeric($v)) {
                        $t = trim((string) $v);
                        if ($t !== '') {
                            $out['time'] = $t;
                            break;
                        }
                    }
                }
            }
            if (($out['date'] ?? '') === '' && ($out['dateTime'] ?? '') === '') {
                foreach (['arrivalDate', 'arrDate'] as $k) {
                    $v = data_get($schedule, $k);
                    if (is_string($v) && trim($v) !== '') {
                        $out['date'] = trim($v);
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<FlightSegmentData>  $segmentModels
     */
    protected function countUnreliableLayovers(array $segmentModels): int
    {
        $n = count($segmentModels);
        if ($n < 2) {
            return 0;
        }
        $bad = 0;
        for ($i = 0; $i < $n - 1; $i++) {
            $a = $segmentModels[$i]->arrival_at;
            $b = $segmentModels[$i + 1]->departure_at;
            if ($a === '' || $b === '') {
                $bad++;

                continue;
            }
            if ($this->durationMinutesFromIso($a, $b) < 0) {
                $bad++;
            }
        }

        return $bad;
    }

    /**
     * Safe route continuity metrics for logging (airport codes and counts only).
     *
     * @param  list<NormalizedFlightOfferData>  $offers
     * @return array{segment_count: int, first_segment_origin: string, last_segment_destination: string, route_continuity_ok: bool, out_of_order_segment_count: int}
     */
    public function routeContinuityDiagnostics(array $offers): array
    {
        if ($offers === []) {
            return [
                'segment_count' => 0,
                'first_segment_origin' => '',
                'last_segment_destination' => '',
                'route_continuity_ok' => true,
                'out_of_order_segment_count' => 0,
            ];
        }

        $o = $offers[0];
        $segs = $o->segments;
        $n = count($segs);
        $firstO = $n > 0 ? strtoupper(trim((string) ($segs[0]['origin'] ?? ''))) : '';
        $lastD = $n > 0 ? strtoupper(trim((string) ($segs[$n - 1]['destination'] ?? ''))) : '';

        $gaps = 0;
        for ($i = 0; $i < $n - 1; $i++) {
            $d = strtoupper(trim((string) ($segs[$i]['destination'] ?? '')));
            $nextO = strtoupper(trim((string) ($segs[$i + 1]['origin'] ?? '')));
            if ($d !== '' && $nextO !== '' && $d !== $nextO) {
                $gaps++;
            }
        }

        return [
            'segment_count' => $n,
            'first_segment_origin' => $firstO,
            'last_segment_destination' => $lastD,
            'route_continuity_ok' => $gaps === 0,
            'out_of_order_segment_count' => $gaps,
        ];
    }

    protected function validYmd(?string $d): ?string
    {
        if ($d === null || trim($d) === '') {
            return null;
        }
        $d = trim($d);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $d);

        return ($dt !== false && $dt->format('Y-m-d') === $d) ? $d : null;
    }

    protected function rawHasCalendarDate(string $raw): bool
    {
        return (bool) preg_match('/\d{4}-\d{2}-\d{2}/', $raw);
    }

    protected function readDateAdjustmentDays(array $schedule, string $endpoint): int
    {
        $node = data_get($schedule, $endpoint);
        if (is_array($node)) {
            $keys = $endpoint === 'departure'
                ? ['dateAdjustment', 'departureDateAdjustment', 'dayAdjustment']
                : ['dateAdjustment', 'arrivalDateAdjustment', 'dayAdjustment'];
            foreach ($keys as $k) {
                if (isset($node[$k]) && is_numeric($node[$k])) {
                    return (int) $node[$k];
                }
            }
        }
        $top = $endpoint === 'departure'
            ? data_get($schedule, 'departureDateAdjustment')
            : data_get($schedule, 'arrivalDateAdjustment');
        if (is_numeric($top)) {
            return (int) $top;
        }

        return 0;
    }

    protected function readElapsedMinutesFromSchedule(array $schedule): int
    {
        foreach ([
            'elapsedTime',
            'elapsedMinutes',
            'elapsedTimeInMinutes',
            'totalElapsedTime',
            'flightTimeInMinutes',
            'blockMinutes',
            'blockTimeInMinutes',
        ] as $k) {
            $e = $schedule[$k] ?? null;
            if (is_string($e) && is_numeric(trim($e))) {
                return max(0, (int) round((float) trim($e)));
            }
            if (is_numeric($e)) {
                return max(0, (int) round((float) $e));
            }
        }
        $ft = $schedule['flightTime'] ?? null;
        if (is_array($ft)) {
            foreach (['totalMinutes', 'minutes', 'elapsedMinutes'] as $fk) {
                $v = $ft[$fk] ?? null;
                if (is_string($v) && is_numeric(trim($v))) {
                    return max(0, (int) round((float) trim($v)));
                }
                if (is_numeric($v)) {
                    return max(0, (int) round((float) $v));
                }
            }
        }

        return 0;
    }

    protected function extractClockPortion(string $raw): ?string
    {
        $raw = trim(str_replace(' ', '', $raw));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/\d{4}-\d{2}-\d{2}[T ](.+)$/i', $raw, $dateMatch)) {
            $raw = $dateMatch[1];
        }
        $raw = preg_replace('/(?:Z|[+-]\d{2}:\d{2})$/i', '', $raw) ?? $raw;
        if (preg_match('/^\d{4}$/', $raw)) {
            $h = (int) substr($raw, 0, 2);
            $m = (int) substr($raw, 2, 2);
            if ($h <= 23 && $m <= 59) {
                return substr($raw, 0, 2).':'.substr($raw, 2, 2).':00';
            }
        }
        if (preg_match('/^(?:T)?(\d{2}:\d{2})(?::(\d{2}))?(?:\.\d+)?$/', $raw, $m)) {
            $sec = $m[2] ?? '00';

            return $m[1].':'.$sec;
        }

        return null;
    }

    protected function parseToLocalImmutable(string $raw): ?DateTimeImmutable
    {
        $raw = trim($raw);
        foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $p) {
            $dt = DateTimeImmutable::createFromFormat($p, $raw);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function composeScheduleEndpointIso(
        array $schedule,
        string $endpoint,
        string $raw,
        ?string $dateHintYmd,
    ): string {
        if ($raw === '') {
            return '';
        }
        $adj = $this->readDateAdjustmentDays($schedule, $endpoint);
        if ($this->rawHasCalendarDate($raw)) {
            $parsed = $this->parseToLocalImmutable($raw);
            if ($parsed === null) {
                return '';
            }
            if ($adj !== 0) {
                $parsed = $parsed->modify(sprintf('%+d days', $adj));
            }

            return $parsed->format('Y-m-d\TH:i:s');
        }

        $clock = $this->extractClockPortion($raw);
        if ($clock === null || $dateHintYmd === null || $this->validYmd($dateHintYmd) === null) {
            return '';
        }
        $base = DateTimeImmutable::createFromFormat('Y-m-d', $dateHintYmd);
        if ($base === false) {
            return '';
        }
        if ($adj !== 0) {
            $base = $base->modify(sprintf('%+d days', $adj));
        }
        $combined = $base->format('Y-m-d').'T'.$clock;
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $combined)
            ?? DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $combined);
        if ($parsed === false) {
            return '';
        }

        return $parsed->format('Y-m-d\TH:i:s');
    }

    /**
     * Sabre scheduleDescs may use departure/arrival as string ISO, or object with time/dateTime.
     *
     * @param  array<string, mixed>|string|null  $node
     */
    protected function stringifyScheduleDateTime(mixed $node): string
    {
        if (is_string($node)) {
            return trim($node);
        }
        if (! is_array($node)) {
            return '';
        }
        if (isset($node['dateTime'])) {
            $dt = $node['dateTime'];
            if (is_string($dt) && trim($dt) !== '') {
                return trim($dt);
            }
            if (is_array($dt)) {
                foreach (['value', 'dateTime', 'localDateTime', 'date'] as $ik) {
                    $iv = $dt[$ik] ?? null;
                    if (is_string($iv) && trim($iv) !== '') {
                        return trim($iv);
                    }
                }
            }
        }
        $depDate = $node['departureDate'] ?? null;
        if (is_string($depDate) && preg_match('/^\d{4}-\d{2}-\d{2}/', trim($depDate))) {
            foreach (['departureTime', 'time', 'localTime'] as $tk) {
                $tv = $node[$tk] ?? null;
                if (is_string($tv) || is_numeric($tv)) {
                    $t = trim((string) $tv);
                    if ($t !== '' && ! preg_match('/\d{4}-\d{2}-\d{2}/', $t)) {
                        $clock = $this->digitsOnlyTimeToClock($t);
                        if ($clock !== null) {
                            return trim($depDate).'T'.$clock;
                        }

                        return trim($depDate).'T'.ltrim($t, 'T');
                    }
                }
            }
        }
        $arrDate = $node['arrivalDate'] ?? null;
        if (is_string($arrDate) && preg_match('/^\d{4}-\d{2}-\d{2}/', trim($arrDate))) {
            foreach (['arrivalTime', 'time', 'localTime'] as $tk) {
                $tv = $node[$tk] ?? null;
                if (is_string($tv) || is_numeric($tv)) {
                    $t = trim((string) $tv);
                    if ($t !== '' && ! preg_match('/\d{4}-\d{2}-\d{2}/', $t)) {
                        $clock = $this->digitsOnlyTimeToClock($t);
                        if ($clock !== null) {
                            return trim($arrDate).'T'.$clock;
                        }

                        return trim($arrDate).'T'.ltrim($t, 'T');
                    }
                }
            }
        }
        $date = $node['date'] ?? null;
        $time = $node['time'] ?? null;
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($date))
            && (is_string($time) || is_numeric($time))) {
            $t = trim((string) $time);
            if ($t !== '' && ! preg_match('/\d{4}-\d{2}-\d{2}/', $t)) {
                $clock = $this->digitsOnlyTimeToClock($t);
                $tClean = $clock !== null ? $clock : ltrim($t, 'T');

                return trim($date).'T'.$tClean;
            }
        }
        foreach (['time', 'dateTime', 'localDateTime', 'localTime'] as $k) {
            $v = $node[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (is_int($v) || is_float($v)) {
                $clock = $this->digitsOnlyTimeToClock(preg_replace('/\D/', '', (string) $v) ?? '');
                if ($clock !== null) {
                    $date = $node['date'] ?? null;
                    if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($date))) {
                        return trim($date).'T'.$clock;
                    }

                    return $clock;
                }
            }
            if (is_array($v)) {
                $inner = (string) ($v['dateTime'] ?? $v['localDateTime'] ?? $v['value'] ?? '');
                if ($inner !== '') {
                    return trim($inner);
                }
            }
        }

        return '';
    }

    /**
     * HHMM or HMM as Sabre sometimes returns without punctuation (e.g. 930 → 09:30).
     */
    protected function digitsOnlyTimeToClock(string $digitsRaw): ?string
    {
        $digits = preg_replace('/\D/', '', $digitsRaw) ?? '';
        if ($digits === '') {
            return null;
        }
        $digits = str_pad($digits, 4, '0', STR_PAD_LEFT);
        if (strlen($digits) !== 4) {
            return null;
        }
        $h = (int) substr($digits, 0, 2);
        $m = (int) substr($digits, 2, 2);
        if ($h > 23 || $m > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $h, $m);
    }

    /**
     * Sabre scheduleDescs may use airport as string or nested object (e.g. {"code":"LHE"}).
     */
    protected function stringifyAirportCode(mixed $value): string
    {
        if (is_string($value)) {
            return strtoupper(trim($value));
        }
        if (is_array($value)) {
            return strtoupper(trim((string) (
                $value['code'] ?? $value['airportCode'] ?? $value['airport'] ?? $value['locationCode'] ?? ''
            )));
        }

        return '';
    }

    protected function durationMinutesFromIso(string $dep, string $arr): int
    {
        if ($dep === '' || $arr === '') {
            return 0;
        }
        $dep = preg_replace('/\.\d+/', '', trim($dep));
        $arr = preg_replace('/\.\d+/', '', trim($arr));
        $dep = preg_replace('/Z$/i', '', $dep) ?? $dep;
        $arr = preg_replace('/Z$/i', '', $arr) ?? $arr;
        try {
            $d1 = new DateTimeImmutable($dep);
            $d2 = new DateTimeImmutable($arr);
            $diff = (int) round(($d2->getTimestamp() - $d1->getTimestamp()) / 60);

            return max(0, $diff);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array{
     *     base_fare: float,
     *     taxes: float,
     *     supplier_total: float,
     *     currency: string,
     *     display_base_fare: float,
     *     display_taxes: float,
     *     raw_base_fare: float|null,
     *     base_fare_display_source: string,
     *     breakdown_reconciled: bool
     * }
     */
    protected function extractFareBreakdownFromFare(array $fare): array
    {
        $currency = '';
        $base = 0.0;
        $tax = 0.0;
        $total = 0.0;
        $equiv = 0.0;

        $totalFareNode = $fare['totalFare'] ?? null;
        if (is_array($totalFareNode)) {
            $currency = strtoupper(trim((string) ($totalFareNode['currency']
                ?? $totalFareNode['currencyCode']
                ?? '')));
            $total = $this->parseAmount($totalFareNode['totalPrice'] ?? $totalFareNode['totalFare'] ?? null);
            $base = $this->parseAmount($totalFareNode['baseFareAmount'] ?? $totalFareNode['baseFare'] ?? null);
            $tax = $this->parseAmount($totalFareNode['totalTaxAmount'] ?? $totalFareNode['taxAmount'] ?? null);
            $equiv = $this->parseAmount($totalFareNode['equivFareAmount'] ?? null);
        } elseif (is_numeric($totalFareNode)) {
            $total = (float) $totalFareNode;
        }

        if ($currency === '') {
            $currency = strtoupper(trim((string) ($fare['currency'] ?? '')));
        }
        if ($base <= 0) {
            $base = $this->parseAmount($fare['baseFareAmount'] ?? $fare['baseFare'] ?? null);
        }
        if ($tax <= 0) {
            $tax = $this->parseAmount($fare['taxAmount'] ?? $fare['totalTaxAmount'] ?? null);
        }
        if ($equiv <= 0) {
            $equiv = $this->parseAmount($fare['equivFareAmount'] ?? null);
        }
        if ($total <= 0 && ($base > 0 || $tax > 0)) {
            $total = $base + $tax;
        }

        if ($total <= 0) {
            $total = $this->sumPassengerTotalFares($fare);
        }

        if ($currency === '' && ($total > 0 || $base > 0)) {
            $currency = 'USD';
        }

        if ($total <= 0) {
            $currency = $currency !== '' ? $currency : '';
        }

        $rawBase = round($base, 2);
        $taxes = round($tax, 2);
        $supplierTotal = round($total, 2);
        $display = $this->reconcileSabreDisplayFareComponents($supplierTotal, $rawBase, $taxes, round($equiv, 2));

        $passengerPack = $this->extractPassengerPricingFromFare($fare, $supplierTotal);

        return [
            'base_fare' => $rawBase,
            'taxes' => $taxes,
            'supplier_total' => $supplierTotal,
            'currency' => $currency,
            'display_base_fare' => $display['display_base_fare'],
            'display_taxes' => $display['display_taxes'],
            'raw_base_fare' => $rawBase > 0 ? $rawBase : null,
            'base_fare_display_source' => $display['base_fare_display_source'],
            'breakdown_reconciled' => $display['breakdown_reconciled'],
            'passenger_pricing' => $passengerPack['passenger_pricing'],
            'passenger_pricing_available' => $passengerPack['passenger_pricing_available'],
        ];
    }

    /**
     * Customer-facing base/tax when Sabre raw base is not in the same currency as total/tax.
     *
     * @return array{
     *     display_base_fare: float,
     *     display_taxes: float,
     *     base_fare_display_source: string,
     *     breakdown_reconciled: bool
     * }
     */
    protected function reconcileSabreDisplayFareComponents(
        float $supplierTotal,
        float $rawBase,
        float $taxes,
        float $equivFare,
    ): array {
        $eps = 0.5;

        if ($supplierTotal <= 0 || $taxes <= 0) {
            return [
                'display_base_fare' => $rawBase,
                'display_taxes' => $taxes,
                'base_fare_display_source' => 'supplier_raw',
                'breakdown_reconciled' => false,
            ];
        }

        if ($rawBase > 0 && abs(($rawBase + $taxes) - $supplierTotal) <= $eps) {
            return [
                'display_base_fare' => $rawBase,
                'display_taxes' => $taxes,
                'base_fare_display_source' => 'supplier_raw',
                'breakdown_reconciled' => false,
            ];
        }

        if ($equivFare > 0 && abs(($equivFare + $taxes) - $supplierTotal) <= $eps) {
            return [
                'display_base_fare' => $equivFare,
                'display_taxes' => $taxes,
                'base_fare_display_source' => 'equiv_fare_amount',
                'breakdown_reconciled' => true,
            ];
        }

        if ($supplierTotal > $taxes) {
            return [
                'display_base_fare' => round($supplierTotal - $taxes, 2),
                'display_taxes' => $taxes,
                'base_fare_display_source' => 'total_minus_taxes',
                'breakdown_reconciled' => true,
            ];
        }

        return [
            'display_base_fare' => $rawBase,
            'display_taxes' => $taxes,
            'base_fare_display_source' => 'supplier_raw',
            'breakdown_reconciled' => false,
        ];
    }

    /**
     * Per-PTC pricing from BFM {@code fare.passengerInfoList[]} (real supplier rows only).
     *
     * @param  array<string, mixed>  $fare
     * @return array{passenger_pricing: list<array<string, mixed>>|null, passenger_pricing_available: bool}
     */
    protected function extractPassengerPricingFromFare(array $fare, float $supplierTotal): array
    {
        $list = $fare['passengerInfoList'] ?? null;
        if (! is_array($list) || $list === []) {
            return ['passenger_pricing' => null, 'passenger_pricing_available' => false];
        }

        $rows = [];
        $rowIndex = 0;
        foreach ($list as $wrap) {
            if (! is_array($wrap)) {
                continue;
            }
            $pi = $wrap['passengerInfo'] ?? null;
            if (! is_array($pi)) {
                continue;
            }
            $ptc = strtoupper(trim((string) ($pi['passengerType'] ?? $pi['passengerTypeCode'] ?? '')));
            $canonical = $this->mapSabrePassengerTypeCodeToCanonical($ptc);
            if ($canonical === null) {
                continue;
            }
            $ptf = $pi['passengerTotalFare'] ?? null;
            if (! is_array($ptf)) {
                continue;
            }
            $amounts = $this->extractSabrePassengerTotalFareAmounts($ptf);
            if ($amounts === null) {
                continue;
            }
            $quantity = $this->resolveSabrePassengerInfoQuantity($pi);
            $rows[] = [
                'supplier_passenger_id' => 'ptc_'.($ptc !== '' ? strtolower($ptc) : $canonical).'_'.$rowIndex,
                'passenger_type' => $canonical,
                'passenger_count' => $quantity,
                'ptc' => $ptc !== '' ? $ptc : null,
                'base_amount' => $amounts['base_amount'],
                'tax_amount' => $amounts['tax_amount'],
                'total_amount' => $amounts['total_amount'],
                'currency' => $amounts['currency'],
            ];
            $rowIndex++;
        }

        if ($rows === []) {
            return ['passenger_pricing' => null, 'passenger_pricing_available' => false];
        }

        $rowSum = round(array_sum(array_map(
            static fn (array $row): float => (float) ($row['total_amount'] ?? 0),
            $rows
        )), 2);
        $available = $supplierTotal <= 0
            || abs($rowSum - round($supplierTotal, 2)) <= max(2.0, $supplierTotal * 0.02);

        if (! $available) {
            return ['passenger_pricing' => null, 'passenger_pricing_available' => false];
        }

        return ['passenger_pricing' => $rows, 'passenger_pricing_available' => true];
    }

    /**
     * @param  array<string, mixed>  $passengerInfo
     */
    protected function resolveSabrePassengerInfoQuantity(array $passengerInfo): int
    {
        foreach (['passengerNumber', 'passengerCount', 'quantity'] as $key) {
            if (! isset($passengerInfo[$key]) || ! is_numeric($passengerInfo[$key])) {
                continue;
            }
            $qty = (int) $passengerInfo[$key];
            if ($qty > 0) {
                return $qty;
            }
        }

        return 1;
    }

    /**
     * @param  array<string, mixed>  $ptf  {@code passengerTotalFare} node
     * @return array{base_amount: float, tax_amount: float, total_amount: float, currency: string}|null
     */
    protected function extractSabrePassengerTotalFareAmounts(array $ptf): ?array
    {
        $currency = strtoupper(trim((string) ($ptf['currency'] ?? $ptf['currencyCode'] ?? '')));
        $total = $this->parseAmount($ptf['totalFare'] ?? $ptf['totalPrice'] ?? null);
        $base = $this->parseAmount($ptf['baseFareAmount'] ?? $ptf['baseFare'] ?? null);
        $tax = $this->parseAmount($ptf['totalTaxAmount'] ?? $ptf['taxAmount'] ?? null);
        $equiv = $this->parseAmount($ptf['equivFareAmount'] ?? null);

        if ($total <= 0 && ($base > 0 || $tax > 0)) {
            $total = $base + $tax;
        }
        if ($total <= 0) {
            return null;
        }

        $display = $this->reconcileSabreDisplayFareComponents($total, $base, $tax, $equiv);

        return [
            'base_amount' => (float) $display['display_base_fare'],
            'tax_amount' => (float) $display['display_taxes'],
            'total_amount' => round($total, 2),
            'currency' => $currency !== '' ? $currency : 'PKR',
        ];
    }

    protected function mapSabrePassengerTypeCodeToCanonical(string $ptc): ?string
    {
        $code = strtoupper(trim($ptc));
        if ($code === '') {
            return null;
        }

        return match ($code) {
            'ADT', 'ADULT' => 'adult',
            'CHD', 'CH', 'CNN' => 'child',
            'INF', 'IN', 'INS', 'INFANT' => 'infant',
            default => preg_match('/^C\d{2}$/', $code) === 1 ? 'child' : null,
        };
    }

    /**
     * @return array{adults: int, children: int, infants: int, total: int}
     */
    protected function buildPassengerCountsFromSearchRequest(?FlightSearchRequestData $searchRequest): array
    {
        if (! $searchRequest instanceof FlightSearchRequestData) {
            return ['adults' => 1, 'children' => 0, 'infants' => 0, 'total' => 1];
        }

        $adults = max(0, $searchRequest->adults);
        $children = max(0, $searchRequest->children);
        $infants = max(0, $searchRequest->infants);
        if ($adults + $children + $infants === 0) {
            $adults = 1;
        }

        return [
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'total' => $adults + $children + $infants,
        ];
    }

    protected function sumPassengerTotalFares(array $fare): float
    {
        $sum = 0.0;
        $list = $fare['passengerInfoList'] ?? null;
        if (! is_array($list)) {
            return 0.0;
        }
        foreach ($list as $wrap) {
            $pi = is_array($wrap) ? ($wrap['passengerInfo'] ?? null) : null;
            if (! is_array($pi)) {
                continue;
            }
            $ptf = $pi['passengerTotalFare'] ?? null;
            if (is_array($ptf)) {
                $sum += $this->parseAmount($ptf['totalFare'] ?? $ptf['totalPrice'] ?? null);
            }
        }

        return round($sum, 2);
    }

    protected function parseAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            return (float) str_replace(',', '', trim($value));
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @return array<string, mixed>
     */
    protected function firstFareNode(array $itinerary): array
    {
        $pi = $itinerary['pricingInformation'] ?? null;
        if (! is_array($pi) || $pi === []) {
            return [];
        }
        $first = $pi[0] ?? null;

        return is_array($first) && is_array($first['fare'] ?? null) ? $first['fare'] : [];
    }

    protected function extractCabinCodeFromFare(array $fare): string
    {
        $cabin = (string) data_get(
            $fare,
            'passengerInfoList.0.passengerInfo.fareComponents.0.segments.0.segment.cabinCode'
        );
        $cabin = strtolower(trim($cabin));
        if ($cabin === '') {
            return '';
        }

        return match ($cabin) {
            'y', 'm', 'k', 'h', 'b', 'l', 'v', 's', 'n', 'q', 'o', 'g' => 'economy',
            'w', 'e', 't' => 'premium_economy',
            'c', 'j', 'd', 'i', 'z' => 'business',
            'f', 'a', 'p' => 'first',
            default => $cabin,
        };
    }

    protected function extractRefundable(array $fare): bool
    {
        if (data_get($fare, 'passengerInfoList.0.passengerInfo.refundable') === true) {
            return true;
        }
        $non = data_get($fare, 'passengerInfoList.0.passengerInfo.nonRefundable');
        if ($non === true) {
            return false;
        }
        if ($non === false) {
            return true;
        }

        return false;
    }

    /**
     * Fare brand / family from the first priced fare component when present (inline, fare basis, then fareComponentDescs brand).
     */
    protected function extractFareFamily(array $fare): ?string
    {
        $list = data_get($fare, 'passengerInfoList.0.passengerInfo.fareComponents');
        if (! is_array($list)) {
            $resolved = $this->resolveBrandFieldsFromFareNode($fare);

            return $resolved['name'] !== '' ? $resolved['name'] : null;
        }
        foreach ($list as $fc) {
            if (! is_array($fc)) {
                continue;
            }
            $brand = trim((string) ($fc['brandCode'] ?? $fc['fareFamily'] ?? $fc['fareFamilyCode'] ?? $fc['fareFamilyName'] ?? ''));
            if ($brand !== '') {
                return $brand;
            }
            $name = trim((string) ($fc['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
            $fareBasis = trim((string) ($fc['fareBasisCode'] ?? ''));
            if ($fareBasis !== '') {
                return $fareBasis;
            }
            $fareType = trim((string) ($fc['fareType'] ?? ''));
            if ($fareType !== '') {
                return $fareType;
            }
            $descList = $fc['fareComponentDesc'] ?? $fc['descriptions'] ?? null;
            if (is_array($descList)) {
                foreach ($descList as $row) {
                    if (is_string($row) && trim($row) !== '') {
                        return trim($row);
                    }
                    if (is_array($row)) {
                        $t = trim((string) ($row['value'] ?? $row['text'] ?? ''));
                        if ($t !== '') {
                            return $t;
                        }
                    }
                }
            }
            $descriptorName = $this->resolveBrandFieldsFromFareComponent($fc)['name'];
            if ($descriptorName !== '') {
                return $descriptorName;
            }
        }

        $resolved = $this->resolveBrandFieldsFromFareNode($fare);

        return $resolved['name'] !== '' ? $resolved['name'] : null;
    }

    /**
     * Collect baggage rows from all passengers and nested fare components (BFM v4).
     *
     * @param  array<string, mixed>  $fare
     * @return list<array<string, mixed>>
     */
    protected function collectBaggageInformationRows(array $fare): array
    {
        $rows = [];
        $list = $fare['passengerInfoList'] ?? null;
        if (! is_array($list)) {
            return [];
        }
        foreach ($list as $wrap) {
            if (! is_array($wrap)) {
                continue;
            }
            $pi = $wrap['passengerInfo'] ?? null;
            if (! is_array($pi)) {
                continue;
            }
            foreach ($pi['baggageInformation'] ?? [] as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
            foreach ($pi['fareComponents'] ?? [] as $fc) {
                if (! is_array($fc)) {
                    continue;
                }
                foreach ($fc['baggageInformation'] ?? [] as $row) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function extractBaggage(array $fare): BaggageAllowanceData
    {
        $bags = $this->collectBaggageInformationRows($fare);
        if ($bags === []) {
            return new BaggageAllowanceData;
        }

        $parts = [];
        $checkedHints = [];
        $cabinHints = [];

        foreach ($bags as $bagRow) {
            if (! is_array($bagRow)) {
                continue;
            }
            $typeRaw = strtolower((string) ($bagRow['type'] ?? $bagRow['provisionType'] ?? $bagRow['baggageType'] ?? ''));
            $allowance = $bagRow['allowance'] ?? null;
            $text = is_array($allowance)
                ? $this->resolveBaggageAllowanceHumanText($allowance)
                : '';
            if ($text === '' && is_array($allowance)) {
                $descriptorParts = $this->resolveBaggageDescriptorParts($allowance);
                if ($descriptorParts['checked'] !== null) {
                    $checkedHints[] = $descriptorParts['checked'];
                    $parts[] = $descriptorParts['checked'];
                }
                if ($descriptorParts['cabin'] !== null) {
                    $cabinHints[] = $descriptorParts['cabin'];
                    $parts[] = $descriptorParts['cabin'];
                }

                continue;
            }
            if ($text === '') {
                continue;
            }
            $parts[] = $text;

            if (str_contains($typeRaw, 'carry') || str_contains($typeRaw, 'cabin') || str_contains($typeRaw, 'carryon')) {
                $cabinHints[] = $text;
            } else {
                $checkedHints[] = $text;
                $split = BaggageDisplayNormalizer::splitCombinedSummary($text);
                if ($split['cabin'] !== null) {
                    $cabinHints[] = $split['cabin'];
                }
                if ($split['checked'] !== null && ! in_array($split['checked'], $checkedHints, true)) {
                    $checkedHints[] = $split['checked'];
                }
            }
        }

        if ($parts === []) {
            return new BaggageAllowanceData;
        }

        $summary = implode(' · ', array_values(array_unique($parts)));
        $checked = $checkedHints !== [] ? implode(' · ', array_values(array_unique($checkedHints))) : null;
        $cabin = $cabinHints !== [] ? implode(' · ', array_values(array_unique($cabinHints))) : null;

        return new BaggageAllowanceData(
            checked: BaggageDisplayNormalizer::normalizeLabel($checked),
            cabin: BaggageDisplayNormalizer::normalizeLabel($cabin),
            summary: BaggageDisplayNormalizer::normalizeLabel($summary) ?? $summary,
        );
    }

    protected function resolveBaggageAllowanceHumanText(array $allowance): string
    {
        foreach (['description', 'description1', 'description2', 'details', 'text'] as $k) {
            $t = trim((string) ($allowance[$k] ?? ''));
            if ($t !== '') {
                return $this->normalizeSabreBaggageHumanText($t);
            }
        }
        foreach (['pieceCount', 'numPieces', 'numberOfPieces', 'baggagePieces'] as $pk) {
            $pieces = $allowance[$pk] ?? null;
            if ($pieces !== null && $pieces !== '' && is_numeric($pieces)) {
                $n = (int) $pieces;

                return $this->normalizeSabreBaggageHumanText($n === 1 ? '1 piece' : $n.' pieces');
            }
        }
        $weight = trim((string) ($allowance['weight'] ?? $allowance['weightAllowance'] ?? ''));
        $unit = trim((string) ($allowance['unit'] ?? $allowance['unitOfMeasure'] ?? $allowance['weightUnit'] ?? ''));
        if ($weight !== '') {
            $u = strtoupper($unit);

            return $this->normalizeSabreBaggageHumanText(trim($weight.($u !== '' ? ' '.$u : '')));
        }
        $ref = null;
        if (isset($allowance['ref']) && is_numeric($allowance['ref'])) {
            $ref = (int) $allowance['ref'];
        } elseif (isset($allowance['id']) && is_numeric($allowance['id'])) {
            $ref = (int) $allowance['id'];
        }
        if ($ref === null) {
            return '';
        }
        $row = $this->resolveDescriptorKey('baggage', $ref);
        if ($row === null) {
            $row = $this->resolveDescriptorKey('fare_component', $ref);
        }
        if (! is_array($row)) {
            return '';
        }
        $descriptorParts = $this->resolveBaggageDescriptorParts(['ref' => $ref] + $row);
        if ($descriptorParts['combined'] !== null) {
            return $descriptorParts['combined'];
        }
        $d1 = trim((string) ($row['description1'] ?? ''));
        $d2 = trim((string) ($row['description2'] ?? ''));
        if ($d1 !== '' && $d2 !== '') {
            return $this->normalizeSabreBaggageHumanText($d1.' · '.$d2);
        }
        foreach (['description', 'description1', 'description2', 'text', 'details', 'value'] as $k) {
            $t = trim((string) ($row[$k] ?? ''));
            if ($t !== '') {
                return $this->normalizeSabreBaggageHumanText($t);
            }
        }
        foreach (['pieceCount', 'numPieces', 'numberOfPieces'] as $pk) {
            $pieces = $row[$pk] ?? null;
            if ($pieces !== null && $pieces !== '' && is_numeric($pieces)) {
                $n = (int) $pieces;

                return $this->normalizeSabreBaggageHumanText($n === 1 ? '1 piece' : $n.' pieces');
            }
        }
        $w = trim((string) ($row['weight'] ?? ''));
        $wu = strtoupper(trim((string) ($row['unit'] ?? $row['unitOfMeasure'] ?? '')));
        if ($w !== '') {
            return $this->normalizeSabreBaggageHumanText(trim($w.($wu !== '' ? ' '.$wu : '')));
        }
        $inner = $row['allowance'] ?? null;

        return is_array($inner) ? $this->resolveBaggageAllowanceHumanText($inner) : '';
    }

    /**
     * @param  array<string, mixed>  $allowance
     * @return array{checked: ?string, cabin: ?string, combined: ?string}
     */
    protected function resolveBaggageDescriptorParts(array $allowance): array
    {
        $ref = null;
        if (isset($allowance['ref']) && is_numeric($allowance['ref'])) {
            $ref = (int) $allowance['ref'];
        } elseif (isset($allowance['id']) && is_numeric($allowance['id'])) {
            $ref = (int) $allowance['id'];
        }

        $row = $allowance;
        if ($ref !== null) {
            $resolved = $this->resolveDescriptorKey('baggage', $ref);
            if ($resolved === null) {
                $resolved = $this->resolveDescriptorKey('fare_component', $ref);
            }
            if (is_array($resolved)) {
                $row = $resolved;
            }
        }

        $checked = null;
        $cabin = null;
        $combinedParts = [];
        foreach (['description1', 'description2', 'description', 'text', 'details'] as $key) {
            $part = trim((string) ($row[$key] ?? ''));
            if ($part === '') {
                continue;
            }
            $lower = strtolower($part);
            $fragment = BaggageDisplayNormalizer::extractAllowanceFragment($part)
                ?? BaggageDisplayNormalizer::normalizeLabel($part);
            if ($fragment === null || $fragment === '') {
                continue;
            }
            $combinedParts[] = $fragment;
            if (str_contains($lower, 'cabin') || str_contains($lower, 'carry') || str_contains($lower, 'hand')) {
                $cabin ??= $fragment;

                continue;
            }
            if (str_contains($lower, 'check') || str_contains($lower, 'hold')) {
                $checked ??= $fragment;
            }
        }

        if ($checked === null && $cabin === null && $combinedParts !== []) {
            $split = BaggageDisplayNormalizer::splitCombinedSummary(implode(' · ', $combinedParts));
            $checked = $split['checked'];
            $cabin = $split['cabin'];
        }

        $combined = $combinedParts !== []
            ? BaggageDisplayNormalizer::normalizeLabel(implode(' · ', array_values(array_unique($combinedParts))))
            : null;

        return [
            'checked' => $checked,
            'cabin' => $cabin,
            'combined' => $combined,
        ];
    }

    protected function normalizeSabreBaggageHumanText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return BaggageDisplayNormalizer::normalizeLabel($text) ?? $text;
    }

    /**
     * Merge BFM fare-component segment fields (booking class, fare basis, cabin letter) onto normalized segment rows.
     *
     * @param  list<array<string, mixed>>  $segmentRows
     * @param  array<string, mixed>  $fareNode  First priced fare from itinerary
     * @return list<array<string, mixed>>
     */
    protected function mergeFareBookingMetadataIntoSegmentRows(array $segmentRows, array $fareNode): array
    {
        $metaRows = $this->flattenFareComponentBookingSegments($fareNode);
        if ($metaRows === [] || $segmentRows === []) {
            return $segmentRows;
        }
        $n = count($segmentRows);
        if (count($metaRows) === $n) {
            for ($i = 0; $i < $n; $i++) {
                $segmentRows[$i] = $this->mergeOneSegmentBookingMetaRow($segmentRows[$i], $metaRows[$i]);
            }

            return $segmentRows;
        }
        $pool = $metaRows;
        foreach ($segmentRows as $i => $row) {
            $sig = $this->segmentBookingMatchSignature($row);
            $picked = null;
            $pickedKey = null;
            foreach ($pool as $k => $mr) {
                if ($this->segmentBookingMatchSignature($mr) === $sig) {
                    $picked = $mr;
                    $pickedKey = $k;
                    break;
                }
            }
            if ($picked !== null && $pickedKey !== null) {
                $segmentRows[$i] = $this->mergeOneSegmentBookingMetaRow($row, $picked);
                unset($pool[$pickedKey]);
            }
        }

        return $segmentRows;
    }

    /**
     * @param  array<string, mixed>  $fareNode
     * @return list<array<string, mixed>>
     */
    protected function flattenFareComponentBookingSegments(array $fareNode): array
    {
        $list = $fareNode['passengerInfoList'] ?? null;
        if (! is_array($list)) {
            return [];
        }
        $preferAdt = [];
        $fallback = [];
        foreach ($list as $wrap) {
            if (! is_array($wrap)) {
                continue;
            }
            $pi = $wrap['passengerInfo'] ?? null;
            if (! is_array($pi)) {
                continue;
            }
            $flat = $this->flattenOnePassengerFareComponentSegments($pi);
            if ($flat === []) {
                continue;
            }
            $pt = strtoupper(trim((string) ($pi['passengerType'] ?? $pi['passengerTypeCode'] ?? '')));
            if ($pt === 'ADT' || $pt === 'ADULT' || $pt === '') {
                $preferAdt = $flat;
                break;
            }
            if ($fallback === []) {
                $fallback = $flat;
            }
        }

        return $preferAdt !== [] ? $preferAdt : $fallback;
    }

    /**
     * Supplier fare-component segment rows from a stored offer snapshot (GIR archive; no live HTTP).
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    public function fareComponentBookingSegmentRowsFromOfferSnapshot(array $snapshot, array $meta = []): array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $gir = is_array($raw['sabre_bfm_gir_archive'] ?? null) ? $raw['sabre_bfm_gir_archive'] : [];
        if ($gir === []) {
            return [];
        }
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $fareNode = $this->resolveFareNodeFromGirArchive(
            $gir,
            (int) ($ctx['itinerary_group_index'] ?? $handoff['itinerary_group_index'] ?? 0),
            (int) ($ctx['itinerary_index'] ?? $handoff['itinerary_index'] ?? 0),
            (int) ($handoff['pricing_information_index'] ?? $ctx['pricing_information_index'] ?? $ctx['itinerary_pricing_index'] ?? 0),
        );
        if ($fareNode === null) {
            return [];
        }

        return $this->flattenFareComponentBookingSegments($fareNode);
    }

    /**
     * @param  array<string, mixed>  $passengerInfo
     * @return list<array<string, mixed>>
     */
    protected function flattenOnePassengerFareComponentSegments(array $passengerInfo): array
    {
        $out = [];
        foreach ($passengerInfo['fareComponents'] ?? [] as $fc) {
            if (! is_array($fc)) {
                continue;
            }
            $fcFareBasis = trim((string) ($fc['fareBasisCode'] ?? $fc['fareBasis'] ?? ''));
            if ($fcFareBasis === '') {
                foreach (['ref', 'fareComponentDescRef', 'fareComponentDescNumber', 'fareComponentDescIndex'] as $rk) {
                    if (! isset($fc[$rk]) || ! is_numeric($fc[$rk])) {
                        continue;
                    }
                    $desc = $this->resolveDescriptorKey('fare_component', (int) $fc[$rk]);
                    if (is_array($desc)) {
                        $fcFareBasis = trim((string) ($desc['fareBasisCode'] ?? $desc['fareBasis'] ?? ''));
                    }
                    if ($fcFareBasis !== '') {
                        break;
                    }
                }
            }
            foreach ($fc['segments'] ?? [] as $segWrap) {
                $seg = [];
                if (is_array($segWrap['segment'] ?? null)) {
                    $seg = $segWrap['segment'];
                } elseif (is_array($segWrap)) {
                    $seg = $segWrap;
                }
                if ($seg === []) {
                    continue;
                }
                $dep = is_array($seg['departure'] ?? null) ? $seg['departure'] : [];
                $arr = is_array($seg['arrival'] ?? null) ? $seg['arrival'] : [];
                $origin = strtoupper(trim((string) ($dep['locationCode'] ?? $dep['airport'] ?? $dep['airportCode'] ?? '')));
                $destination = strtoupper(trim((string) ($arr['locationCode'] ?? $arr['airport'] ?? $arr['airportCode'] ?? '')));
                $mkt = $seg['marketingAirline'] ?? $seg['marketing_airline'] ?? null;
                $carrier = '';
                if (is_string($mkt)) {
                    $carrier = strtoupper(trim($mkt));
                } elseif (is_array($mkt)) {
                    $carrier = strtoupper(trim((string) ($mkt['code'] ?? $mkt['airlineCode'] ?? '')));
                }
                if ($carrier === '') {
                    $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airlineCode'] ?? '')));
                }
                $flightNumber = trim((string) ($seg['flightNumber'] ?? $seg['flight_number'] ?? ''));
                if ($flightNumber === '' && is_array($mkt)) {
                    $flightNumber = trim((string) ($mkt['flightNumber'] ?? ''));
                }
                $bookingClass = trim((string) ($seg['resBookDesigCode'] ?? $seg['bookingCode'] ?? $seg['classOfService'] ?? $seg['resBookDesig'] ?? ''));
                $fareBasis = trim((string) ($seg['fareBasisCode'] ?? $seg['fareBasis'] ?? $fcFareBasis));
                $cabinLetter = strtoupper(trim((string) ($seg['cabinCode'] ?? $seg['cabin'] ?? '')));
                $out[] = [
                    'origin' => $origin,
                    'destination' => $destination,
                    'carrier' => $carrier,
                    'flight_number' => $flightNumber,
                    'booking_class' => $bookingClass !== '' ? strtoupper($bookingClass) : null,
                    'fare_basis_code' => $fareBasis !== '' ? strtoupper($fareBasis) : null,
                    'segment_cabin_code' => $cabinLetter !== '' ? $cabinLetter : null,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function segmentBookingMatchSignature(array $row): string
    {
        $o = strtoupper(trim((string) ($row['origin'] ?? '')));
        $d = strtoupper(trim((string) ($row['destination'] ?? '')));
        $c = strtoupper(trim((string) ($row['carrier'] ?? $row['airline_code'] ?? '')));
        $fn = preg_replace('/\D+/', '', (string) ($row['flight_number'] ?? ''));

        return $o.'|'.$d.'|'.$c.'|'.$fn;
    }

    /**
     * @param  array<string, mixed>  $segmentRow
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function mergeOneSegmentBookingMetaRow(array $segmentRow, array $meta): array
    {
        foreach (['booking_class', 'fare_basis_code', 'segment_cabin_code'] as $k) {
            $cur = $segmentRow[$k] ?? null;
            if (is_string($cur) && trim($cur) !== '') {
                continue;
            }
            $v = $meta[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $segmentRow[$k] = $k === 'fare_basis_code' ? strtoupper(trim($v)) : $v;
            }
        }

        return $segmentRow;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function keyTree(array $node, int $depth): array
    {
        if ($depth <= 0) {
            return ['_truncated' => true];
        }
        $out = [];
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                if (array_is_list($v)) {
                    $first = $v[0] ?? null;
                    $out[$k] = [
                        '_type' => 'list',
                        'length' => count($v),
                        'first_element_keys' => is_array($first) ? array_keys($first) : null,
                    ];
                } else {
                    $out[$k] = $this->keyTree($v, $depth - 1);
                }
            } else {
                $out[$k] = 'scalar';
            }
        }

        return $out;
    }

    /**
     * Server-only redacted GIR slice for Sabre revalidate payload reconstruction (never exposed on public JSON).
     *
     * @param  array<string, mixed>  $itinerary
     * @param  array<string, mixed>  $fareNode
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    public function buildSabreBfmGirArchiveSlice(array $itinerary, array $fareNode, array $segments): array
    {
        $lists = $this->currentGirDescriptorLists;
        if (! is_array($lists)) {
            return [];
        }

        $legRefs = $this->collectNumericRefs(is_array($itinerary['legs'] ?? null) ? $itinerary['legs'] : []);
        $scheduleRefs = $this->collectScheduleRefsForLegs($legRefs);
        $fareComponentRefs = $this->collectFareComponentRefsFromFareNode($fareNode, true);
        $sellRows = $this->flattenFareComponentBookingSegments($fareNode);

        $pricingSlice = [];
        $piList = is_array($itinerary['pricingInformation'] ?? null) ? array_values($itinerary['pricingInformation']) : [];
        if (isset($piList[0]) && is_array($piList[0])) {
            $pricingSlice = $piList[0];
        }

        return array_filter([
            'schema' => 'sabre_bfm_gir_archive_v1',
            'itinerary_group_index' => (int) ($itinerary['_ota_itinerary_group_index'] ?? 0),
            'itinerary_index' => (int) ($itinerary['_ota_itinerary_index'] ?? 0),
            'itinerary_ref' => $this->firstSafeScalar($itinerary, ['id', 'ref']),
            'segment_count' => count($segments),
            'segment_sell_rows' => array_slice($sellRows, 0, 24),
            'leg_refs' => array_slice($legRefs, 0, 24),
            'schedule_refs' => array_slice($scheduleRefs, 0, 48),
            'fare_component_desc_refs' => array_slice($fareComponentRefs, 0, 24),
            'legDescs' => $this->filterDescriptorRowsByRefs($lists['legDescs'] ?? [], $legRefs),
            'scheduleDescs' => $this->filterDescriptorRowsByRefs($lists['scheduleDescs'] ?? [], $scheduleRefs),
            'fareComponentDescs' => $this->filterDescriptorRowsByRefs($lists['fareComponentDescs'] ?? [], $fareComponentRefs),
            'pricingInformation' => $pricingSlice !== [] ? [$pricingSlice] : [],
            'itinerary_legs' => is_array($itinerary['legs'] ?? null) ? array_slice($itinerary['legs'], 0, 12) : [],
        ], static fn ($v): bool => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $refs
     * @return list<array<string, mixed>>
     */
    protected function filterDescriptorRowsByRefs(array $rows, array $refs): array
    {
        if ($rows === [] || $refs === []) {
            return [];
        }
        $want = [];
        foreach ($refs as $ref) {
            if (is_numeric($ref)) {
                $want[(int) $ref] = true;
            }
        }
        $out = [];
        foreach ($rows as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = null;
            foreach (['id', 'ref'] as $k) {
                if (isset($row[$k]) && is_numeric($row[$k])) {
                    $id = (int) $row[$k];
                    break;
                }
            }
            if ($id === null) {
                $id = $idx;
            }
            if (isset($want[$id])) {
                $out[] = $row;
            }
        }

        return array_slice($out, 0, 48);
    }

    /**
     * Compact, safe booking handoff slice stored on {@code raw_payload.sabre_booking_context} (no raw BFM JSON / credentials).
     *
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $shopContext
     * @param  list<string>  $fareBasisCodes
     * @param  array<string, mixed>  $readiness  {@see SabreStoredPricingContextDigest::assessReadiness()}
     * @return array<string, mixed>
     */
    public function buildSabreBookingContextHandoff(
        int $supplierConnectionId,
        array $segments,
        array $shopContext,
        string $validatingCarrier,
        array $fareBasisCodes,
        ?string $brandCode,
        array $readiness,
    ): array {
        $bookingBySeg = [];
        $fareBasisBySeg = [];
        $cabinBySeg = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $bookingBySeg[] = strtoupper(trim((string) ($seg['booking_class'] ?? '')));
            $fareBasisBySeg[] = strtoupper(trim((string) ($seg['fare_basis_code'] ?? '')));
            $cab = strtolower(trim((string) ($seg['segment_cabin_code'] ?? $seg['cabin'] ?? '')));
            $cabinBySeg[] = $cab !== '' ? substr($cab, 0, 32) : '';
        }

        $ctxBooking = is_array($shopContext['booking_class'] ?? null) ? $shopContext['booking_class'] : [];
        if ($bookingBySeg === [] && $ctxBooking !== []) {
            foreach ($ctxBooking as $bc) {
                $bookingBySeg[] = strtoupper(trim((string) $bc));
            }
        }

        $ctxFareBasis = is_array($shopContext['fare_basis_codes'] ?? null) ? $shopContext['fare_basis_codes'] : [];
        if ($fareBasisBySeg === [] && $fareBasisCodes !== []) {
            $fareBasisBySeg = array_map(
                static fn ($v): string => strtoupper(trim((string) $v)),
                $fareBasisCodes
            );
        } elseif ($fareBasisBySeg === [] && $ctxFareBasis !== []) {
            foreach ($ctxFareBasis as $fb) {
                $fareBasisBySeg[] = strtoupper(trim((string) $fb));
            }
        }

        $sliceCount = count($segments);
        $hasReval = ($readiness['has_revalidation_linkage_complete'] ?? false) === true;
        $readyPayload = ($readiness['auto_pnr_pricing_context_ready'] ?? false) === true
            && $sliceCount > 0
            && $this->nonEmptyStringList($bookingBySeg);

        $handoff = [
            'supplier_connection_id' => $supplierConnectionId > 0 ? $supplierConnectionId : null,
            'supplier_provider' => SupplierProvider::Sabre->value,
            'pricing_information_index' => (int) ($shopContext['pricing_information_index'] ?? 0),
            'validating_carrier' => $validatingCarrier !== '' ? substr($validatingCarrier, 0, 8) : null,
            'brand_code' => $brandCode !== null && $brandCode !== '' ? substr($brandCode, 0, 32) : null,
            'booking_classes_by_segment' => array_values($bookingBySeg),
            'fare_basis_codes_by_segment' => array_values($fareBasisBySeg),
            'cabin_by_segment' => array_values($cabinBySeg),
            'segment_slice_count' => $sliceCount,
            'has_revalidation_linkage' => $hasReval,
            'ready_for_booking_payload' => $readyPayload,
            'shop_endpoint_path' => isset($shopContext['shop_endpoint_path'])
                ? substr(trim((string) $shopContext['shop_endpoint_path']), 0, 120)
                : null,
            'distribution_channel' => $this->inferDistributionChannelFromShopContext($shopContext),
        ];

        return array_filter($handoff, static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Backfill {@code sabre_booking_context} on cached/search display rows that predate Sprint 1B (no raw Sabre JSON).
     *
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public function ensureSabreBookingContextOnCachedOffer(array $offer): array
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'sabre') !== 0) {
            return $offer;
        }

        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $existing = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
        if (($existing['segment_slice_count'] ?? 0) > 0 && is_array($existing['booking_classes_by_segment'] ?? null)) {
            $offer['sabre_booking_context'] = $existing;

            return $offer;
        }

        $segments = is_array($offer['segments'] ?? null) ? array_values($offer['segments']) : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        if ($ctx !== [] && $ids !== []) {
            $ctx = $this->syncShopContextLinkageFromIdentifiers($ctx, $ids);
        }
        $fare = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
        $fareBasis = is_array($fare['fare_basis_codes'] ?? null) ? $fare['fare_basis_codes'] : [];
        $validating = strtoupper(trim((string) ($offer['validating_carrier'] ?? $ctx['validating_carrier'] ?? '')));
        $readiness = (new SabreStoredPricingContextDigest)->assessReadiness(array_merge($offer, [
            'raw_payload' => array_merge($raw, ['sabre_shop_context' => $ctx, 'sabre_shop_identifiers' => $ids]),
        ]));
        $brand = trim((string) ($offer['fare_family'] ?? ''));
        if ($brand === '') {
            $brand = trim((string) ($ctx['brand_code'] ?? ''));
        }

        $handoff = $this->buildSabreBookingContextHandoff(
            (int) ($offer['supplier_connection_id'] ?? 0),
            $segments,
            $ctx,
            $validating,
            $fareBasis,
            $brand !== '' ? $brand : null,
            $readiness,
        );

        $offer['raw_payload'] = $raw;
        $offer['sabre_booking_context'] = $handoff;

        return $this->mergeSabrePricingLinkageHandoff($offer);
    }

    /**
     * Apply a selected branded-fare row onto the parent offer snapshot before validation/booking meta (Sprint 1B handoff).
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $selectedOption  Row from {@see FlightOfferDisplayPresenter::findFareFamilyOptionByKey()} or raw branded_fares
     * @return array<string, mixed>
     */
    public function applyBrandedFareOptionToOfferSnapshot(array $offer, array $selectedOption): array
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'sabre') !== 0) {
            return $offer;
        }

        $piIndex = isset($selectedOption['pricing_information_index']) && is_numeric($selectedOption['pricing_information_index'])
            ? (int) $selectedOption['pricing_information_index']
            : null;
        $sourceRow = null;
        foreach (is_array($offer['branded_fares'] ?? null) ? $offer['branded_fares'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($piIndex !== null && (int) ($row['pricing_information_index'] ?? -1) === $piIndex) {
                $sourceRow = $row;
                break;
            }
        }
        if ($sourceRow === null) {
            $sourceRow = $selectedOption;
        }

        $offer = $this->ensureSabreBookingContextOnCachedOffer($offer);
        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        if ($piIndex !== null) {
            $ctx['pricing_information_index'] = $piIndex;
            if (trim((string) ($sourceRow['pricing_information_ref'] ?? '')) !== '') {
                $ctx['pricing_information_ref'] = substr(trim((string) $sourceRow['pricing_information_ref']), 0, 120);
            }
            if (trim((string) ($sourceRow['pricing_information_id'] ?? '')) !== '') {
                $ctx['pricing_information_id'] = substr(trim((string) $sourceRow['pricing_information_id']), 0, 120);
            }
            if (trim((string) ($sourceRow['offer_ref'] ?? '')) !== '') {
                $ctx['offer_ref'] = substr(trim((string) $sourceRow['offer_ref']), 0, 120);
            }
            if (trim((string) ($sourceRow['supplier_offer_id'] ?? '')) !== '') {
                $ctx['offer_id'] = substr(trim((string) $sourceRow['supplier_offer_id']), 0, 120);
            }
        }
        $raw['sabre_shop_context'] = $ctx;
        $raw['fare_option_key'] = trim((string) ($selectedOption['option_key'] ?? $selectedOption['fare_option_key'] ?? ''));
        $offer['raw_payload'] = $raw;
        $offer['fare_option_key'] = $raw['fare_option_key'] !== '' ? $raw['fare_option_key'] : null;

        $name = trim((string) ($sourceRow['name'] ?? ''));
        if ($name !== '') {
            $offer['fare_family'] = $name;
        }
        $brand = trim((string) ($sourceRow['supplier_brand_code'] ?? $sourceRow['brand_code'] ?? ''));
        if ($brand !== '') {
            $offer['brand_code'] = $brand;
        }
        if (isset($sourceRow['refundable'])) {
            $offer['refundable'] = (bool) $sourceRow['refundable'];
        }
        if (isset($sourceRow['cabin']) && is_string($sourceRow['cabin']) && trim($sourceRow['cabin']) !== '') {
            $offer['cabin'] = trim($sourceRow['cabin']);
        }
        if (isset($sourceRow['validating_carrier']) && trim((string) $sourceRow['validating_carrier']) !== '') {
            $offer['validating_carrier'] = strtoupper(trim((string) $sourceRow['validating_carrier']));
        }

        $bookingBySeg = is_array($sourceRow['booking_classes_by_segment'] ?? null) ? $sourceRow['booking_classes_by_segment'] : [];
        $fareBasisBySeg = is_array($sourceRow['fare_basis_codes_by_segment'] ?? null) ? $sourceRow['fare_basis_codes_by_segment'] : [];
        $cabinBySeg = is_array($sourceRow['cabin_by_segment'] ?? null) ? $sourceRow['cabin_by_segment'] : [];
        $segments = is_array($offer['segments'] ?? null) ? array_values($offer['segments']) : [];
        if ($segments !== [] && ($bookingBySeg !== [] || $fareBasisBySeg !== [] || $cabinBySeg !== [])) {
            foreach ($segments as $i => $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                if (isset($bookingBySeg[$i]) && trim((string) $bookingBySeg[$i]) !== '') {
                    $seg['booking_class'] = strtoupper(trim((string) $bookingBySeg[$i]));
                }
                if (isset($fareBasisBySeg[$i]) && trim((string) $fareBasisBySeg[$i]) !== '') {
                    $seg['fare_basis_code'] = strtoupper(trim((string) $fareBasisBySeg[$i]));
                }
                if (isset($cabinBySeg[$i]) && trim((string) $cabinBySeg[$i]) !== '') {
                    $seg['segment_cabin_code'] = strtolower(trim((string) $cabinBySeg[$i]));
                }
                $segments[$i] = $seg;
            }
            $offer['segments'] = $segments;
        }

        $fare = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
        $fbcList = is_array($sourceRow['fare_basis_codes'] ?? null) ? $sourceRow['fare_basis_codes'] : [];
        if ($fbcList === [] && $fareBasisBySeg !== []) {
            $fbcList = $fareBasisBySeg;
        }
        if ($fbcList !== []) {
            $fare['fare_basis_codes'] = array_values(array_filter(array_map(
                static fn ($v): string => strtoupper(trim((string) $v)),
                $fbcList
            ), static fn (string $s): bool => $s !== ''));
            $offer['fare_breakdown'] = $fare;
        }

        $priceTotal = (float) ($sourceRow['price_total'] ?? 0);
        if ($priceTotal > 0) {
            $fare['supplier_total'] = $priceTotal;
            $offer['fare_breakdown'] = $fare;
        }

        $readiness = (new SabreStoredPricingContextDigest)->assessBrandedFareOptionReadiness($sourceRow);
        $handoff = $this->buildSabreBookingContextHandoff(
            (int) ($offer['supplier_connection_id'] ?? 0),
            $segments,
            $ctx,
            strtoupper(trim((string) ($offer['validating_carrier'] ?? ''))),
            is_array($fare['fare_basis_codes'] ?? null) ? $fare['fare_basis_codes'] : [],
            $brand !== '' ? $brand : null,
            array_merge($readiness, [
                'has_revalidation_linkage_complete' => ($readiness['has_revalidation_linkage'] ?? false) === true,
                'auto_pnr_pricing_context_ready' => ($readiness['ready_for_booking_payload'] ?? false) === true,
            ]),
        );
        $handoff['selected_pricing_information_index'] = $piIndex;
        $handoff['has_segment_booking_linkage'] = ($readiness['has_segment_booking_linkage'] ?? false) === true;
        $raw['sabre_booking_context'] = $handoff;
        $offer['raw_payload'] = $raw;
        $offer['sabre_booking_context'] = $handoff;

        return $this->mergeSabrePricingLinkageHandoff($offer);
    }

    /**
     * Sprint 11E: Ensure cached/checkout offer arrays retain safe BFM pricing/offer linkage for PNR context rebuild.
     *
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public function mergeSabrePricingLinkageHandoff(array $offer): array
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), 'sabre') !== 0) {
            return $offer;
        }

        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        if ($ctx !== [] && $ids !== []) {
            $ctx = $this->syncShopContextLinkageFromIdentifiers($ctx, $ids);
        }

        $segments = is_array($offer['segments'] ?? null) ? array_values($offer['segments']) : [];
        $carrierDisplay = [
            'marketing_carrier_chain' => is_array($offer['marketing_carrier_chain'] ?? null) ? $offer['marketing_carrier_chain'] : [],
        ];
        $raw = $this->mergeSabrePricingLinkageScalarsIntoRawPayload($raw, $ctx, $ids, $segments, $carrierDisplay);
        $offer['raw_payload'] = $raw;
        $handoff = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
        if ($handoff !== []) {
            $offer['sabre_booking_context'] = $handoff;
        }

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $shopContext
     * @param  array<string, mixed>  $shopIdentifiers
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $carrierDisplay
     * @return array<string, mixed>
     */
    protected function mergeSabrePricingLinkageScalarsIntoRawPayload(
        array $raw,
        array $shopContext,
        array $shopIdentifiers,
        array $segments,
        array $carrierDisplay,
    ): array {
        $ctx = $shopContext;
        if ($ctx !== [] && $shopIdentifiers !== []) {
            $ctx = $this->syncShopContextLinkageFromIdentifiers($ctx, $shopIdentifiers);
        }

        $piRef = trim((string) ($ctx['pricing_information_ref'] ?? $raw['pricing_information_ref'] ?? ''));
        if ($piRef === '') {
            $piRef = $this->firstSafeScalar($shopIdentifiers, [
                'pricing_0_ref', 'pricing_0_pricingInformationRef', 'pricing_0_pricingRef',
                'pricing_0_offerItemId', 'pricing_0_offerItemRef', 'pricing_0_offerItemReference',
            ]);
        }
        if ($piRef !== '') {
            $ctx['pricing_information_ref'] = substr($piRef, 0, 120);
            $raw['pricing_information_ref'] = substr($piRef, 0, 120);
        }

        $offerRef = trim((string) ($ctx['offer_ref'] ?? $raw['offer_reference'] ?? ''));
        if ($offerRef === '') {
            $offerRef = $this->firstSafeScalar($shopIdentifiers, [
                'pricing_0_offer_ref', 'pricing_0_offerRef', 'pricing_0_offerReference',
                'pricing_0_offer_id', 'pricing_0_offerId', 'pricing_0_offerID',
                'pricing_0_offerItem_ref', 'pricing_0_offerItemRef',
            ]);
        }
        if ($offerRef === '' && trim((string) ($ctx['offer_id'] ?? '')) !== '') {
            $offerRef = trim((string) $ctx['offer_id']);
        }
        if ($offerRef !== '') {
            $ctx['offer_ref'] = substr($offerRef, 0, 120);
            $raw['offer_reference'] = substr($offerRef, 0, 120);
        }

        $itinRef = trim((string) ($ctx['itinerary_ref'] ?? $raw['itinerary_reference'] ?? ''));
        if ($itinRef === '') {
            $itinRef = $this->firstSafeScalar($shopIdentifiers, ['itinerary_id', 'itinerary_ref']);
        }
        if ($itinRef !== '') {
            $ctx['itinerary_ref'] = substr($itinRef, 0, 120);
            $raw['itinerary_reference'] = substr($itinRef, 0, 120);
        }

        $raw['sabre_shop_context'] = $ctx;
        if ($shopIdentifiers !== []) {
            $raw['sabre_shop_identifiers'] = $shopIdentifiers;
        }

        $handoff = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
        if ($piRef !== '') {
            $handoff['pricing_information_ref'] = substr($piRef, 0, 120);
        }
        if ($offerRef !== '') {
            $handoff['offer_reference'] = substr($offerRef, 0, 120);
        }
        if ($itinRef !== '') {
            $handoff['itinerary_reference'] = substr($itinRef, 0, 120);
        }
        $vc = strtoupper(trim((string) ($ctx['validating_carrier'] ?? '')));
        if ($vc !== '') {
            $handoff['validating_carrier'] = substr($vc, 0, 8);
        }
        $chain = is_array($ctx['carrier_chain'] ?? null) ? $ctx['carrier_chain'] : [];
        if ($chain === [] && is_array($carrierDisplay['marketing_carrier_chain'] ?? null)) {
            $chain = array_values(array_filter(array_map(
                static fn ($v): string => strtoupper(trim((string) $v)),
                $carrierDisplay['marketing_carrier_chain']
            ), static fn (string $v): bool => $v !== ''));
        }
        if ($chain !== []) {
            $handoff['carrier_chain'] = array_slice($chain, 0, 12);
        }
        $legRefs = is_array($ctx['leg_refs'] ?? null) ? $ctx['leg_refs'] : [];
        if ($legRefs !== []) {
            $handoff['leg_refs'] = array_slice($legRefs, 0, 24);
        }
        $scheduleRefs = is_array($ctx['schedule_refs'] ?? null) ? $ctx['schedule_refs'] : [];
        if ($scheduleRefs !== []) {
            $handoff['schedule_refs'] = array_slice($scheduleRefs, 0, 48);
        }
        $handoff['segment_count'] = count($segments);
        if ($handoff !== []) {
            $raw['sabre_booking_context'] = array_filter($handoff, static fn ($v) => $v !== null && $v !== []);
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $shopContext
     */
    protected function inferDistributionChannelFromShopContext(array $shopContext): string
    {
        foreach (['pricing_subsource', 'fare_source', 'itinerary_source'] as $key) {
            $v = strtolower(trim((string) ($shopContext[$key] ?? '')));
            if ($v !== '' && str_contains($v, 'ndc')) {
                return 'ndc';
            }
        }

        return 'gds';
    }

    /**
     * @param  mixed  $list
     */
    protected function nonEmptyStringList($list): bool
    {
        if (! is_array($list)) {
            return false;
        }
        foreach ($list as $v) {
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Public wrapper for branded-fare / GIR fare-node per-segment booking slices (no raw payload return).
     *
     * @param  array<string, mixed>  $fareNode
     * @return array{
     *     fare_basis_codes_by_segment: list<string>,
     *     booking_classes_by_segment: list<string>,
     *     cabin_by_segment: list<string>,
     *     segment_slice_count: int
     * }
     */
    public function segmentBookingSlicesFromFareNode(array $fareNode): array
    {
        return $this->collectBrandedFareSegmentBookingSlices($fareNode);
    }

    /**
     * Resolve a priced fare node from a stored BFM GIR archive + shop indices (no live HTTP).
     *
     * @param  array<string, mixed>  $girArchive
     * @return array<string, mixed>|null
     */
    public function resolveFareNodeFromGirArchive(
        array $girArchive,
        int $itineraryGroupIndex,
        int $itineraryIndex,
        int $pricingInformationIndex,
    ): ?array {
        $groups = data_get($girArchive, 'groupedItineraryResponse.itineraryGroups');
        if (! is_array($groups) || $groups === []) {
            return null;
        }
        $group = $groups[$itineraryGroupIndex] ?? null;
        if (! is_array($group)) {
            return null;
        }
        $itineraries = is_array($group['itineraries'] ?? null) ? array_values($group['itineraries']) : [];
        $itinerary = $itineraries[$itineraryIndex] ?? null;
        if (! is_array($itinerary)) {
            return null;
        }
        $pricing = is_array($itinerary['pricingInformation'] ?? null)
            ? array_values($itinerary['pricingInformation'])
            : [];
        $piRow = $pricing[$pricingInformationIndex] ?? null;
        if (! is_array($piRow)) {
            return null;
        }
        $fare = $piRow['fare'] ?? null;

        return is_array($fare) ? $fare : null;
    }
}
