<?php

namespace App\Services\Suppliers\Sabre\Cancel;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;

/**
 * Sprint 0/1: Safe cancelBooking context from booking meta, PNR snapshot, and prior inspect attempts (no PII).
 */
final class SabreCancelBookingContext
{
    /** @var list<string> */
    private const PII_BRANCH_KEYS = [
        'travelers', 'traveler', 'contactinfo', 'contact', 'payments', 'payment',
        'specialservices', 'customerinfo', 'personname', 'passenger', 'passengers',
        'email', 'phone', 'address', 'givenname', 'surname', 'firstname', 'lastname',
    ];

    /** @var list<string> */
    private const ORDER_ID_KEYS = ['orderid', 'bookingid', 'reservationid'];

    /** @var list<string> */
    private const ORDER_ITEM_ID_KEYS = ['orderitemid', 'orderitemids'];

    /** @var list<string> */
    private const SEGMENT_ID_KEYS = ['segmentid', 'segmentids'];

    /** @var list<string> */
    private const SERVICE_ITEM_ID_KEYS = ['serviceitemid', 'serviceitemids'];

    public function __construct(
        public readonly bool $cancelDataMissingDetected,
        public readonly ?int $latestCancelAttemptHttpStatus,
        public readonly ?string $latestCancelAttemptPayloadStyle,
        /** @var list<string> */
        public readonly array $latestCancelAttemptErrorCodes,
        /** @var list<string> */
        public readonly array $latestCancelAttemptErrorMessages,
        /** @var array<string, string> style => failure reason (e.g. CANCEL_DATA_MISSING) */
        public readonly array $failedCancelPayloadStyles,
        /** @var array<string, string> style => ineffective reason (HTTP 200 but booking still active) */
        public readonly array $ineffectiveCancelPayloadStyles,
        /** @var list<string> */
        public readonly array $cancelDataMissingStyles,
        public readonly SabreTripOrderCancelContext $tripOrderContext,
        /** @var list<array<string, mixed>> */
        public readonly array $lastTwoCancelAttemptsSummary,
        public readonly bool $pnrSnapshotPresent,
        /** @var list<string> */
        public readonly array $pnrSnapshotSafeKeys,
        public readonly ?string $orderId,
        /** @var list<string> */
        public readonly array $orderItemIds,
        /** @var list<string> */
        public readonly array $segmentIds,
        /** @var list<string> */
        public readonly array $serviceItemIds,
        public readonly bool $includePnrSnapshotDiagnostics,
    ) {}

    public static function fromBooking(
        Booking $booking,
        bool $includePnrSnapshotDiagnostics = false,
        ?SabreTripOrderCancelContext $tripOrderContext = null,
    ): self {
        $tripOrderContext ??= SabreTripOrderCancelContext::unavailable();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['pnr_itinerary_snapshot'] ?? null) ? $meta['pnr_itinerary_snapshot'] : null;
        $pnrSnapshotPresent = $snapshot !== null
            && is_array($snapshot['segments'] ?? null)
            && $snapshot['segments'] !== [];

        $orderId = null;
        $orderItemIds = [];
        $segmentIds = [];
        $serviceItemIds = [];

        self::collectIdsFromValue($meta, $orderId, $orderItemIds, $segmentIds, $serviceItemIds, 0);
        if ($snapshot !== null) {
            self::collectIdsFromValue($snapshot, $orderId, $orderItemIds, $segmentIds, $serviceItemIds, 0);
        }

        $bookingApiId = trim((string) ($booking->supplier_api_booking_id ?? ''));
        if ($orderId === null && $bookingApiId !== '' && ! self::looksLikePnr($bookingApiId)) {
            $orderId = $bookingApiId;
        }

        $booking->loadMissing('supplierBookings');
        foreach ($booking->supplierBookings as $sb) {
            $apiId = trim((string) ($sb->supplier_api_booking_id ?? ''));
            if ($orderId === null && $apiId !== '' && ! self::looksLikePnr($apiId)) {
                $orderId = $apiId;
            }
        }

        $apiFromMeta = data_get($meta, 'sabre_provider_snapshot.supplier_api_booking_id');
        if ($orderId === null && is_string($apiFromMeta) && trim($apiFromMeta) !== '' && ! self::looksLikePnr($apiFromMeta)) {
            $orderId = trim($apiFromMeta);
        }

        $orderId = $orderId !== null ? self::truncateId($orderId) : null;
        $orderItemIds = self::uniqueIds($orderItemIds);
        $segmentIds = self::uniqueIds($segmentIds);
        $serviceItemIds = self::uniqueIds($serviceItemIds);

        $attempts = self::resolveCancelAttemptHistory($booking);

        return self::buildInstance(
            tripOrderContext: $tripOrderContext,
            attempts: $attempts,
            pnrSnapshotPresent: $pnrSnapshotPresent,
            pnrSnapshotSafeKeys: $snapshot !== null ? self::safeKeys($snapshot) : [],
            orderId: $orderId,
            orderItemIds: $orderItemIds,
            segmentIds: $segmentIds,
            serviceItemIds: $serviceItemIds,
            includePnrSnapshotDiagnostics: $includePnrSnapshotDiagnostics,
        );
    }

    public static function fromDirectPnr(?SabreTripOrderCancelContext $tripOrderContext = null): self
    {
        return self::buildInstance(
            tripOrderContext: $tripOrderContext ?? SabreTripOrderCancelContext::unavailable(),
            attempts: [],
            pnrSnapshotPresent: false,
            pnrSnapshotSafeKeys: [],
            orderId: null,
            orderItemIds: [],
            segmentIds: [],
            serviceItemIds: [],
            includePnrSnapshotDiagnostics: false,
        );
    }

    /**
     * @param  list<SupplierBookingAttempt>  $attempts
     */
    protected static function buildInstance(
        SabreTripOrderCancelContext $tripOrderContext,
        array $attempts,
        bool $pnrSnapshotPresent,
        array $pnrSnapshotSafeKeys,
        ?string $orderId,
        array $orderItemIds,
        array $segmentIds,
        array $serviceItemIds,
        bool $includePnrSnapshotDiagnostics,
    ): self {
        $latest = $attempts[0] ?? null;
        $summary = is_array($latest?->safe_summary) ? $latest->safe_summary : [];
        $codes = self::stringList($summary['response_error_codes'] ?? []);
        $messages = self::stringList($summary['response_error_messages'] ?? []);
        $cancelDataMissing = self::detectCancelDataMissing($codes, $messages);

        $failedStyles = self::buildFailedPayloadStyleMap($attempts);
        $ineffectiveStyles = self::buildIneffectivePayloadStyleMap($attempts, $tripOrderContext);
        $cancelDataMissingStyles = array_values(array_keys(array_filter(
            $failedStyles,
            fn (string $reason): bool => stripos($reason, 'CANCEL_DATA_MISSING') !== false,
        )));

        return new self(
            cancelDataMissingDetected: $cancelDataMissing || $cancelDataMissingStyles !== [],
            latestCancelAttemptHttpStatus: $latest !== null ? self::parseHttpStatus($summary['http_status'] ?? null) : null,
            latestCancelAttemptPayloadStyle: is_string($summary['payload_style'] ?? null) && $summary['payload_style'] !== ''
                ? (string) $summary['payload_style']
                : null,
            latestCancelAttemptErrorCodes: array_slice($codes, 0, 12),
            latestCancelAttemptErrorMessages: array_slice($messages, 0, 8),
            failedCancelPayloadStyles: $failedStyles,
            ineffectiveCancelPayloadStyles: $ineffectiveStyles,
            cancelDataMissingStyles: $cancelDataMissingStyles,
            tripOrderContext: $tripOrderContext,
            lastTwoCancelAttemptsSummary: self::summarizeLastAttempts($attempts, 2),
            pnrSnapshotPresent: $pnrSnapshotPresent,
            pnrSnapshotSafeKeys: $pnrSnapshotSafeKeys,
            orderId: $orderId,
            orderItemIds: $orderItemIds,
            segmentIds: $segmentIds,
            serviceItemIds: $serviceItemIds,
            includePnrSnapshotDiagnostics: $includePnrSnapshotDiagnostics,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsSlice(): array
    {
        return [
            'cancel_data_missing_detected' => $this->cancelDataMissingDetected,
            'latest_cancel_attempt_http_status' => $this->latestCancelAttemptHttpStatus,
            'latest_cancel_attempt_payload_style' => $this->latestCancelAttemptPayloadStyle,
            'latest_cancel_attempt_error_codes' => $this->latestCancelAttemptErrorCodes,
            'latest_cancel_attempt_error_messages' => $this->latestCancelAttemptErrorMessages,
            'pnr_snapshot_present' => $this->pnrSnapshotPresent,
            'pnr_snapshot_safe_keys' => $this->pnrSnapshotSafeKeys,
            'order_id_present' => $this->orderId !== null && $this->orderId !== '',
            'order_item_ids_count' => count($this->orderItemIds),
            'segment_ids_count' => count($this->segmentIds),
            'service_item_ids_count' => count($this->serviceItemIds),
            'pnr_snapshot_diagnostics_included' => $this->includePnrSnapshotDiagnostics,
            'failed_cancel_payload_styles' => $this->failedCancelPayloadStyles,
            'ineffective_cancel_payload_styles' => $this->ineffectiveCancelPayloadStyles,
            'cancel_data_missing_styles' => $this->cancelDataMissingStyles,
            'last_two_cancel_attempts_summary' => $this->lastTwoCancelAttemptsSummary,
        ] + $this->tripOrderContext->safePublicSlice();
    }

    public function stylePreviouslyFailed(string $style): bool
    {
        return isset($this->failedCancelPayloadStyles[$style]);
    }

    public function previouslyFailedReason(string $style): ?string
    {
        return $this->failedCancelPayloadStyles[$style] ?? null;
    }

    public function stylePreviouslyIneffective(string $style): bool
    {
        return isset($this->ineffectiveCancelPayloadStyles[$style]);
    }

    public function previouslyIneffectiveReason(string $style): ?string
    {
        return $this->ineffectiveCancelPayloadStyles[$style] ?? null;
    }

    public function hasRichOrderIds(): bool
    {
        return $this->orderId !== null
            && ($this->orderItemIds !== [] || $this->segmentIds !== [] || $this->serviceItemIds !== []);
    }

    /**
     * @return list<SupplierBookingAttempt>
     */
    protected static function resolveCancelAttemptHistory(Booking $booking): array
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->whereIn('action', ['inspect_cancel_pnr', 'cancel_pnr', 'cancel_booking'])
            ->orderByDesc('attempted_at')
            ->orderByDesc('id')
            ->limit(24)
            ->get()
            ->all();
    }

    /**
     * @param  list<SupplierBookingAttempt>  $attempts
     * @return array<string, string>
     */
    protected static function buildFailedPayloadStyleMap(array $attempts): array
    {
        $failed = [];
        foreach ($attempts as $attempt) {
            $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
            $style = is_string($summary['payload_style'] ?? null) ? trim((string) $summary['payload_style']) : '';
            if ($style === '') {
                continue;
            }
            $codes = self::stringList($summary['response_error_codes'] ?? []);
            $messages = self::stringList($summary['response_error_messages'] ?? []);
            $reason = self::resolveAttemptFailureReason($codes, $messages, (int) self::parseHttpStatus($summary['http_status'] ?? null));
            if ($reason !== null) {
                $failed[$style] = $reason;
            }
        }

        return $failed;
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     */
    protected static function resolveAttemptFailureReason(array $codes, array $messages, ?int $httpStatus): ?string
    {
        if (self::detectCancelDataMissing($codes, $messages)) {
            return 'CANCEL_DATA_MISSING';
        }

        if (self::detectNoItemsCancelled($codes, $messages)) {
            return 'NO_ITEMS_CANCELLED';
        }

        if (self::detectInvalidCancelTarget($codes, $messages)) {
            return 'INVALID_CANCEL_TARGET';
        }

        if ($httpStatus !== null && ($httpStatus < 200 || $httpStatus >= 300)) {
            return 'HTTP_'.$httpStatus;
        }

        foreach ($codes as $code) {
            $code = trim($code);
            if ($code !== '') {
                return $code;
            }
        }

        return null;
    }

    /**
     * HTTP 200 cancel probes that did not change supplier state (getBooking still isCancelable=true).
     *
     * @param  list<SupplierBookingAttempt>  $attempts
     * @return array<string, string>
     */
    protected static function buildIneffectivePayloadStyleMap(
        array $attempts,
        SabreTripOrderCancelContext $tripOrderContext,
    ): array {
        $ineffective = [];
        $stillCancelable = $tripOrderContext->isCancelable === true;

        foreach ($attempts as $attempt) {
            $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
            $style = is_string($summary['payload_style'] ?? null) ? trim((string) $summary['payload_style']) : '';
            if ($style === '') {
                continue;
            }

            $httpStatus = self::parseHttpStatus($summary['http_status'] ?? null);
            if ($httpStatus === null || $httpStatus < 200 || $httpStatus >= 300) {
                continue;
            }

            $codes = self::stringList($summary['response_error_codes'] ?? []);
            $messages = self::stringList($summary['response_error_messages'] ?? []);
            if (self::detectCancelDataMissing($codes, $messages)) {
                continue;
            }

            if ($stillCancelable || self::isKnownVerificationIneffectiveStyle($style)) {
                $ineffective[$style] = 'HTTP_200_BUT_STILL_ACTIVE';
            }
        }

        return $ineffective;
    }

    protected static function isKnownVerificationIneffectiveStyle(string $style): bool
    {
        return $style === SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT;
    }

    /**
     * @param  list<SupplierBookingAttempt>  $attempts
     * @return list<array<string, mixed>>
     */
    protected static function summarizeLastAttempts(array $attempts, int $limit): array
    {
        $out = [];
        foreach (array_slice($attempts, 0, $limit) as $attempt) {
            $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
            $out[] = [
                'action' => (string) ($attempt->action ?? ''),
                'attempted_at' => $attempt->attempted_at?->toIso8601String(),
                'payload_style' => is_string($summary['payload_style'] ?? null) ? (string) $summary['payload_style'] : null,
                'http_status' => self::parseHttpStatus($summary['http_status'] ?? null),
                'access_result' => is_string($summary['access_result'] ?? null) ? (string) $summary['access_result'] : null,
                'response_error_codes' => array_slice(self::stringList($summary['response_error_codes'] ?? []), 0, 8),
                'response_error_messages' => array_slice(self::stringList($summary['response_error_messages'] ?? []), 0, 4),
                'response_error_details_sanitized' => is_array($summary['response_error_details_sanitized'] ?? null)
                    ? array_slice($summary['response_error_details_sanitized'], 0, 4)
                    : SabreCancelProbeDiagnostics::fallbackErrorDetailsFromDigest([
                        'response_error_codes' => self::stringList($summary['response_error_codes'] ?? []),
                        'response_error_messages' => self::stringList($summary['response_error_messages'] ?? []),
                        'response_error_paths' => self::stringList($summary['response_error_paths'] ?? []),
                    ]),
                'validation_missing_fields_sanitized' => is_array($summary['validation_missing_fields_sanitized'] ?? null)
                    ? array_slice(self::stringList($summary['validation_missing_fields_sanitized']), 0, 12)
                    : SabreCancelProbeDiagnostics::validationMissingFieldsSanitized([
                        'response_error_codes' => self::stringList($summary['response_error_codes'] ?? []),
                        'response_error_messages' => self::stringList($summary['response_error_messages'] ?? []),
                        'response_error_paths' => self::stringList($summary['response_error_paths'] ?? []),
                        'response_missing_fields' => self::stringList($summary['response_missing_fields'] ?? []),
                    ], is_array($summary['response_error_details_sanitized'] ?? null)
                        ? $summary['response_error_details_sanitized']
                        : []),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     */
    protected static function detectCancelDataMissing(array $codes, array $messages): bool
    {
        foreach ($codes as $code) {
            if (stripos($code, 'CANCEL_DATA_MISSING') !== false) {
                return true;
            }
        }
        foreach ($messages as $msg) {
            if (stripos($msg, 'CANCEL_DATA_MISSING') !== false
                || stripos($msg, 'No cancel data provided') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     */
    protected static function detectNoItemsCancelled(array $codes, array $messages): bool
    {
        foreach (array_merge($codes, $messages) as $value) {
            if (stripos($value, 'NO_ITEMS_CANCELLED') !== false
                || stripos($value, 'no items cancelled') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     */
    protected static function detectInvalidCancelTarget(array $codes, array $messages): bool
    {
        foreach (array_merge($codes, $messages) as $value) {
            if (stripos($value, 'INVALID_CANCEL_TARGET') !== false
                || stripos($value, 'invalid cancel target') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $value
     * @param  list<string>  $orderItemIds
     * @param  list<string>  $segmentIds
     * @param  list<string>  $serviceItemIds
     */
    protected static function collectIdsFromValue(
        mixed $value,
        ?string &$orderId,
        array &$orderItemIds,
        array &$segmentIds,
        array &$serviceItemIds,
        int $depth,
    ): void {
        if ($depth > 10 || $value === null) {
            return;
        }
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }
            $keyStr = is_string($key) ? strtolower($key) : '';
            if ($keyStr !== '' && self::isPiiBranchKey($keyStr)) {
                continue;
            }

            if ($keyStr !== '' && in_array($keyStr, self::ORDER_ID_KEYS, true) && is_scalar($child)) {
                $id = self::truncateId((string) $child);
                if ($id !== '' && $orderId === null && ! self::looksLikePnr($id)) {
                    $orderId = $id;
                }
            }

            if ($keyStr !== '' && in_array($keyStr, self::ORDER_ITEM_ID_KEYS, true)) {
                self::appendIds($child, $orderItemIds);
            }
            if ($keyStr !== '' && in_array($keyStr, self::SEGMENT_ID_KEYS, true)) {
                self::appendIds($child, $segmentIds);
            }
            if ($keyStr !== '' && in_array($keyStr, self::SERVICE_ITEM_ID_KEYS, true)) {
                self::appendIds($child, $serviceItemIds);
            }

            if ($keyStr === 'id' && is_scalar($child) && is_array($value)) {
                $parentHint = self::parentCollectionHint($value);
                $id = self::truncateId((string) $child);
                if ($id !== '') {
                    match ($parentHint) {
                        'order_item' => $orderItemIds[] = $id,
                        'segment' => $segmentIds[] = $id,
                        'service_item' => $serviceItemIds[] = $id,
                        default => null,
                    };
                }
            }

            if (is_array($child)) {
                self::collectIdsFromValue($child, $orderId, $orderItemIds, $segmentIds, $serviceItemIds, $depth + 1);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected static function parentCollectionHint(array $row): ?string
    {
        foreach (array_keys($row) as $k) {
            if (! is_string($k)) {
                continue;
            }
            $lk = strtolower($k);
            if (str_contains($lk, 'orderitem') || str_contains($lk, 'itemtype')) {
                return 'order_item';
            }
            if (str_contains($lk, 'segment') || str_contains($lk, 'flightnumber')) {
                return 'segment';
            }
            if (str_contains($lk, 'service')) {
                return 'service_item';
            }
        }

        return null;
    }

    protected static function isPiiBranchKey(string $key): bool
    {
        foreach (self::PII_BRANCH_KEYS as $pii) {
            if ($key === $pii || str_contains($key, $pii)) {
                return true;
            }
        }

        return false;
    }

    protected static function appendIds(mixed $value, array &$target): void
    {
        if (is_scalar($value)) {
            $id = self::truncateId((string) $value);
            if ($id !== '') {
                $target[] = $id;
            }

            return;
        }
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $id = self::truncateId((string) $item);
                if ($id !== '') {
                    $target[] = $id;
                }
            } elseif (is_array($item)) {
                $nested = $item['id'] ?? $item['orderItemId'] ?? null;
                if (is_scalar($nested)) {
                    $id = self::truncateId((string) $nested);
                    if ($id !== '') {
                        $target[] = $id;
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected static function safeKeys(array $data): array
    {
        $keys = [];
        foreach (array_keys($data) as $k) {
            if (is_string($k) && $k !== '') {
                $keys[] = $k;
            }
        }

        return array_values(array_slice($keys, 0, 24));
    }

    /**
     * @return list<string>
     */
    protected static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $v): string => is_string($v) || is_int($v) || is_float($v) ? trim((string) $v) : '',
            $value,
        ), fn (string $s): bool => $s !== ''));
    }

    protected static function parseHttpStatus(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric(trim($value))) {
            return (int) trim($value);
        }

        return null;
    }

    protected static function looksLikePnr(string $value): bool
    {
        $v = strtoupper(trim($value));

        return strlen($v) === 6 && ctype_alnum($v);
    }

    protected static function truncateId(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }

        return substr($v, 0, 120);
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    protected static function uniqueIds(array $ids): array
    {
        $out = [];
        $seen = [];
        foreach ($ids as $id) {
            $id = self::truncateId($id);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
            if (count($out) >= 32) {
                break;
            }
        }

        return $out;
    }
}
