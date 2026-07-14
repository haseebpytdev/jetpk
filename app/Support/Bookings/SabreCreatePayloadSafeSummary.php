<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Security\SensitiveDataRedactor;

/**
 * E3: Safe Passenger Records create-payload summary for supplier booking attempts (no raw CPNR bodies or PII).
 */
final class SabreCreatePayloadSafeSummary
{
    /** @var list<string> */
    public const PERSISTENCE_KEYS = [
        'create_endpoint_path',
        'create_payload_style',
        'create_segment_count',
        'create_segments_summary',
        'create_segment_linkage_present',
        'create_marriage_group_present',
        'create_action_code_present',
        'create_status_codes',
        'create_number_in_party_values',
        'create_marketing_operating_carrier_summary',
        'create_od_group_count',
        'create_segments_per_od_group',
        'create_halt_on_status_codes',
        'create_halt_on_status_nn_omitted',
        'create_halt_on_status_policy',
        'create_segment_sell_status_intent',
        'create_nn_halt_fatal_without_policy',
        'create_air_price_present',
        'create_air_price_ptc_summary',
        'create_ticketing_time_limit_present',
        'create_payload_strategy_version',
        'create_passenger_count',
        'create_contact_present',
        'create_received_from_present',
        'create_ticketing_disabled',
        'create_post_ticketing_action',
        'create_price_quote_present',
        'create_host_command_style',
        'create_segment_source',
        'create_route_continuity',
        'create_chronology_gaps',
        'create_snapshot_segment_count',
        'create_segment_order_repaired',
        'create_date_repair_applied',
    ];

    /** @var list<string> */
    private const FORBIDDEN_KEY_SUBSTRINGS = [
        'raw_payload', 'request_body', 'response_body', 'password', 'secret', 'credential',
        'passport', 'email', 'phone', 'first_name', 'last_name', 'givenname', 'surname',
    ];

    public function __construct(
        protected SabreBookingPayloadBuilder $payloadBuilder,
        protected SabreSafeRefreshContext $safeRefreshContext,
    ) {}

    /**
     * @param  array<string, mixed>  $envelope  Outbound CPNR envelope (may include {@code _ota*} keys)
     * @param  list<array<string, mixed>>  $offerSnapshotSegments
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function summarize(
        array $envelope,
        array $offerSnapshotSegments,
        array $context = [],
    ): array {
        $summary = $this->payloadBuilder->summarizeCreatePayloadForAttempt(
            $envelope,
            $offerSnapshotSegments,
            $context,
        );

        $summary = array_merge($summary, $this->structuralDiagnostics($envelope));

        return $this->stripForbiddenKeys(SensitiveDataRedactor::redact($summary));
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function sliceForAttemptPersistence(array $summary): array
    {
        $out = [];
        foreach (self::PERSISTENCE_KEYS as $key) {
            if (! array_key_exists($key, $summary)) {
                continue;
            }
            $value = $summary[$key];
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $out[$key] = $value;
        }

        return $this->stripForbiddenKeys($out);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public function resolveSegmentSource(?int $bookingId, array $offer): string
    {
        if ($bookingId === null || $bookingId < 1) {
            return 'offer_passed_in';
        }

        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            return 'offer_passed_in';
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $refreshStatus = trim((string) ($meta['offer_refresh_status'] ?? ''));
        $safeContext = $this->safeRefreshContext->fromMeta($meta);

        if ($refreshStatus === 'refreshed') {
            return 'refreshed_offer';
        }

        if ($safeContext !== null && trim((string) ($safeContext['refreshed_at'] ?? '')) !== '') {
            return 'refreshed_offer';
        }

        if ($safeContext !== null && is_array($safeContext['selected_segments'] ?? null) && $safeContext['selected_segments'] !== []) {
            return 'safe_refresh_context';
        }

        if (trim((string) ($meta['checkout_search_id'] ?? '')) !== '') {
            return 'cache';
        }

        return 'original_booking';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function containsForbiddenKeys(array $data): bool
    {
        return $this->findForbiddenKey($data) !== null;
    }

    /**
     * E5A: Safe CPNR structural diagnostics only. No raw wire, passenger PII, credentials,
     * or response content is returned.
     *
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    private function structuralDiagnostics(array $envelope): array
    {
        $wire = $this->payloadBuilder->stripOtaInternalKeysFromBookingWire($envelope);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $odGroups = $this->originDestinationGroups($cpnr);
        $segments = [];
        $segmentsPerOd = [];
        foreach ($odGroups as $group) {
            $rows = $this->flightSegmentsFromOriginDestinationGroup($group);
            $segmentsPerOd[] = count($rows);
            foreach ($rows as $row) {
                $segments[] = $row;
            }
        }

        $statusCodes = [];
        $numberInParty = [];
        $carrierSummary = [];
        $marriagePresent = false;
        $linkagePresent = false;
        $actionCodePresent = false;
        foreach ($segments as $idx => $seg) {
            $status = strtoupper(trim((string) ($seg['Status'] ?? '')));
            if ($status !== '') {
                $statusCodes[$status] = true;
            }
            if (array_key_exists('ActionCode', $seg) && trim((string) $seg['ActionCode']) !== '') {
                $actionCodePresent = true;
            }
            $nip = trim((string) ($seg['NumberInParty'] ?? ''));
            if ($nip !== '') {
                $numberInParty[$nip] = true;
            }
            $marriage = trim((string) ($seg['MarriageGrp'] ?? ''));
            if ($marriage !== '') {
                $marriagePresent = true;
                $linkagePresent = true;
            }
            foreach (['ConnectionInd', 'SegmentNumber', 'RPH', 'Sequence'] as $key) {
                if (trim((string) ($seg[$key] ?? '')) !== '') {
                    $linkagePresent = true;
                }
            }
            $marketing = is_array($seg['MarketingAirline'] ?? null) ? $seg['MarketingAirline'] : [];
            $operating = is_array($seg['OperatingAirline'] ?? null) ? $seg['OperatingAirline'] : [];
            $carrierSummary[] = array_filter([
                'index' => $idx,
                'marketing' => strtoupper(trim((string) ($marketing['Code'] ?? ''))) ?: null,
                'operating' => strtoupper(trim((string) ($operating['Code'] ?? ''))) ?: null,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        $haltCodes = [];
        foreach ($this->arrayRows(data_get($cpnr, 'AirBook.HaltOnStatus')) as $row) {
            $code = strtoupper(trim((string) ($row['Code'] ?? '')));
            if ($code !== '') {
                $haltCodes[$code] = true;
            }
        }

        $airPriceRows = $this->arrayRows($cpnr['AirPrice'] ?? null);
        $ptcSummary = [];
        foreach ($airPriceRows as $row) {
            $ptcRows = $this->arrayRows(data_get($row, 'PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType'));
            foreach ($ptcRows as $ptc) {
                $code = strtoupper(trim((string) ($ptc['Code'] ?? '')));
                $quantity = trim((string) ($ptc['Quantity'] ?? ''));
                if ($code !== '' || $quantity !== '') {
                    $ptcSummary[] = array_filter([
                        'code' => $code !== '' ? $code : null,
                        'quantity' => $quantity !== '' ? $quantity : null,
                    ], static fn ($value) => $value !== null && $value !== '');
                }
            }
        }

        $ticketing = is_array(data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing'))
            ? data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing')
            : [];
        $ticketingTimeLimitPresent = false;
        foreach (['ShortText', 'TimeLimit', 'TicketTimeLimit', 'TicketTimeLimitDate'] as $key) {
            if (trim((string) ($ticketing[$key] ?? '')) !== '') {
                $ticketingTimeLimitPresent = true;
            }
        }

        $haltCodesList = $this->sortedKeys($haltCodes);
        $statusCodesList = $this->sortedKeys($statusCodes);
        $nnOmittedFromHalt = $haltCodesList !== []
            && ! in_array('NN', $haltCodesList, true)
            && ! in_array('WN', $haltCodesList, true);

        return [
            'create_segment_linkage_present' => $linkagePresent,
            'create_marriage_group_present' => $marriagePresent,
            'create_action_code_present' => $actionCodePresent,
            'create_status_codes' => $statusCodesList,
            'create_number_in_party_values' => $this->sortedKeys($numberInParty),
            'create_marketing_operating_carrier_summary' => array_slice($carrierSummary, 0, 8),
            'create_od_group_count' => count($odGroups),
            'create_segments_per_od_group' => $segmentsPerOd,
            'create_halt_on_status_codes' => $haltCodesList,
            'create_halt_on_status_nn_omitted' => $nnOmittedFromHalt,
            'create_halt_on_status_policy' => $nnOmittedFromHalt
                ? SabreCpnrOperationalAllowNnPolicy::POLICY_CERT_OPERATIONAL_OMIT_NN_WN
                : SabreCpnrOperationalAllowNnPolicy::POLICY_DEFAULT_IATI_WITH_NN,
            'create_segment_sell_status_intent' => $statusCodesList !== [] ? $statusCodesList[0] : 'NN',
            'create_nn_halt_fatal_without_policy' => in_array('NN', $haltCodesList, true),
            'create_air_price_present' => $airPriceRows !== [],
            'create_air_price_ptc_summary' => array_slice($ptcSummary, 0, 8),
            'create_ticketing_time_limit_present' => $ticketingTimeLimitPresent,
            'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
        ];
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @return list<array<string, mixed>>
     */
    private function originDestinationGroups(array $cpnr): array
    {
        $odi = data_get($cpnr, 'AirBook.OriginDestinationInformation');
        if (! is_array($odi)) {
            return [];
        }
        if (array_is_list($odi)) {
            return array_values(array_filter($odi, 'is_array'));
        }

        return [$odi];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return list<array<string, mixed>>
     */
    private function flightSegmentsFromOriginDestinationGroup(array $group): array
    {
        $fs = $group['FlightSegment'] ?? null;
        if (! is_array($fs)) {
            return [];
        }
        if (array_is_list($fs)) {
            return array_values(array_filter($fs, 'is_array'));
        }

        return [$fs];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function arrayRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        if (array_is_list($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        return [$value];
    }

    /**
     * @param  array<string, true>  $set
     * @return list<string>
     */
    private function sortedKeys(array $set): array
    {
        $keys = array_map(static fn ($key): string => (string) $key, array_keys($set));
        sort($keys);

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripForbiddenKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($this->keyIsForbidden(strtolower((string) $key))) {
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function findForbiddenKey(array $data, string $prefix = ''): ?string
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if ($this->keyIsForbidden(strtolower((string) $key))) {
                return $path;
            }
            if (is_array($value)) {
                $nested = $this->findForbiddenKey($value, $path);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function keyIsForbidden(string $keyLower): bool
    {
        foreach (self::FORBIDDEN_KEY_SUBSTRINGS as $needle) {
            if (str_contains($keyLower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
