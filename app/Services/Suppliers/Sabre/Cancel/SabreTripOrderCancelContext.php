<?php

namespace App\Services\Suppliers\Sabre\Cancel;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabrePnrRetrieveProbe;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabreTripOrdersGetBookingInspectSummary;

/**
 * Safe Trip Orders getBooking identifiers for cancelBooking probe payloads (values kept internal; no PII in output).
 */
final class SabreTripOrderCancelContext
{
    public const SOURCE_GET_BOOKING = 'getBooking';

    public const SOURCE_CACHED_SNAPSHOT = 'cached_snapshot';

    public const SOURCE_UNAVAILABLE = 'unavailable';

    public function __construct(
        public readonly ?string $bookingId,
        public readonly ?string $bookingSignature,
        public readonly ?bool $isCancelable,
        public readonly ?bool $isTicketed,
        public readonly string $contextSource,
    ) {}

    public static function unavailable(): self
    {
        return new self(null, null, null, null, self::SOURCE_UNAVAILABLE);
    }

    public function hasBookingId(): bool
    {
        return $this->bookingId !== null && trim($this->bookingId) !== '';
    }

    public function hasBookingSignature(): bool
    {
        return $this->bookingSignature !== null && trim($this->bookingSignature) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function safePublicSlice(): array
    {
        return [
            'trip_order_booking_id_present' => $this->hasBookingId(),
            'trip_order_booking_signature_present' => $this->hasBookingSignature(),
            'trip_order_is_cancelable' => $this->isCancelable,
            'trip_order_is_ticketed' => $this->isTicketed,
            'trip_order_context_source' => $this->contextSource,
        ];
    }

    public static function resolve(
        Booking $booking,
        bool $withPnrSnapshot,
        bool $refreshGetBooking,
        bool $dryRun,
        SabrePnrRetrieveProbe $retrieveProbe,
        SabreTripOrdersGetBookingInspectSummary $inspectSummary,
    ): self {
        if ($withPnrSnapshot) {
            $cached = self::fromStoredMeta($booking);
            if ($cached->hasBookingId()) {
                return $cached;
            }
        }

        if ($refreshGetBooking) {
            $live = self::fromLiveGetBooking($booking, $retrieveProbe, $inspectSummary);
            if ($live->contextSource === self::SOURCE_GET_BOOKING) {
                return $live;
            }
        }

        if ($withPnrSnapshot) {
            $cached = self::fromStoredMeta($booking);

            return $cached->contextSource !== self::SOURCE_UNAVAILABLE
                ? $cached
                : self::unavailable();
        }

        return self::unavailable();
    }

    public static function fromLiveGetBooking(
        Booking $booking,
        SabrePnrRetrieveProbe $retrieveProbe,
        SabreTripOrdersGetBookingInspectSummary $inspectSummary,
    ): self {
        $fetch = $retrieveProbe->fetchTripOrdersGetBooking($booking);
        if (isset($fetch['error'])) {
            return self::unavailable();
        }

        $json = is_array($fetch['json'] ?? null) ? $fetch['json'] : [];
        if ($json === []) {
            return self::unavailable();
        }

        return self::fromGetBookingJson($inspectSummary, $json);
    }

    public static function fromLiveGetBookingDirect(
        SupplierConnection $connection,
        string $pnr,
        SabrePnrRetrieveProbe $retrieveProbe,
        SabreTripOrdersGetBookingInspectSummary $inspectSummary,
    ): self {
        $fetch = $retrieveProbe->fetchTripOrdersGetBookingDirect($connection, $pnr);
        if (isset($fetch['error'])) {
            return self::unavailable();
        }

        $json = is_array($fetch['json'] ?? null) ? $fetch['json'] : [];
        if ($json === []) {
            return self::unavailable();
        }

        return self::fromGetBookingJson($inspectSummary, $json);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromGetBookingJson(
        SabreTripOrdersGetBookingInspectSummary $inspectSummary,
        array $json,
    ): self {
        $extracted = $inspectSummary->extractTripOrderIdentifiers($json);

        return new self(
            bookingId: $extracted['booking_id'],
            bookingSignature: $extracted['booking_signature'],
            isCancelable: $extracted['is_cancelable'],
            isTicketed: $extracted['is_ticketed'],
            contextSource: self::SOURCE_GET_BOOKING,
        );
    }

    public static function fromStoredMeta(Booking $booking): self
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $sources = [
            is_array($meta['trip_order_cancel_context'] ?? null) ? $meta['trip_order_cancel_context'] : null,
            is_array($meta['pnr_itinerary_snapshot'] ?? null) ? $meta['pnr_itinerary_snapshot'] : null,
            is_array($meta['sabre_provider_snapshot'] ?? null) ? $meta['sabre_provider_snapshot'] : null,
            $meta,
        ];

        $bookingId = null;
        $signature = null;
        $isCancelable = null;
        $isTicketed = null;

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }
            $bookingId ??= self::extractScalar($source, ['bookingId', 'booking_id']);
            $signature ??= self::extractScalar($source, ['bookingSignature', 'booking_signature']);
            $isCancelable ??= self::extractNullableBool($source, ['isCancelable', 'is_cancelable']);
            $isTicketed ??= self::extractNullableBool($source, ['isTicketed', 'is_ticketed']);
        }

        if ($bookingId === null && $signature === null && $isCancelable === null && $isTicketed === null) {
            return self::unavailable();
        }

        return new self(
            bookingId: $bookingId,
            bookingSignature: $signature,
            isCancelable: $isCancelable,
            isTicketed: $isTicketed,
            contextSource: self::SOURCE_CACHED_SNAPSHOT,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    protected static function extractScalar(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $v = $row[$key];
            if (! is_scalar($v)) {
                continue;
            }
            $s = trim((string) $v);
            if ($s === '') {
                continue;
            }

            return substr($s, 0, 120);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    protected static function extractNullableBool(array $row, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && is_bool($row[$key])) {
                return $row[$key];
            }
        }

        return null;
    }
}
