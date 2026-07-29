<?php

namespace App\Support\AgentPortal;

use App\Models\Booking;
use App\Support\CustomerPortal\CustomerPortalStatusPresenter;
use App\Support\Payments\BookingPayableResolver;

/**
 * Portal-safe status labels for agent dashboard JSON.
 */
class AgentPortalStatusPresenter
{
    /**
     * @return array{code: string, label: string, terminal?: bool}
     */
    public static function bookingStatus(Booking $booking): array
    {
        return CustomerPortalStatusPresenter::bookingStatus($booking);
    }

    /**
     * @return array{code: string, label: string, terminal?: bool}
     */
    public static function paymentStatus(Booking $booking): array
    {
        return CustomerPortalStatusPresenter::paymentStatus($booking);
    }

    /**
     * @return array{code: string, label: string, terminal?: bool}
     */
    public static function ticketingStatus(Booking $booking): array
    {
        return CustomerPortalStatusPresenter::ticketingStatus($booking);
    }

    public static function bookingTotal(Booking $booking): float
    {
        return BookingPayableResolver::customerPayableTotal($booking);
    }
}
