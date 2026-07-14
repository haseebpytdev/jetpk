<?php

namespace App\Services\Communication;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Support\Bookings\BookingItineraryOverviewPresenter;
use App\Support\Bookings\SupplierOperationalStatus;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds one normalized payload shape for customer and operational booking emails.
 */
class BookingEmailPayloadFactory
{
    public function bookingReceived(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'booking_received',
            'Your booking request was received — '.$booking->reference_code,
            'Booking request received',
            'Received',
            'info',
            'Thank you. We have received your booking request and our team will review it shortly.',
            $this->bookingRequestNotes($booking, ['Please keep your booking reference handy when contacting support.'])
        );
    }

    public function paymentPending(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'payment_pending',
            'Payment pending - '.$booking->reference_code,
            'Payment pending',
            'Payment pending',
            'warning',
            'Your booking is waiting for payment confirmation.',
            ['Please complete payment or contact support if you have already submitted proof.']
        );
    }

    public function pnrCreated(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'pnr_created',
            'PNR created - '.$booking->reference_code,
            'PNR created',
            'PNR created',
            'success',
            'Your supplier booking record has been created.',
            ['Ticketing may follow separately once all checks are complete.']
        );
    }

    public function ticketIssued(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'ticket_issued',
            'Ticket issued - '.$booking->reference_code,
            'Ticket issued',
            'Ticketed',
            'success',
            'Your booking has been ticketed.',
            ['Keep ticket numbers confidential and contact support if any itinerary detail looks incorrect.']
        );
    }

    public function b2bTicketIssued(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'ticket_issued_b2b',
            'Ticket issued for booking '.$booking->reference_code,
            'Ticket issued',
            'Ticketed',
            'success',
            'Ticketing has been completed for this agency booking.',
            ['Use the booking reference, route, amount summary, and status for customer follow-up.'],
            ['recipient_type' => 'agent']
        );
    }

    public function b2bBookingCreated(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);
        $payload = $this->basePayload(
            $booking,
            'booking_created_b2b',
            'New agency booking created - '.$booking->reference_code,
            'New agency booking created',
            'Created',
            'info',
            'A new agency booking request has been created and is ready for follow-up.',
            $this->bookingRequestNotes($booking, [
                'Passenger count: '.$booking->passengers->count(),
                'Use the booking reference, route, amount summary, and current status for customer follow-up.',
            ]),
            ['recipient_type' => 'agent']
        );

        $payload['greeting_name'] = 'Team';
        $payload['passengers'] = [];
        $payload['contact'] = [];

        return $payload;
    }

    public function cancellationRequested(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'cancellation_requested_internal',
            'Cancellation requested - '.$booking->reference_code,
            'Cancellation requested',
            'Requested',
            'warning',
            'A cancellation request has been recorded for this booking.',
            ['Our team will review airline and supplier rules before confirming the outcome.'],
            ['recipient_type' => 'admin', 'admin_booking_url' => $this->adminBookingUrl($booking)]
        );
    }

    public function cancellationConfirmed(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'cancellation_confirmed',
            'Cancellation confirmed - '.$booking->reference_code,
            'Cancellation confirmed',
            'Cancelled',
            'danger',
            'Cancellation has been confirmed for this booking.',
            ['Refund handling, if applicable, may be communicated separately.']
        );
    }

    public function customerCancellationRequested(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'cancellation_requested_customer',
            'Cancellation request received - '.$booking->reference_code,
            'Cancellation request received',
            'Requested',
            'warning',
            'We have received your cancellation request.',
            [
                'Our team will review airline and supplier rules before confirming the outcome.',
                'We will update you when the review is complete.',
            ]
        );
    }

    public function customerCancellationUpdate(Booking $booking, ?string $status = null): array
    {
        $booking = $this->preparedBooking($booking);
        $statusLabel = $this->headline($status ?: (string) ($booking->cancellation_status ?? $booking->status->value));

        return $this->basePayload(
            $booking,
            'cancellation_status_customer',
            'Cancellation update - '.$booking->reference_code,
            'Cancellation update',
            $statusLabel,
            $booking->status->value === 'cancelled' ? 'danger' : 'warning',
            'There is an update on your cancellation request.',
            [
                'Our team will share the final outcome after airline and supplier rules are reviewed.',
                'If a refund applies, processing timelines depend on the airline, supplier, and payment method.',
            ]
        );
    }

    public function agencyCancellationUpdate(Booking $booking, ?string $status = null): array
    {
        $booking = $this->preparedBooking($booking);
        $statusLabel = $this->headline($status ?: (string) ($booking->cancellation_status ?? $booking->status->value));

        return $this->basePayload(
            $booking,
            'cancellation_update_b2b',
            'Cancellation update for booking '.$booking->reference_code,
            'Cancellation update',
            $statusLabel,
            'warning',
            'A cancellation status update has been recorded for this agency booking.',
            ['Use the booking reference, route, amount summary, and status for customer follow-up.'],
            ['recipient_type' => 'agent']
        );
    }

    public function refundPending(Booking $booking): array
    {
        return $this->customerRefundUpdate($booking, 'refund_requested');
    }

    public function customerRefundUpdate(
        Booking $booking,
        string $refundEventKey,
        ?float $refundAmount = null,
        ?string $currency = null,
    ): array {
        $booking = $this->preparedBooking($booking);
        [$type, $subject, $title, $statusLabel, $tone, $intro, $notes] = $this->refundCustomerCopy($refundEventKey, $booking->reference_code);

        $payload = $this->basePayload(
            $booking,
            $type,
            $subject,
            $title,
            $statusLabel,
            $tone,
            $intro,
            $notes,
        );

        $payload['booking']['current_status'] = $statusLabel;
        $payload['payment'] = array_merge(
            $payload['payment'],
            $this->refundOverlay($refundAmount, $currency ?? (string) ($booking->currency ?? 'PKR'), $statusLabel),
        );

        return $payload;
    }

    public function refundActionRequired(
        Booking $booking,
        ?float $amount = null,
        ?string $currency = null,
        ?array $meta = [],
    ): array {
        $booking = $this->preparedBooking($booking);
        $currency = $currency ?? (string) ($booking->currency ?? 'PKR');
        $statusLabel = 'Refund Requested';

        $payload = $this->basePayload(
            $booking,
            'refund_action_required',
            'Refund action required - '.$booking->reference_code,
            'Refund action required',
            $statusLabel,
            'warning',
            'A refund request needs review.',
            [
                'Review refund request, fare rules, supplier/payment implications, and update the refund status.',
            ],
            ['recipient_type' => 'admin', 'admin_booking_url' => $this->adminBookingUrl($booking)]
        );

        $payload['greeting_name'] = 'Operations Team';
        $payload['booking']['current_status'] = $statusLabel;
        $payload['payment'] = array_merge(
            $payload['payment'],
            $this->refundOverlay($amount, $currency, $statusLabel),
        );

        if ($meta !== []) {
            $payload['admin'] = array_merge($payload['admin'], $meta);
        }

        return $payload;
    }

    public function agencyRefundUpdate(
        Booking $booking,
        string $refundEventKey,
        ?float $refundAmount = null,
        ?string $currency = null,
    ): array {
        $booking = $this->preparedBooking($booking);
        [$type, $subject, $title, $statusLabel, $tone, $intro, $notes] = $this->refundAgencyCopy($refundEventKey, $booking->reference_code);

        $payload = $this->basePayload(
            $booking,
            $type,
            $subject,
            $title,
            $statusLabel,
            $tone,
            $intro,
            $notes,
            ['recipient_type' => 'agent'],
        );

        $payload['greeting_name'] = 'Team';
        $payload['passengers'] = [];
        $payload['contact'] = [];
        $payload['booking']['current_status'] = $statusLabel;
        $payload['payment'] = array_merge(
            $payload['payment'],
            $this->refundOverlay($refundAmount, $currency ?? (string) ($booking->currency ?? 'PKR'), $statusLabel),
        );

        return $payload;
    }

    public function manualReviewRequired(Booking $booking): array
    {
        return $this->staffReviewRequired($booking, 'staff_review');
    }

    public function staffReviewRequired(Booking $booking, ?string $reason = null): array
    {
        $booking = $this->preparedBooking($booking);
        $supplierStatus = SupplierOperationalStatus::fromValues(
            (string) ($booking->supplier_booking_status ?? ''),
            (string) ($booking->supplier ?? ''),
            $this->hasPnrOrReference($booking),
            is_array($booking->meta) ? $booking->meta : null,
        );
        $safeReason = $this->safeManualReviewReasonLabel($reason, $supplierStatus['meaning']);
        $notes = [
            'Review booking details, supplier status, and customer/agent communication before proceeding.',
        ];
        if ($safeReason !== null && $safeReason !== '') {
            array_unshift($notes, 'Reason: '.$safeReason);
        }

        $payload = $this->basePayload(
            $booking,
            'staff_review_required',
            'Staff review required - '.$booking->reference_code,
            'Staff review required',
            'Staff Review Required',
            'warning',
            'A booking requires staff review before the next operational action.',
            $notes,
            [
                'recipient_type' => 'admin',
                'admin_booking_url' => $this->adminBookingUrl($booking),
                'staff_review_reason' => $safeReason,
                'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
                'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
            ]
        );

        $payload['greeting_name'] = 'Operations Team';
        $payload['booking']['current_status'] = 'Manual Review';

        return $payload;
    }

    public function agencyManualReviewRequired(Booking $booking, ?string $reason = null): array
    {
        $booking = $this->preparedBooking($booking);

        $payload = $this->basePayload(
            $booking,
            'booking_manual_review_b2b',
            'Booking review update - '.$booking->reference_code,
            'Booking review update',
            'Review required',
            'warning',
            'This agency booking requires review before it can proceed.',
            [
                'Use the booking reference, route, and status summary for customer follow-up.',
                'No action is required from the customer unless support contacts them.',
            ],
            ['recipient_type' => 'agent']
        );

        $payload['greeting_name'] = 'Team';
        $payload['passengers'] = [];
        $payload['contact'] = [];
        $payload['booking']['current_status'] = 'Review required';

        return $payload;
    }

    public function customerManualReviewRequired(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'manual_review_customer_notice',
            'Booking review update - '.$booking->reference_code,
            'Booking review update',
            'Under review',
            'warning',
            'Your booking is being reviewed by our team before the next step.',
            [
                'No action is required from you right now unless our support team contacts you.',
                'We will update you as soon as the review is complete.',
            ]
        );
    }

    public function bookingFailed(Booking $booking, ?Throwable $e = null): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'booking_failed',
            'Booking issue detected - '.$booking->reference_code,
            'Booking issue detected',
            'Needs attention',
            'danger',
            'We could not complete part of this booking workflow automatically.',
            ['Our team has been alerted and will review the booking.'],
            [
                'error' => $e !== null ? Str::limit($e->getMessage(), 500) : null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function supplierFailureAlert(Booking $booking, string $type, ?array $context = null): array
    {
        $booking = $this->preparedBooking($booking);
        $context = is_array($context) ? $context : [];
        [$title, $statusLabel, $intro] = $this->supplierFailureCopy($type);
        $notes = [
            'Review booking, supplier status, payment/ticketing prerequisites, and decide whether to retry, manual process, or contact the customer/agent.',
        ];
        $safeReason = $this->safeFailureReasonLabel($context);
        if ($safeReason !== null) {
            array_unshift($notes, 'Reason: '.$safeReason);
        }

        $payload = $this->basePayload(
            $booking,
            $type,
            $title.' - '.$booking->reference_code,
            $title,
            $statusLabel,
            'danger',
            $intro,
            $notes,
            [
                'recipient_type' => 'admin',
                'admin_booking_url' => $this->adminBookingUrl($booking),
                'failure_type' => $type,
                'failure_reason' => $safeReason,
                'failure_classification' => $this->safeFailureClassification($context),
                'supplier_booking_status' => (string) ($context['supplier_booking_status'] ?? $booking->supplier_booking_status ?? ''),
                'ticketing_status' => (string) ($context['ticketing_status'] ?? $booking->ticketing_status ?? ''),
                'supplier_name' => $this->safeSupplierName($booking, $context),
                'supplier_connection_id' => isset($context['supplier_connection_id']) ? (int) $context['supplier_connection_id'] : null,
            ]
        );

        $payload['greeting_name'] = 'Operations Team';
        $payload['passengers'] = [];
        $payload['contact'] = [];
        $payload['booking']['current_status'] = $statusLabel;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function supplierConnectionAuthFailureAlert(Booking $booking, array $context): array
    {
        $payload = $this->supplierFailureAlert($booking, 'supplier_connection_auth_failed', $context);
        $safeReason = $this->safeFailureReasonLabel($context);
        $supplierName = $this->safeSupplierName($booking, $context);
        $notes = [
            'Check supplier credentials, token/link status, environment, and connection configuration.',
            'Do not share credentials, tokens, or secrets in email.',
        ];
        if ($safeReason !== null) {
            array_unshift($notes, 'Classification: '.$safeReason);
        }
        if ($supplierName !== null && $supplierName !== '') {
            array_unshift($notes, 'Provider/connection: '.$supplierName);
        }

        $payload['type'] = 'supplier_connection_auth_failed';
        $payload['subject'] = 'Supplier connection credential/auth alert - '.$booking->reference_code;
        $payload['title'] = 'Supplier connection credential/auth alert';
        $payload['status_label'] = 'Credential/Auth Review';
        $payload['status_tone'] = 'danger';
        $payload['intro'] = 'A supplier connection requires platform-admin review due to a credential, authentication, or link failure signal.';
        $payload['notes'] = $notes;
        $payload['greeting_name'] = 'Platform Admin';
        $payload['booking']['current_status'] = 'Credential/Auth Review';

        return $payload;
    }

    /**
     * Agency-scoped wallet/deposit summary (no booking context).
     *
     * @param  array{
     *     balance?: float,
     *     pending_deposits?: float,
     *     currency?: string,
     *     pending_deposit_count?: int,
     *     recent_transaction_count?: int,
     *     period_label?: string,
     *     wallet_count?: int
     * }  $summary
     */
    public function agencyWalletDepositSummary(Agency $agency, array $summary): array
    {
        $agency->loadMissing('agencySetting');
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $currency = (string) ($summary['currency'] ?? 'PKR');
        $balance = number_format((float) ($summary['balance'] ?? 0), 2);
        $pendingDeposits = number_format((float) ($summary['pending_deposits'] ?? 0), 2);
        $periodLabel = trim((string) ($summary['period_label'] ?? now()->format('F Y')));
        $agencyName = trim((string) ($agency->agencySetting?->display_name ?? $agency->name ?? 'Agency'));
        $pendingCount = (int) ($summary['pending_deposit_count'] ?? 0);
        $recentTxCount = (int) ($summary['recent_transaction_count'] ?? 0);

        $notes = [
            'This summary is scoped to your agency only.',
            'Current wallet balance: '.$currency.' '.$balance,
            'Pending deposit total: '.$currency.' '.$pendingDeposits,
            'Pending deposit requests: '.$pendingCount,
            'Recent wallet transactions (30 days): '.$recentTxCount,
        ];

        return [
            'type' => 'agency_wallet_deposit_summary',
            'subject' => 'Agency wallet/deposit summary — '.$periodLabel,
            'title' => 'Agency wallet/deposit summary',
            'status_label' => 'Wallet Summary',
            'status_tone' => 'info',
            'greeting_name' => 'Agency Admin',
            'intro' => 'Here is your agency wallet/deposit summary for '.$periodLabel.'.',
            'company' => $profile->toArray(),
            'booking' => [
                'reference' => 'WALLET-'.$agency->id,
                'route' => 'N/A',
                'trip_type' => 'Finance Summary',
                'travel_date' => $periodLabel,
                'current_status' => 'Summary',
            ],
            'segments' => [],
            'passengers' => [],
            'contact' => [],
            'payment' => [
                'currency' => $currency,
                'total' => $currency.' '.$balance,
                'balance_due' => $pendingDeposits !== '0.00' ? $currency.' '.$pendingDeposits : null,
                'status' => $pendingCount > 0 ? $pendingCount.' pending deposit request(s)' : 'No pending deposit requests',
            ],
            'cta' => [],
            'notes' => $notes,
            'admin' => [
                'recipient_type' => 'agent',
                'agency_name' => $agencyName,
                'period_label' => $periodLabel,
                'wallet_balance' => $balance,
                'wallet_currency' => $currency,
                'pending_deposit_count' => $pendingCount,
                'recent_transaction_count' => $recentTxCount,
            ],
        ];
    }

    /**
     * Agency-scoped booking activity summary (no booking context).
     *
     * @param  array{
     *     period_label?: string,
     *     total_bookings?: int,
     *     agent_booking_count?: int,
     *     direct_customer_booking_count?: int,
     *     agent_staff_created_count?: int,
     *     pending_count?: int,
     *     confirmed_count?: int,
     *     ticketed_count?: int,
     *     cancelled_count?: int,
     *     manual_review_count?: int,
     *     pending_payment_count?: int,
     *     pending_ticketing_count?: int,
     *     total_booking_value?: float,
     *     currency?: string,
     *     sample_refs?: list<string>
     * }  $summary
     */
    public function agencyBookingActivitySummary(Agency $agency, array $summary): array
    {
        $agency->loadMissing('agencySetting');
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $periodLabel = trim((string) ($summary['period_label'] ?? now()->format('Y-m-d')));
        $agencyName = trim((string) ($agency->agencySetting?->display_name ?? $agency->name ?? 'Agency'));
        $currency = trim((string) ($summary['currency'] ?? 'PKR'));
        $totalBookings = (int) ($summary['total_bookings'] ?? 0);
        $totalValue = number_format((float) ($summary['total_booking_value'] ?? 0), 2);

        $notes = [
            'Period: '.$periodLabel,
            'Total bookings: '.$totalBookings,
            'Pending: '.(int) ($summary['pending_count'] ?? 0),
            'Confirmed: '.(int) ($summary['confirmed_count'] ?? 0),
            'Ticketed: '.(int) ($summary['ticketed_count'] ?? 0),
            'Cancelled: '.(int) ($summary['cancelled_count'] ?? 0),
            'Manual review: '.(int) ($summary['manual_review_count'] ?? 0),
            'Pending payment: '.(int) ($summary['pending_payment_count'] ?? 0),
            'Pending ticketing: '.(int) ($summary['pending_ticketing_count'] ?? 0),
            'Total booking value: '.$currency.' '.$totalValue,
            'Agent-channel bookings: '.(int) ($summary['agent_booking_count'] ?? 0),
            'Direct customer bookings: '.(int) ($summary['direct_customer_booking_count'] ?? 0),
            'Agent-staff created (when context stored): '.(int) ($summary['agent_staff_created_count'] ?? 0),
        ];

        $sampleRefs = array_values(array_filter((array) ($summary['sample_refs'] ?? [])));
        if ($sampleRefs !== []) {
            $notes[] = 'Sample booking references: '.implode(', ', array_slice($sampleRefs, 0, 10));
        }

        $notes[] = 'Review pending/manual-review bookings in the agency portal and follow up with customers where needed.';

        return [
            'type' => 'agency_booking_activity_summary',
            'subject' => 'Agency booking activity summary — '.$periodLabel,
            'title' => 'Agency booking activity summary',
            'status_label' => 'Activity Summary',
            'status_tone' => 'info',
            'greeting_name' => 'Agency Admin',
            'intro' => 'Here is your agency booking activity summary for the selected period.',
            'company' => $profile->toArray(),
            'booking' => [
                'reference' => 'A3-SUMMARY-'.$agency->id,
                'route' => 'N/A',
                'trip_type' => 'Agency Activity',
                'travel_date' => $periodLabel,
                'current_status' => 'Summary',
            ],
            'segments' => [],
            'passengers' => [],
            'contact' => [],
            'payment' => [
                'currency' => $currency,
                'total' => $currency.' '.$totalValue,
                'status' => $totalBookings.' booking(s) in period',
            ],
            'cta' => [],
            'notes' => $notes,
            'admin' => [
                'recipient_type' => 'agent',
                'agency_name' => $agencyName,
                'period_label' => $periodLabel,
                'total_bookings' => $totalBookings,
                'pending_count' => (int) ($summary['pending_count'] ?? 0),
                'confirmed_count' => (int) ($summary['confirmed_count'] ?? 0),
                'ticketed_count' => (int) ($summary['ticketed_count'] ?? 0),
                'cancelled_count' => (int) ($summary['cancelled_count'] ?? 0),
                'manual_review_count' => (int) ($summary['manual_review_count'] ?? 0),
                'pending_payment_count' => (int) ($summary['pending_payment_count'] ?? 0),
                'pending_ticketing_count' => (int) ($summary['pending_ticketing_count'] ?? 0),
                'total_booking_value' => $totalValue,
                'sample_refs' => array_slice($sampleRefs, 0, 10),
            ],
        ];
    }

    /**
     * Platform-admin PNR / manual-review operational digest (no booking context).
     *
     * @param  array{
     *     period_label?: string,
     *     total_bookings?: int,
     *     supplier_failed_count?: int,
     *     manual_review_count?: int,
     *     pending_ticketing_count?: int,
     *     ticketing_failed_count?: int,
     *     ticketing_not_supported_count?: int,
     *     pnr_created_count?: int,
     *     failed_ratio?: float,
     *     top_failure_codes?: array<string, int>,
     *     sample_refs?: list<string>
     * }  $digest
     */
    public function pnrManualReviewDigest(Agency $agency, array $digest): array
    {
        $agency->loadMissing('agencySetting');
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $periodLabel = trim((string) ($digest['period_label'] ?? now()->format('Y-m-d')));
        $totalBookings = (int) ($digest['total_bookings'] ?? 0);
        $manualReviewCount = (int) ($digest['manual_review_count'] ?? 0);
        $supplierFailedCount = (int) ($digest['supplier_failed_count'] ?? 0);
        $pendingTicketingCount = (int) ($digest['pending_ticketing_count'] ?? 0);
        $ticketingFailedCount = (int) ($digest['ticketing_failed_count'] ?? 0);
        $ticketingNotSupportedCount = (int) ($digest['ticketing_not_supported_count'] ?? 0);
        $pnrCreatedCount = (int) ($digest['pnr_created_count'] ?? 0);
        $failedRatio = number_format((float) ($digest['failed_ratio'] ?? 0), 2);
        $attentionCount = $supplierFailedCount + $manualReviewCount;

        $notes = [
            'Period: '.$periodLabel,
            'Total bookings: '.$totalBookings,
            'Manual review: '.$manualReviewCount,
            'Supplier failed: '.$supplierFailedCount,
            'PNR / supplier reference created: '.$pnrCreatedCount,
            'Pending ticketing: '.$pendingTicketingCount,
            'Ticketing failed: '.$ticketingFailedCount,
            'Ticketing not supported: '.$ticketingNotSupportedCount,
            'Failed/manual-review ratio: '.$failedRatio.'%',
        ];

        foreach ((array) ($digest['top_failure_codes'] ?? []) as $label => $count) {
            $notes[] = 'Top failure code — '.$label.': '.$count;
        }

        $sampleRefs = array_values(array_filter((array) ($digest['sample_refs'] ?? [])));
        if ($sampleRefs !== []) {
            $notes[] = 'Sample booking references: '.implode(', ', array_slice($sampleRefs, 0, 10));
        }

        $notes[] = 'Review failed PNR/manual-review bookings, inspect supplier classifications, and prioritize retries/manual processing where appropriate.';

        return [
            'type' => 'pnr_manual_review_digest',
            'subject' => 'PNR / manual review digest — '.$periodLabel,
            'title' => 'PNR / manual review digest',
            'status_label' => 'Operational Digest',
            'status_tone' => $attentionCount > 0 ? 'warning' : 'info',
            'greeting_name' => 'Platform Admin',
            'intro' => 'Here is the failed PNR / manual review digest for the selected period.',
            'company' => $profile->toArray(),
            'booking' => [
                'reference' => 'P3-DIGEST-'.$agency->id,
                'route' => 'N/A',
                'trip_type' => 'Operational Digest',
                'travel_date' => $periodLabel,
                'current_status' => 'Digest',
            ],
            'segments' => [],
            'passengers' => [],
            'contact' => [],
            'payment' => [],
            'cta' => [],
            'notes' => $notes,
            'admin' => [
                'recipient_type' => 'admin',
                'period_label' => $periodLabel,
                'total_bookings' => $totalBookings,
                'manual_review_count' => $manualReviewCount,
                'supplier_failed_count' => $supplierFailedCount,
                'ticketing_failed_count' => $ticketingFailedCount,
                'pnr_created_count' => $pnrCreatedCount,
                'failed_ratio' => $failedRatio,
                'sample_refs' => array_slice($sampleRefs, 0, 10),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function ticketingFailureAlert(Booking $booking, string $type, ?array $context = null): array
    {
        $booking = $this->preparedBooking($booking);
        $context = is_array($context) ? $context : [];
        $notes = [
            'Review ticketing status, supplier response classification, and decide whether to retry, ticket manually, or contact the customer/agent.',
        ];
        $safeReason = $this->safeFailureReasonLabel($context);
        if ($safeReason !== null) {
            array_unshift($notes, 'Reason: '.$safeReason);
        }

        $payload = $this->basePayload(
            $booking,
            $type,
            'Ticketing failed - '.$booking->reference_code,
            'Ticketing failed',
            'Ticketing Failure',
            'danger',
            'A ticketing action failed and requires staff review.',
            $notes,
            [
                'recipient_type' => 'admin',
                'admin_booking_url' => $this->adminBookingUrl($booking),
                'failure_type' => $type,
                'failure_reason' => $safeReason,
                'failure_classification' => $this->safeFailureClassification($context),
                'supplier_booking_status' => (string) ($context['supplier_booking_status'] ?? $booking->supplier_booking_status ?? ''),
                'ticketing_status' => (string) ($context['ticketing_status'] ?? $booking->ticketing_status ?? ''),
                'supplier_name' => $this->safeSupplierName($booking, $context),
                'supplier_connection_id' => isset($context['supplier_connection_id']) ? (int) $context['supplier_connection_id'] : null,
            ]
        );

        $payload['greeting_name'] = 'Operations Team';
        $payload['passengers'] = [];
        $payload['contact'] = [];
        $payload['booking']['current_status'] = 'Pending Ticketing';

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function ticketingNotSupportedAlert(Booking $booking, ?array $context = null): array
    {
        $booking = $this->preparedBooking($booking);
        $context = is_array($context) ? $context : [];
        $type = 'ticketing_not_supported';
        $notes = [
            'Review ticketing prerequisites, supplier capability, and decide whether to ticket manually or contact the customer/agent.',
        ];
        $safeReason = $this->safeFailureReasonLabel($context);
        if ($safeReason !== null) {
            array_unshift($notes, 'Reason: '.$safeReason);
        }

        $payload = $this->basePayload(
            $booking,
            $type,
            'Ticketing not supported - '.$booking->reference_code,
            'Ticketing not supported',
            'Ticketing Not Supported',
            'warning',
            'Automated ticketing is not supported for this booking and requires staff review.',
            $notes,
            [
                'recipient_type' => 'admin',
                'admin_booking_url' => $this->adminBookingUrl($booking),
                'failure_type' => $type,
                'failure_reason' => $safeReason,
                'failure_classification' => $this->safeFailureClassification($context),
                'supplier_booking_status' => (string) ($context['supplier_booking_status'] ?? $booking->supplier_booking_status ?? ''),
                'ticketing_status' => (string) ($context['ticketing_status'] ?? $booking->ticketing_status ?? ''),
                'supplier_name' => $this->safeSupplierName($booking, $context),
                'supplier_connection_id' => isset($context['supplier_connection_id']) ? (int) $context['supplier_connection_id'] : null,
            ]
        );

        $payload['greeting_name'] = 'Operations Team';
        $payload['passengers'] = [];
        $payload['contact'] = [];
        $payload['booking']['current_status'] = 'Pending Ticketing';

        return $payload;
    }

    public function adminNewBookingAlert(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'admin_new_booking_alert',
            'New customer booking - '.$booking->reference_code,
            'New customer booking',
            'New booking',
            'info',
            'A new customer booking request has been received.',
            $this->bookingRequestNotes($booking, ['Review passenger, payment, and supplier readiness details in the admin console.']),
            ['recipient_type' => 'admin', 'admin_booking_url' => $this->adminBookingUrl($booking)]
        );
    }

    public function adminCancellationAlert(Booking $booking): array
    {
        $booking = $this->preparedBooking($booking);

        return $this->basePayload(
            $booking,
            'cancellation_status_internal',
            'Cancellation alert - '.$booking->reference_code,
            'Cancellation alert',
            'Cancellation',
            'warning',
            'A cancellation event needs operational attention.',
            ['Review supplier and refund implications before updating the customer.'],
            ['recipient_type' => 'admin', 'admin_booking_url' => $this->adminBookingUrl($booking)]
        );
    }

    public function paymentVerified(BookingPayment $payment): array
    {
        $payment->loadMissing(['booking.agency.agencySetting', 'booking.contact', 'booking.customer', 'booking.passengers', 'booking.fareBreakdown']);
        $payload = $this->basePayload(
            $payment->booking,
            'payment_verified',
            'Payment verified - '.$payment->booking->reference_code,
            'Payment verified',
            'Verified',
            'success',
            'We have verified your payment. Thank you.',
            ['Your ticket or itinerary may follow in a separate email.']
        );

        $payload['payment'] = array_merge($payload['payment'], $this->paymentOverlay($payment, 'Verified'));

        return $payload;
    }

    public function paymentRejected(BookingPayment $payment): array
    {
        $payment->loadMissing(['booking.agency.agencySetting', 'booking.contact', 'booking.customer', 'booking.passengers', 'booking.fareBreakdown']);
        $payload = $this->basePayload(
            $payment->booking,
            'payment_rejected',
            'Payment update - '.$payment->booking->reference_code,
            'Payment could not be verified',
            'Could not be verified',
            'danger',
            'We reviewed your payment submission but could not verify it at this time.',
            ['Please submit updated proof or contact support if you need assistance.']
        );

        $payload['payment'] = array_merge($payload['payment'], $this->paymentOverlay($payment, 'Could not be verified'));

        return $payload;
    }

    public function agencyPaymentProofSubmitted(Booking $booking, ?BookingPayment $payment = null): array
    {
        $booking = $this->preparedBooking($booking);
        $payload = $this->basePayload(
            $booking,
            'payment_proof_submitted_b2b',
            'Payment proof submitted for booking '.$booking->reference_code,
            'Payment proof submitted',
            'Submitted',
            'info',
            'Payment proof has been submitted for this agency booking and is being reviewed.',
            [
                'Use the booking reference, route, amount summary, and current status for customer follow-up.',
                'Internal finance review notes are not included in this agency notification.',
            ],
            ['recipient_type' => 'agent'],
        );

        return $this->finalizeAgencyPaymentPayload($payload, $payment, 'Submitted');
    }

    public function agencyPaymentVerified(Booking $booking, ?BookingPayment $payment = null): array
    {
        $booking = $this->preparedBooking($booking);
        $payload = $this->basePayload(
            $booking,
            'payment_verified_b2b',
            'Payment verified for booking '.$booking->reference_code,
            'Payment verified',
            'Verified',
            'success',
            'A payment for this agency booking has been verified.',
            [
                'Customer payment emails remain separate and are not duplicated through this B2B path.',
                'Use the booking reference, route, amount summary, and current status for customer follow-up.',
            ],
            ['recipient_type' => 'agent'],
        );

        return $this->finalizeAgencyPaymentPayload($payload, $payment, 'Verified');
    }

    public function agencyPaymentRejected(Booking $booking, ?BookingPayment $payment = null): array
    {
        $booking = $this->preparedBooking($booking);
        $payload = $this->basePayload(
            $booking,
            'payment_rejected_b2b',
            'Payment could not be verified for booking '.$booking->reference_code,
            'Payment update',
            'Could not be verified',
            'danger',
            'A payment submission for this agency booking could not be verified.',
            [
                'Customer payment emails remain separate and are not duplicated through this B2B path.',
                'Internal finance rejection notes are not included in this agency notification.',
            ],
            ['recipient_type' => 'agent'],
        );

        return $this->finalizeAgencyPaymentPayload($payload, $payment, 'Could not be verified');
    }

    public function statusChanged(Booking $booking, string $statusLabel): array
    {
        $booking = $this->preparedBooking($booking);
        $label = $this->headline($statusLabel);

        return $this->basePayload(
            $booking,
            $booking->status->value === 'cancelled' ? 'booking_cancelled' : 'booking_status_changed',
            'Booking status update - '.$booking->reference_code,
            'Booking status updated',
            $label,
            $booking->status->value === 'cancelled' ? 'danger' : 'info',
            'Your booking status has been updated.',
            ['If you did not expect this change, please contact support with your booking reference.']
        );
    }

    public function itineraryReady(Booking $booking, ?string $staffNote = null, bool $hasPdfAttachment = false): array
    {
        $booking = $this->preparedBooking($booking);
        $notes = $hasPdfAttachment
            ? ['Your ticket itinerary PDF is attached to this email.']
            : ['Please check your booking account for full itinerary details.'];

        if ($staffNote !== null && trim($staffNote) !== '') {
            $notes[] = 'Note from our team: '.trim($staffNote);
        }

        return $this->basePayload(
            $booking,
            'itinerary_ready',
            'Your ticket itinerary is ready - Booking '.$booking->reference_code,
            'Your ticket itinerary is ready',
            'Itinerary ready',
            'success',
            'Your ticket itinerary is ready.',
            $notes
        );
    }

    protected function preparedBooking(Booking $booking): Booking
    {
        $booking->loadMissing([
            'agency.agencySetting',
            'contact',
            'passengers',
            'customer',
            'fareBreakdown',
            'tickets.passenger',
            'payments',
        ]);

        return $booking;
    }

    protected function basePayload(
        Booking $booking,
        string $type,
        string $subject,
        string $title,
        string $statusLabel,
        string $statusTone,
        string $intro,
        array $notes = [],
        array $admin = [],
    ): array {
        $booking = $this->preparedBooking($booking);
        $profile = CompanyEmailProfileResolver::resolve($booking->agency);
        $selectedFareFamily = $this->selectedFareFamilySection($booking);

        $payload = [
            'type' => $type,
            'subject' => $subject,
            'title' => $title,
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'greeting_name' => $this->contactName($booking),
            'intro' => $intro,
            'company' => $profile->toArray(),
            'booking' => $this->bookingSection($booking),
            'segments' => $this->segments($booking),
            'passengers' => $this->passengers($booking),
            'contact' => $this->contact($booking),
            'payment' => $this->payment($booking),
            'cta' => $this->cta($booking, (string) ($admin['recipient_type'] ?? 'customer')),
            'notes' => array_values(array_filter($notes, fn ($note): bool => is_string($note) && trim($note) !== '')),
            'admin' => $admin,
            'passenger_summary' => $this->passengerCountSummary($booking),
        ];

        if ($selectedFareFamily !== null) {
            $payload['selected_fare_family'] = $selectedFareFamily;
        }

        return $payload;
    }

    protected function bookingSection(Booking $booking): array
    {
        $overview = BookingItineraryOverviewPresenter::fromBookingMeta($booking->meta, $this->hasPnrOrReference($booking));

        return [
            'id' => $booking->id,
            'reference' => $booking->reference_code,
            'route' => $overview['journey_od'] ?? (trim((string) $booking->route) !== '' ? (string) $booking->route : 'N/A'),
            'trip_type' => $overview['trip_type_label'] ?? 'One way',
            'travel_date' => $booking->travel_date?->format('d M Y') ?? 'N/A',
            'current_status' => $this->headline($booking->status->value),
            'pnr' => trim((string) ($booking->pnr ?? '')) !== '' ? (string) $booking->pnr : null,
            'invoice_reference' => trim((string) ($booking->supplier_reference ?? '')) !== '' ? (string) $booking->supplier_reference : null,
            'airline' => trim((string) ($booking->airline ?? '')) !== '' ? (string) $booking->airline : null,
            'supplier' => trim((string) ($booking->supplier ?? '')) !== '' ? (string) $booking->supplier : null,
            'submitted_at' => $booking->submitted_at?->format('d M Y, g:i A'),
            'ticketed_at' => $booking->ticketed_at?->format('d M Y, g:i A'),
        ];
    }

    protected function segments(Booking $booking): array
    {
        $overview = BookingItineraryOverviewPresenter::fromBookingMeta($booking->meta, $this->hasPnrOrReference($booking));
        if (! is_array($overview) || empty($overview['segment_lines']) || ! is_array($overview['segment_lines'])) {
            return [];
        }

        return collect($overview['segment_lines'])
            ->filter(fn ($line): bool => is_string($line) && trim($line) !== '')
            ->values()
            ->map(fn (string $line, int $index): array => [
                'label' => 'Segment '.($index + 1),
                'summary' => trim($line),
            ])
            ->all();
    }

    protected function passengers(Booking $booking): array
    {
        return $booking->passengers
            ->sortBy('passenger_index')
            ->values()
            ->map(fn ($passenger, int $index): array => [
                'index' => $index + 1,
                'type' => $this->headline((string) ($passenger->passenger_type ?? 'passenger')),
                'is_lead' => (bool) ($passenger->is_lead_passenger ?? false),
                'name' => $this->passengerName($passenger),
            ])
            ->filter(fn (array $passenger): bool => trim((string) $passenger['name']) !== '')
            ->values()
            ->all();
    }

    protected function passengerCountSummary(Booking $booking): string
    {
        $counts = [];
        foreach ($booking->passengers as $passenger) {
            $type = strtolower((string) ($passenger->passenger_type ?? 'adult'));
            $label = match (true) {
                str_contains($type, 'infant') => 'infant',
                str_contains($type, 'child') => 'child',
                default => 'adult',
            };
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        if ($counts === []) {
            return '';
        }

        $parts = [];
        foreach (['adult' => 'adult', 'child' => 'child', 'infant' => 'infant'] as $key => $singular) {
            if (($counts[$key] ?? 0) > 0) {
                $count = $counts[$key];
                $parts[] = $count.' '.($count === 1 ? $singular : $singular.'s');
            }
        }

        return implode(', ', $parts);
    }

    protected function contact(Booking $booking): array
    {
        return [
            'name' => $this->contactName($booking),
            'email' => $booking->contact?->email ?? $booking->customer?->email,
            'phone' => $booking->contact?->phone,
            'country' => $booking->contact?->country,
        ];
    }

    protected function payment(Booking $booking): array
    {
        $fare = $booking->fareBreakdown;
        $currency = (string) ($booking->currency ?? $fare?->currency ?? 'PKR');
        $total = $fare !== null ? (float) ($fare->total ?? 0) : 0.0;
        $amountPaid = (float) ($booking->amount_paid ?? 0);
        $balanceDue = $booking->balance_due !== null ? (float) $booking->balance_due : max(0, $total - $amountPaid);
        $selectedFareFamily = $this->selectedFareFamilySection($booking);

        $payment = [
            'currency' => $currency,
            'amount_paid' => $amountPaid > 0 ? number_format($amountPaid, 2).' '.$currency : null,
            'status' => $booking->payment_status !== null ? $this->headline((string) $booking->payment_status) : null,
            'payment_due_at' => $booking->payment_due_at?->format('d M Y, g:i A'),
        ];

        if ($selectedFareFamily !== null) {
            $estimatedFareDisplay = $selectedFareFamily['estimated_fare_display'] ?? null;
            $payment['has_selected_fare_family'] = true;
            $payment['estimated_selected_fare'] = $estimatedFareDisplay;
            $payment['estimated_selected_fare_label'] = $selectedFareFamily['estimated_fare_label'] ?? 'Estimated selected fare';
            $payment['estimated_amount_due'] = $estimatedFareDisplay;
            $payment['estimated_amount_due_label'] = 'Estimated amount due';
            $payment['payable_disclaimer'] = $selectedFareFamily['payable_disclaimer'] ?? FlightOfferDisplayPresenter::SELECTED_FARE_PAYABLE_DISCLAIMER;
            $payment['final_payable_status'] = 'Pending validation';
            if ($total > 0) {
                $payment['base_fare_total'] = number_format($total, 2).' '.$currency;
            }

            return array_filter(
                $payment,
                static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== false,
            );
        }

        $payment['balance_due'] = $balanceDue > 0 ? number_format($balanceDue, 2).' '.$currency : null;
        $payment['total'] = $total > 0 ? number_format($total, 2).' '.$currency : null;

        return array_filter(
            $payment,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== false,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function selectedFareFamilySection(Booking $booking): ?array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $intent = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : null;

        return FlightOfferDisplayPresenter::buildSelectedFareFamilyEmailSection($intent);
    }

    /**
     * @param  list<string>  $notes
     * @return list<string>
     */
    protected function bookingRequestNotes(Booking $booking, array $notes): array
    {
        $section = $this->selectedFareFamilySection($booking);
        if ($section !== null) {
            $disclaimer = trim((string) ($section['payable_disclaimer'] ?? ''));
            if ($disclaimer !== '') {
                $notes[] = $disclaimer;
            }
        }

        return $notes;
    }

    protected function paymentOverlay(BookingPayment $payment, string $statusLabel): array
    {
        $currency = (string) ($payment->currency ?? $payment->booking?->currency ?? 'PKR');

        return [
            'payment_reference' => $payment->payment_reference,
            'payment_amount' => number_format((float) $payment->amount, 2).' '.$currency,
            'payment_method' => $payment->method !== null ? $this->headline($payment->method->value) : null,
            'payment_status_label' => $statusLabel,
            'verified_at' => $payment->verified_at?->format('d M Y, g:i A'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function finalizeAgencyPaymentPayload(array $payload, ?BookingPayment $payment, string $statusLabel): array
    {
        $payload['greeting_name'] = 'Team';
        $payload['passengers'] = [];
        $payload['contact'] = [];

        if ($payment !== null) {
            $payment->loadMissing(['booking.agency.agencySetting', 'booking.fareBreakdown']);
            $payload['payment'] = array_merge($payload['payment'], $this->paymentOverlay($payment, $statusLabel));
            $payload['booking']['current_status'] = $statusLabel;
        }

        return $payload;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: list<string>}
     */
    protected function refundCustomerCopy(string $refundEventKey, string $referenceCode): array
    {
        return match ($refundEventKey) {
            'refund_approved' => [
                'refund_approved',
                'Refund approved - '.$referenceCode,
                'Refund approved',
                'Refund approved',
                'success',
                'Your refund has been approved and is being prepared for processing.',
                [
                    'Processing timelines may vary depending on your bank, payment provider, and fare rules.',
                    'We will notify you when the refund has been processed.',
                ],
            ],
            'refund_paid' => [
                'refund_paid',
                'Refund processed - '.$referenceCode,
                'Refund processed',
                'Refund paid',
                'success',
                'Your refund has been marked as paid/processed.',
                [
                    'If the amount does not appear in your account within the expected timeframe, please contact support with your booking reference.',
                ],
            ],
            'refund_rejected' => [
                'refund_rejected',
                'Refund update - '.$referenceCode,
                'Refund update',
                'Refund not approved',
                'danger',
                'Your refund request could not be approved. Please contact support for details.',
                [
                    'Our support team can review your booking and explain the next available options.',
                ],
            ],
            default => [
                'refund_requested',
                'Refund request received - '.$referenceCode,
                'Refund request received',
                'Refund requested',
                'info',
                'We have received your refund request and our team is reviewing it.',
                [
                    'We will update you when the review is complete.',
                    'Processing timelines may vary depending on airline, supplier, and payment method rules.',
                ],
            ],
        };
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: list<string>}
     */
    protected function refundAgencyCopy(string $refundEventKey, string $referenceCode): array
    {
        return match ($refundEventKey) {
            'refund_approved' => [
                'agency_refund_approved',
                'Refund approved for booking '.$referenceCode,
                'Refund approved',
                'Refund approved',
                'success',
                'A refund for this agency booking has been approved and is being prepared for processing.',
                ['Use the booking reference, route, amount summary, and status for customer follow-up.'],
            ],
            'refund_paid' => [
                'agency_refund_paid',
                'Refund processed for booking '.$referenceCode,
                'Refund processed',
                'Refund paid',
                'success',
                'A refund for this agency booking has been marked as paid/processed.',
                ['Confirm customer receipt if follow-up is required.'],
            ],
            'refund_rejected' => [
                'agency_refund_rejected',
                'Refund not approved for booking '.$referenceCode,
                'Refund update',
                'Refund not approved',
                'danger',
                'A refund request for this agency booking could not be approved.',
                ['Contact the customer with support guidance; internal finance notes are not included in this email.'],
            ],
            default => [
                'agency_refund_requested',
                'Refund requested for booking '.$referenceCode,
                'Refund requested',
                'Refund requested',
                'info',
                'A refund request has been recorded for this agency booking.',
                ['Review the booking reference, route, amount summary, and current status before customer follow-up.'],
            ],
        };
    }

    /**
     * @return array<string, string|null>
     */
    protected function refundOverlay(?float $refundAmount, string $currency, string $statusLabel): array
    {
        return [
            'status' => $statusLabel,
            'refund_status_label' => $statusLabel,
            'refund_amount' => $refundAmount !== null && $refundAmount > 0
                ? number_format($refundAmount, 2).' '.$currency
                : null,
        ];
    }

    protected function cta(Booking $booking, string $recipientType): array
    {
        if ($recipientType === 'admin') {
            $url = $this->adminBookingUrl($booking);

            return $url !== null ? [['label' => 'Open booking', 'url' => $url]] : [];
        }

        if ($booking->customer_id !== null && Route::has('customer.bookings.show')) {
            return [['label' => 'View booking', 'url' => route('customer.bookings.show', $booking, absolute: true)]];
        }

        if ($booking->customer_id !== null && Route::has('customer.bookings.index')) {
            return [['label' => 'My bookings', 'url' => route('customer.bookings.index', absolute: true)]];
        }

        if (Route::has('booking.lookup')) {
            return [['label' => 'Look up booking', 'url' => route('booking.lookup', absolute: true)]];
        }

        return [];
    }

    protected function contactName(Booking $booking): string
    {
        $meta = is_array($booking->contact?->meta) ? $booking->contact->meta : [];
        foreach ([$meta['name'] ?? null, $meta['contact_name'] ?? null, $booking->customer?->name] as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        $lead = $booking->passengers->firstWhere('is_lead_passenger', true) ?? $booking->passengers->first();

        return $lead !== null ? $this->passengerName($lead) : 'Customer';
    }

    protected function passengerName(object $passenger): string
    {
        return trim(implode(' ', array_filter([
            (string) ($passenger->title ?? ''),
            (string) ($passenger->first_name ?? ''),
            (string) ($passenger->last_name ?? ''),
        ]))) ?: 'Passenger';
    }

    protected function hasPnrOrReference(Booking $booking): bool
    {
        return trim((string) ($booking->pnr ?? '')) !== ''
            || trim((string) ($booking->supplier_reference ?? $booking->supplier_api_booking_id ?? '')) !== '';
    }

    protected function adminBookingUrl(Booking $booking): ?string
    {
        if (! Route::has('admin.bookings.show')) {
            return null;
        }

        try {
            return route('admin.bookings.show', $booking, absolute: true);
        } catch (Throwable) {
            return null;
        }
    }

    protected function headline(string $value): string
    {
        return Str::headline(str_replace('_', ' ', $value));
    }

    protected function safeManualReviewReasonLabel(?string $reason, ?string $supplierMeaning = null): ?string
    {
        $normalized = strtolower(trim(str_replace([' ', '-'], '_', (string) $reason)));

        $label = match ($normalized) {
            'staff_review', 'manual_review', 'supplier_manual_review' => 'Manual review required',
            'fare_review', 'fare_changed_review_required' => 'Fare review required',
            'needs_review', 'review_required' => 'Review required',
            'certified_route_not_available' => 'Certified route unavailable',
            '' => null,
            default => Str::headline(str_replace('_', ' ', $normalized)),
        };

        if ($label === null && filled($supplierMeaning)) {
            return Str::limit((string) $supplierMeaning, 160);
        }

        return $label;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function supplierFailureCopy(string $type): array
    {
        return match ($type) {
            'supplier_readiness_failed' => [
                'Supplier readiness failed',
                'Supplier Failure',
                'Supplier readiness checks failed and require staff review.',
            ],
            'supplier_search_failed' => [
                'Supplier search failed',
                'Supplier Failure',
                'A supplier search action failed and requires staff review.',
            ],
            'supplier_order_failed' => [
                'Supplier order failed',
                'Supplier Failure',
                'A supplier order action failed and requires staff review.',
            ],
            default => [
                'Supplier booking failed',
                'Supplier Failure',
                'A supplier booking action failed and requires staff review.',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function safeFailureReasonLabel(array $context): ?string
    {
        $classification = trim((string) ($context['failure_classification'] ?? ''));
        if ($classification !== '') {
            return Str::limit($this->headline(str_replace('.', ' ', $classification)), 160);
        }

        $hostSummary = trim((string) ($context['host_classification_summary'] ?? ''));
        if ($hostSummary !== '') {
            return Str::limit($hostSummary, 160);
        }

        $reason = SensitiveDataRedactor::sanitizeErrorMessage(
            is_string($context['failure_reason'] ?? null) ? (string) $context['failure_reason'] : null
        );

        return filled($reason) ? Str::limit((string) $reason, 160) : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function safeFailureClassification(array $context): ?string
    {
        $classification = trim((string) ($context['failure_classification'] ?? ''));
        if ($classification !== '') {
            return Str::limit($classification, 120);
        }

        $hostReason = trim((string) ($context['host_classification_reason'] ?? ''));
        if ($hostReason !== '') {
            return Str::limit($hostReason, 120);
        }

        $errorCode = trim((string) ($context['error_code'] ?? ''));
        if ($errorCode !== '') {
            return Str::limit($errorCode, 120);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function safeSupplierName(Booking $booking, array $context): ?string
    {
        $provider = trim((string) ($context['supplier_name'] ?? $context['provider'] ?? $booking->supplier ?? ''));

        return $provider !== '' ? Str::limit($provider, 80) : null;
    }
}
