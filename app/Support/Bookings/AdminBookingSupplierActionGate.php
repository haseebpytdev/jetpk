<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Bookings\PiaNdcEticketDeliveryService;
use App\Services\Suppliers\PiaNdc\PiaNdcOrderOperationPreflight;
use App\Services\Suppliers\PiaNdc\PiaNdcReleaseOptionPnrService;
use App\Services\Suppliers\PiaNdc\PiaNdcVoidTicketService;
use App\Support\Phone\SupplierContactFormatter;
use App\Support\Platform\PlatformModuleEnforcer;

/**
 * Admin booking detail: operator action eligibility with warnings vs hard blocks (PIA-NDC-OPS1).
 *
 * Supplier-impacting admin actions remain available when business rules permit, even when
 * readiness checks (itinerary sync, generic ticketing eligibility) are incomplete.
 */
final class AdminBookingSupplierActionGate
{
    public function __construct(
        private readonly PiaNdcOrderOperationPreflight $preflight,
        private readonly PiaNdcVoidTicketService $voidTicketService,
        private readonly PiaNdcEticketDeliveryService $eticketDeliveryService,
        private readonly PiaNdcReleaseOptionPnrService $releaseService,
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
    ) {}

    /**
     * @return array{
     *     provider: string,
     *     can_manual_preview: bool,
     *     can_manual_issue: bool,
     *     can_retry_ticketing: bool,
     *     admin_override_allowed: bool,
     *     hard_block_reason: ?string,
     *     warnings: list<string>,
     *     itinerary_synced: bool,
     *     selected_fare_present: bool,
     *     normalized_supplier_phone: ?string,
     *     requires_admin_confirm: bool
     * }
     */
    public function piaNdcManualTicketing(Booking $booking, bool $genericTicketingEligible): array
    {
        $booking->loadMissing(['contact', 'tickets', 'latestSupplierBooking', 'ticketingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];

        $warnings = [];
        $hardBlock = $this->piaNdcHardBlockReason($booking, $context, $meta);
        $itinerarySynced = $this->itinerarySynced($meta);
        $selectedFarePresent = $this->selectedBrandedFarePresent($booking, $meta);

        if ($hardBlock === null) {
            if (! $itinerarySynced) {
                $warnings[] = 'Warning: PNR itinerary has not synced. Proceed only after verifying itinerary with supplier/airline.';
            }
            if (! $genericTicketingEligible) {
                $warnings[] = 'Automated ticketing readiness is incomplete — manual admin confirmation is required before proceeding.';
            }
            if (! $selectedFarePresent) {
                $warnings[] = 'Selected branded fare snapshot is missing — verify fare with supplier before ticketing.';
            }
        }

        $canManual = $hardBlock === null;
        $adminOverrideAllowed = $canManual && $warnings !== [];
        $requiresAdminConfirm = $adminOverrideAllowed;

        $ticketed = $booking->tickets->isNotEmpty()
            || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)
            || $this->preflight->duplicateTicketGuard($booking);
        $latestTicketAttempt = $booking->ticketingAttempts->sortByDesc('created_at')->first();
        $canRetryTicketing = $canManual
            && ! $ticketed
            && in_array((string) ($latestTicketAttempt?->status ?? ''), ['failed'], true);

        return [
            'provider' => SupplierProvider::PiaNdc->value,
            'can_manual_preview' => $canManual,
            'can_manual_issue' => $canManual,
            'can_retry_ticketing' => $canRetryTicketing,
            'admin_override_allowed' => $adminOverrideAllowed,
            'hard_block_reason' => $hardBlock,
            'warnings' => $warnings,
            'itinerary_synced' => $itinerarySynced,
            'selected_fare_present' => $selectedFarePresent,
            'normalized_supplier_phone' => $this->normalizedSupplierPhonePreview($booking, $meta),
            'requires_admin_confirm' => $requiresAdminConfirm,
        ];
    }

    /**
     * @return array<string, array{enabled: bool, admin_override_allowed: bool, reason: string}>
     */
    public function actionMatrix(Booking $booking, bool $genericTicketingEligible, bool $genericSupplierEligible): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $matrix = [];

        if ($provider === SupplierProvider::PiaNdc->value) {
            $manual = $this->piaNdcManualTicketing($booking, $genericTicketingEligible);
            $matrix['ticket_preview'] = [
                'enabled' => $manual['can_manual_preview'],
                'admin_override_allowed' => $manual['admin_override_allowed'],
                'reason' => $manual['hard_block_reason'] ?? ($manual['can_manual_preview'] ? 'Ready with operator confirmation.' : 'Unavailable.'),
            ];
            $matrix['issue_ticket'] = [
                'enabled' => $manual['can_manual_issue'],
                'admin_override_allowed' => $manual['admin_override_allowed'],
                'reason' => $manual['hard_block_reason'] ?? ($manual['can_manual_issue'] ? 'Ready with operator confirmation.' : 'Unavailable.'),
            ];
            $matrix['retry_ticketing'] = [
                'enabled' => $manual['can_retry_ticketing'],
                'admin_override_allowed' => $manual['admin_override_allowed'],
                'reason' => $manual['can_retry_ticketing'] ? 'Retry after failed ticketing attempt.' : 'No failed ticketing attempt to retry.',
            ];
            $matrix['void_ticket'] = $this->simpleActionRow(
                $this->voidTicketService->canVoidBooking($booking),
                false,
                $this->voidTicketService->voidBlockedReason($booking) ?? 'Void available with confirmation.',
            );
            $matrix['send_eticket_email'] = $this->simpleActionRow(
                $this->eticketDeliveryService->canResend($booking),
                false,
                $this->eticketDeliveryService->resendBlockedReason($booking) ?? 'Resend available with confirmation.',
            );
            $matrix['release_option_pnr'] = $this->simpleActionRow(
                $this->releaseService->canReleaseBooking($booking),
                false,
                $this->releaseService->canReleaseBooking($booking) ? 'Release available with confirmation.' : 'Option PNR already released or not releasable.',
            );
            $matrix['generate_ticket_itinerary'] = $this->simpleActionRow(
                $booking->tickets->isNotEmpty(),
                false,
                $booking->tickets->isNotEmpty() ? 'Generate from issued tickets.' : 'Ticket must be issued first.',
            );
        }

        return $matrix;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $meta
     */
    private function piaNdcHardBlockReason(Booking $booking, array $context, array $meta): ?string
    {
        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);
        if ($this->platformModuleEnforcer->ticketingBlockedMessage(SupplierProvider::PiaNdc->value, $distributionChannel) !== null) {
            return 'Ticketing is disabled for this deployment.';
        }
        if (! $this->platformModuleEnforcer->providerChannelEnabled(SupplierProvider::PiaNdc->value, $distributionChannel)) {
            return 'PIA NDC provider module is disabled for this channel.';
        }

        if ($booking->status === BookingStatus::Cancelled) {
            return 'Booking is cancelled.';
        }

        $ticketed = $booking->tickets->isNotEmpty()
            || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)
            || $this->preflight->duplicateTicketGuard($booking);
        if ($ticketed) {
            return 'Tickets have already been issued for this booking.';
        }

        $interpreted = strtolower(trim((string) ($context['interpreted_status'] ?? '')));
        $orderStatus = strtoupper(trim((string) ($context['order_status'] ?? '')));
        $released = ($context['option_pnr_released'] ?? false) === true
            || in_array($interpreted, [
                PiaNdcBookingStatusInterpreter::STATUS_RELEASED,
                PiaNdcBookingStatusInterpreter::STATUS_NO_ACTIVE_SEGMENTS,
            ], true)
            || in_array($orderStatus, ['CLOSED', 'CANCELLED', 'CANCELED'], true);
        if ($released) {
            return 'PIA NDC option PNR is released or closed.';
        }

        if ((string) ($booking->payment_status ?? 'unpaid') !== 'paid') {
            return 'Payment must be verified before ticketing.';
        }

        if (trim((string) ($booking->pnr ?? '')) === '') {
            return 'Supplier PNR is required before ticketing.';
        }

        if (in_array($interpreted, [
            PiaNdcBookingStatusInterpreter::STATUS_RELEASED,
            PiaNdcBookingStatusInterpreter::STATUS_NO_ACTIVE_SEGMENTS,
        ], true)) {
            return 'PIA NDC order has no active segments.';
        }

        $resolved = $this->preflight->orderContext($booking);
        if ($resolved['order_id'] === '' || $resolved['owner_code'] === '') {
            return 'PIA NDC order context is incomplete.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function itinerarySynced(array $meta): bool
    {
        $pnrSnapshot = $meta['pnr_itinerary_snapshot'] ?? null;
        $segments = is_array($pnrSnapshot) && is_array($pnrSnapshot['segments'] ?? null)
            ? $pnrSnapshot['segments']
            : [];
        $syncSidecar = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $syncStatus = strtolower(trim((string) ($syncSidecar['status'] ?? '')));

        return ($segments !== [] && $syncStatus === 'synced')
            || PiaNdcPnrItinerarySyncMapper::piaNdcSupplierTicketingEvidence($meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function selectedBrandedFarePresent(Booking $booking, array $meta): bool
    {
        if ((float) ($booking->selected_fare_total ?? 0) > 0 || (float) ($booking->revalidated_fare_total ?? 0) > 0) {
            return true;
        }

        foreach (['selected_fare_family_option', 'outbound_selected_fare_family_option', 'return_selected_fare_family_option'] as $key) {
            if (is_array($meta[$key] ?? null) && $meta[$key] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function normalizedSupplierPhonePreview(Booking $booking, array $meta): ?string
    {
        $contact = $booking->contact;
        $phoneRaw = trim((string) ($contact?->phone ?? $booking->contact_phone ?? ''));
        if ($phoneRaw === '') {
            return null;
        }

        $countryHint = trim((string) ($contact?->phone_country_code ?? $meta['phone_country_code'] ?? ''));
        $areaHint = trim((string) (is_array($contact?->meta) ? ($contact->meta['phone_area_code'] ?? $contact->meta['area_code'] ?? '') : ''));
        $formatted = SupplierContactFormatter::format(
            $phoneRaw,
            $countryHint !== '' ? $countryHint : null,
            $areaHint !== '' ? $areaHint : null,
        );
        if (! ($formatted['valid'] ?? false)) {
            return null;
        }

        return (string) ($formatted['e164'] ?? '');
    }

    /**
     * @return array{enabled: bool, admin_override_allowed: bool, reason: string}
     */
    private function simpleActionRow(bool $enabled, bool $adminOverrideAllowed, string $reason): array
    {
        return [
            'enabled' => $enabled,
            'admin_override_allowed' => $adminOverrideAllowed,
            'reason' => $reason,
        ];
    }
}
