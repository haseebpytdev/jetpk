<?php

namespace App\Support\Bookings;

/**
 * Maps PIA NDC DoOrderRetrieve normalized fields to local supplier booking state (R12L).
 */
final class PiaNdcBookingStatusInterpreter
{
    public const STATUS_ACTIVE_OPTION_PNR = 'active_option_pnr';

    public const STATUS_RELEASED = 'released';

    public const STATUS_NO_ACTIVE_SEGMENTS = 'no_active_segments';

    public const STATUS_TICKETED = 'ticketed';

    public const STATUS_OPTION_PNR_AFTER_VOID = 'option_pnr_after_void';

    public const STATUS_UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{
     *     interpreted_status: string,
     *     released: bool,
     *     ticketed: bool,
     *     active_option_pnr: bool,
     *     segment_count: int,
     *     has_ticket_numbers: bool,
     *     order_status: string|null,
     *     payment_time_limit: string|null
     * }
     */
    public static function interpret(array $normalized): array
    {
        $segmentCount = (int) ($normalized['segment_count'] ?? 0);
        $paymentTimeLimit = trim((string) ($normalized['payment_time_limit'] ?? ''));
        $ticketNumbers = is_array($normalized['ticket_numbers'] ?? null) ? $normalized['ticket_numbers'] : [];
        $hasBlockingTickets = (bool) ($normalized['has_blocking_ticket_numbers'] ?? false);
        $orderStatus = strtoupper(trim((string) ($normalized['order_status'] ?? '')));

        if ($hasBlockingTickets) {
            return self::result(
                self::STATUS_TICKETED,
                released: false,
                ticketed: true,
                activeOptionPnr: false,
                segmentCount: $segmentCount,
                hasTicketNumbers: true,
                orderStatus: $orderStatus !== '' ? $orderStatus : null,
                paymentTimeLimit: $paymentTimeLimit !== '' ? $paymentTimeLimit : null,
            );
        }

        if ($ticketNumbers !== []) {
            return self::result(
                self::STATUS_OPTION_PNR_AFTER_VOID,
                released: false,
                ticketed: false,
                activeOptionPnr: $segmentCount > 0,
                segmentCount: $segmentCount,
                hasTicketNumbers: true,
                orderStatus: $orderStatus !== '' ? $orderStatus : null,
                paymentTimeLimit: $paymentTimeLimit !== '' ? $paymentTimeLimit : null,
            );
        }

        if (in_array($orderStatus, ['CLOSED', 'CANCELLED', 'CANCELED', 'VOIDED'], true)) {
            return self::result(
                self::STATUS_RELEASED,
                released: true,
                ticketed: false,
                activeOptionPnr: false,
                segmentCount: $segmentCount,
                hasTicketNumbers: false,
                orderStatus: $orderStatus,
                paymentTimeLimit: null,
            );
        }

        if ($segmentCount === 0 && $paymentTimeLimit === '' && $ticketNumbers === []) {
            return self::result(
                self::STATUS_NO_ACTIVE_SEGMENTS,
                released: true,
                ticketed: false,
                activeOptionPnr: false,
                segmentCount: 0,
                hasTicketNumbers: false,
                orderStatus: $orderStatus !== '' ? $orderStatus : null,
                paymentTimeLimit: null,
            );
        }

        if ($segmentCount > 0) {
            return self::result(
                self::STATUS_ACTIVE_OPTION_PNR,
                released: false,
                ticketed: false,
                activeOptionPnr: true,
                segmentCount: $segmentCount,
                hasTicketNumbers: false,
                orderStatus: $orderStatus !== '' ? $orderStatus : null,
                paymentTimeLimit: $paymentTimeLimit !== '' ? $paymentTimeLimit : null,
            );
        }

        return self::result(
            self::STATUS_UNKNOWN,
            released: false,
            ticketed: false,
            activeOptionPnr: false,
            segmentCount: $segmentCount,
            hasTicketNumbers: false,
            orderStatus: $orderStatus !== '' ? $orderStatus : null,
            paymentTimeLimit: $paymentTimeLimit !== '' ? $paymentTimeLimit : null,
        );
    }

    /**
     * @return array{
     *     interpreted_status: string,
     *     released: bool,
     *     ticketed: bool,
     *     active_option_pnr: bool,
     *     segment_count: int,
     *     has_ticket_numbers: bool,
     *     order_status: string|null,
     *     payment_time_limit: string|null
     * }
     */
    private static function result(
        string $interpretedStatus,
        bool $released,
        bool $ticketed,
        bool $activeOptionPnr,
        int $segmentCount,
        bool $hasTicketNumbers,
        ?string $orderStatus,
        ?string $paymentTimeLimit,
    ): array {
        return [
            'interpreted_status' => $interpretedStatus,
            'released' => $released,
            'ticketed' => $ticketed,
            'active_option_pnr' => $activeOptionPnr,
            'segment_count' => $segmentCount,
            'has_ticket_numbers' => $hasTicketNumbers,
            'order_status' => $orderStatus,
            'payment_time_limit' => $paymentTimeLimit,
        ];
    }
}
