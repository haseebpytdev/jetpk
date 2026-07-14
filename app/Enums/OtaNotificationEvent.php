<?php

namespace App\Enums;

enum OtaNotificationEvent: string
{
    // Booking
    case BookingRequestReceived = 'booking_request_received';
    case BookingFareUpdatedRequiresAcceptance = 'booking_fare_updated_requires_acceptance';
    case BookingUpdatedFareAccepted = 'booking_updated_fare_accepted';
    case BookingConfirmed = 'booking_confirmed';
    case BookingStatusChanged = 'booking_status_changed';
    case BookingAssigned = 'booking_assigned';
    case BookingCancelled = 'booking_cancelled';
    case BookingFailedValidation = 'booking_failed_validation';
    case BookingManualReviewRequired = 'booking_manual_review_required';
    case StaleSegmentRequiresNewSearch = 'stale_segment_requires_new_search';
    case PnrItinerarySynced = 'pnr_itinerary_synced';
    case PnrItinerarySyncFailed = 'pnr_itinerary_sync_failed';
    case CancellationRequested = 'cancellation_requested';
    case CancellationStatusChanged = 'cancellation_status_changed';

    // Payment / refund
    case PaymentProofSubmitted = 'payment_proof_submitted';
    case PaymentRecorded = 'payment_recorded';
    case PaymentVerified = 'payment_verified';
    case PaymentRejected = 'payment_rejected';
    case PaymentCompleted = 'payment_completed';
    case RefundRequested = 'refund_requested';
    case RefundApproved = 'refund_approved';
    case RefundPaid = 'refund_paid';
    case RefundRejected = 'refund_rejected';

    // Supplier / ticketing
    case SupplierBookingCreated = 'supplier_booking_created';
    case SupplierBookingFailed = 'supplier_booking_failed';
    case SupplierReadinessFailed = 'supplier_readiness_failed';
    case SupplierSearchFailed = 'supplier_search_failed';
    case SupplierOrderFailed = 'supplier_order_failed';
    case FxConversionFailed = 'fx_conversion_failed';
    case TicketIssued = 'ticket_issued';
    case TicketingFailed = 'ticketing_failed';
    case TicketingNotSupported = 'ticketing_not_supported';

    // User/account/security
    case CustomerRegistered = 'customer_registered';
    case AgentApplicationSubmitted = 'agent_application_submitted';
    case AgentApplicationApproved = 'agent_application_approved';
    case AgentApplicationNeedsMoreInfo = 'agent_application_needs_more_info';
    case AgentApplicationRejected = 'agent_application_rejected';
    case StaffCreated = 'staff_created';
    case AgentCreated = 'agent_created';
    case AdminCreated = 'admin_created';
    case UserSuspended = 'user_suspended';
    case UserActivated = 'user_activated';
    case PasswordResetRequested = 'password_reset_requested';
    case CustomerLoginSuccess = 'customer_login_success';
    case AdminLoginSuccess = 'admin_login_success';
    case StaffLoginSuccess = 'staff_login_success';
    case AgentLoginSuccess = 'agent_login_success';
    case LoginFailedSensitive = 'login_failed_sensitive';
    case LoginFailedAlert = 'login_failed_alert';
    case AuthNewDeviceLogin = 'auth_new_device_login';

    // Commission / docs
    case CommissionEarned = 'commission_earned';
    case CommissionApproved = 'commission_approved';
    case CommissionPayoutRecorded = 'commission_payout_recorded';
    case CommissionStatementIssued = 'commission_statement_issued';
    case AgentDepositSubmitted = 'agent_deposit_submitted';
    case AgentDepositApproved = 'agent_deposit_approved';
    case AgentDepositRejected = 'agent_deposit_rejected';
    case DocumentGenerated = 'document_generated';
    case DocumentDownloadedAdminOptional = 'document_downloaded_admin_optional';
    case TicketItineraryGenerated = 'ticket_itinerary_generated';
    case InvoiceGenerated = 'invoice_generated';
    case PaymentReceiptGenerated = 'payment_receipt_generated';

    // Support tickets
    case SupportTicketCreated = 'support_ticket_created';
    case SupportTicketAssigned = 'support_ticket_assigned';
    case SupportTicketForwarded = 'support_ticket_forwarded';
    case SupportTicketReplied = 'support_ticket_replied';
    case SupportTicketStatusChanged = 'support_ticket_status_changed';

    // Reports
    case DailyAdminReport = 'daily_admin_report';
    case WeeklyAdminReport = 'weekly_admin_report';
    case MonthlyAdminReport = 'monthly_admin_report';
    case MonthlyAgentLedger = 'monthly_agent_ledger';
    case MonthlyFinanceLedger = 'monthly_finance_ledger';
    case AgencyWalletDepositSummary = 'agency_wallet_deposit_summary';
    case AgencyBookingActivitySummary = 'agency_booking_activity_summary';
    case PnrManualReviewDigest = 'pnr_manual_review_digest';

    // Group ticketing
    case GroupBookingReservationCreated = 'group_booking_reservation_created';
    case GroupBookingPaymentSubmitted = 'group_booking_payment_submitted';
    case GroupBookingReleasedUnpaid = 'group_booking_released_unpaid';
    case GroupBookingSupplierReleaseFailed = 'group_booking_supplier_release_failed';
    case GroupBookingAccessRestricted = 'group_booking_access_restricted';

    public function defaultScope(): string
    {
        return match ($this) {
            self::BookingRequestReceived,
            self::BookingFareUpdatedRequiresAcceptance,
            self::BookingUpdatedFareAccepted,
            self::BookingConfirmed,
            self::BookingStatusChanged,
            self::BookingAssigned,
            self::BookingCancelled,
            self::BookingFailedValidation,
            self::BookingManualReviewRequired,
            self::StaleSegmentRequiresNewSearch,
            self::PnrItinerarySynced,
            self::PnrItinerarySyncFailed,
            self::CancellationRequested,
            self::CancellationStatusChanged,
            self::PaymentProofSubmitted,
            self::PaymentRecorded,
            self::PaymentVerified,
            self::PaymentRejected,
            self::PaymentCompleted,
            self::RefundRequested,
            self::RefundApproved,
            self::RefundPaid,
            self::RefundRejected,
            self::SupplierBookingCreated,
            self::SupplierBookingFailed,
            self::SupplierReadinessFailed,
            self::SupplierSearchFailed,
            self::SupplierOrderFailed,
            self::FxConversionFailed,
            self::TicketIssued,
            self::TicketingFailed,
            self::TicketingNotSupported,
            self::AdminLoginSuccess,
            self::StaffLoginSuccess,
            self::AgentLoginSuccess,
            self::LoginFailedSensitive,
            self::LoginFailedAlert,
            self::DailyAdminReport,
            self::WeeklyAdminReport,
            self::MonthlyAdminReport,
            self::MonthlyAgentLedger,
            self::MonthlyFinanceLedger,
            self::PnrManualReviewDigest,
            self::SupportTicketCreated,
            self::SupportTicketReplied,
            self::GroupBookingReservationCreated,
            self::GroupBookingPaymentSubmitted,
            self::GroupBookingReleasedUnpaid,
            self::GroupBookingSupplierReleaseFailed,
            self::GroupBookingAccessRestricted => 'admin',

            self::SupportTicketAssigned => 'staff',

            self::CustomerRegistered,
            self::CustomerLoginSuccess,
            self::AuthNewDeviceLogin,
            self::LoginFailedAlert,
            self::SupportTicketStatusChanged,
            self::GroupBookingReservationCreated,
            self::GroupBookingPaymentSubmitted,
            self::GroupBookingReleasedUnpaid,
            self::GroupBookingAccessRestricted => 'customer',

            self::SupportTicketForwarded => 'agent',

            self::AgentApplicationSubmitted,
            self::AgentApplicationApproved,
            self::AgentApplicationNeedsMoreInfo,
            self::AgentApplicationRejected,
            self::AgentCreated,
            self::CommissionEarned,
            self::CommissionApproved,
            self::CommissionPayoutRecorded,
            self::CommissionStatementIssued,
            self::AgentDepositApproved,
            self::AgentDepositRejected,
            self::AgencyWalletDepositSummary,
            self::AgencyBookingActivitySummary => 'agent',

            self::AgentDepositSubmitted => 'admin',

            self::StaffCreated,
            self::AdminCreated,
            self::UserSuspended,
            self::UserActivated,
            self::PasswordResetRequested,
            self::DocumentGenerated,
            self::DocumentDownloadedAdminOptional,
            self::TicketItineraryGenerated,
            self::InvoiceGenerated,
            self::PaymentReceiptGenerated => 'staff',
        };
    }
}
