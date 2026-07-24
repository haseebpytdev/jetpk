<?php

namespace App\Services\Communication;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencyNotificationSetting;
use App\Models\Booking;
use App\Models\User;

class NotificationRecipientResolver
{
    /**
     * E6 role-based routing buckets per operational event (email only).
     *
     * @var array<string, list<string>>
     */
    private const POLICY_BUCKETS = [
        OtaNotificationEvent::BookingRequestReceived->value => ['admin', 'agent_booking'],
        OtaNotificationEvent::BookingFareUpdatedRequiresAcceptance->value => ['admin', 'customer_party', 'agent_booking'],
        OtaNotificationEvent::BookingUpdatedFareAccepted->value => ['admin', 'customer_party', 'agent_booking'],
        OtaNotificationEvent::BookingConfirmed->value => ['admin', 'agent_booking', 'agency_admin'],
        OtaNotificationEvent::BookingStatusChanged->value => ['admin', 'agent_booking', 'agency_admin'],
        OtaNotificationEvent::BookingAssigned->value => ['admin', 'staff_assigned'],
        OtaNotificationEvent::BookingCancelled->value => ['admin', 'agent_booking', 'agency_admin'],
        OtaNotificationEvent::PaymentProofSubmitted->value => ['finance', 'assigned_staff', 'operations_queue', 'platform_admin'],
        OtaNotificationEvent::PaymentRecorded->value => ['finance', 'admin', 'staff_assigned'],
        OtaNotificationEvent::PaymentVerified->value => ['platform_admin', 'assigned_staff'],
        OtaNotificationEvent::PaymentRejected->value => ['platform_admin', 'assigned_staff'],
        OtaNotificationEvent::RefundRequested->value => ['admin', 'staff_assigned', 'agent_booking', 'agency_admin'],
        OtaNotificationEvent::RefundApproved->value => ['admin', 'agent_booking', 'agency_admin'],
        OtaNotificationEvent::RefundPaid->value => ['admin', 'agent_booking', 'agency_admin'],
        OtaNotificationEvent::RefundRejected->value => ['admin', 'agent_booking', 'agency_admin'],
        OtaNotificationEvent::SupplierBookingCreated->value => ['customer_party', 'admin', 'staff_assigned'],
        OtaNotificationEvent::SupplierBookingFailed->value => ['assigned_staff', 'operations_queue', 'platform_admin'],
        OtaNotificationEvent::SupplierReadinessFailed->value => ['assigned_staff', 'operations_queue', 'platform_admin'],
        OtaNotificationEvent::SupplierSearchFailed->value => ['assigned_staff', 'operations_queue', 'platform_admin'],
        OtaNotificationEvent::SupplierOrderFailed->value => ['assigned_staff', 'operations_queue', 'platform_admin'],
        OtaNotificationEvent::PnrItinerarySynced->value => ['admin', 'staff_assigned'],
        OtaNotificationEvent::PnrItinerarySyncFailed->value => ['admin', 'staff_assigned'],
        OtaNotificationEvent::CancellationRequested->value => ['assigned_staff', 'operations_queue', 'platform_admin'],
        OtaNotificationEvent::CancellationStatusChanged->value => ['admin', 'agent_booking', 'agency_admin'],
        OtaNotificationEvent::BookingManualReviewRequired->value => ['assigned_staff', 'operations_queue', 'platform_admin'],
        OtaNotificationEvent::StaleSegmentRequiresNewSearch->value => ['admin', 'staff_assigned'],
        OtaNotificationEvent::AgentApplicationSubmitted->value => ['admin', 'applicant'],
        OtaNotificationEvent::AgentApplicationApproved->value => ['applicant', 'admin'],
        OtaNotificationEvent::AgentApplicationNeedsMoreInfo->value => ['applicant', 'admin'],
        OtaNotificationEvent::AgentApplicationRejected->value => ['applicant', 'admin'],
        OtaNotificationEvent::SupportTicketCreated->value => ['admin', 'ticket_assigned_staff', 'ticket_creator'],
        OtaNotificationEvent::SupportTicketAssigned->value => ['ticket_assigned_staff'],
        OtaNotificationEvent::SupportTicketForwarded->value => ['ticket_forwarded_agent'],
        OtaNotificationEvent::SupportTicketReplied->value => ['admin', 'ticket_assigned_staff'],
        OtaNotificationEvent::SupportTicketStatusChanged->value => ['ticket_creator'],
        OtaNotificationEvent::AgentDepositSubmitted->value => ['finance', 'admin'],
        OtaNotificationEvent::AgentDepositApproved->value => ['applicant'],
        OtaNotificationEvent::AgentDepositRejected->value => ['applicant'],
        OtaNotificationEvent::StaffCreated->value => ['staff', 'admin'],
        OtaNotificationEvent::AgentCreated->value => ['agent', 'admin'],
        OtaNotificationEvent::UserSuspended->value => ['user', 'admin'],
        OtaNotificationEvent::UserActivated->value => ['user', 'admin'],
        OtaNotificationEvent::AgencyWalletDepositSummary->value => ['agency_admin'],
        OtaNotificationEvent::AgencyBookingActivitySummary->value => ['agency_admin'],
        OtaNotificationEvent::PnrManualReviewDigest->value => ['platform_admin'],
        OtaNotificationEvent::AdminLoginSuccess->value => ['logged_in_user'],
        OtaNotificationEvent::StaffLoginSuccess->value => ['logged_in_user'],
        OtaNotificationEvent::AgentLoginSuccess->value => ['logged_in_user'],
        OtaNotificationEvent::CustomerLoginSuccess->value => ['logged_in_user'],
        OtaNotificationEvent::LoginFailedSensitive->value => ['admin'],
        OtaNotificationEvent::LoginFailedAlert->value => ['logged_in_user'],
        OtaNotificationEvent::AuthNewDeviceLogin->value => ['logged_in_user'],
        OtaNotificationEvent::TicketIssued->value => ['booking_agent', 'agency_admin', 'agent_staff_creator'],
        OtaNotificationEvent::TicketingFailed->value => ['assigned_staff', 'operations_queue', 'platform_admin'],
        OtaNotificationEvent::TicketingNotSupported->value => ['assigned_staff', 'operations_queue', 'platform_admin'],
    ];

    /**
     * @return list<string>
     */
    public static function policyBucketsFor(string $eventKey): array
    {
        $buckets = self::POLICY_BUCKETS[$eventKey] ?? [];

        return is_array($buckets) ? array_values($buckets) : [];
    }

    /**
     * Resolve emails for a single bucket (per-bucket operational delivery).
     *
     * @param  array<string, mixed>  $context
     * @return array{emails: array<int, string>, skipped: bool, reason: string}
     */
    public function resolveBucket(
        Agency $agency,
        string $bucket,
        ?Booking $booking = null,
        ?User $actor = null,
        array $context = [],
    ): array {
        $resolved = $this->emailsForBuckets($agency, [$bucket], $booking, $actor, $context);
        $emails = $resolved['emails'];
        $skipped = $emails === [];
        $reason = $skipped
            ? ($resolved['skipped_buckets'][0]['reason'] ?? 'No safe recipient email resolved for bucket.')
            : '';

        return [
            'emails' => $emails,
            'skipped' => $skipped,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array{applicant_email?: string|null, ticket_creator_email?: string|null, ticket_assigned_staff_email?: string|null, ticket_forwarded_agent_emails?: list<string|null>, agent_staff_creator_email?: string|null, agent_staff_creator_user_id?: int|null, notify_buckets?: list<string>, user_email?: string|null}  $context
     * @return array{to: array<int, string>, cc: array<int, string>, bcc: array<int, string>, scope: string, buckets: list<string>, skipped_buckets: list<array{bucket: string, reason: string}>}
     */
    public function resolve(
        Agency $agency,
        string $eventKey,
        ?Booking $booking = null,
        ?User $actor = null,
        array $context = [],
    ): array {
        $eventSetting = AgencyNotificationSetting::query()
            ->where('agency_id', $agency->id)
            ->where('event_key', $eventKey)
            ->where('channel', 'email')
            ->first();

        $explicitRecipients = $eventSetting !== null
            && is_array($eventSetting->recipient_emails)
            && $eventSetting->recipient_emails !== [];

        $buckets = $context['notify_buckets'] ?? ($explicitRecipients
            ? null
            : (self::POLICY_BUCKETS[$eventKey] ?? null));
        $skippedBuckets = [];

        if ($explicitRecipients && $buckets === null) {
            $to = $this->normalizeEmails($eventSetting->recipient_emails);
        } else {
            if ($buckets !== null) {
                $resolved = $this->emailsForBuckets($agency, $buckets, $booking, $actor, $context);
                $to = $resolved['emails'];
                $skippedBuckets = $resolved['skipped_buckets'];
            } else {
                $to = $this->emailsForScope(
                    $agency,
                    (string) ($eventSetting?->recipient_scope ?? 'admin'),
                    $booking,
                    $actor,
                );
            }

            if ($to === [] && $buckets === null) {
                $to = $this->supportEmailFallback($agency);
            }
        }

        $scope = $buckets !== null
            ? $this->primarySanitizeScope($buckets, $to)
            : (string) ($eventSetting?->recipient_scope ?? 'admin');

        return [
            'scope' => $scope,
            'to' => $to,
            'cc' => $this->normalizeEmails($eventSetting?->cc_emails ?? []),
            'bcc' => $this->normalizeEmails($eventSetting?->bcc_emails ?? []),
            'buckets' => $buckets !== null ? array_values($buckets) : [],
            'skipped_buckets' => $skippedBuckets,
        ];
    }

    /**
     * @param  list<string>  $buckets
     * @param  array{applicant_email?: string|null, ticket_creator_email?: string|null, ticket_assigned_staff_email?: string|null, ticket_forwarded_agent_emails?: list<string|null>, agent_staff_creator_email?: string|null, agent_staff_creator_user_id?: int|null}  $context
     * @return array{emails: array<int, string>, skipped_buckets: list<array{bucket: string, reason: string}>}
     */
    private function emailsForBuckets(
        Agency $agency,
        array $buckets,
        ?Booking $booking,
        ?User $actor,
        array $context,
    ): array {
        $emails = [];
        $skippedBuckets = [];

        foreach ($buckets as $bucket) {
            $bucketEmails = match ($bucket) {
                'customer_party' => $this->customerPartyEmails($booking),
                'booking_customer' => $this->customerEmails($booking),
                'agent_booking' => $this->agentBookingEmails($booking),
                'booking_agent' => $this->agentBookingEmails($booking),
                'agent' => $this->agentEmails($booking, $actor),
                'staff_assigned' => $this->assignedStaffEmails($booking),
                'assigned_staff' => $this->assignedStaffEmails($booking),
                'finance' => $this->financeEmails($agency),
                'admin' => $this->adminEmails($agency),
                'platform_admin' => $this->adminEmails($agency),
                'platform_staff' => $this->platformStaffEmails($agency),
                'agency_admin' => $this->agencyAdminEmails($agency),
                'agent_staff_creator' => $this->agentStaffCreatorEmails($actor, $context),
                'operations_queue' => $this->supportEmailFallback($agency),
                'applicant' => $this->normalizeEmails([
                    $context['applicant_email'] ?? null,
                    $actor?->email,
                ]),
                'ticket_creator' => $this->normalizeEmails([
                    $context['ticket_creator_email'] ?? null,
                    $actor?->email,
                ]),
                'ticket_assigned_staff' => $this->normalizeEmails([
                    $context['ticket_assigned_staff_email'] ?? null,
                ]),
                'ticket_forwarded_agent' => $this->normalizeEmails(
                    is_array($context['ticket_forwarded_agent_emails'] ?? null)
                        ? $context['ticket_forwarded_agent_emails']
                        : [],
                ),
                'logged_in_user' => $this->normalizeEmails([
                    $context['logged_in_user_email'] ?? null,
                    $actor?->email,
                ]),
                'user' => $this->normalizeEmails([
                    $context['user_email'] ?? null,
                    $actor?->email,
                ]),
                'staff' => $this->normalizeEmails([
                    $context['staff_email'] ?? null,
                    $actor?->email,
                ]),
                default => [],
            };

            if ($bucketEmails === []) {
                $skippedBuckets[] = [
                    'bucket' => $bucket,
                    'reason' => 'No safe recipient email resolved for bucket.',
                ];

                continue;
            }

            $emails = array_merge($emails, $bucketEmails);
        }

        return [
            'emails' => $this->normalizeEmails($emails),
            'skipped_buckets' => $skippedBuckets,
        ];
    }

    /**
     * Customer or agent on the booking (never both duplicated).
     *
     * @return array<int, string>
     */
    private function customerPartyEmails(?Booking $booking): array
    {
        if ($booking === null) {
            return [];
        }

        if ($booking->agent_id !== null) {
            return $this->agentEmails($booking, null);
        }

        return $this->customerEmails($booking);
    }

    /**
     * @return array<int, string>
     */
    private function agentBookingEmails(?Booking $booking): array
    {
        if ($booking === null || $booking->agent_id === null) {
            return [];
        }

        return $this->agentEmails($booking, null);
    }

    /**
     * @return array<int, string>
     */
    private function assignedStaffEmails(?Booking $booking): array
    {
        if ($booking === null) {
            return [];
        }

        $booking->loadMissing('assignedStaff');

        return $this->normalizeEmails([$booking->assignedStaff?->email]);
    }

    /**
     * @return array<int, string>
     */
    private function financeEmails(Agency $agency): array
    {
        $agency->loadMissing('agencySetting');
        $communication = AgencyCommunicationSetting::query()->where('agency_id', $agency->id)->first();
        $meta = $communication?->meta;
        $finance = is_array($meta) ? ($meta['finance_email'] ?? null) : null;

        return $this->normalizeEmails([
            is_string($finance) ? $finance : null,
            $agency->agencySetting?->support_email,
            config('client.canonical_support_email', 'ota@jetpakistan.pk'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function agencyAdminEmails(Agency $agency): array
    {
        return $this->normalizeEmails(
            $agency->users()
                ->where('account_type', AccountType::AgencyAdmin)
                ->where('users.status', UserAccountStatus::Active)
                ->pluck('email')
                ->all()
        );
    }

    /**
     * @return array<int, string>
     */
    private function platformStaffEmails(Agency $agency): array
    {
        return $this->normalizeEmails(
            $agency->users()
                ->where('account_type', AccountType::Staff)
                ->where('users.status', UserAccountStatus::Active)
                ->pluck('email')
                ->all()
        );
    }

    /**
     * @param  array{agent_staff_creator_email?: string|null, agent_staff_creator_user_id?: int|null}  $context
     * @return array<int, string>
     */
    private function agentStaffCreatorEmails(?User $actor, array $context): array
    {
        $emails = [];

        if ($actor?->account_type === AccountType::AgentStaff && $actor->status === UserAccountStatus::Active) {
            $emails[] = $context['agent_staff_creator_email'] ?? null;
            $emails[] = $actor->email;
        }

        $creatorUserId = (int) ($context['agent_staff_creator_user_id'] ?? 0);
        if ($creatorUserId > 0) {
            $creator = User::query()
                ->whereKey($creatorUserId)
                ->where('account_type', AccountType::AgentStaff)
                ->where('status', UserAccountStatus::Active)
                ->first();
            $emails[] = $creator?->email;
        }

        return $this->normalizeEmails($emails);
    }

    /**
     * @return array<int, string>
     */
    private function emailsForScope(Agency $agency, string $scope, ?Booking $booking, ?User $actor): array
    {
        return match ($scope) {
            'customer' => $this->customerEmails($booking),
            'agent' => $this->agentEmails($booking, $actor),
            'staff' => $this->staffEmails($agency, $booking),
            default => $this->adminEmails($agency),
        };
    }

    /**
     * @return array<int, string>
     */
    private function customerEmails(?Booking $booking): array
    {
        if ($booking === null) {
            return [];
        }

        $booking->loadMissing(['contact', 'customer']);

        return $this->normalizeEmails([
            $booking->contact?->email,
            $booking->customer?->email,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function agentEmails(?Booking $booking, ?User $actor): array
    {
        $emails = [];

        if ($booking !== null) {
            $booking->loadMissing('agent.user');
            $emails[] = $booking->agent?->user?->email;
        }

        if ($actor?->account_type === AccountType::Agent) {
            $emails[] = $actor->email;
        }

        return $this->normalizeEmails($emails);
    }

    /**
     * Legacy single-scope staff resolution (all staff users).
     *
     * @return array<int, string>
     */
    private function staffEmails(Agency $agency, ?Booking $booking): array
    {
        $emails = $this->assignedStaffEmails($booking);
        $staff = $agency->users()->where('account_type', AccountType::Staff)->pluck('email')->all();

        return $this->normalizeEmails(array_merge($emails, $staff));
    }

    /**
     * @return array<int, string>
     */
    private function adminEmails(Agency $agency): array
    {
        $platformAdminEmails = $agency->users()
            ->where('account_type', AccountType::PlatformAdmin)
            ->where('users.status', UserAccountStatus::Active)
            ->pluck('email')
            ->all();

        if ($platformAdminEmails !== []) {
            return $this->normalizeEmails($platformAdminEmails);
        }

        $agencyAdminEmails = $agency->users()
            ->where('account_type', AccountType::AgencyAdmin)
            ->where('users.status', UserAccountStatus::Active)
            ->pluck('email')
            ->all();

        if ($agencyAdminEmails !== []) {
            return $this->normalizeEmails($agencyAdminEmails);
        }

        return $this->supportEmailFallback($agency);
    }

    /**
     * Ops inbox when no active platform_admin is on the agency pivot (never agency_admin users).
     *
     * @return array<int, string>
     */
    private function supportEmailFallback(Agency $agency): array
    {
        $agency->loadMissing('agencySetting');

        return $this->normalizeEmails([
            $agency->agencySetting?->support_email,
            config('client.canonical_support_email', 'ota@jetpakistan.pk'),
        ]);
    }

    /**
     * @param  list<string>  $buckets
     * @param  array<int, string>  $to
     */
    private function primarySanitizeScope(array $buckets, array $to): string
    {
        if ($to === []) {
            return 'admin';
        }

        if (in_array('logged_in_user', $buckets, true)) {
            return 'staff';
        }

        if (in_array('user', $buckets, true) || in_array('staff', $buckets, true)) {
            return 'customer';
        }

        if (in_array('customer_party', $buckets, true) || in_array('booking_customer', $buckets, true) || in_array('applicant', $buckets, true) || in_array('ticket_creator', $buckets, true)) {
            return 'customer';
        }

        if (in_array('agent_booking', $buckets, true) || in_array('booking_agent', $buckets, true) || in_array('agent', $buckets, true) || in_array('agent_staff_creator', $buckets, true)) {
            return 'agent';
        }

        if (in_array('assigned_staff', $buckets, true) || in_array('staff_assigned', $buckets, true) || in_array('platform_staff', $buckets, true)) {
            return 'staff';
        }

        return 'admin';
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return array<int, string>
     */
    private function normalizeEmails(array $emails): array
    {
        return collect($emails)
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->unique()
            ->values()
            ->all();
    }
}
