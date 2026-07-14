<?php

namespace App\Enums;

/**
 * IATI payment/reservation lifecycle — local vs supplier reservation separation.
 */
enum IatiReservationLifecycleStatus: string
{
    case LocalPaymentPendingNotReserved = 'local_payment_pending_not_reserved';
    case SupplierHoldPendingPayment = 'supplier_hold_pending_payment';
    case PaymentVerifiedPendingSupplierBooking = 'payment_verified_pending_supplier_booking';
    case SupplierBookingInProgress = 'supplier_booking_in_progress';
    case SupplierBookingConfirmed = 'supplier_booking_confirmed';
    case FareChangedCustomerActionRequired = 'fare_changed_customer_action_required';
    case FareUnavailableAdminReview = 'fare_unavailable_admin_review';
    case PaymentReceivedSupplierBookingFailed = 'payment_received_supplier_booking_failed';
    case ExpiredLocalPaymentRequest = 'expired_local_payment_request';
    case ExpiredSupplierHold = 'expired_supplier_hold';

    public function label(): string
    {
        return match ($this) {
            self::LocalPaymentPendingNotReserved => 'Payment pending — not reserved with supplier',
            self::SupplierHoldPendingPayment => 'Supplier hold active — payment required',
            self::PaymentVerifiedPendingSupplierBooking => 'Payment verified — supplier booking pending',
            self::SupplierBookingInProgress => 'Supplier booking in progress',
            self::SupplierBookingConfirmed => 'Supplier booking confirmed',
            self::FareChangedCustomerActionRequired => 'Fare changed — acceptance required',
            self::FareUnavailableAdminReview => 'Fare unavailable — admin review required',
            self::PaymentReceivedSupplierBookingFailed => 'Payment received — supplier booking failed',
            self::ExpiredLocalPaymentRequest => 'Local payment request expired',
            self::ExpiredSupplierHold => 'Supplier hold expired',
        };
    }

    public function customerHeadline(): string
    {
        return match ($this) {
            self::LocalPaymentPendingNotReserved => 'Not Reserved Yet',
            self::SupplierHoldPendingPayment => 'Supplier Hold Active',
            self::PaymentVerifiedPendingSupplierBooking => 'Payment Received — Creating Reservation',
            self::SupplierBookingInProgress => 'Creating Airline Reservation',
            self::SupplierBookingConfirmed => 'Reserved with Airline',
            self::FareChangedCustomerActionRequired => 'Fare Changed — Action Required',
            self::FareUnavailableAdminReview => 'Fare No Longer Available',
            self::PaymentReceivedSupplierBookingFailed => 'Reservation Issue — Support Review',
            self::ExpiredLocalPaymentRequest => 'Payment Window Expired',
            self::ExpiredSupplierHold => 'Supplier Hold Expired',
        };
    }

    public function customerDetail(): string
    {
        return match ($this) {
            self::LocalPaymentPendingNotReserved => 'Not reserved with supplier — fare will be revalidated before PNR.',
            self::SupplierHoldPendingPayment => 'Your fare is held with the supplier. Complete payment before the hold expires.',
            self::PaymentVerifiedPendingSupplierBooking => 'Payment verified. We will revalidate and create your airline reservation.',
            self::SupplierBookingInProgress => 'Please wait while we confirm your reservation with the airline.',
            self::SupplierBookingConfirmed => 'Your booking is confirmed with the airline.',
            self::FareChangedCustomerActionRequired => 'The fare changed. Accept the new fare to continue.',
            self::FareUnavailableAdminReview => 'This fare is no longer available. Select a new fare or request a refund.',
            self::PaymentReceivedSupplierBookingFailed => 'Payment was received but the airline reservation could not be completed. Our team will assist.',
            self::ExpiredLocalPaymentRequest => 'Your checkout window expired. Search again to continue.',
            self::ExpiredSupplierHold => 'The supplier hold expired before payment. Search again or contact support.',
        };
    }
}
