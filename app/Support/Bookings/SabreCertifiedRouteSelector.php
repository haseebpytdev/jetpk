<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Support\Sabre\GdsPnrCreate\SabreGdsOneWayTripShapeClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreGdsReturnTripClassifier;

/**
 * Q1/R6 + Sprint 11B: Selects the single Sabre booking route for public checkout (no endpoint/style fallback chains).
 */
final class SabreCertifiedRouteSelector
{
    public const CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER = 'one_way_direct_same_carrier';

    public const CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS = 'one_way_connecting_same_carrier_gds';

    public const CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS = 'one_way_multistop_same_carrier_gds';

    public const CATEGORY_MIXED_INTERLINE = 'mixed_interline';

    public const CATEGORY_RETURN = 'return';

    public const CATEGORY_MULTI_CITY = 'multi_city';

    public const CATEGORY_ONE_WAY_CONNECTING = 'one_way_connecting';

    public const CATEGORY_UNKNOWN = 'unknown';

    public const STATUS_CERTIFIED = 'certified';

    public const STATUS_CONTROLLED_CERTIFIED = 'controlled_certified';

    public const STATUS_NOT_CERTIFIED = 'not_certified';

    public const STATUS_PENDING_CERTIFICATION = 'pending_certification';

    public const CONTROLLED_PNR_VERIFIED = 'controlled_pnr_verified';

    public const CONTROLLED_PNR_HOST_NOOP_BLOCKED = 'host_noop_blocked';

    public const CONTROLLED_PNR_FARE_RBD_NOT_SELLABLE = 'fare_rbd_not_sellable';

    public const CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY = 'unknown_controlled_only';

    public const EVIDENCE_STATUS_EXACT_SUCCESS = 'exact_success_evidence';

    public const EVIDENCE_STATUS_EXACT_FAILED = 'exact_failed_evidence';

    public const EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE = 'insufficient_flight_date_sellability_evidence';

    public const EVIDENCE_STATUS_HOST_NOOP_BLOCKED = 'host_noop_blocked';

    public const EVIDENCE_STATUS_UNKNOWN_CONTROLLED_ONLY = 'unknown_controlled_only';

    public const REASON_INSUFFICIENT_FARE_RBD_EVIDENCE = 'insufficient_fare_rbd_evidence';

    public const REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE = 'insufficient_flight_date_sellability_evidence';

    public const REASON_FARE_RBD_CARRIER_NOT_SELLABLE = 'fare_rbd_carrier_not_sellable';

    public const ENDPOINT_PASSENGER_RECORDS_V25_CREATE = '/v2.5.0/passenger/records?mode=create';

    public const ENDPOINT_PASSENGER_RECORDS_V24_CREATE = '/v2.4.0/passenger/records?mode=create';

    public const ERROR_CODE_PENDING = 'sabre_certified_route_pending';

    public const ERROR_CODE_NOT_CERTIFIED = 'sabre_certified_route_not_certified';

    public const DEFER_REASON = 'certified_route_not_available';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
    ) {}

    public static function isConnectingSameCarrierGdsEnabled(): bool
    {
        return (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled', false);
    }

    public static function isConnectingSameCarrierPublicCheckoutEnabled(): bool
    {
        return (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function selectForBooking(Booking $booking): array
    {
        $tripType = $this->certificationSupport->detectTripType($booking);
        $readiness = $this->certificationSupport->buildReadiness($booking);
        $category = $this->resolveCategory($tripType, $readiness);
        $controlledPnrCertification = $this->controlledPnrCertificationForBooking($booking, $tripType);

        $selection = match ($category) {
            self::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER => $this->certifiedPassengerRecordsV25Row($category),
            self::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS => $this->connectingSameCarrierGdsRow($category),
            self::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS => $this->connectingSameCarrierGdsRow($category),
            self::CATEGORY_MIXED_INTERLINE => $this->notCertifiedMixedInterlineRow($category),
            self::CATEGORY_RETURN => $this->pendingCertificationRow($category, 'return'),
            self::CATEGORY_MULTI_CITY => $this->pendingCertificationRow($category, 'multi_city'),
            self::CATEGORY_ONE_WAY_CONNECTING => $this->pendingCertificationRow($category, 'one_way_connecting'),
            default => $this->pendingCertificationRow(self::CATEGORY_UNKNOWN, 'unknown'),
        };

        if (in_array($category, [
            self::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
            self::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS,
        ], true)) {
            return $this->applyControlledPnrCertification($selection, $controlledPnrCertification);
        }

        return $selection;
    }

    public function publicCheckoutNoticeForSelection(array $selection): string
    {
        $category = (string) ($selection['category'] ?? '');
        if ($category === self::CATEGORY_MIXED_INTERLINE) {
            return 'Your booking request has been received. This itinerary requires staff confirmation — mixed or interline fares are not yet certified for automatic PNR creation.';
        }
        if ($category === self::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS
            && ($selection['route_status'] ?? '') === self::STATUS_CONTROLLED_CERTIFIED) {
            return 'Your booking request has been received. Same-carrier connecting itineraries require staff confirmation — automatic PNR is not enabled for public checkout yet.';
        }

        return 'Your booking request has been received. This itinerary type is not yet certified for automatic airline hold/PNR.';
    }

    /**
     * Admin/staff controlled PNR action — not the public-checkout defer copy.
     *
     * @param  list<string>  $blockers
     */
    public function adminStaffBlockedNoticeForSelection(array $selection, array $blockers = []): string
    {
        $category = (string) ($selection['category'] ?? '');
        if ($category === self::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS) {
            $message = 'Admin/staff PNR is not allowed for this same-carrier connecting booking yet.';
            if ($blockers !== []) {
                $message .= ' Blockers: '.implode(', ', array_slice($blockers, 0, 4)).'.';
            }

            return $message;
        }
        if ($category === self::CATEGORY_MIXED_INTERLINE) {
            return 'Mixed or interline itineraries are not certified for automated PNR — use manual PNR or staff workflow.';
        }
        if ($category === self::CATEGORY_RETURN || $category === self::CATEGORY_MULTI_CITY) {
            return 'Return and multi-city itineraries are not certified for automated admin/staff PNR yet.';
        }

        return 'This itinerary type is not certified for admin/staff automated PNR yet.';
    }

    /**
     * E5F: Safe fare/RBD/flight evidence profile for verified-lane public auto-PNR (no PII, no raw payloads).
     *
     * @return array<string, mixed>
     */
    public function buildPublicAutoPnrEvidenceProfile(Booking $booking): array
    {
        $tripType = $this->certificationSupport->detectTripType($booking);
        $routeProfile = $this->controlledPnrRouteProfile($booking, $tripType);
        if ($routeProfile === []) {
            return [];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = [];
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            if (is_array($meta[$key] ?? null) && $meta[$key] !== []) {
                $snapshot = $meta[$key];
                break;
            }
        }

        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $bookingClasses = [];
        $fareBasisCodes = [];
        $flightNumbers = [];
        $departDates = [];

        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $rbd = strtoupper(trim((string) (
                $seg['booking_class'] ?? $seg['class_of_service'] ?? $seg['rbd'] ?? $seg['resBookDesigCode'] ?? ''
            )));
            if ($rbd !== '') {
                $bookingClasses[] = $rbd;
            }
            $fb = strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fareBasisCode'] ?? '')));
            if ($fb !== '') {
                $fareBasisCodes[] = $fb;
            }
            $flight = trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? ''));
            if ($flight !== '') {
                $flightNumbers[] = $flight;
            }
            $dep = trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''));
            if ($dep !== '') {
                $departDates[] = substr($dep, 0, 10);
            }
        }

        $payloadStrategy = $this->resolvePayloadStrategyFromBooking($booking, $meta);

        $fingerprintParts = [
            'carrier_chain' => $routeProfile['carrier_chain'] ?? [],
            'origin' => (string) ($routeProfile['origin'] ?? ''),
            'connection_airport' => (string) ($routeProfile['connection_airport'] ?? ''),
            'destination' => (string) ($routeProfile['destination'] ?? ''),
            'segment_count' => (int) ($routeProfile['segment_count'] ?? 0),
            'booking_classes' => $bookingClasses,
            'fare_basis_codes' => $fareBasisCodes,
            'flight_numbers' => $flightNumbers,
            'depart_dates' => $departDates,
        ];
        if ($payloadStrategy !== '') {
            $fingerprintParts['payload_strategy'] = $payloadStrategy;
        }

        return array_merge($routeProfile, [
            'booking_classes' => $bookingClasses,
            'fare_basis_codes' => $fareBasisCodes,
            'flight_numbers' => $flightNumbers,
            'depart_dates' => $departDates,
            'payload_strategy' => $payloadStrategy !== '' ? $payloadStrategy : null,
            'evidence_fingerprint' => $this->buildEvidenceFingerprint($fingerprintParts),
        ]);
    }

    /**
     * E5F: Assess whether booking offer evidence matches verified success patterns or known failures.
     *
     * @return array<string, mixed>
     */
    public function assessVerifiedPublicAutoPnrEvidence(Booking $booking): array
    {
        $profile = $this->buildPublicAutoPnrEvidenceProfile($booking);
        $fingerprint = (string) ($profile['evidence_fingerprint'] ?? '');

        $base = [
            'status' => self::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE,
            'reason_code' => self::REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE,
            'reason_message' => 'Verified public auto-PNR requires exact flight/date sellability evidence — insufficient for this offer.',
            'evidence_fingerprint' => $fingerprint,
            'booking_classes' => $profile['booking_classes'] ?? [],
            'flight_numbers' => $profile['flight_numbers'] ?? [],
            'fare_basis_codes' => $profile['fare_basis_codes'] ?? [],
            'depart_dates' => $profile['depart_dates'] ?? [],
            'payload_strategy' => $profile['payload_strategy'] ?? null,
            'matched_success_booking_id' => null,
            'matched_failed_booking_id' => null,
        ];

        if ($profile === []) {
            return array_merge($base, [
                'reason_code' => self::REASON_INSUFFICIENT_FARE_RBD_EVIDENCE,
                'reason_message' => 'Offer snapshot segments missing — cannot assess fare/RBD/flight evidence.',
            ]);
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (($meta['verified_multiseg_auto_pnr_result'] ?? '') === 'failed'
            && ($meta['verified_multiseg_auto_pnr_reason_code'] ?? '') === self::REASON_FARE_RBD_CARRIER_NOT_SELLABLE) {
            return array_merge($base, [
                'status' => self::EVIDENCE_STATUS_EXACT_FAILED,
                'reason_code' => self::REASON_FARE_RBD_CARRIER_NOT_SELLABLE,
                'reason_message' => 'Prior verified public auto-PNR failed for this offer — terminal fare/RBD/carrier rejection.',
            ]);
        }

        foreach ($this->verifiedPublicAutoPnrFailedEvidenceMatrix() as $row) {
            if ($this->publicAutoPnrEvidenceProfileMatches($profile, $row)) {
                return array_merge($base, [
                    'status' => self::EVIDENCE_STATUS_EXACT_FAILED,
                    'reason_code' => self::REASON_FARE_RBD_CARRIER_NOT_SELLABLE,
                    'reason_message' => 'Static failed evidence registry — Sabre host rejected this fare/RBD/flight pattern.',
                    'failed_evidence_key' => $row['key'] ?? null,
                    'matched_failed_booking_id' => $row['verified_booking_id'] ?? null,
                ]);
            }
        }

        foreach ($this->verifiedPublicAutoPnrSuccessEvidenceRows() as $row) {
            if ($this->publicAutoPnrEvidenceProfileMatchesSuccess($profile, $row)) {
                $evidence = is_array($row['evidence'] ?? null) ? $row['evidence'] : [];

                return array_merge($base, [
                    'status' => self::EVIDENCE_STATUS_EXACT_SUCCESS,
                    'reason_code' => '',
                    'reason_message' => 'Offer evidence matches verified controlled PNR success pattern.',
                    'success_evidence_key' => $row['key'] ?? null,
                    'matched_success_booking_id' => $evidence['verified_booking_id'] ?? null,
                ]);
            }
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    protected function resolveCategory(string $tripType, array $readiness): string
    {
        if ($tripType === 'multi_city') {
            return self::CATEGORY_MULTI_CITY;
        }
        if (in_array($tripType, ['round_trip', SabreGdsReturnTripClassifier::TRIP_RETURN_SAME_CARRIER], true)) {
            return self::CATEGORY_RETURN;
        }
        if ($tripType === SabreGdsReturnTripClassifier::TRIP_RETURN_MIXED_CARRIER) {
            return self::CATEGORY_MIXED_INTERLINE;
        }
        if ($tripType === SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER) {
            return self::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS;
        }
        if (in_array($tripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER,
            'one_way_connecting',
        ], true)) {
            if ($this->isSameCarrierConnectingGdsCandidate($tripType, $readiness)) {
                return self::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS;
            }
        }
        if (in_array($tripType, [
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
        ], true)) {
            return self::CATEGORY_MIXED_INTERLINE;
        }
        if ($this->isMixedInterline($tripType, $readiness)) {
            return self::CATEGORY_MIXED_INTERLINE;
        }
        if ($tripType === 'one_way_direct'
            && $this->certificationSupport->isSimpleOneWaySameCarrierForCertification($readiness)) {
            return self::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER;
        }
        if ($this->isSameCarrierConnectingGdsCandidate($tripType, $readiness)) {
            return self::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS;
        }
        if ($tripType === 'one_way_connecting') {
            return self::CATEGORY_ONE_WAY_CONNECTING;
        }

        return self::CATEGORY_UNKNOWN;
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    protected function isMixedInterline(string $tripType, array $readiness): bool
    {
        if (($readiness['has_codeshare_segment'] ?? false) === true) {
            return true;
        }
        if (($readiness['validating_carrier_mismatch'] ?? false) === true) {
            return true;
        }
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];

        return count($carriers) > 1;
    }

    /**
     * Sprint 11B: Exactly two segments, single marketing carrier, no codeshare, validating carrier aligned.
     *
     * @param  array<string, mixed>  $readiness
     */
    protected function isSameCarrierConnectingGdsCandidate(string $tripType, array $readiness): bool
    {
        if (! in_array($tripType, [
            'one_way_connecting',
            SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER,
        ], true)) {
            return false;
        }
        if ((int) ($readiness['segment_count'] ?? 0) !== 2) {
            return false;
        }
        if ($this->isMixedInterline($tripType, $readiness)) {
            return false;
        }
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];

        return count($carriers) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    protected function certifiedPassengerRecordsV25Row(string $category): array
    {
        return [
            'category' => $category,
            'route_status' => self::STATUS_CERTIFIED,
            'live_booking_allowed' => true,
            'booking_schema' => 'create_passenger_name_record',
            'endpoint_path' => self::ENDPOINT_PASSENGER_RECORDS_V25_CREATE,
            'payload_style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            'payload_style_label' => 'baseline',
            'recommended_path_label' => 'passenger_records_v2_5_baseline',
            'notes' => 'Only certified public-checkout Sabre path; no fallback to /v2/passengers/create or Trip Orders.',
            'iati_like_preference_enabled' => (bool) config('suppliers.sabre.cpnr_iati_style_certified_gds_enabled', false),
            'eligible_for_iati_like_cpnr' => null,
            'not_eligible_reason' => null,
            'connecting_same_carrier_gds_enabled' => false,
            'connecting_same_carrier_public_checkout_enabled' => false,
        ];
    }

    /**
     * Sprint 11B: Controlled same-carrier 2-segment GDS — iati-like v2.4 when config + readiness allow.
     *
     * @return array<string, mixed>
     */
    protected function connectingSameCarrierGdsRow(string $category): array
    {
        $gdsEnabled = self::isConnectingSameCarrierGdsEnabled();
        $publicEnabled = $gdsEnabled && self::isConnectingSameCarrierPublicCheckoutEnabled();

        if (! $gdsEnabled) {
            return array_merge($this->pendingCertificationRow($category, 'one_way_connecting_same_carrier_gds'), [
                'category' => $category,
                'notes' => 'Same-carrier 2-segment GDS: controlled certification disabled (SABRE_CPNR_CONNECTING_SAME_CARRIER_GDS_ENABLED=false).',
                'connecting_same_carrier_gds_enabled' => false,
                'connecting_same_carrier_public_checkout_enabled' => false,
                'controlled_certification_required' => true,
            ]);
        }

        return [
            'category' => $category,
            'route_status' => $publicEnabled ? self::STATUS_CERTIFIED : self::STATUS_CONTROLLED_CERTIFIED,
            'live_booking_allowed' => $publicEnabled,
            'booking_schema' => 'create_passenger_name_record',
            'endpoint_path' => self::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
            'payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'payload_style_label' => 'iati_like_cpnr_v2_4_gds',
            'recommended_path_label' => 'passenger_records_v2_4_iati_like_controlled',
            'notes' => $publicEnabled
                ? 'Same-carrier 2-segment GDS certified for public checkout (iati-like CPNR v2.4 when readiness passes).'
                : 'Same-carrier 2-segment GDS: admin/staff controlled certification only; public checkout live PNR disabled.',
            'iati_like_preference_enabled' => (bool) config('suppliers.sabre.cpnr_iati_style_certified_gds_enabled', false),
            'eligible_for_iati_like_cpnr' => null,
            'not_eligible_reason' => null,
            'connecting_same_carrier_gds_enabled' => true,
            'connecting_same_carrier_public_checkout_enabled' => $publicEnabled,
            'controlled_certification_required' => ! $publicEnabled,
            'admin_staff_pnr_retry_allowed' => true,
            'error_code' => $publicEnabled ? null : self::ERROR_CODE_PENDING,
        ];
    }

    /**
     * Safe static certification matrix for controlled admin/staff PNR eligibility.
     *
     * @return array<string, array{
     *     carrier_chain: list<string>,
     *     origin: string,
     *     connection_airport: string,
     *     destination: string,
     *     segment_count: int,
     *     trip_type: string,
     *     result_status: string,
     *     evidence: array<string, mixed>
     * }>
     */
    protected function controlledPnrCertificationMatrix(): array
    {
        return [
            'gf_lhe_bah_jed_booking_44' => [
                'carrier_chain' => ['GF', 'GF'],
                'origin' => 'LHE',
                'connection_airport' => 'BAH',
                'destination' => 'JED',
                'segment_count' => 2,
                'trip_type' => 'one_way_connecting',
                'result_status' => self::CONTROLLED_PNR_VERIFIED,
                'evidence' => [
                    'verified_booking_id' => 44,
                    'verified_pnr_present' => true,
                    'airline_locator_present' => true,
                    'ticketing_enabled' => false,
                    'booking_classes' => ['W', 'W'],
                    'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                    'flight_numbers' => ['767', '171'],
                    'depart_dates' => ['2026-07-29', '2026-07-30'],
                    'payload_strategy' => 'E5A_SAFE_STRUCTURE_V1',
                ],
            ],
            'pk_lhe_khi_jed_host_noop_booking_43' => [
                'carrier_chain' => ['PK', 'PK'],
                'origin' => 'LHE',
                'connection_airport' => 'KHI',
                'destination' => 'JED',
                'segment_count' => 2,
                'trip_type' => 'one_way_connecting',
                'result_status' => self::CONTROLLED_PNR_HOST_NOOP_BLOCKED,
                'evidence' => [
                    'verified_booking_id' => 43,
                    'verified_pnr_present' => false,
                    'airline_locator_present' => false,
                    'ticketing_enabled' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function controlledPnrCertificationForBooking(Booking $booking, string $tripType): array
    {
        $profile = $this->controlledPnrRouteProfile($booking, $tripType);
        if ($profile === []) {
            return $this->unknownControlledPnrCertification();
        }

        foreach ($this->controlledPnrCertificationMatrix() as $key => $row) {
            if ($this->controlledPnrProfileMatches($profile, $row)) {
                return array_merge($row, [
                    'key' => $key,
                    'matched' => true,
                ]);
            }
        }

        return array_merge($profile, $this->unknownControlledPnrCertification());
    }

    /**
     * @return array<string, mixed>
     */
    protected function unknownControlledPnrCertification(): array
    {
        return [
            'key' => null,
            'matched' => false,
            'result_status' => self::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY,
            'evidence' => [
                'verified_booking_id' => null,
                'verified_pnr_present' => false,
                'airline_locator_present' => false,
                'ticketing_enabled' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function controlledPnrRouteProfile(Booking $booking, string $tripType): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = [];
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            if (is_array($meta[$key] ?? null) && $meta[$key] !== []) {
                $snapshot = $meta[$key];
                break;
            }
        }

        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        if ($segments === []) {
            return [];
        }

        $carrierChain = [];
        $routeChain = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_carrier'] ?? '')));
            if ($carrier !== '') {
                $carrierChain[] = $carrier;
            }
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($seg['destination'] ?? '')));
            if ($origin !== '') {
                $routeChain[] = $origin;
            }
            if ($destination !== '') {
                $routeChain[] = $destination;
            }
        }
        $routeChain = $this->dedupeAdjacentAirports($routeChain);

        return [
            'carrier_chain' => $carrierChain,
            'origin' => (string) ($routeChain[0] ?? ''),
            'connection_airport' => count($routeChain) === 3 ? (string) $routeChain[1] : '',
            'destination' => (string) ($routeChain[count($routeChain) - 1] ?? ''),
            'segment_count' => count($segments),
            'trip_type' => $tripType,
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $row
     */
    protected function controlledPnrProfileMatches(array $profile, array $row): bool
    {
        return $profile['carrier_chain'] === $row['carrier_chain']
            && (string) ($profile['origin'] ?? '') === (string) ($row['origin'] ?? '')
            && (string) ($profile['connection_airport'] ?? '') === (string) ($row['connection_airport'] ?? '')
            && (string) ($profile['destination'] ?? '') === (string) ($row['destination'] ?? '')
            && (int) ($profile['segment_count'] ?? 0) === (int) ($row['segment_count'] ?? 0)
            && (string) ($profile['trip_type'] ?? '') === (string) ($row['trip_type'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $certification
     * @return array<string, mixed>
     */
    protected function applyControlledPnrCertification(array $selection, array $certification): array
    {
        $evidence = is_array($certification['evidence'] ?? null) ? $certification['evidence'] : [];
        $status = (string) ($certification['result_status'] ?? self::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY);

        $selection['controlled_pnr_certification_status'] = $status;
        $selection['controlled_pnr_certification_key'] = $certification['key'] ?? null;
        $selection['controlled_pnr_verified_booking_id'] = $evidence['verified_booking_id'] ?? null;
        $selection['controlled_pnr_verified_pnr_present'] = ($evidence['verified_pnr_present'] ?? false) === true;
        $selection['controlled_pnr_airline_locator_present'] = ($evidence['airline_locator_present'] ?? false) === true;
        $selection['controlled_pnr_ticketing_enabled'] = ($evidence['ticketing_enabled'] ?? false) === true;
        $selection['controlled_pnr_carrier_chain'] = implode('→', (array) ($certification['carrier_chain'] ?? []));
        $selection['controlled_pnr_origin'] = (string) ($certification['origin'] ?? '');
        $selection['controlled_pnr_connection_airport'] = (string) ($certification['connection_airport'] ?? '');
        $selection['controlled_pnr_destination'] = (string) ($certification['destination'] ?? '');

        if ($status === self::CONTROLLED_PNR_HOST_NOOP_BLOCKED) {
            $selection['route_status'] = self::STATUS_NOT_CERTIFIED;
            $selection['live_booking_allowed'] = false;
            $selection['admin_staff_pnr_retry_allowed'] = false;
            $selection['error_code'] = self::ERROR_CODE_NOT_CERTIFIED;
            $selection['notes'] = 'Host rejected this certified route evidence (FLIGHT NOOP / 0118). Do not retry the same itinerary; use fresh search/alternate itinerary or manual fulfillment.';
        }

        return $selection;
    }

    /**
     * @return array<string, mixed>
     */
    protected function notCertifiedMixedInterlineRow(string $category): array
    {
        return [
            'category' => $category,
            'route_status' => self::STATUS_NOT_CERTIFIED,
            'live_booking_allowed' => false,
            'booking_schema' => null,
            'endpoint_path' => null,
            'payload_style' => null,
            'payload_style_label' => null,
            'recommended_path_label' => 'trip_orders_pending_agency_profile',
            'notes' => 'Mixed/interline: Passenger Records often returns NO FARES; Trip Orders blocked by AGENCY_PHONE_MISSING until PCC profile is fixed.',
            'error_code' => self::ERROR_CODE_NOT_CERTIFIED,
            'connecting_same_carrier_gds_enabled' => false,
            'connecting_same_carrier_public_checkout_enabled' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pendingCertificationRow(string $category, string $tripTypeHint): array
    {
        return [
            'category' => $category,
            'route_status' => self::STATUS_PENDING_CERTIFICATION,
            'live_booking_allowed' => false,
            'booking_schema' => null,
            'endpoint_path' => null,
            'payload_style' => null,
            'payload_style_label' => null,
            'recommended_path_label' => 'pending_certification',
            'trip_type_hint' => $tripTypeHint,
            'notes' => 'Itinerary type not certified for public automatic PNR; staff confirmation required.',
            'error_code' => self::ERROR_CODE_PENDING,
            'connecting_same_carrier_gds_enabled' => false,
            'connecting_same_carrier_public_checkout_enabled' => false,
        ];
    }

    /**
     * @param  list<string>  $chain
     * @return list<string>
     */
    protected function dedupeAdjacentAirports(array $chain): array
    {
        $out = [];
        foreach ($chain as $code) {
            if ($out !== [] && end($out) === $code) {
                continue;
            }
            $out[] = $code;
        }

        return $out;
    }

    /**
     * E5F: Verified public auto-PNR success evidence rows (Booking 44 pattern).
     *
     * @return list<array<string, mixed>>
     */
    protected function verifiedPublicAutoPnrSuccessEvidenceRows(): array
    {
        $rows = [];
        foreach ($this->controlledPnrCertificationMatrix() as $key => $row) {
            if (($row['result_status'] ?? '') !== self::CONTROLLED_PNR_VERIFIED) {
                continue;
            }
            $evidence = is_array($row['evidence'] ?? null) ? $row['evidence'] : [];
            $rows[] = array_merge($row, [
                'key' => $key,
                'booking_classes' => (array) ($evidence['booking_classes'] ?? []),
                'fare_basis_codes' => (array) ($evidence['fare_basis_codes'] ?? []),
                'flight_numbers' => (array) ($evidence['flight_numbers'] ?? []),
                'depart_dates' => (array) ($evidence['depart_dates'] ?? []),
                'payload_strategy' => (string) ($evidence['payload_strategy'] ?? ''),
            ]);
        }

        return $rows;
    }

    /**
     * E5F: Static failed fare/RBD evidence — add Booking 46 production segment values when confirmed.
     *
     * @return list<array<string, mixed>>
     */
    protected function verifiedPublicAutoPnrFailedEvidenceMatrix(): array
    {
        return [
            [
                'key' => 'gf_lhe_bah_jed_booking_46',
                'carrier_chain' => ['GF', 'GF'],
                'origin' => 'LHE',
                'connection_airport' => 'BAH',
                'destination' => 'JED',
                'segment_count' => 2,
                'trip_type' => 'one_way_connecting',
                'booking_classes' => ['W', 'W'],
                'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                'flight_numbers' => ['765', '173'],
                'depart_dates' => ['2026-07-31', '2026-08-01'],
                'verified_booking_id' => 46,
                'classification' => self::REASON_FARE_RBD_CARRIER_NOT_SELLABLE,
            ],
        ];
    }

    /**
     * Safe payload strategy from booking meta or latest successful create_pnr attempt (no raw payloads).
     *
     * Does not use latestSupplierBookingAttempt — a newer pnr_retrieve success would hide create strategy.
     */
    protected function resolvePayloadStrategyFromBooking(Booking $booking, array $meta): string
    {
        $fromMeta = trim((string) ($meta['create_payload_strategy_version'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        if ($booking->exists) {
            $attempts = SupplierBookingAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('action', 'create_pnr')
                ->where('status', 'success')
                ->orderByDesc('id')
                ->get();

            foreach ($attempts as $attempt) {
                $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
                $fromAttempt = trim((string) ($safe['create_payload_strategy_version'] ?? ''));

                if ($fromAttempt !== '') {
                    return $fromAttempt;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $row
     */
    protected function publicAutoPnrEvidenceProfileMatches(array $profile, array $row): bool
    {
        if (! $this->controlledPnrProfileMatches($profile, $row)) {
            return false;
        }

        $profileClasses = (array) ($profile['booking_classes'] ?? []);
        $rowClasses = (array) ($row['booking_classes'] ?? []);
        if ($rowClasses !== [] && $profileClasses !== $rowClasses) {
            return false;
        }

        $profileFlights = (array) ($profile['flight_numbers'] ?? []);
        $rowFlights = (array) ($row['flight_numbers'] ?? []);
        if ($rowFlights !== [] && $profileFlights !== $rowFlights) {
            return false;
        }

        $profileFare = (array) ($profile['fare_basis_codes'] ?? []);
        $rowFare = (array) ($row['fare_basis_codes'] ?? []);
        if ($rowFare !== [] && $profileFare !== $rowFare) {
            return false;
        }

        $profileDates = (array) ($profile['depart_dates'] ?? []);
        $rowDates = (array) ($row['depart_dates'] ?? []);
        if ($rowDates !== [] && $profileDates !== $rowDates) {
            return false;
        }

        return $this->publicAutoPnrPayloadStrategyMatches($profile, $row);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $row
     */
    protected function publicAutoPnrEvidenceProfileMatchesSuccess(array $profile, array $row): bool
    {
        if (! $this->controlledPnrProfileMatches($profile, $row)) {
            return false;
        }

        $profileClasses = (array) ($profile['booking_classes'] ?? []);
        $rowClasses = (array) ($row['booking_classes'] ?? []);
        if ($rowClasses === [] || $profileClasses !== $rowClasses) {
            return false;
        }

        $profileFlights = (array) ($profile['flight_numbers'] ?? []);
        $rowFlights = (array) ($row['flight_numbers'] ?? []);
        if ($rowFlights === [] || $profileFlights !== $rowFlights) {
            return false;
        }

        $profileFare = (array) ($profile['fare_basis_codes'] ?? []);
        $rowFare = (array) ($row['fare_basis_codes'] ?? []);
        if ($rowFare === [] || $profileFare !== $rowFare) {
            return false;
        }

        $profileDates = (array) ($profile['depart_dates'] ?? []);
        $rowDates = (array) ($row['depart_dates'] ?? []);
        if ($rowDates === [] || $profileDates !== $rowDates) {
            return false;
        }

        return $this->publicAutoPnrPayloadStrategyMatches($profile, $row);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $row
     */
    protected function publicAutoPnrPayloadStrategyMatches(array $profile, array $row): bool
    {
        $evidence = is_array($row['evidence'] ?? null) ? $row['evidence'] : [];
        $rowStrategy = trim((string) ($row['payload_strategy'] ?? $evidence['payload_strategy'] ?? ''));
        if ($rowStrategy === '') {
            return true;
        }

        $profileStrategy = trim((string) ($profile['payload_strategy'] ?? ''));

        return $profileStrategy !== '' && $profileStrategy === $rowStrategy;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    protected function buildEvidenceFingerprint(array $parts): string
    {
        $normalized = json_encode($parts, JSON_UNESCAPED_UNICODE);

        return $normalized !== false ? hash('sha256', $normalized) : '';
    }
}
