<?php

namespace App\Support\Sabre;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Security\SensitiveDataRedactor;
use App\Support\Suppliers\SabreTraditionalCpnrIatiWireStructureDiagnostic;

/**
 * Safe structural snapshots for Passenger Records create_pnr attempts (no raw payload, PII, PCC, or credentials).
 */
final class SabrePnrAttemptStructureSnapshot
{
    /** @var list<string> */
    public const PERSISTENCE_KEYS = [
        'safe_request_structure',
        'safe_enhanced_airbook_structure',
        'safe_airbook_structure',
        'safe_airprice_structure',
        'safe_postprocessing_structure',
        'safe_response_structure',
        'safe_enhanced_airbook_fingerprint',
        'structure_snapshot_version',
        'structure_snapshot_source',
    ];

    /** @var list<string> */
    private const FORBIDDEN_KEY_SUBSTRINGS = [
        'raw_payload', 'request_body', 'response_body', 'password', 'secret', 'credential',
        'passport', 'email', 'phone', 'first_name', 'last_name', 'givenname', 'surname',
        'personname', 'contactnumbers', 'document', 'createpassengernamerecordrq', 'pcc', 'token',
        'address', 'receivedfrom',
    ];

    /** @var list<string> */
    private const SAFE_STATUS_CODES = [
        'NN', 'QF', 'HK', 'HL', 'NO', 'UC', 'US', 'KK', 'UN', 'UU', 'WN', 'SS', 'TK', 'TL',
    ];

    public function __construct(
        protected SabreBookingPayloadBuilder $payloadBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $envelope  Outbound CPNR envelope (may include {@code _ota*})
     * @param  array<string, mixed>  $context  endpoint_path, payload_schema, selected_payload_style, source
     * @return array<string, mixed>
     */
    public function buildFromWire(array $envelope, array $context = []): array
    {
        $wire = $this->payloadBuilder->stripOtaInternalKeysFromBookingWire($envelope);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $payloadStyle = trim((string) (
            $context['selected_payload_style']
            ?? $context['payload_schema']
            ?? $envelope['_ota_payload_schema']
            ?? ''
        ));
        $endpointPath = trim((string) ($context['endpoint_path'] ?? ''));
        $source = trim((string) ($context['structure_snapshot_source'] ?? 'live_pre_call'));

        $request = array_filter([
            'endpoint_path' => $endpointPath !== '' ? $endpointPath : null,
            'payload_schema' => $payloadStyle !== '' ? $payloadStyle : null,
            'selected_payload_style' => $payloadStyle !== '' ? $payloadStyle : null,
            'cpnr_version' => is_scalar($cpnr['version'] ?? null) ? (string) $cpnr['version'] : null,
            'top_level_block_order' => $this->sortedKeys($cpnr),
            'passenger_details_present' => isset($cpnr['TravelItineraryAddInfo']),
            'passenger_details_block_order' => $this->sortedKeys(
                is_array($cpnr['TravelItineraryAddInfo'] ?? null) ? $cpnr['TravelItineraryAddInfo'] : []
            ),
            'target_city_present' => trim((string) ($cpnr['targetCity'] ?? '')) !== '',
            'halt_on_air_price_error' => ($cpnr['haltOnAirPriceError'] ?? null) === true ? true : null,
        ], static fn ($v) => $v !== null && $v !== []);

        $airbook = $this->buildAirBookStructure($cpnr);
        $airprice = $this->buildAirPriceStructure($cpnr);
        $post = $this->buildPostProcessingStructure($cpnr);
        $enhanced = $this->buildEnhancedAirBookStructure($cpnr, $airbook);
        $fingerprint = $this->payloadBuilder->fingerprintPassengerRecordsFinalPostBody($envelope);
        $fingerprint['iati_template_key_inventory'] = SabreTraditionalCpnrIatiWireStructureDiagnostic::cpnrKeyNameInventory($cpnr);

        return $this->sliceForPersistence(SensitiveDataRedactor::redact([
            'structure_snapshot_version' => 'IATI_V24_SAFE_STRUCTURE_V1',
            'structure_snapshot_source' => $source !== '' ? $source : 'live_pre_call',
            'safe_request_structure' => $request,
            'safe_airbook_structure' => $airbook,
            'safe_enhanced_airbook_structure' => $enhanced,
            'safe_airprice_structure' => $airprice,
            'safe_postprocessing_structure' => $post,
            'safe_response_structure' => null,
            'safe_enhanced_airbook_fingerprint' => $fingerprint,
        ]));
    }

    /**
     * @param  array<string, mixed>  $responseDigest  Safe response digest slice (no raw body)
     * @return array<string, mixed>
     */
    public function buildResponseStructure(array $responseDigest): array
    {
        $status = strtoupper(trim((string) ($responseDigest['application_results_status'] ?? '')));
        $hostCodes = [];
        foreach ((array) ($responseDigest['host_warning_sabre_codes'] ?? []) as $code) {
            $c = strtoupper(trim((string) $code));
            if ($c !== '') {
                $hostCodes[] = $c;
            }
        }
        $hostCodes = array_slice(array_values(array_unique($hostCodes)), 0, 16);

        $messages = [];
        foreach ((array) ($responseDigest['host_warning_messages_truncated'] ?? []) as $line) {
            $msg = $this->sanitizeHostMessageExcerpt((string) $line);
            if ($msg !== '') {
                $messages[] = $msg;
            }
        }
        $messages = array_slice($messages, 0, 8);

        return array_filter([
            'http_status' => isset($responseDigest['http_status']) ? (int) $responseDigest['http_status'] : null,
            'application_results_status' => $status !== '' ? $status : null,
            'application_results_incomplete' => ($responseDigest['application_results_incomplete'] ?? false) === true ? true : null,
            'host_warning_modules' => array_slice(array_map('strval', (array) ($responseDigest['host_warning_modules'] ?? [])), 0, 12),
            'host_warning_sabre_codes' => $hostCodes !== [] ? $hostCodes : null,
            'host_warning_messages_excerpt' => $messages !== [] ? $messages : null,
            'response_error_codes' => array_slice(array_map('strval', (array) ($responseDigest['response_error_codes'] ?? [])), 0, 12),
            'pnr_present' => trim((string) ($responseDigest['pnr'] ?? '')) !== '' ? true : null,
            'safe_host_error_fingerprint' => $this->hostErrorFingerprint($status, $hostCodes, $messages),
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $snapshots
     * @return array<string, mixed>
     */
    public function sliceForPersistence(array $snapshots): array
    {
        $out = [];
        foreach (self::PERSISTENCE_KEYS as $key) {
            if (! array_key_exists($key, $snapshots)) {
                continue;
            }
            $value = $snapshots[$key];
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $out[$key] = is_array($value) ? $this->stripForbiddenKeys($value) : $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function buildAirBookStructure(array $cpnr): array
    {
        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $odiGroups = $this->originDestinationGroups($air);
        $segmentMatrix = [];
        $odiSummaries = [];
        foreach ($odiGroups as $gIdx => $group) {
            $rows = $this->flightSegmentsFromGroup($group);
            $odiSummaries[] = [
                'index' => $gIdx,
                'segment_count' => count($rows),
                'group_keys' => $this->sortedKeys($group),
            ];
            foreach ($rows as $sIdx => $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                $segmentMatrix[] = $this->flightSegmentFieldMatrix($seg, $gIdx, $sIdx);
            }
        }

        $haltCodes = $this->payloadBuilder->extractHaltOnStatusCodesFromCpnr($cpnr);
        $haltSanitized = array_values(array_filter($haltCodes, fn (string $c): bool => in_array($c, self::SAFE_STATUS_CODES, true)));

        return array_filter([
            'present' => $air !== [],
            'block_order' => $this->sortedKeys($air),
            'enhanced_airbook_present' => false,
            'ota_airbook_rq_present' => isset($air['OriginDestinationInformation']),
            'origin_destination_information_count' => count($odiGroups),
            'origin_destination_groups' => $odiSummaries !== [] ? $odiSummaries : null,
            'flight_segment_count' => count($segmentMatrix),
            'flight_segment_field_matrix' => $segmentMatrix !== [] ? $segmentMatrix : null,
            'halt_on_status_present' => $haltSanitized !== [],
            'halt_on_status_codes' => $haltSanitized !== [] ? $haltSanitized : null,
            'retry_rebook_present' => isset($air['RetryRebook']),
            'airbook_redisplay_present' => isset($air['RedisplayReservation']),
            'ignore_after_present' => isset($air['IgnoreAfter']),
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @param  array<string, mixed>  $airbook
     * @return array<string, mixed>
     */
    protected function buildEnhancedAirBookStructure(array $cpnr, array $airbook): array
    {
        $matrix = is_array($airbook['flight_segment_field_matrix'] ?? null)
            ? $airbook['flight_segment_field_matrix']
            : [];
        $sellStatuses = [];
        $rbds = [];
        $nips = [];
        foreach ($matrix as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row['status_category']) && is_string($row['status_category'])) {
                $sellStatuses[] = $row['status_category'];
            }
            if (($row['res_book_desig_code_present'] ?? false) === true) {
                $rbds[] = 'present';
            }
            if (($row['number_in_party_present'] ?? false) === true) {
                $nips[] = 'present';
            }
        }

        return array_filter([
            'note' => 'REST wire has AirBook only; host maps sell rows to EnhancedAirBook processing.',
            'enhanced_airbook_block_present' => false,
            'sell_segment_count' => (int) ($airbook['flight_segment_count'] ?? count($matrix)),
            'sell_all_required_present' => $matrix !== []
                && collect($matrix)->every(fn (array $r): bool => ($r['res_book_desig_code_present'] ?? false) === true
                    && ($r['marketing_airline_present'] ?? false) === true
                    && ($r['departure_airport_present'] ?? false) === true
                    && ($r['arrival_airport_present'] ?? false) === true),
            'sell_status_values' => array_values(array_unique($sellStatuses)),
            'sell_rbd_present_count' => count($rbds),
            'sell_number_in_party_present_count' => count($nips),
            'airbook_structure_ref' => array_intersect_key($airbook, array_flip([
                'flight_segment_count', 'halt_on_status_codes', 'retry_rebook_present', 'airbook_redisplay_present',
            ])),
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function buildAirPriceStructure(array $cpnr): array
    {
        $apRaw = $cpnr['AirPrice'] ?? null;
        $rows = $this->arrayRows($apRaw);
        $inventory = SabreTraditionalCpnrIatiWireStructureDiagnostic::cpnrKeyNameInventory($cpnr);
        $apInv = is_array($inventory['AirPrice'] ?? null) ? $inventory['AirPrice'] : [];

        $ptcSummary = [];
        $brandPresent = false;
        $vcFlightQualifiers = false;
        $vcPricingQualifiers = false;
        foreach ($rows as $row) {
            $pri = is_array($row['PriceRequestInformation'] ?? null) ? $row['PriceRequestInformation'] : [];
            $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
            $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
            $fq = is_array($oq['FlightQualifiers'] ?? null) ? $oq['FlightQualifiers'] : [];
            $vcFlightQualifiers = $vcFlightQualifiers || trim((string) data_get($fq, 'VendorPrefs.Airline.Code', '')) !== '';
            $vcPricingQualifiers = $vcPricingQualifiers || trim((string) ($pq['ValidatingCarrier'] ?? '')) !== '';
            $brandPresent = $brandPresent || isset($pq['Brand']);
            foreach ($this->arrayRows($pq['PassengerType'] ?? null) as $ptc) {
                $code = strtoupper(trim((string) ($ptc['Code'] ?? '')));
                $qty = trim((string) ($ptc['Quantity'] ?? ''));
                if ($code !== '' || $qty !== '') {
                    $ptcSummary[] = array_filter([
                        'code' => $code !== '' ? $code : null,
                        'quantity_present' => $qty !== '',
                    ], static fn ($v) => $v !== null);
                }
            }
        }

        return array_filter([
            'present' => $rows !== [],
            'root_type' => $apInv['root_type'] ?? ($rows !== [] ? 'array' : 'missing'),
            'row_count' => count($rows),
            'block_order' => $this->sortedKeys(is_array($apRaw) && ! array_is_list($apRaw) ? $apRaw : ($rows[0] ?? [])),
            'price_request_information_present' => ($apInv['price_request_information_keys'] ?? []) !== [],
            'optional_qualifiers_present' => ($apInv['optional_qualifiers_keys'] ?? []) !== [],
            'pricing_qualifiers_present' => ($apInv['optional_qualifiers_pricing_qualifiers_keys'] ?? []) !== [],
            'passenger_type_present' => $ptcSummary !== [],
            'passenger_type_summary' => array_slice($ptcSummary, 0, 8),
            'brand_qualifier_present' => $brandPresent,
            'validating_carrier_flight_qualifiers_present' => $vcFlightQualifiers,
            'validating_carrier_pricing_qualifiers_present' => $vcPricingQualifiers,
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    protected function buildPostProcessingStructure(array $cpnr): array
    {
        $pp = is_array($cpnr['PostProcessing'] ?? null) ? $cpnr['PostProcessing'] : [];
        $et = is_array($pp['EndTransaction'] ?? null) ? $pp['EndTransaction'] : [];
        $src = is_array($et['Source'] ?? null) ? $et['Source'] : [];
        $rd = is_array($pp['RedisplayReservation'] ?? null) ? $pp['RedisplayReservation'] : [];
        $ticketing = is_array(data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing'))
            ? data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing')
            : [];

        return array_filter([
            'present' => $pp !== [],
            'block_order' => $this->sortedKeys($pp),
            'end_transaction_present' => $et !== [],
            'end_transaction_source_present' => $src !== [],
            'received_from_present' => trim((string) ($src['ReceivedFrom'] ?? '')) !== '',
            'redisplay_reservation_present' => $rd !== [],
            'redisplay_wait_interval_present' => array_key_exists('waitInterval', $rd) || array_key_exists('WaitInterval', $rd),
            'ticketing_time_limit_marker_present' => trim((string) ($ticketing['TicketType'] ?? '')) !== '',
            'manual_ticketing_short_text_present' => trim((string) ($ticketing['ShortText'] ?? '')) !== '',
            'ignore_after_present' => isset($pp['IgnoreAfter']),
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $seg
     * @return array<string, mixed>
     */
    protected function flightSegmentFieldMatrix(array $seg, int $odiIndex, int $segIndex): array
    {
        $mkt = is_array($seg['MarketingAirline'] ?? null) ? $seg['MarketingAirline'] : [];
        $op = is_array($seg['OperatingAirline'] ?? null) ? $seg['OperatingAirline'] : [];
        $status = strtoupper(trim((string) ($seg['Status'] ?? '')));
        $action = strtoupper(trim((string) ($seg['ActionCode'] ?? '')));
        $nip = trim((string) ($seg['NumberInParty'] ?? ''));
        $marriage = trim((string) ($seg['MarriageGrp'] ?? ''));
        $fn = trim((string) ($seg['FlightNumber'] ?? ''));
        $dep = trim((string) ($seg['DepartureDateTime'] ?? ''));
        $arr = trim((string) ($seg['ArrivalDateTime'] ?? ''));

        return array_filter([
            'odi_index' => $odiIndex,
            'segment_index' => $segIndex,
            'field_keys' => $this->sortedKeys($seg),
            'departure_datetime_format' => $this->datetimeFormatCategory($dep),
            'arrival_datetime_format' => $this->datetimeFormatCategory($arr),
            'flight_number_format' => $this->flightNumberFormatCategory($fn),
            'res_book_desig_code_present' => trim((string) ($seg['ResBookDesigCode'] ?? '')) !== '',
            'status_category' => in_array($status, self::SAFE_STATUS_CODES, true) ? $status : ($status !== '' ? 'other' : null),
            'action_code_category' => in_array($action, self::SAFE_STATUS_CODES, true) ? $action : ($action !== '' ? 'other' : null),
            'number_in_party_present' => $nip !== '',
            'number_in_party_type' => $nip === '' ? null : (ctype_digit($nip) ? 'numeric_string' : 'other'),
            'marriage_grp_present' => $marriage !== '',
            'marriage_grp_category' => $marriage !== '' ? strtoupper($marriage) : null,
            'marketing_airline_present' => trim((string) ($mkt['Code'] ?? '')) !== '',
            'operating_airline_present' => trim((string) ($op['Code'] ?? '')) !== '',
            'departure_airport_present' => trim((string) data_get($seg, 'OriginLocation.LocationCode', '')) !== '',
            'arrival_airport_present' => trim((string) data_get($seg, 'DestinationLocation.LocationCode', '')) !== '',
        ], static fn ($v) => $v !== null && $v !== '');
    }

    protected function datetimeFormatCategory(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/[Zz]|[+-]\d{2}:?\d{2}$/', $value) === 1) {
            return 'iso_with_offset';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return 'iso_local_seconds';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1) {
            return 'iso_local_minutes';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return 'date_prefix_other';
        }

        return 'unknown';
    }

    protected function flightNumberFormatCategory(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}$/', $value) === 1) {
            return 'zero_padded_4';
        }
        if (preg_match('/^\d{1,3}$/', $value) === 1) {
            return 'unpadded_numeric';
        }
        if (preg_match('/^[A-Z0-9]{1,8}$/i', $value) === 1) {
            return 'alphanumeric';
        }

        return 'other';
    }

    /**
     * @param  list<string>  $hostCodes
     * @param  list<string>  $messages
     */
    protected function hostErrorFingerprint(string $status, array $hostCodes, array $messages): ?string
    {
        $tokens = [];
        if ($status !== '') {
            $tokens[] = 'app:'.$status;
        }
        foreach ($hostCodes as $code) {
            $tokens[] = 'host:'.$code;
        }
        foreach ($messages as $msg) {
            if (str_contains(strtoupper($msg), 'FORMAT')) {
                $tokens[] = 'enhanced_airbook_format';
                break;
            }
            if (str_contains(strtoupper($msg), 'NO FARES') || str_contains(strtoupper($msg), 'RBD')) {
                $tokens[] = 'no_fares_rbd_carrier';
                break;
            }
        }

        return $tokens !== [] ? implode('|', array_slice($tokens, 0, 12)) : null;
    }

    protected function sanitizeHostMessageExcerpt(string $line): string
    {
        $line = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[email]', $line) ?? $line;
        $line = preg_replace('/\b\d{7,}\b/', '[digits]', $line) ?? $line;

        return substr(trim($line), 0, 120);
    }

    /**
     * @param  array<string, mixed>  $air
     * @return list<array<string, mixed>>
     */
    protected function originDestinationGroups(array $air): array
    {
        $odi = $air['OriginDestinationInformation'] ?? null;
        if (! is_array($odi)) {
            return [];
        }

        return array_is_list($odi) ? array_values(array_filter($odi, 'is_array')) : [$odi];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return list<array<string, mixed>>
     */
    protected function flightSegmentsFromGroup(array $group): array
    {
        $fs = $group['FlightSegment'] ?? null;
        if (! is_array($fs)) {
            return [];
        }

        return array_is_list($fs) ? array_values(array_filter($fs, 'is_array')) : [$fs];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function arrayRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_is_list($value) ? array_values(array_filter($value, 'is_array')) : [$value];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    protected function sortedKeys(array $node): array
    {
        $keys = array_values(array_filter(array_keys($node), static fn ($k): bool => is_string($k) && $k !== ''));
        sort($keys);

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripForbiddenKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (! is_string($key) || $this->keyIsForbidden(strtolower($key))) {
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->stripForbiddenKeys($value);
            } elseif (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    protected function keyIsForbidden(string $keyLower): bool
    {
        foreach (self::FORBIDDEN_KEY_SUBSTRINGS as $needle) {
            if (str_contains($keyLower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
