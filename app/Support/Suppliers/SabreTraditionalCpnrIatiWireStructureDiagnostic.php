<?php

namespace App\Support\Suppliers;

/**
 * Safe structural comparison between OTA traditional Passenger Records CPNR wire
 * and the operational GDS JSON shape used by Binham IATI (`SABRE_CREATE_PNR` GDS branch).
 *
 * Compares **key paths only** (no passenger names, phones, emails, DOB, or document numbers).
 * Reference template is frozen from IATI `modules/flights/sabre/helper.php` (GDS branch, ~2026-05).
 *
 * **B57:** {@see cpnrKeyNameInventory} adds side-by-side key-name inventories (PersonName row unions, Email row unions,
 * FlightSegment unions, AirPrice/PriceRequestInformation) for host-warning triage without reading PII values.
 * **B60:** {@see analyze} appends {@code b60_post_b59_residual_hypotheses} + semantic note when Passenger Records stays Incomplete after B59 (RetryRebook, AirBook Redisplay, SpecialServiceInfo, Brand, inventory staleness).
 * **B61:** When {@code suppliers.sabre.traditional_cpnr_airbook_retry_redisplay} is true and OTA {@code AirBook} carries matching helper blocks with **boolean** {@code RetryRebook.Option=true} (**B61B**) and **integer** {@code NumAttempts}/{@code WaitInterval} (**B61A**), {@code key_paths_only_in_iati_template} suppresses {@code AirBook.RetryRebook*} / {@code AirBook.RedisplayReservation*} residual prefixes.
 */
final class SabreTraditionalCpnrIatiWireStructureDiagnostic
{
    public const IATI_REFERENCE_SOURCE = 'binham_iati_modules_flights_sabre_helper_SABRE_CREATE_PNR_gds_branch';

    public const OTA_PASSENGER_RECORDS_CREATE_PATH = '/v2.5.0/passenger/records?mode=create';

    public const IATI_KNOWN_PASSENGER_RECORDS_CREATE_PATH = '/v2.4.0/passenger/records?mode=create';

    /**
     * @return array<string, mixed>
     */
    public static function analyze(array $otaCreatePassengerNameRecordRq): array
    {
        $iati = self::iatiOperationalGdsCpnrKeyTemplate();
        $otaPaths = self::collectStructuralKeyPaths($otaCreatePassengerNameRecordRq);
        $iatiPaths = self::collectStructuralKeyPaths($iati);

        $otaSet = array_flip($otaPaths);
        $iatiSet = array_flip($iatiPaths);

        $onlyIatiRaw = array_values(array_filter($iatiPaths, static fn (string $p): bool => ! isset($otaSet[$p])));
        $onlyOta = array_values(array_filter($otaPaths, static fn (string $p): bool => ! isset($iatiSet[$p])));

        sort($onlyIatiRaw);
        sort($onlyOta);

        $b61Cfg = (bool) config('suppliers.sabre.traditional_cpnr_airbook_retry_redisplay', false);
        $b61OtaSatisfied = self::otaAirBookRetryRedisplayExperimentStructuralMatch($otaCreatePassengerNameRecordRq);
        $b61SuppressIatiDelta = $b61Cfg && $b61OtaSatisfied;
        $onlyIatiForDelta = $onlyIatiRaw;
        if ($b61SuppressIatiDelta) {
            $onlyIatiForDelta = array_values(array_filter($onlyIatiRaw, static function (string $p): bool {
                foreach (['AirBook.RetryRebook', 'AirBook.RedisplayReservation'] as $pre) {
                    if ($p === $pre || str_starts_with($p, $pre.'.')) {
                        return false;
                    }
                }

                return true;
            }));
        }

        $b60Residual = [
            'B59 PassengerType / OptionalQualifiers.PricingQualifiers structural delta vs IATI: closed when wire_air_price_passenger_type_contract_valid.',
            '1) AirBook.RetryRebook — IATI template includes; OTA omits unless suppliers.sabre.traditional_cpnr_airbook_retry_redisplay is true with NumAttempts/WaitInterval (B61).',
            '2) AirBook.RedisplayReservation (AirBook subtree) — IATI template includes; OTA omits unless B61 flag is true with NumAttempts/WaitInterval.',
            '3) SpecialReqDetails.SpecialService.SpecialServiceInfo — IATI template includes; OTA omits Service subtree (B54 schema).',
            '4) AirPrice OptionalQualifiers.PricingQualifiers.Brand — IATI template includes; OTA omits until a safe source exists (B61+).',
            '5) Booking context: shop/offer staleness or unbookable inventory may still yield Incomplete even when JSON contract is valid.',
        ];
        if ($b61SuppressIatiDelta) {
            $b60Residual[] = 'B61: key_paths_only_in_iati_template suppresses AirBook.RetryRebook* and AirBook.RedisplayReservation* when the experiment is enabled and OTA wire carries both helper blocks with NumAttempts/WaitInterval.';
        }

        return [
            'iati_reference_source' => self::IATI_REFERENCE_SOURCE,
            'cpnr_key_name_inventory' => [
                'ota' => self::cpnrKeyNameInventory($otaCreatePassengerNameRecordRq),
                'iati_template' => self::cpnrKeyNameInventory($iati),
            ],
            'b57_host_warning_correlation' => self::hostWarningCorrelationNotes(),
            'passenger_records_paths' => [
                'ota_schema_aligned_default' => self::OTA_PASSENGER_RECORDS_CREATE_PATH,
                'iati_operational_known' => self::IATI_KNOWN_PASSENGER_RECORDS_CREATE_PATH,
                'note' => 'Path/version differ; JSON uses the same CreatePassengerNameRecordRQ family. EnhancedAirBook is absent in both stacks — compare AirBook + root AirPrice.',
            ],
            'enhanced_airbook' => [
                'present_in_ota_wire' => self::pathSetHasPrefix($otaPaths, 'AirBook.EnhancedAirBook'),
                'present_in_iati_reference' => self::pathSetHasPrefix($iatiPaths, 'AirBook.EnhancedAirBook'),
            ],
            'cpnr_version' => [
                'ota_wire_value' => is_scalar($otaCreatePassengerNameRecordRq['version'] ?? null)
                    ? (string) $otaCreatePassengerNameRecordRq['version']
                    : null,
                'iati_operational_template' => is_scalar($iati['version'] ?? null) ? (string) $iati['version'] : null,
            ],
            'structural_key_path_counts' => [
                'ota' => count($otaPaths),
                'iati_template' => count($iatiPaths),
            ],
            'b61_airbook_retry_redisplay_config_on' => $b61Cfg,
            'b61_airbook_retry_redisplay_ota_experiment_satisfied' => $b61OtaSatisfied,
            'b61_key_paths_only_in_iati_template_airbook_retry_redisplay_suppressed' => $b61SuppressIatiDelta,
            'key_paths_only_in_iati_template' => $onlyIatiForDelta,
            'key_paths_only_in_iati_template_unadjusted_count' => count($onlyIatiRaw),
            'key_paths_only_in_ota_wire' => $onlyOta,
            'focused' => [
                'TravelItineraryAddInfo' => self::filterPathsByPrefix($onlyIatiForDelta, $onlyOta, 'TravelItineraryAddInfo'),
                'AirBook' => self::filterPathsByPrefix($onlyIatiForDelta, $onlyOta, 'AirBook'),
                'AirPrice' => self::filterPathsByPrefix($onlyIatiForDelta, $onlyOta, 'AirPrice'),
            ],
            'semantic_notes' => [
                'IATI sends SpecialReqDetails.SpecialService.SpecialServiceInfo (AdvancePassenger, SecureFlight, Service/SSR); OTA strips SpecialService for Passenger Records schema (B54) and pushes TTL hints via AddRemark only.',
                'IATI AgencyInfo.Ticketing includes ShortText; OTA Ticketing omits ShortText (TicketType only).',
                'IATI CustomerInfo.Email rows include Type (e.g. TO); OTA may emit Address-only Email rows.',
                'IATI PersonName rows include Infant flag; OTA PersonName rows use GivenName/Surname/PassengerType/NameNumber.',
                'IATI AirBook adds RetryRebook and RedisplayReservation (NumAttempts/WaitInterval) alongside sell ODI; OTA omits those keys unless B61 suppliers.sabre.traditional_cpnr_airbook_retry_redisplay is enabled.',
                'HaltOnStatus code lists differ (IATI includes KK, UN, UU; OTA includes NN, WN — compare live host behavior separately).',
                'IATI root AirPrice uses OptionalQualifiers.PricingQualifiers (PassengerType quantities, optional Brand); OTA emits PassengerType rows (B59) and omits Brand until explicitly required.',
                'OTA FlightSegment may include MarriageGrp and OperatingAirline; IATI template segment omits those optional sell keys.',
                'B57: Sabre host text `.FRMT.NOT ENT BGNG WITH` under TravelItineraryAddInfoLLSRQ often ties to PNR name/contact/email/SSR formatting; compare Email.Type, PersonName fields, and CTCE/CTCM-style SSRs present in IATI but omitted on OTA wire.',
                'B57: `EnhancedAirBookRQ *NO FARES/RBD/CARRIER` usually means the sell leg lacks a bookable class/carrier context for inventory, or root AirPrice lacks pricing qualifiers (PassengerType/Brand) the host expects for that flow — validate ResBookDesigCode + MarketingAirline/FlightNumber and consider OptionalQualifiers next (B58).',
                'B59: PassengerType / PricingQualifiers gap vs IATI template is closed on OTA traditional wire when contract passes.',
                'B60: If host warnings persist after B59, triage in order: (1) AirBook.RetryRebook, (2) AirBook.RedisplayReservation (AirBook subtree) — try B61 experiment flag, (3) SpecialReqDetails.SpecialService.SpecialServiceInfo (SSR/contact/DOCS), (4) root AirPrice Brand qualifier when a safe branded shop source exists, (5) stale or unbookable segment inventory vs *NO FARES/RBD/CARRIER*.',
            ],
            'b60_post_b59_residual_hypotheses' => $b60Residual,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function hostWarningCorrelationNotes(): array
    {
        return [
            'TravelItineraryAddInfoLLSRQ_.FRMT.NOT_ENT_BGNG_WITH' => [
                'Check CustomerInfo.PersonName row shape vs host (GivenName/Surname/PassengerType/NameNumber; Infant flag only in IATI template).',
                'Check CustomerInfo.Email row keys: IATI includes Type=TO; OTA often sends Address-only rows.',
                'IATI adds CTCM/CTCE via SpecialService.Service; OTA omits SpecialService subtree (schema B54) — host may still require contact SSRs for some carriers/markets.',
                'Check ContactNumbers.PhoneUseType and digit-only phone formatting.',
            ],
            'EnhancedAirBookRQ_*NO_FARES_RBD_CARRIER' => [
                'Verify every FlightSegment has non-empty ResBookDesigCode and coherent MarketingAirline.Code + FlightNumber.',
                'Compare root AirPrice.PriceRequestInformation: IATI sends OptionalQualifiers.PricingQualifiers (PassengerType quantities, optional Brand); OTA wire is often Retain-only.',
                'If shop context is stale, host may reject NN sells even when JSON validates.',
            ],
        ];
    }

    /**
     * Key names only at the paths called out for B57 structural review (no scalar values).
     *
     * @return array<string, mixed>
     */
    public static function cpnrKeyNameInventory(array $cpnr): array
    {
        $tia = is_array($cpnr['TravelItineraryAddInfo'] ?? null) ? $cpnr['TravelItineraryAddInfo'] : [];
        $ci = is_array($tia['CustomerInfo'] ?? null) ? $tia['CustomerInfo'] : [];
        $agency = is_array($tia['AgencyInfo'] ?? null) ? $tia['AgencyInfo'] : [];
        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $odi = is_array($air['OriginDestinationInformation'] ?? null) ? $air['OriginDestinationInformation'] : [];
        $fs = $odi['FlightSegment'] ?? null;
        $sr = is_array($cpnr['SpecialReqDetails'] ?? null) ? $cpnr['SpecialReqDetails'] : [];
        $pp = is_array($cpnr['PostProcessing'] ?? null) ? $cpnr['PostProcessing'] : [];
        $et = is_array($pp['EndTransaction'] ?? null) ? $pp['EndTransaction'] : [];
        $rd = is_array($pp['RedisplayReservation'] ?? null) ? $pp['RedisplayReservation'] : [];
        $apRoot = $cpnr['AirPrice'] ?? null;

        return [
            'CreatePassengerNameRecordRQ.top_level_keys' => self::sortedStringKeys($cpnr),
            'TravelItineraryAddInfo' => self::sortedStringKeys($tia),
            'TravelItineraryAddInfo.AgencyInfo' => self::sortedStringKeys($agency),
            'TravelItineraryAddInfo.AgencyInfo.Ticketing' => self::sortedStringKeys(is_array($agency['Ticketing'] ?? null) ? $agency['Ticketing'] : []),
            'TravelItineraryAddInfo.CustomerInfo' => self::sortedStringKeys($ci),
            'TravelItineraryAddInfo.CustomerInfo.PersonName' => self::personNameStructure($ci['PersonName'] ?? null),
            'TravelItineraryAddInfo.CustomerInfo.ContactNumbers' => self::contactNumbersStructure($ci['ContactNumbers'] ?? null),
            'TravelItineraryAddInfo.CustomerInfo.Email' => self::emailRowsStructure($ci['Email'] ?? null),
            'AirBook' => self::sortedStringKeys($air),
            'AirBook.OriginDestinationInformation.FlightSegment' => self::flightSegmentRowsStructure($fs),
            'SpecialReqDetails' => self::sortedStringKeys($sr),
            'SpecialReqDetails.AddRemark' => self::sortedStringKeys(is_array($sr['AddRemark'] ?? null) ? $sr['AddRemark'] : []),
            'SpecialReqDetails.AddRemark.RemarkInfo' => self::sortedStringKeys(is_array($sr['AddRemark']['RemarkInfo'] ?? null) ? $sr['AddRemark']['RemarkInfo'] : []),
            'SpecialReqDetails.AddRemark.RemarkInfo.Remark_row_key_union' => self::remarkRowKeyUnion($sr),
            'SpecialReqDetails.SpecialService' => self::sortedStringKeys(is_array($sr['SpecialService'] ?? null) ? $sr['SpecialService'] : []),
            'SpecialReqDetails.SpecialService.SpecialServiceInfo' => self::sortedStringKeys(
                is_array($sr['SpecialService']['SpecialServiceInfo'] ?? null) ? $sr['SpecialService']['SpecialServiceInfo'] : []
            ),
            'PostProcessing' => self::sortedStringKeys($pp),
            'PostProcessing.EndTransaction' => self::sortedStringKeys($et),
            'PostProcessing.EndTransaction.Source' => self::sortedStringKeys(is_array($et['Source'] ?? null) ? $et['Source'] : []),
            'PostProcessing.RedisplayReservation' => self::sortedStringKeys($rd),
            'AirPrice' => self::airPriceStructure($apRoot),
        ];
    }

    /**
     * @return list<string>
     */
    private static function sortedStringKeys(array $a): array
    {
        $keys = array_values(array_filter(array_keys($a), static fn ($k): bool => is_string($k) && $k !== ''));
        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private static function personNameStructure(mixed $pn): array
    {
        if ($pn === null) {
            return ['container' => 'missing', 'row_count' => 0, 'row_key_union_sorted' => []];
        }
        if (is_array($pn) && array_is_list($pn)) {
            $union = [];
            foreach ($pn as $row) {
                if (is_array($row)) {
                    foreach (self::sortedStringKeys($row) as $k) {
                        $union[$k] = true;
                    }
                }
            }

            $keys = array_keys($union);
            sort($keys);

            return [
                'container' => 'array',
                'row_count' => count($pn),
                'row_key_union_sorted' => $keys,
            ];
        }
        if (is_array($pn)) {
            return [
                'container' => 'object',
                'row_count' => 1,
                'row_key_union_sorted' => self::sortedStringKeys($pn),
            ];
        }

        return ['container' => 'invalid', 'row_count' => 0, 'row_key_union_sorted' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private static function contactNumbersStructure(mixed $cn): array
    {
        if (! is_array($cn)) {
            return ['present' => false, 'contact_number_row_count' => 0, 'contact_number_row_key_union_sorted' => []];
        }
        $raw = $cn['ContactNumber'] ?? $cn['contactNumber'] ?? null;
        $rows = [];
        if (is_array($raw) && array_is_list($raw)) {
            foreach ($raw as $r) {
                if (is_array($r)) {
                    $rows[] = $r;
                }
            }
        } elseif (is_array($raw)) {
            $rows = [$raw];
        }

        $union = [];
        foreach ($rows as $row) {
            foreach (self::sortedStringKeys($row) as $k) {
                $union[$k] = true;
            }
        }
        $keys = array_keys($union);
        sort($keys);

        return [
            'present' => true,
            'contact_number_row_count' => count($rows),
            'contact_number_row_key_union_sorted' => $keys,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emailRowsStructure(mixed $em): array
    {
        if (! is_array($em)) {
            return ['row_count' => 0, 'row_key_union_sorted' => []];
        }
        $rows = [];
        if (array_is_list($em)) {
            foreach ($em as $r) {
                if (is_array($r)) {
                    $rows[] = $r;
                }
            }
        } else {
            $rows = [$em];
        }
        $union = [];
        foreach ($rows as $row) {
            foreach (self::sortedStringKeys($row) as $k) {
                $union[$k] = true;
            }
        }

        $keys = array_keys($union);
        sort($keys);

        return [
            'row_count' => count($rows),
            'row_key_union_sorted' => $keys,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function flightSegmentRowsStructure(mixed $fs): array
    {
        if (! is_array($fs)) {
            return ['segment_row_count' => 0, 'flight_segment_row_key_union_sorted' => []];
        }
        $list = array_is_list($fs) ? $fs : [$fs];
        $union = [];
        foreach ($list as $row) {
            if (is_array($row)) {
                foreach (self::sortedStringKeys($row) as $k) {
                    $union[$k] = true;
                }
            }
        }

        $keys = array_keys($union);
        sort($keys);

        return [
            'segment_row_count' => count($list),
            'flight_segment_row_key_union_sorted' => $keys,
        ];
    }

    /**
     * @return list<string>
     */
    private static function remarkRowKeyUnion(array $sr): array
    {
        $ri = $sr['AddRemark']['RemarkInfo'] ?? null;
        if (! is_array($ri)) {
            return [];
        }
        $rm = $ri['Remark'] ?? null;
        $rows = [];
        if (is_array($rm) && array_is_list($rm)) {
            foreach ($rm as $r) {
                if (is_array($r)) {
                    $rows[] = $r;
                }
            }
        } elseif (is_array($rm)) {
            $rows = [$rm];
        }
        $union = [];
        foreach ($rows as $row) {
            foreach (self::sortedStringKeys($row) as $k) {
                $union[$k] = true;
            }
        }
        $keys = array_keys($union);
        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private static function airPriceStructure(mixed $ap): array
    {
        if ($ap === null) {
            return ['root_type' => 'missing', 'row_count' => 0, 'price_request_information_keys' => [], 'optional_qualifiers_keys' => [], 'optional_qualifiers_pricing_qualifiers_keys' => []];
        }
        if (is_array($ap) && array_is_list($ap)) {
            $first = is_array($ap[0] ?? null) ? $ap[0] : [];
            $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
            $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
            $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];

            return [
                'root_type' => 'array',
                'row_count' => count($ap),
                'price_request_information_keys' => self::sortedStringKeys($pri),
                'optional_qualifiers_keys' => self::sortedStringKeys($oq),
                'optional_qualifiers_pricing_qualifiers_keys' => self::sortedStringKeys($pq),
            ];
        }
        if (is_array($ap)) {
            $pri = is_array($ap['PriceRequestInformation'] ?? null) ? $ap['PriceRequestInformation'] : [];
            $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
            $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];

            return [
                'root_type' => 'object',
                'row_count' => 1,
                'price_request_information_keys' => self::sortedStringKeys($pri),
                'optional_qualifiers_keys' => self::sortedStringKeys($oq),
                'optional_qualifiers_pricing_qualifiers_keys' => self::sortedStringKeys($pq),
            ];
        }

        return ['root_type' => 'invalid', 'row_count' => 0, 'price_request_information_keys' => [], 'optional_qualifiers_keys' => [], 'optional_qualifiers_pricing_qualifiers_keys' => []];
    }

    /**
     * Frozen key scaffold matching IATI GDS CPNR (scalar values are placeholders only for traversal).
     *
     * @return array<string, mixed>
     */
    public static function iatiOperationalGdsCpnrKeyTemplate(): array
    {
        return [
            'version' => '2.4.0',
            'targetCity' => 'PCC',
            'haltOnAirPriceError' => true,
            'TravelItineraryAddInfo' => [
                'AgencyInfo' => [
                    'Ticketing' => [
                        'TicketType' => '7TAW',
                        'ShortText' => 'JTP',
                    ],
                ],
                'CustomerInfo' => [
                    'ContactNumbers' => [
                        'ContactNumber' => [
                            ['Phone' => '0', 'PhoneUseType' => 'H'],
                        ],
                    ],
                    'PersonName' => [
                        [
                            'Infant' => false,
                            'PassengerType' => 'ADT',
                            'NameNumber' => '1.1',
                            'GivenName' => 'A',
                            'Surname' => 'B',
                        ],
                    ],
                    'Email' => [
                        ['Address' => 'a@b.c', 'Type' => 'TO'],
                    ],
                ],
            ],
            'AirBook' => [
                'RetryRebook' => ['Option' => true],
                'HaltOnStatus' => [
                    ['Code' => 'HL'],
                    ['Code' => 'KK'],
                    ['Code' => 'LL'],
                    ['Code' => 'NO'],
                    ['Code' => 'UC'],
                    ['Code' => 'UN'],
                    ['Code' => 'US'],
                    ['Code' => 'UU'],
                ],
                'RedisplayReservation' => ['NumAttempts' => 10, 'WaitInterval' => 1500],
                'OriginDestinationInformation' => [
                    'FlightSegment' => [
                        [
                            'DepartureDateTime' => '2000-01-01T00:00:00',
                            'ArrivalDateTime' => '2000-01-01T00:00:00',
                            'FlightNumber' => '0615',
                            'NumberInParty' => '1',
                            'ResBookDesigCode' => 'Y',
                            'Status' => 'NN',
                            'DestinationLocation' => ['LocationCode' => 'DXB'],
                            'MarketingAirline' => ['Code' => 'EK', 'FlightNumber' => '0615'],
                            'OriginLocation' => ['LocationCode' => 'LHE'],
                        ],
                    ],
                ],
            ],
            'SpecialReqDetails' => [
                'AddRemark' => [
                    'RemarkInfo' => [
                        'Remark' => [
                            ['Type' => 'Historical', 'Text' => 'X'],
                        ],
                    ],
                ],
                'SpecialService' => [
                    'SpecialServiceInfo' => [
                        'AdvancePassenger' => [
                            [
                                'Document' => [
                                    'IssueCountry' => 'PK',
                                    'NationalityCountry' => 'PK',
                                    'ExpirationDate' => '2031-12-31',
                                    'Number' => 'X',
                                    'Type' => 'P',
                                ],
                                'PersonName' => [
                                    'GivenName' => 'A',
                                    'Surname' => 'B',
                                    'DateOfBirth' => '1990-01-01',
                                    'DocumentHolder' => true,
                                    'Gender' => 'M',
                                    'NameNumber' => '1.1',
                                ],
                                'VendorPrefs' => ['Airline' => ['Hosted' => false]],
                                'SegmentNumber' => 'A',
                            ],
                        ],
                        'SecureFlight' => [
                            [
                                'PersonName' => [
                                    'GivenName' => 'A',
                                    'Surname' => 'B',
                                    'DateOfBirth' => '1990-01-01',
                                    'Gender' => 'M',
                                    'NameNumber' => '1.1',
                                ],
                                'SegmentNumber' => 'A',
                            ],
                        ],
                        'Service' => [
                            [
                                'PersonName' => ['NameNumber' => '1.1'],
                                'Text' => '0',
                                'VendorPrefs' => ['Airline' => ['Hosted' => false]],
                                'SegmentNumber' => 'A',
                                'SSR_Code' => 'CTCM',
                            ],
                        ],
                    ],
                ],
            ],
            'PostProcessing' => [
                'EndTransaction' => [
                    'Source' => ['ReceivedFrom' => 'B2C'],
                ],
                'RedisplayReservation' => ['waitInterval' => 1000],
            ],
            'AirPrice' => [
                [
                    'PriceRequestInformation' => [
                        'Retain' => true,
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'PassengerType' => [
                                    ['Code' => 'ADT', 'Quantity' => '1'],
                                ],
                                'Brand' => [
                                    ['Code' => 'BRAND'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $paths
     */
    private static function pathSetHasPrefix(array $paths, string $prefix): bool
    {
        $prefixDot = $prefix.'.';

        foreach ($paths as $p) {
            if ($p === $prefix || str_starts_with($p, $prefixDot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $onlyIati
     * @param  list<string>  $onlyOta
     * @return array{only_in_iati: list<string>, only_in_ota: list<string>}
     */
    private static function filterPathsByPrefix(array $onlyIati, array $onlyOta, string $prefix): array
    {
        $pDot = $prefix.'.';

        $inIati = array_values(array_filter($onlyIati, static function (string $p) use ($prefix, $pDot): bool {
            return $p === $prefix || str_starts_with($p, $pDot);
        }));
        $inOta = array_values(array_filter($onlyOta, static function (string $p) use ($prefix, $pDot): bool {
            return $p === $prefix || str_starts_with($p, $pDot);
        }));

        return [
            'only_in_iati' => $inIati,
            'only_in_ota' => $inOta,
        ];
    }

    /**
     * @return list<string>
     */
    public static function collectStructuralKeyPaths(array $node, string $prefix = ''): array
    {
        $paths = [];
        if ($prefix !== '') {
            $paths[] = $prefix;
        }
        foreach ($node as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            $next = $prefix === '' ? $k : $prefix.'.'.$k;
            if (! is_array($v)) {
                continue;
            }
            if ($v === []) {
                $paths[] = $next;

                continue;
            }
            if (array_is_list($v)) {
                $first = $v[0] ?? null;
                if (is_array($first) && self::isAssocArray($first)) {
                    $paths = array_merge($paths, self::collectStructuralKeyPaths($first, $next.'[*]'));
                } else {
                    $paths[] = $next.'[]';
                }

                continue;
            }
            $paths = array_merge($paths, self::collectStructuralKeyPaths($v, $next));
        }

        return array_values(array_unique($paths));
    }

    /**
     * B61/B61A/B61B: True when OTA {@code AirBook} carries experiment blocks with **boolean** {@code RetryRebook.Option=true}
     * and **integer** {@code NumAttempts}/{@code WaitInterval} on both subtrees.
     */
    private static function otaAirBookRetryRedisplayExperimentStructuralMatch(array $cpnr): bool
    {
        $ab = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $rr = is_array($ab['RetryRebook'] ?? null) ? $ab['RetryRebook'] : [];
        $rd = is_array($ab['RedisplayReservation'] ?? null) ? $ab['RedisplayReservation'] : [];
        if ($rr === [] || $rd === []) {
            return false;
        }
        if (! array_key_exists('Option', $rr) || ! is_bool($rr['Option']) || $rr['Option'] !== true) {
            return false;
        }
        foreach (['NumAttempts', 'WaitInterval'] as $key) {
            if (! array_key_exists($key, $rr) || ! is_int($rr[$key])) {
                return false;
            }
            if (! array_key_exists($key, $rd) || ! is_int($rd[$key])) {
                return false;
            }
        }

        return true;
    }

    private static function isAssocArray(array $a): bool
    {
        return array_keys($a) !== range(0, count($a) - 1);
    }
}
