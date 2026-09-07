<?php

namespace App\Services\Notifications;

use App\Enums\OtaNotificationEvent;

/**
 * Classifies how notification event_id may be derived.
 *
 * Default is OCCURRENCE_SCOPED: a new UUID unless an explicit id, occurrence id, or variant is supplied.
 * ONE_SHOT_AGGREGATE is an explicit allowlist of events proven to fire at most once per aggregate.
 */
final class NotificationEventIdentityPolicy
{
    /**
     * Events allowed to use deterministic event_type|aggregate_type:id identity.
     *
     * @var list<string>
     */
    public const ONE_SHOT_ALLOWLIST = [
        OtaNotificationEvent::CustomerRegistered->value,
        OtaNotificationEvent::BookingRequestReceived->value,
        OtaNotificationEvent::BookingConfirmed->value,
        OtaNotificationEvent::BookingCancelled->value,
        OtaNotificationEvent::BookingExpired->value,
        OtaNotificationEvent::SupportTicketCreated->value,
        OtaNotificationEvent::StaffCreated->value,
        OtaNotificationEvent::AgentCreated->value,
        OtaNotificationEvent::AdminCreated->value,
        OtaNotificationEvent::InvoiceGenerated->value,
        OtaNotificationEvent::PaymentReceiptGenerated->value,
        OtaNotificationEvent::TicketItineraryGenerated->value,
        'admin_new_customer_signup',
    ];

    /**
     * Events that must not use aggregate-only identity; they need an occurrence id, variant, or a fresh UUID.
     *
     * @var list<string>
     */
    public const REQUIRES_VARIANT_OR_OCCURRENCE = [
        OtaNotificationEvent::BookingStatusChanged->value,
        OtaNotificationEvent::BookingAssigned->value,
        OtaNotificationEvent::CancellationStatusChanged->value,
        OtaNotificationEvent::PaymentReminder->value,
        OtaNotificationEvent::PaymentProofSubmitted->value,
        OtaNotificationEvent::PaymentRecorded->value,
        OtaNotificationEvent::PaymentVerified->value,
        OtaNotificationEvent::PaymentRejected->value,
        OtaNotificationEvent::PaymentCompleted->value,
        OtaNotificationEvent::PnrItinerarySynced->value,
        OtaNotificationEvent::PnrItinerarySyncFailed->value,
        OtaNotificationEvent::BookingManualReviewRequired->value,
        OtaNotificationEvent::SupportTicketReplied->value,
        OtaNotificationEvent::SupportTicketStatusChanged->value,
        OtaNotificationEvent::SupportTicketAssigned->value,
        OtaNotificationEvent::SupportTicketForwarded->value,
        OtaNotificationEvent::SupplierBookingFailed->value,
        OtaNotificationEvent::SupplierReadinessFailed->value,
        OtaNotificationEvent::SupplierSearchFailed->value,
        OtaNotificationEvent::SupplierOrderFailed->value,
        OtaNotificationEvent::TicketingFailed->value,
        OtaNotificationEvent::RefundRequested->value,
        OtaNotificationEvent::RefundApproved->value,
        OtaNotificationEvent::RefundPaid->value,
        OtaNotificationEvent::RefundRejected->value,
        OtaNotificationEvent::DailyAdminReport->value,
        OtaNotificationEvent::WeeklyAdminReport->value,
        OtaNotificationEvent::MonthlyAdminReport->value,
        OtaNotificationEvent::MonthlyAgentLedger->value,
        OtaNotificationEvent::MonthlyFinanceLedger->value,
        OtaNotificationEvent::PnrManualReviewDigest->value,
        OtaNotificationEvent::AgencyWalletDepositSummary->value,
        OtaNotificationEvent::AgencyBookingActivitySummary->value,
        OtaNotificationEvent::AdminLoginSuccess->value,
        OtaNotificationEvent::StaffLoginSuccess->value,
        OtaNotificationEvent::AgentLoginSuccess->value,
        OtaNotificationEvent::CustomerLoginSuccess->value,
        OtaNotificationEvent::LoginFailedSensitive->value,
        OtaNotificationEvent::LoginFailedAlert->value,
        OtaNotificationEvent::AuthNewDeviceLogin->value,
        OtaNotificationEvent::PasswordResetRequested->value,
    ];

    public static function kind(string $eventType): NotificationEventIdentityKind
    {
        $event = strtolower($eventType);

        if (in_array($event, self::ONE_SHOT_ALLOWLIST, true)) {
            return NotificationEventIdentityKind::OneShotAggregate;
        }

        if (in_array($event, self::REQUIRES_VARIANT_OR_OCCURRENCE, true)) {
            return NotificationEventIdentityKind::RequiresVariantOrOccurrence;
        }

        return NotificationEventIdentityKind::OccurrenceScoped;
    }
}
