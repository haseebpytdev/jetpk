<?php

namespace App\Support\Emails;

/**
 * Maps legacy JetPK type keys to canonical OTA event keys.
 *
 * Preserves compatibility with JetpkEmailViewResolver type-based callers.
 */
final class JetpkEmailEventTypeMap
{
    /** @var array<string, string> */
    protected static array $typeToEvent = [
        'otp' => 'login_otp',
        'sign_in_success' => 'customer_login_success',
        'password_reset' => 'password_reset',
        'account_created' => 'customer_welcome',
        'email_verification' => 'email_verification',
        'password_changed' => 'password_reset_requested',
        'security_notice' => 'auth_new_device_login',
        'booking_created' => 'booking_request_received',
        'booking_pending_manual_payment' => 'payment_proof_submitted',
        'booking_confirmed' => 'booking_confirmed',
        'booking_failed' => 'booking_failed_validation',
        'booking_cancelled' => 'booking_cancelled',
        'booking_updated' => 'booking_status_changed',
        // Canonical hold/payment expiry surface (must not alias onto manual-review).
        'booking_expiring' => 'booking_expiring',
        // Canonical admin manual-review / ops alert surface.
        'manual_review' => 'booking_manual_review_required',
        'admin_operational_notification' => 'booking_manual_review_required',
        'pnr_created' => 'pnr_itinerary_synced',
        'manual_payment_received' => 'payment_proof_submitted',
        'payment_success' => 'payment_verified',
        'payment_failed' => 'payment_rejected',
        'invoice' => 'invoice_generated',
        'refund_requested' => 'refund_requested',
        'refund_updated' => 'refund_paid',
        'support_ticket_created' => 'support_ticket_created',
        'support_reply' => 'support_ticket_replied',
        'group_reservation_created' => 'group_booking_reservation_created',
        'group_reservation_expiring' => 'group_booking_released_unpaid',
        'agent_registration_received' => 'agent_application_submitted',
        'agent_registration_approved' => 'agent_application_approved',
        'notification' => 'booking_status_changed',
    ];

    /**
     * Preferred reverse map when multiple type keys share one event key.
     *
     * @var array<string, string>
     */
    protected static array $preferredEventToType = [
        'booking_manual_review_required' => 'manual_review',
        'payment_proof_submitted' => 'manual_payment_received',
        'booking_status_changed' => 'booking_updated',
    ];

    public static function eventForType(string $type): ?string
    {
        $type = strtolower(str_replace(['-', ' '], '_', trim($type)));

        return self::$typeToEvent[$type] ?? null;
    }

    public static function typeForEvent(string $eventKey): ?string
    {
        if (isset(self::$preferredEventToType[$eventKey])) {
            return self::$preferredEventToType[$eventKey];
        }

        foreach (self::$typeToEvent as $type => $event) {
            if ($event === $eventKey) {
                return $type;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::$typeToEvent;
    }
}
