<?php

namespace App\Services\Suppliers\Sabre\Cancel;

/**
 * Sprint 0/1: Build candidate Trip Orders cancelBooking request bodies (inspect/cert only).
 * Mirrors getBooking confirmationId probes; adds cancelData/cancelAll and order-ID variants when context allows.
 */
final class SabreCancelPayloadBuilder
{
    public const STYLE_TRIP_ORDERS_CONFIRMATION_API_ID = 'trip_orders_confirmation_api_id';

    public const STYLE_TRIP_ORDERS_CONFIRMATION_SUPPLIER_REF = 'trip_orders_confirmation_supplier_ref';

    public const STYLE_TRIP_ORDERS_CONFIRMATION_PNR = 'trip_orders_confirmation_pnr';

    public const STYLE_TRIP_ORDERS_RECORD_LOCATOR = 'trip_orders_record_locator';

    public const STYLE_TRIP_ORDERS_CONFIRMATION_CANCEL_ALL = 'trip_orders_confirmation_cancel_all';

    public const STYLE_TRIP_ORDERS_CONFIRMATION_CANCEL_DATA = 'trip_orders_confirmation_cancel_data';

    public const STYLE_TRIP_ORDERS_REQUEST_WRAPPED_CANCEL_DATA = 'trip_orders_request_wrapped_cancel_data';

    public const STYLE_TRIP_ORDERS_ORDER_ID_CANCEL_DATA = 'trip_orders_order_id_cancel_data';

    public const STYLE_TRIP_ORDERS_ORDER_ITEMS_CANCEL = 'trip_orders_order_items_cancel';

    public const STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL = 'trip_orders_segment_ids_cancel';

    public const STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_DATA_CANCEL_ALL = 'trip_orders_confirmation_pnr_cancel_data_cancel_all';

    public const STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT = 'trip_orders_confirmation_pnr_cancel_all_root';

    public const STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE = 'trip_orders_confirmation_pnr_cancel_all_booking_source';

    public const STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL = 'official_postman_confirmation_cancel_all';

    private const DEFAULT_CANCEL_BOOKING_SOURCE = 'SABRE';

    private const DEFAULT_CANCEL_RECEIVED_FROM = 'LW CANCEL API';

    public const CONFIG_STYLE_AUTO_MATRIX_CURRENT = 'auto_matrix_current';

    public const CONFIG_STYLE_CONFIRMATION_ID_ONLY = 'confirmation_id_only';

    public const CONFIG_STYLE_CONFIRMATION_ID_CANCEL_ALL = 'confirmation_id_cancel_all';

    public const CONFIG_STYLE_CONFIRMATION_ID_RETRIEVE_CANCEL_ALL = 'confirmation_id_retrieve_cancel_all';

    public const CONFIG_STYLE_CONFIRMATION_ID_CANCEL_ALL_BOOKING_SOURCE = 'confirmation_id_cancel_all_booking_source';

    public const CONFIG_STYLE_BOOKING_ID_SIGNATURE_CANCEL_ALL = 'booking_id_signature_cancel_all';

    public const CONFIG_STYLE_ORDER_ITEM_IDS = 'order_item_ids';

    public const STYLE_TRIP_ORDERS_REQUEST_CONFIRMATION_CANCEL_DATA = 'trip_orders_request_confirmation_cancel_data';

    public const STYLE_TRIP_ORDERS_CANCEL_REQUEST_CONFIRMATION = 'trip_orders_cancel_request_confirmation';

    public const STYLE_TRIP_ORDERS_CANCEL_REQUEST_ROOT = 'trip_orders_cancel_request_root';

    public const STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT = 'trip_orders_booking_id_cancel_all_root';

    public const STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_DATA = 'trip_orders_booking_id_cancel_data';

    public const STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL = 'trip_orders_booking_id_signature_cancel_all';

    public const STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_DATA = 'trip_orders_booking_id_signature_cancel_data';

    public const STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED = 'trip_orders_booking_id_request_wrapped';

    public const STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL = 'trip_orders_cancel_booking_request_booking_id_signature_cancel_all';

    public const STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_DATA = 'trip_orders_cancel_booking_request_booking_id_signature_cancel_data';

    public const STYLE_TRIP_ORDERS_CANCELBOOKINGREQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL = 'trip_orders_CancelBookingRequest_booking_id_signature_cancel_all';

    public const STYLE_TRIP_ORDERS_CANCELBOOKINGRQ_BOOKING_ID_SIGNATURE_CANCEL_ALL = 'trip_orders_CancelBookingRQ_booking_id_signature_cancel_all';

    /** @var list<string> */
    public const DRY_RUN_ONLY_WRAPPER_STYLE_CONSTANTS = [
        self::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        self::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_CANCELBOOKINGREQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        self::STYLE_TRIP_ORDERS_CANCELBOOKINGRQ_BOOKING_ID_SIGNATURE_CANCEL_ALL,
    ];

    /** @var list<string> */
    public const ALL_STYLE_CONSTANTS = [
        self::STYLE_TRIP_ORDERS_CONFIRMATION_API_ID,
        self::STYLE_TRIP_ORDERS_CONFIRMATION_SUPPLIER_REF,
        self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
        self::STYLE_TRIP_ORDERS_RECORD_LOCATOR,
        self::STYLE_TRIP_ORDERS_CONFIRMATION_CANCEL_ALL,
        self::STYLE_TRIP_ORDERS_CONFIRMATION_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_REQUEST_WRAPPED_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_ORDER_ID_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_ORDER_ITEMS_CANCEL,
        self::STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL,
        self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_DATA_CANCEL_ALL,
        self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
        self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE,
        self::STYLE_TRIP_ORDERS_REQUEST_CONFIRMATION_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_CANCEL_REQUEST_CONFIRMATION,
        self::STYLE_TRIP_ORDERS_CANCEL_REQUEST_ROOT,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED,
        self::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        self::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_CANCELBOOKINGREQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        self::STYLE_TRIP_ORDERS_CANCELBOOKINGRQ_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        self::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
    ];

    /** @var list<string> */
    public const BOOKING_ID_BASED_STYLE_CONSTANTS = [
        self::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED,
    ];

    /** @var list<string> */
    public const BOOKING_ID_SIGNATURE_REQUIRED_STYLES = [
        self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_DATA,
        self::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED,
    ];

    public static function isBookingIdBasedStyle(string $style): bool
    {
        return in_array(trim($style), self::BOOKING_ID_BASED_STYLE_CONSTANTS, true);
    }

    /**
     * @return list<string>
     */
    public static function allowedConfiguredPayloadStyles(): array
    {
        return [
            self::CONFIG_STYLE_AUTO_MATRIX_CURRENT,
            self::CONFIG_STYLE_CONFIRMATION_ID_ONLY,
            self::CONFIG_STYLE_CONFIRMATION_ID_CANCEL_ALL,
            self::CONFIG_STYLE_CONFIRMATION_ID_RETRIEVE_CANCEL_ALL,
            self::CONFIG_STYLE_CONFIRMATION_ID_CANCEL_ALL_BOOKING_SOURCE,
            self::CONFIG_STYLE_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            self::CONFIG_STYLE_ORDER_ITEM_IDS,
        ];
    }

    public static function configuredPayloadStyle(): string
    {
        $style = trim((string) config('suppliers.sabre.cancel_payload_style', self::CONFIG_STYLE_AUTO_MATRIX_CURRENT));

        return in_array($style, self::allowedConfiguredPayloadStyles(), true)
            ? $style
            : self::CONFIG_STYLE_AUTO_MATRIX_CURRENT;
    }

    public static function usesAutoConfiguredPayloadStyle(): bool
    {
        return self::configuredPayloadStyle() === self::CONFIG_STYLE_AUTO_MATRIX_CURRENT;
    }

    public static function isDryRunOnlyWrapperStyle(string $style): bool
    {
        return in_array(trim($style), self::DRY_RUN_ONLY_WRAPPER_STYLE_CONSTANTS, true);
    }

    public static function isCertifiedGdsConfirmationFullCancelStyle(string $style): bool
    {
        return trim($style) === self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE;
    }

    public static function isOfficialPostmanConfirmationCancelAllStyle(string $style): bool
    {
        return trim($style) === self::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL;
    }

    public static function isConfirmationOnlyFullCancelStyle(string $style): bool
    {
        return self::isCertifiedGdsConfirmationFullCancelStyle($style)
            || self::isOfficialPostmanConfirmationCancelAllStyle($style);
    }

    public static function styleRequiresTripOrderBookingSignature(string $style): bool
    {
        return in_array(trim($style), self::BOOKING_ID_SIGNATURE_REQUIRED_STYLES, true)
            || in_array(trim($style), self::DRY_RUN_ONLY_WRAPPER_STYLE_CONSTANTS, true);
    }

    /** @var list<string> */
    private const CONFIRMATION_ONLY_STYLES = [
        self::STYLE_TRIP_ORDERS_CONFIRMATION_API_ID,
        self::STYLE_TRIP_ORDERS_CONFIRMATION_SUPPLIER_REF,
        self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
        self::STYLE_TRIP_ORDERS_RECORD_LOCATOR,
    ];

    /**
     * @return list<array{
     *   style: string,
     *   body: array<string, mixed>,
     *   primary_identifier_field: string,
     *   primary_identifier_source: string,
     *   recommended: bool,
     *   why_candidate_exists: string,
     *   required_snapshot_fields_present: bool,
     *   safe_shape_keys: list<string>,
     *   required_trip_order_fields_present: bool,
     *   previously_failed_reason: ?string,
     *   previously_ineffective_reason: ?string
     * }>
     */
    public function buildCandidatePayloads(
        string $pnr,
        ?string $supplierApiBookingId,
        ?string $supplierReference,
        ?SabreCancelBookingContext $context = null,
        ?array &$equivalenceAnalysis = null,
    ): array {
        $pnr = strtoupper(trim($pnr));
        $apiId = is_string($supplierApiBookingId) && trim($supplierApiBookingId) !== ''
            ? trim($supplierApiBookingId)
            : null;
        $supRef = is_string($supplierReference) && trim($supplierReference) !== ''
            ? trim($supplierReference)
            : null;

        $candidates = [];
        $confirmationSources = [];

        if ($apiId !== null) {
            $confirmationSources[] = ['id' => $apiId, 'source' => 'supplier_api_booking_id', 'prefer' => true];
        }
        if ($supRef !== null && strtoupper($supRef) !== $pnr && ($apiId === null || $supRef !== $apiId)) {
            $confirmationSources[] = ['id' => $supRef, 'source' => 'supplier_reference', 'prefer' => $apiId === null];
        }
        if ($pnr !== '') {
            $confirmationSources[] = [
                'id' => $pnr,
                'source' => 'pnr',
                'prefer' => $apiId === null && ($supRef === null || strtoupper($supRef) === $pnr),
            ];
        }

        foreach ($confirmationSources as $src) {
            $id = (string) $src['id'];
            $source = (string) $src['source'];
            $styleBase = match ($source) {
                'supplier_api_booking_id' => self::STYLE_TRIP_ORDERS_CONFIRMATION_API_ID,
                'supplier_reference' => self::STYLE_TRIP_ORDERS_CONFIRMATION_SUPPLIER_REF,
                default => self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
            };

            $candidates[] = $this->row(
                $styleBase,
                ['confirmationId' => $id],
                'confirmationId',
                $source,
                false,
                'Sabre Trip Orders cancelBooking commonly accepts confirmationId; prior probe may need cancelData.',
                false,
            );

            $candidates[] = $this->row(
                $styleBase.'_cancel_all',
                ['confirmationId' => $id, 'cancelAll' => true],
                'confirmationId',
                $source,
                false,
                'Adds cancelAll flag when confirmationId alone returned CANCEL_DATA_MISSING.',
                false,
            );

            $candidates[] = $this->row(
                $styleBase.'_cancel_data',
                [
                    'confirmationId' => $id,
                    'cancelData' => ['cancelAll' => true],
                ],
                'confirmationId',
                $source,
                false,
                'Wraps cancel intent in cancelData.cancelAll per Sabre cancelBooking error hint.',
                false,
            );

            $candidates[] = $this->row(
                $styleBase.'_request_wrapped',
                [
                    'request' => [
                        'confirmationId' => $id,
                        'cancelData' => ['cancelAll' => true],
                    ],
                ],
                'confirmationId',
                $source,
                false,
                'Alternate request-root wrapper seen in some Trip Orders payloads.',
                false,
            );
        }

        if ($pnr !== '') {
            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_RECORD_LOCATOR,
                ['recordLocator' => $pnr],
                'recordLocator',
                'pnr',
                false,
                'Record locator alias for the PNR when confirmationId shape is rejected.',
                false,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_DATA_CANCEL_ALL,
                [
                    'confirmationId' => $pnr,
                    'cancelData' => ['cancelAll' => true],
                ],
                'confirmationId',
                'pnr',
                false,
                'Explicit cancelData.cancelAll under confirmationId (probe matrix).',
                false,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
                ['confirmationId' => $pnr, 'cancelAll' => true],
                'confirmationId',
                'pnr',
                false,
                'cancelAll at root next to confirmationId (probe matrix).',
                false,
            );

            $candidates[] = $this->row(
                self::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
                [
                    'confirmationId' => $pnr,
                    'retrieveBooking' => true,
                    'cancelAll' => true,
                    'errorHandlingPolicy' => 'ALLOW_PARTIAL_CANCEL',
                ],
                'confirmationId',
                'pnr',
                false,
                'Official Sabre Dev Studio / Postman cancelBooking: confirmationId + retrieveBooking + cancelAll + errorHandlingPolicy (no wrappers).',
                false,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE,
                [
                    'confirmationId' => $pnr,
                    'cancelAll' => true,
                    'bookingSource' => self::DEFAULT_CANCEL_BOOKING_SOURCE,
                    'receivedFrom' => self::DEFAULT_CANCEL_RECEIVED_FROM,
                ],
                'confirmationId',
                'pnr',
                false,
                'Sabre-confirmed GDS full cancel: confirmationId + cancelAll + bookingSource + receivedFrom at request root.',
                false,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_REQUEST_CONFIRMATION_CANCEL_DATA,
                [
                    'request' => [
                        'confirmationId' => $pnr,
                        'cancelData' => ['cancelAll' => true],
                    ],
                ],
                'confirmationId',
                'pnr',
                false,
                'request-root wrapper with confirmationId and cancelData.cancelAll.',
                false,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_CANCEL_REQUEST_CONFIRMATION,
                [
                    'CancelBookingRQ' => [
                        'confirmationId' => $pnr,
                        'cancelData' => ['cancelAll' => true],
                    ],
                ],
                'confirmationId',
                'pnr',
                false,
                'CancelBookingRQ wrapper (legacy SOAP-style root).',
                false,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_CANCEL_REQUEST_ROOT,
                [
                    'CancelBookingRequest' => [
                        'confirmationId' => $pnr,
                        'cancelData' => ['cancelAll' => true],
                    ],
                ],
                'confirmationId',
                'pnr',
                false,
                'CancelBookingRequest root wrapper.',
                false,
            );
        }

        if ($context !== null) {
            $this->appendOrderIdCandidates($candidates, $context, $pnr);
            $this->appendTripOrderBookingIdCandidates($candidates, $context);
        }

        $candidates = $this->dedupeCandidates($candidates);
        if ($context !== null) {
            $this->applyAttemptHistoryMarkers($candidates, $context);
            $equivalenceAnalysis = SabreCancelProbeDiagnostics::enrichCandidatesWithDuplicateSemantics(
                $candidates,
                $context,
            );
        }
        $this->applyRecommendation($candidates, $context, $equivalenceAnalysis);
        if (is_array($equivalenceAnalysis)) {
            $equivalenceAnalysis = SabreCancelProbeDiagnostics::finalizeEquivalenceAnalysis(
                $candidates,
                $equivalenceAnalysis,
                $context,
            );
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    public function allKnownStyleNames(): array
    {
        $names = self::ALL_STYLE_CONSTANTS;
        foreach (['supplier_api_booking_id', 'supplier_reference', 'pnr'] as $source) {
            foreach (['_cancel_all', '_cancel_data', '_request_wrapped'] as $suffix) {
                $base = match ($source) {
                    'supplier_api_booking_id' => self::STYLE_TRIP_ORDERS_CONFIRMATION_API_ID,
                    'supplier_reference' => self::STYLE_TRIP_ORDERS_CONFIRMATION_SUPPLIER_REF,
                    default => self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
                };
                $names[] = $base.$suffix;
            }
        }
        $names[] = 'trip_orders_service_items_cancel';

        return array_values(array_unique($names));
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected function appendOrderIdCandidates(array &$candidates, SabreCancelBookingContext $context, string $pnr): void
    {
        $orderId = $context->orderId;
        if ($orderId === null || $orderId === '') {
            if ($context->segmentIds !== [] && $pnr !== '') {
                $candidates[] = $this->row(
                    self::STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL,
                    [
                        'confirmationId' => $pnr,
                        'cancelData' => ['segmentIds' => $context->segmentIds],
                    ],
                    'confirmationId',
                    'pnr',
                    false,
                    'Segment-level cancel using PNR confirmationId plus cancelData.segmentIds from snapshot/meta.',
                    true,
                );
            }

            return;
        }

        $candidates[] = $this->row(
            self::STYLE_TRIP_ORDERS_ORDER_ID_CANCEL_DATA,
            [
                'orderId' => $orderId,
                'cancelData' => ['cancelAll' => true],
            ],
            'orderId',
            'stored_meta_or_supplier_booking',
            false,
            'Uses Trip Orders orderId from stored booking meta or supplier_api_booking_id when not PNR-shaped.',
            true,
        );

        if ($context->orderItemIds !== []) {
            $body = ['orderId' => $orderId, 'orderItemIds' => $context->orderItemIds];
            if (count($context->orderItemIds) === 1) {
                $body = ['orderId' => $orderId, 'orderItemId' => $context->orderItemIds[0]];
            }
            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_ORDER_ITEMS_CANCEL,
                $body,
                'orderId',
                'pnr_itinerary_snapshot_or_meta',
                false,
                'Targets specific order items when getBooking/snapshot exposed orderItemIds.',
                true,
            );
        }

        if ($context->segmentIds !== []) {
            $confirm = $pnr !== '' ? $pnr : $orderId;
            $body = [
                'confirmationId' => $confirm,
                'cancelData' => ['segmentIds' => $context->segmentIds],
            ];
            if (count($context->segmentIds) === 1) {
                $body['cancelData'] = ['segmentId' => $context->segmentIds[0]];
            }
            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL,
                $body,
                'confirmationId',
                $pnr !== '' ? 'pnr' : 'stored_meta_or_supplier_booking',
                false,
                'Segment-level cancel when snapshot/meta includes segmentIds.',
                true,
            );
        }

        if ($context->serviceItemIds !== []) {
            $candidates[] = $this->row(
                'trip_orders_service_items_cancel',
                [
                    'orderId' => $orderId,
                    'serviceItemIds' => $context->serviceItemIds,
                ],
                'serviceItemIds',
                'pnr_itinerary_snapshot_or_meta',
                false,
                'Service-item cancel when snapshot/meta includes serviceItemIds.',
                true,
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected function appendTripOrderBookingIdCandidates(array &$candidates, SabreCancelBookingContext $context): void
    {
        $trip = $context->tripOrderContext;
        $bookingId = $trip->bookingId;
        if ($bookingId === null || $bookingId === '') {
            return;
        }

        $hasSignature = $trip->hasBookingSignature();

        $candidates[] = $this->row(
            self::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
            ['bookingId' => $bookingId, 'cancelAll' => true],
            'bookingId',
            'trip_orders_get_booking',
            false,
            'Uses Trip Orders bookingId from getBooking with cancelAll at root (preferred when isCancelable=true).',
            true,
            true,
        );

        $candidates[] = $this->row(
            self::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_DATA,
            [
                'bookingId' => $bookingId,
                'cancelData' => ['cancelAll' => true],
            ],
            'bookingId',
            'trip_orders_get_booking',
            false,
            'Uses bookingId with cancelData.cancelAll wrapper from getBooking identifiers.',
            true,
            true,
        );

        if ($hasSignature) {
            $signature = (string) $trip->bookingSignature;
            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
                [
                    'bookingId' => $bookingId,
                    'bookingSignature' => $signature,
                    'cancelAll' => true,
                ],
                'bookingId',
                'trip_orders_get_booking',
                false,
                'Adds bookingSignature from getBooking when present (candidate only; bookingId-only preferred).',
                true,
                true,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_DATA,
                [
                    'bookingId' => $bookingId,
                    'bookingSignature' => $signature,
                    'cancelData' => ['cancelAll' => true],
                ],
                'bookingId',
                'trip_orders_get_booking',
                false,
                'bookingId + bookingSignature with cancelData.cancelAll.',
                true,
                true,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED,
                [
                    'request' => [
                        'bookingId' => $bookingId,
                        'bookingSignature' => $signature,
                        'cancelAll' => true,
                    ],
                ],
                'bookingId',
                'trip_orders_get_booking',
                false,
                'request-root wrapper with bookingId and bookingSignature from getBooking.',
                true,
                true,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
                [
                    'cancelBookingRequest' => [
                        'bookingId' => $bookingId,
                        'bookingSignature' => $signature,
                        'cancelAll' => true,
                    ],
                ],
                'bookingId',
                'trip_orders_get_booking',
                false,
                'cancelBookingRequest wrapper (Sabre validation pointer /cancelBookingRequest/bookingSignature).',
                true,
                true,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_DATA,
                [
                    'cancelBookingRequest' => [
                        'bookingId' => $bookingId,
                        'bookingSignature' => $signature,
                        'cancelData' => ['cancelAll' => true],
                    ],
                ],
                'bookingId',
                'trip_orders_get_booking',
                false,
                'cancelBookingRequest wrapper with cancelData.cancelAll.',
                true,
                true,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_CANCELBOOKINGREQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
                [
                    'CancelBookingRequest' => [
                        'bookingId' => $bookingId,
                        'bookingSignature' => $signature,
                        'cancelAll' => true,
                    ],
                ],
                'bookingId',
                'trip_orders_get_booking',
                false,
                'CancelBookingRequest wrapper with bookingId + bookingSignature (not confirmationId-only).',
                true,
                true,
            );

            $candidates[] = $this->row(
                self::STYLE_TRIP_ORDERS_CANCELBOOKINGRQ_BOOKING_ID_SIGNATURE_CANCEL_ALL,
                [
                    'CancelBookingRQ' => [
                        'bookingId' => $bookingId,
                        'bookingSignature' => $signature,
                        'cancelAll' => true,
                    ],
                ],
                'bookingId',
                'trip_orders_get_booking',
                false,
                'CancelBookingRQ wrapper with bookingId + bookingSignature.',
                true,
                true,
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected function applyAttemptHistoryMarkers(array &$candidates, SabreCancelBookingContext $context): void
    {
        foreach ($candidates as &$row) {
            $style = (string) ($row['style'] ?? '');
            $failed = $context->previouslyFailedReason($style);
            if ($failed !== null) {
                $row['previously_failed_reason'] = $failed;
                $row['recommended'] = false;
            }
            $ineffective = $context->previouslyIneffectiveReason($style);
            if ($ineffective !== null) {
                $row['previously_ineffective_reason'] = $ineffective;
                $row['recommended'] = false;
            }
        }
        unset($row);
    }

    protected function applyRecommendation(
        array &$candidates,
        ?SabreCancelBookingContext $context,
        ?array $equivalenceAnalysis = null,
    ): void {
        foreach ($candidates as &$row) {
            if (($row['previously_failed_reason'] ?? null) !== null
                || ($row['previously_ineffective_reason'] ?? null) !== null) {
                $row['recommended'] = false;
            }
        }
        unset($row);

        $configuredPick = $this->pickConfiguredRecommendedStyle($candidates);
        if ($configuredPick !== null) {
            foreach ($candidates as &$row) {
                $row['recommended'] = ($row['style'] ?? '') === $configuredPick;
                if (($row['style'] ?? '') === $configuredPick) {
                    $row['configured_payload_style'] = self::configuredPayloadStyle();
                }
            }
            unset($row);

            return;
        }
        if (! self::usesAutoConfiguredPayloadStyle()) {
            return;
        }

        $pick = $this->pickRecommendedStyle($candidates, $context);
        if ($context !== null
            && is_array($equivalenceAnalysis)
            && SabreCancelProbeDiagnostics::shouldStopLiveProbing($context, $equivalenceAnalysis, $candidates, $pick)) {
            SabreCancelProbeDiagnostics::applyStopLiveProbingToCandidates($candidates);

            return;
        }

        if ($pick === null) {
            return;
        }
        foreach ($candidates as &$row) {
            $row['recommended'] = ($row['style'] ?? '') === $pick;
        }
        unset($row);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected function pickRecommendedStyle(array $candidates, ?SabreCancelBookingContext $context): ?string
    {
        if ($context !== null
            && $context->tripOrderContext->hasBookingId()
            && $context->tripOrderContext->isCancelable === true) {
            if ($context->tripOrderContext->hasBookingSignature()
                && $this->isRecommendableStyle($candidates, self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL)) {
                return self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL;
            }
            if ($this->isRecommendableStyle($candidates, self::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT)) {
                return self::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT;
            }
        }

        if ($context !== null && $context->hasRichOrderIds()) {
            foreach ([self::STYLE_TRIP_ORDERS_ORDER_ITEMS_CANCEL, self::STYLE_TRIP_ORDERS_ORDER_ID_CANCEL_DATA] as $wanted) {
                if ($this->isRecommendableStyle($candidates, $wanted)) {
                    return $wanted;
                }
            }
        }

        if ($context !== null && $context->segmentIds !== [] && ! $context->hasRichOrderIds()) {
            if ($this->isRecommendableStyle($candidates, self::STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL)) {
                return self::STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL;
            }
        }

        $priorityStyles = [
            self::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_DATA,
            self::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
            self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_data',
            self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_DATA_CANCEL_ALL,
            self::STYLE_TRIP_ORDERS_REQUEST_CONFIRMATION_CANCEL_DATA,
            self::STYLE_TRIP_ORDERS_CANCEL_REQUEST_CONFIRMATION,
            self::STYLE_TRIP_ORDERS_CANCEL_REQUEST_ROOT,
            self::STYLE_TRIP_ORDERS_CONFIRMATION_API_ID.'_cancel_data',
            self::STYLE_TRIP_ORDERS_CONFIRMATION_SUPPLIER_REF.'_cancel_data',
            self::STYLE_TRIP_ORDERS_CONFIRMATION_API_ID.'_cancel_all',
            self::STYLE_TRIP_ORDERS_CONFIRMATION_SUPPLIER_REF.'_cancel_all',
            self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_all',
            self::STYLE_TRIP_ORDERS_CONFIRMATION_CANCEL_DATA,
            self::STYLE_TRIP_ORDERS_CONFIRMATION_CANCEL_ALL,
        ];
        foreach ($priorityStyles as $wanted) {
            if ($this->isRecommendableStyle($candidates, $wanted)) {
                return $wanted;
            }
        }

        foreach ($candidates as $row) {
            $style = (string) ($row['style'] ?? '');
            if ($style !== '' && $this->isRecommendableStyle($candidates, $style)) {
                return $style;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected function pickConfiguredRecommendedStyle(array $candidates): ?string
    {
        $style = self::configuredPayloadStyle();
        if ($style === self::CONFIG_STYLE_AUTO_MATRIX_CURRENT) {
            return null;
        }

        $mapped = $this->candidateStyleForConfiguredPayloadStyle($style);
        if ($mapped === null) {
            return null;
        }

        foreach ($candidates as $row) {
            if (($row['style'] ?? '') !== $mapped) {
                continue;
            }
            if (! $this->isRecommendableStyle($candidates, $mapped)) {
                return null;
            }

            return $mapped;
        }

        return null;
    }

    public function candidateStyleForConfiguredPayloadStyle(string $style): ?string
    {
        return match (trim($style)) {
            self::CONFIG_STYLE_CONFIRMATION_ID_ONLY => self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
            self::CONFIG_STYLE_CONFIRMATION_ID_CANCEL_ALL => self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
            self::CONFIG_STYLE_CONFIRMATION_ID_RETRIEVE_CANCEL_ALL => self::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
            self::CONFIG_STYLE_CONFIRMATION_ID_CANCEL_ALL_BOOKING_SOURCE => self::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE,
            self::CONFIG_STYLE_BOOKING_ID_SIGNATURE_CANCEL_ALL => self::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            self::CONFIG_STYLE_ORDER_ITEM_IDS => self::STYLE_TRIP_ORDERS_ORDER_ITEMS_CANCEL,
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected function isRecommendableStyle(array $candidates, string $wanted): bool
    {
        foreach ($candidates as $row) {
            if (($row['style'] ?? '') !== $wanted) {
                continue;
            }
            if (($row['duplicate_of_failed_style'] ?? false) === true) {
                return false;
            }
            $failed = $row['previously_failed_reason'] ?? null;
            if ($failed !== null && stripos((string) $failed, 'CANCEL_DATA_MISSING') !== false) {
                return false;
            }
            if ($failed !== null || ($row['previously_ineffective_reason'] ?? null) !== null) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    protected function dedupeCandidates(array $candidates): array
    {
        $out = [];
        $seenStyles = [];
        foreach ($candidates as $row) {
            $style = (string) ($row['style'] ?? '');
            if ($style === '' || isset($seenStyles[$style])) {
                continue;
            }
            $seenStyles[$style] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function redactBodyForPreview(array $body): array
    {
        return $this->redactValue($body);
    }

    /**
     * @return array<string, mixed>|list<mixed>|string
     */
    protected function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value !== '' ? '***REDACTED***' : $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }
        if (! is_array($value)) {
            return '***REDACTED***';
        }
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = $this->redactValue($v);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<string>
     */
    public function safeShapeKeys(array $body): array
    {
        $keys = [];
        foreach (array_keys($body) as $k) {
            if (is_string($k) && $k !== '') {
                $keys[] = $k;
            }
        }

        return $keys;
    }

    /**
     * Safe selected cancel body diagnostics (shape/null paths and presence flags only; no identifier values).
     *
     * @param  array<string, mixed>  $body
     * @return array{
     *   selected_payload_safe_shape_keys: list<string>,
     *   selected_payload_null_keys: list<string>,
     *   selected_payload_has_confirmation_id: bool,
     *   selected_payload_has_retrieve_booking: bool,
     *   selected_payload_has_cancel_all: bool,
     *   selected_payload_has_error_handling_policy: bool,
     *   selected_payload_has_booking_id: bool,
     *   selected_payload_has_booking_signature: bool,
     *   selected_payload_has_cancel_data: bool,
     *   selected_payload_has_order_item_ids: bool,
     *   selected_payload_has_segment_ids: bool
     * }
     */
    public function selectedPayloadDiagnostics(array $body): array
    {
        $root = $this->effectiveCancelRequestRoot($body);

        return [
            'selected_payload_safe_shape_keys' => $this->safeShapeKeys($body),
            'selected_payload_null_keys' => $this->nullKeyPaths($body),
            'selected_payload_has_confirmation_id' => $this->hasNonEmptyScalar($root, 'confirmationId'),
            'selected_payload_has_retrieve_booking' => ($root['retrieveBooking'] ?? null) === true,
            'selected_payload_has_cancel_all' => ($root['cancelAll'] ?? null) === true,
            'selected_payload_has_error_handling_policy' => is_string($root['errorHandlingPolicy'] ?? null)
                && trim((string) $root['errorHandlingPolicy']) !== '',
            'selected_payload_has_booking_id' => $this->hasNonEmptyScalar($root, 'bookingId'),
            'selected_payload_has_booking_signature' => $this->hasNonEmptyScalar($root, 'bookingSignature'),
            'selected_payload_has_cancel_data' => is_array($root['cancelData'] ?? null)
                && $root['cancelData'] !== [],
            'selected_payload_has_order_item_ids' => $this->hasNonEmptyScalar($root, 'orderItemId')
                || $this->hasNonEmptyList($root, 'orderItemIds'),
            'selected_payload_has_segment_ids' => $this->hasNonEmptyScalar((array) ($root['cancelData'] ?? []), 'segmentId')
                || $this->hasNonEmptyList((array) ($root['cancelData'] ?? []), 'segmentIds')
                || $this->hasNonEmptyScalar($root, 'segmentId')
                || $this->hasNonEmptyList($root, 'segmentIds'),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function effectiveCancelRequestRoot(array $body): array
    {
        foreach (['cancelBookingRequest', 'request', 'CancelBookingRQ', 'CancelBookingRequest'] as $wrap) {
            if (isset($body[$wrap]) && is_array($body[$wrap])) {
                return $body[$wrap];
            }
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    protected function nullKeyPaths(array $node, string $prefix = ''): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            if ($value === null) {
                $out[] = $path;

                continue;
            }
            if (is_array($value)) {
                $out = array_merge($out, $this->nullKeyPaths($value, $path));
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $root
     */
    protected function hasNonEmptyScalar(array $root, string $key): bool
    {
        if (! array_key_exists($key, $root)) {
            return false;
        }
        $value = $root[$key];
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return is_int($value) || is_float($value);
    }

    /**
     * @param  array<string, mixed>  $root
     */
    protected function hasNonEmptyList(array $root, string $key): bool
    {
        return isset($root[$key]) && is_array($root[$key]) && $root[$key] !== [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{
     *   style: string,
     *   body: array<string, mixed>,
     *   primary_identifier_field: string,
     *   primary_identifier_source: string,
     *   recommended: bool,
     *   why_candidate_exists: string,
     *   required_snapshot_fields_present: bool,
     *   safe_shape_keys: list<string>,
     *   required_trip_order_fields_present: bool,
     *   previously_failed_reason: ?string,
     *   previously_ineffective_reason: ?string
     * }
     */
    protected function row(
        string $style,
        array $body,
        string $field,
        string $source,
        bool $recommended,
        string $why,
        bool $snapshotFieldsPresent,
        bool $tripOrderFieldsPresent = false,
        ?string $previouslyFailed = null,
        ?string $previouslyIneffective = null,
    ): array {
        return [
            'style' => $style,
            'body' => $body,
            'primary_identifier_field' => $field,
            'primary_identifier_source' => $source,
            'recommended' => $recommended,
            'why_candidate_exists' => $why,
            'required_snapshot_fields_present' => $snapshotFieldsPresent,
            'required_trip_order_fields_present' => $tripOrderFieldsPresent,
            'safe_shape_keys' => $this->safeShapeKeys($body),
            'previously_failed_reason' => $previouslyFailed,
            'previously_ineffective_reason' => $previouslyIneffective,
        ];
    }
}
