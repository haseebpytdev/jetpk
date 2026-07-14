<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\TicketingAttempt;
use App\Models\User;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use App\Support\Bookings\SabreGdsPnrCancellationStateResolver;
use App\Support\Bookings\SabreGdsPnrItinerarySyncResolver;
use App\Support\Bookings\TicketingReadinessPresenter;
use App\Support\Platform\PlatformModuleEnforcer;

/**
 * Sabre GDS ticketing eligibility and blocker evaluation (no HTTP).
 */
final class SabreGdsTicketingReadiness
{
    public const META_KEY = 'sabre_gds_ticketing';

    public const ACTION_ISSUE_TICKET = 'issue_ticket';

    public const ACTION_TICKETING_PENDING = 'ticketing_pending';

    public const ACTION_TICKETED = 'ticketed';

    public const ACTION_MANUAL_ACTION_REQUIRED = 'manual_action_required';

    public const ACTION_NOT_ELIGIBLE = 'not_eligible';

    public const ACTION_PNR_CANCELLED_RELEASED = 'pnr_cancelled_released';

    public function __construct(
        private readonly SabreGdsTicketingRequestBuilder $requestBuilder,
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
        private readonly SabreControlledFinalPnrRetryAllowanceGate $f9rGate,
        private readonly SabreGdsPnrItinerarySyncResolver $itinerarySyncResolver,
        private readonly SabreGdsPnrCancellationStateResolver $pnrCancellationStateResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking, array $context = []): array
    {
        $booking->loadMissing(['passengers', 'tickets', 'latestTicketingAttempt', 'latestSupplierBooking', 'fareBreakdown']);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);
        $isNdcChannel = $this->platformModuleEnforcer->isSabreNdcDistributionChannel($distributionChannel);

        $blockers = [];
        $warnings = [];

        if ($provider !== SupplierProvider::Sabre->value) {
            $blockers[] = 'supplier_not_sabre';
        }

        if ($isNdcChannel) {
            $blockers[] = 'sabre_ndc_channel_use_ndc_services';
        }

        if (! $this->isGdsChannel($meta)) {
            $blockers[] = 'distribution_channel_not_gds';
        }

        $pnrCancelledOrReleased = $this->isPnrCancelledOrReleased($booking, $meta);

        if ($this->isCancelled($booking)) {
            $blockers[] = 'booking_cancelled';
        }

        if ($pnrCancelledOrReleased) {
            $blockers[] = 'pnr_cancelled_released';
        }

        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierRef = trim((string) ($booking->supplier_reference ?? ''));
        if ($pnr === '' && $supplierRef === '') {
            $blockers[] = 'missing_pnr_or_locator';
        }

        if (! $pnrCancelledOrReleased && ! $this->isItinerarySynced($booking, $meta)) {
            $blockers[] = 'itinerary_not_synced';
        }

        if (($booking->payment_status ?? '') !== 'paid') {
            $blockers[] = 'payment_not_verified';
        }

        if ($this->isTicketed($booking, $meta)) {
            $blockers[] = 'duplicate_ticketing_guard';
        }

        if ($this->supplierTicketNumbersPresent($meta) && $booking->tickets->isEmpty()) {
            $blockers[] = 'supplier_ticket_numbers_present';
        }

        if (! (bool) config('suppliers.sabre.ticketing_enabled', false)) {
            $blockers[] = 'ticketing_disabled_by_env';
        }

        if (! (bool) config('suppliers.sabre.ticketing_live_call_enabled', false)) {
            $blockers[] = 'ticketing_live_call_disabled';
        }

        $moduleBlock = $this->platformModuleEnforcer->ticketingBlockedMessage($provider !== '' ? $provider : null, $distributionChannel);
        if ($moduleBlock !== null) {
            $blockers[] = 'platform_ticketing_module_disabled';
        }

        $gdsModuleEnabled = $this->platformModuleEnforcer->effectiveModuleEnabled('sabre_gds');
        if (! $gdsModuleEnabled) {
            $blockers[] = 'sabre_gds_module_disabled';
        }

        $latestAttempt = $booking->latestTicketingAttempt;
        if ($this->isSupplierTicketingInProgress($booking, $meta)) {
            $blockers[] = 'ticketing_attempt_in_progress';
        }

        if ($latestAttempt instanceof TicketingAttempt
            && $latestAttempt->status === 'failed'
            && ! ($context['allow_unsafe_retry'] ?? false)) {
            $warnings[] = 'previous_ticketing_attempt_failed';
        }

        $f9r = $this->f9rGate->assessPostFinalRetryContainment($booking);
        if (($f9r['contained'] ?? false) === true) {
            $blockers[] = 'post_final_retry_containment';
        }

        $builder = $this->requestBuilder->build($booking);
        foreach ($builder['missing'] as $missing) {
            $blockers[] = 'missing_'.$missing;
        }

        $e10 = ($context['skip_e10_presenter'] ?? false)
            ? null
            : TicketingReadinessPresenter::forBooking($booking);
        if (is_array($e10)) {
            foreach ($e10['items'] as $item) {
                if (($item['status'] ?? '') !== 'fail') {
                    continue;
                }
                $key = (string) ($item['key'] ?? '');
                if ($key === 'pnr_itinerary_synced' && $this->isItinerarySynced($booking, $meta)) {
                    continue;
                }
                if ($pnrCancelledOrReleased && in_array($key, ['pnr_itinerary_synced', 'segments_active'], true)) {
                    continue;
                }
                $blockers[] = 'e10_'.$key;
            }
        }

        $requireConfirm = (bool) ($context['require_confirmation'] ?? false);
        $confirmProvided = (bool) ($context['confirmation_provided'] ?? false);
        if ($requireConfirm && ! $confirmProvided) {
            $blockers[] = 'exact_confirmation_required';
        }

        $dryRun = (bool) ($context['dry_run'] ?? false);
        $operationallyReady = $blockers === [];
        $liveAllowed = $operationallyReady && ! $dryRun;
        $action = $this->resolveActionState($booking, $meta, $blockers, $operationallyReady, $pnrCancelledOrReleased);

        return [
            'eligible' => $blockers === [] || ($dryRun && ! in_array('duplicate_ticketing_guard', $blockers, true)),
            'can_preview' => true,
            'can_attempt_live_ticketing' => $liveAllowed,
            'live_supplier_call_allowed' => $liveAllowed,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'confirm_phrase' => 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
            'pnr_present' => $pnr !== '' || $supplierRef !== '',
            'distribution_channel' => $distributionChannel,
            'e10_overall_status' => is_array($e10) ? ($e10['overall_status'] ?? '') : '',
            'payload_missing_fields' => $builder['missing'],
            'itinerary_synced' => $this->isItinerarySynced($booking, $meta),
            'ticketed' => $this->isTicketed($booking, $meta),
            'cancelled' => $this->isCancelled($booking),
            'pnr_cancelled_or_released' => $pnrCancelledOrReleased,
            'in_progress' => $this->isTicketingInProgressForDisplay($booking, $meta, $latestAttempt),
            'action_state' => $action['action_state'],
            'action_label' => $action['action_label'],
            'admin_message' => $action['admin_message'],
            'customer_message' => $action['customer_message'],
            'can_execute' => $action['can_execute'],
            'config' => [
                'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
                'ticketing_live_call_enabled' => (bool) config('suppliers.sabre.ticketing_live_call_enabled', false),
                'public_ticketing_enabled' => (bool) config('suppliers.sabre.public_ticketing_enabled', false),
            ],
        ];
    }

    public function isCancelled(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Cancelled || $booking->cancelled_at !== null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function isTicketed(Booking $booking, array $meta = []): bool
    {
        if ($booking->tickets()->exists()) {
            return true;
        }
        if ($booking->status === BookingStatus::Ticketed || $booking->ticketed_at !== null) {
            return true;
        }
        if (in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)) {
            return true;
        }

        $ticketingMeta = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        return in_array((string) ($ticketingMeta['status'] ?? ''), ['ticketed', 'issued'], true);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function isItinerarySynced(Booking $booking, ?array $meta = null): bool
    {
        return $this->itinerarySyncResolver->isSynced($booking, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function isPnrCancelledOrReleased(Booking $booking, ?array $meta = null): bool
    {
        return $this->pnrCancellationStateResolver->isPnrCancelledOrReleased($booking, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function supplierTicketNumbersPresent(array $meta): bool
    {
        $sync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];

        return ($sync['ticket_numbers_present'] ?? false) === true
            || ($sync['is_ticketed'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function isGdsChannel(array $meta): bool
    {
        $channel = strtolower(trim((string) ($meta['distribution_channel'] ?? 'gds')));

        return $channel === '' || $channel === 'gds';
    }

    /**
     * Supplier duplicate guard — meta lock only (TicketingService may own a processing attempt row).
     *
     * @param  array<string, mixed>  $meta
     */
    public function isSupplierTicketingInProgress(Booking $booking, array $meta = []): bool
    {
        $meta = $meta !== [] ? $meta : (is_array($booking->meta) ? $booking->meta : []);
        $ticketingMeta = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        return ($ticketingMeta['status'] ?? '') === 'in_progress';
    }

    /**
     * Admin UI pending state — meta lock or open processing attempt.
     *
     * @param  array<string, mixed>  $meta
     */
    public function isTicketingInProgressForDisplay(Booking $booking, array $meta = [], ?TicketingAttempt $latestAttempt = null): bool
    {
        if ($this->isSupplierTicketingInProgress($booking, $meta)) {
            return true;
        }

        $latestAttempt ??= $booking->latestTicketingAttempt;

        return $latestAttempt instanceof TicketingAttempt
            && strtolower((string) $latestAttempt->status) === 'processing'
            && $latestAttempt->completed_at === null;
    }

    /**
     * @param  list<string>  $blockers
     * @return array{action_state: string, action_label: string, admin_message: string, customer_message: string, can_execute: bool}
     */
    protected function resolveActionState(
        Booking $booking,
        array $meta,
        array $blockers,
        bool $operationallyReady,
        bool $pnrCancelledOrReleased = false,
    ): array {
        if ($pnrCancelledOrReleased || in_array('pnr_cancelled_released', $blockers, true)) {
            return [
                'action_state' => self::ACTION_PNR_CANCELLED_RELEASED,
                'action_label' => 'PNR released/cancelled',
                'admin_message' => 'Sabre/GDS PNR has been released/cancelled. Ticketing is no longer available for this PNR.',
                'customer_message' => 'Your reservation with the airline has been cancelled. Contact our team for refund or credit assistance.',
                'can_execute' => false,
            ];
        }

        if ($this->isTicketed($booking, $meta)) {
            return [
                'action_state' => self::ACTION_TICKETED,
                'action_label' => 'Ticketed',
                'admin_message' => 'Tickets have already been issued for this booking.',
                'customer_message' => 'Your tickets have been issued.',
                'can_execute' => false,
            ];
        }

        if ($this->isTicketingInProgressForDisplay($booking, $meta)) {
            return [
                'action_state' => self::ACTION_TICKETING_PENDING,
                'action_label' => 'Ticketing pending',
                'admin_message' => 'Sabre ticketing is already in progress. Do not submit another issue request.',
                'customer_message' => 'Your tickets are being processed. We will update you shortly.',
                'can_execute' => false,
            ];
        }

        if ($this->isCancelled($booking)) {
            return [
                'action_state' => self::ACTION_MANUAL_ACTION_REQUIRED,
                'action_label' => 'Manual action required',
                'admin_message' => 'Cancelled bookings cannot be ticketed via Sabre GDS.',
                'customer_message' => 'This booking was cancelled; contact our team for assistance.',
                'can_execute' => false,
            ];
        }

        if (in_array('supplier_ticket_numbers_present', $blockers, true)) {
            return [
                'action_state' => self::ACTION_MANUAL_ACTION_REQUIRED,
                'action_label' => 'Manual action required',
                'admin_message' => 'Supplier getBooking shows ticket numbers already present. Verify locally before re-issuing.',
                'customer_message' => 'Ticketing requires manual review by our team.',
                'can_execute' => false,
            ];
        }

        if ($operationallyReady) {
            return [
                'action_state' => self::ACTION_ISSUE_TICKET,
                'action_label' => 'Issue Ticket',
                'admin_message' => 'Unticketed Sabre GDS PNR is ready for Enhanced Air Ticket issuance.',
                'customer_message' => 'Your booking is ready for ticket issuance after payment confirmation.',
                'can_execute' => true,
            ];
        }

        return [
            'action_state' => self::ACTION_NOT_ELIGIBLE,
            'action_label' => 'Not eligible',
            'admin_message' => $blockers !== []
                ? 'Sabre GDS ticketing blocked: '.implode(', ', array_slice($blockers, 0, 6))
                : 'Sabre GDS ticketing is not available for this booking.',
            'customer_message' => 'Ticket issuance must be handled by our team.',
            'can_execute' => false,
        ];
    }

    public static function confirmPhraseMatches(Booking $booking, ?string $confirm): bool
    {
        $expected = 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id;

        return is_string($confirm) && trim($confirm) === $expected;
    }

    public function actorMayIssue(?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        return $actor->isPlatformAdmin() || $actor->isAgencyAdmin() || $actor->isStaff();
    }
}
