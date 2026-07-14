<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Sabre\SabreReadinessReasonPresenter;

/**
 * Admin/staff ticketing readiness checklist (E10). Operational prerequisites only —
 * does not issue tickets or enable live Sabre ticketing.
 */
class TicketingReadinessPresenter
{
    public const OVERALL_READY_EXCEPT_TICKETING_DISABLED = 'ready_except_ticketing_disabled';

    public const OVERALL_BLOCKED_MISSING_PNR = 'blocked_missing_pnr';

    public const OVERALL_BLOCKED_ITINERARY_NOT_SYNCED = 'blocked_itinerary_not_synced';

    public const OVERALL_BLOCKED_SEGMENT_STATUS = 'blocked_segment_status';

    public const OVERALL_BLOCKED_PAYMENT = 'blocked_payment';

    public const OVERALL_BLOCKED_PASSENGER_DATA = 'blocked_passenger_data';

    public const OVERALL_BLOCKED_MISSING_FARE = 'blocked_missing_fare';

    public const OVERALL_MANUAL_REVIEW_WITH_WARNINGS = 'manual_review_with_warnings';

    public const OVERALL_BLOCKED_SUPPLIER_NOT_SUPPORTED = 'blocked_supplier_not_supported';

    /**
     * @return array{
     *     overall_status: string,
     *     overall_label: string,
     *     items: list<array{key: string, label: string, status: string, message: string}>,
     *     can_attempt_live_ticketing: false
     * }
     */
    public static function forBooking(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'customer', 'fareBreakdown']);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierReference = trim((string) ($booking->supplier_reference ?? ''));
        $hasPnr = $pnr !== '' || $supplierReference !== '';

        $pnrSnapshot = $meta['pnr_itinerary_snapshot'] ?? null;
        $segments = is_array($pnrSnapshot) && is_array($pnrSnapshot['segments'] ?? null)
            ? $pnrSnapshot['segments']
            : [];
        $hasSnapshotSegments = $segments !== [];

        $syncSidecar = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $syncStatus = strtolower(trim((string) ($syncSidecar['status'] ?? '')));
        $isSabreGds = $provider === SupplierProvider::Sabre->value
            && app(SupplierLifecycleContextResolver::class)->isHandler($booking, SupplierLifecycleContextResolver::HANDLER_SABRE_GDS);
        $pnrCancelledOrReleased = $isSabreGds
            && app(SabreGdsPnrCancellationStateResolver::class)->isPnrCancelledOrReleased($booking, $meta);
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        $iatiOrderSynced = $provider === SupplierProvider::Iati->value
            && trim((string) ($iatiContext['last_sync_at'] ?? '')) !== ''
            && trim((string) ($booking->supplier_reference ?? $iatiContext['order_id'] ?? '')) !== '';
        $hasIatiOrder = $provider === SupplierProvider::Iati->value
            && trim((string) ($booking->supplier_reference ?? $iatiContext['order_id'] ?? '')) !== '';
        $itinerarySynced = $isSabreGds
            ? app(SabreGdsPnrItinerarySyncResolver::class)->isSynced($booking, $meta)
            : ($provider === SupplierProvider::Iati->value
                ? (! $hasIatiOrder || $iatiOrderSynced)
                : ($provider === SupplierProvider::PiaNdc->value
                    ? (($hasSnapshotSegments && $syncStatus === 'synced') || PiaNdcPnrItinerarySyncMapper::piaNdcSupplierTicketingEvidence($meta))
                    : ($hasSnapshotSegments && $syncStatus === 'synced')));
        $piaNdcItineraryWarningOnly = $provider === SupplierProvider::PiaNdc->value
            && $hasPnr
            && ! $itinerarySynced
            && ! PiaNdcPnrItinerarySyncMapper::piaNdcSupplierTicketingEvidence($meta);

        $segmentEvaluation = self::evaluateSegmentStatuses($segments);
        $paymentSummary = BookingPaymentSummaryPresenter::forBooking($booking, true, 'admin');
        $paymentPass = self::paymentVerified($booking, $paymentSummary);

        $contactEmail = trim((string) ($booking->contact?->email ?? ''));
        $customerEmail = trim((string) ($booking->customer?->email ?? ''));
        $passengerContactPass = $booking->passengers->isNotEmpty()
            && ($contactEmail !== '' || $customerEmail !== '');

        $fareEvaluation = self::evaluateFareTotal($booking, $meta);

        $ticketingConfigEnabled = (bool) config('suppliers.sabre.ticketing_enabled', false);
        $liveTicketingSupported = self::providerSupportsLiveTicketing($provider);
        $sabreGdsReadiness = $provider === SupplierProvider::Sabre->value
            && app(SupplierLifecycleContextResolver::class)->isHandler($booking, SupplierLifecycleContextResolver::HANDLER_SABRE_GDS)
            ? app(SabreGdsTicketingReadiness::class)->evaluate($booking, [
                'dry_run' => true,
                'skip_e10_presenter' => true,
            ])
            : null;
        $sabreGdsIssueReady = is_array($sabreGdsReadiness)
            && ($sabreGdsReadiness['action_state'] ?? '') === SabreGdsTicketingReadiness::ACTION_ISSUE_TICKET
            && ($sabreGdsReadiness['can_execute'] ?? false);
        $distributionChannel = app(PlatformModuleEnforcer::class)->distributionChannelFromBookingMeta($meta);
        $ticketingModuleBlock = app(PlatformModuleEnforcer::class)->ticketingBlockedMessage(
            $provider !== '' ? $provider : null,
            $distributionChannel,
        );
        $ticketingModuleEnabled = $ticketingModuleBlock === null;

        $items = [
            self::item(
                'pnr_exists',
                'PNR exists',
                $hasPnr ? 'pass' : 'fail',
                $hasPnr
                    ? 'PNR or supplier reference is stored on the booking.'
                    : app(SabreReadinessReasonPresenter::class)->messageForCode('missing_sabre_pnr'),
            ),
            self::item(
                'pnr_itinerary_synced',
                $provider === SupplierProvider::Iati->value ? 'IATI order synced' : 'PNR itinerary synced',
                $itinerarySynced ? 'pass' : ($piaNdcItineraryWarningOnly ? 'warning' : 'fail'),
                $itinerarySynced
                    ? ($provider === SupplierProvider::Iati->value
                        ? 'IATI order was synced from /order/{orderId}.'
                        : 'PNR itinerary snapshot is synced from getBooking.')
                    : ($provider === SupplierProvider::Iati->value
                        ? 'Sync the IATI order from the Supplier tab before confirm/book.'
                        : ($piaNdcItineraryWarningOnly
                            ? 'Warning: PNR itinerary has not synced. Proceed only after verifying itinerary with supplier/airline.'
                            : ($hasSnapshotSegments
                                ? 'Itinerary snapshot exists but sync status is not synced.'
                                : 'PNR itinerary must be synced from the Supplier tab before ticketing.'))),
            ),
            self::item(
                'segments_active',
                'All segments active',
                $segmentEvaluation['status'],
                $segmentEvaluation['message'],
            ),
            self::item(
                'payment_verified',
                'Payment verified / no balance due',
                $paymentPass ? 'pass' : 'fail',
                $paymentPass
                    ? 'Payment is verified or no balance remains due.'
                    : self::paymentBlockMessage($paymentSummary),
            ),
            self::item(
                'passenger_contact',
                'Passenger / contact data complete',
                $passengerContactPass ? 'pass' : 'fail',
                $passengerContactPass
                    ? 'At least one passenger and a contact or customer email are present.'
                    : 'Add passengers and a contact or customer email before ticketing.',
            ),
            self::item(
                'fare_total',
                'Fare / customer total present',
                $fareEvaluation['status'],
                $fareEvaluation['message'],
            ),
            self::item(
                'platform_ticketing_module',
                'Platform ticketing module',
                $ticketingModuleEnabled ? 'pass' : 'blocked',
                $ticketingModuleEnabled
                    ? 'Ticketing module is enabled for this deployment.'
                    : 'Ticketing is disabled for this deployment.',
            ),
            self::item(
                'ticketing_config',
                'Ticketing config',
                $sabreGdsIssueReady || $ticketingConfigEnabled ? 'pass' : 'blocked',
                $sabreGdsIssueReady
                    ? 'Sabre GDS admin ticketing is enabled for this booking.'
                    : ($ticketingConfigEnabled
                        ? 'Live API ticketing is enabled in configuration (unexpected for this phase).'
                        : ($provider === SupplierProvider::Sabre->value
                            ? app(SabreReadinessReasonPresenter::class)->messageForCode('ticketing_disabled')
                            : 'Live API ticketing is disabled by configuration (expected).')),
            ),
            self::item(
                'supplier_ticketing',
                'Supplier ticketing supported',
                $sabreGdsIssueReady ? 'pass' : 'blocked',
                self::supplierTicketingMessage($provider, $liveTicketingSupported, $booking, $sabreGdsReadiness),
            ),
        ];

        [$overallStatus, $overallLabel] = self::resolveOverall(
            $booking,
            $items,
            $provider,
            $hasPnr,
            $itinerarySynced,
            $piaNdcItineraryWarningOnly,
            $segmentEvaluation,
            $paymentPass,
            $passengerContactPass,
            $fareEvaluation,
            $liveTicketingSupported,
            $isSabreGds,
            $pnrCancelledOrReleased,
        );

        return [
            'overall_status' => $overallStatus,
            'overall_label' => $overallLabel,
            'items' => $items,
            'can_attempt_live_ticketing' => false,
        ];
    }

    /**
     * @param  list<array{key: string, label: string, status: string, message: string}>  $items
     * @param  array{status: string, blocking: bool}  $segmentEvaluation
     * @param  array{status: string, blocking: bool}  $fareEvaluation
     * @return array{0: string, 1: string}
     */
    protected static function resolveOverall(
        Booking $booking,
        array $items,
        string $provider,
        bool $hasPnr,
        bool $itinerarySynced,
        bool $piaNdcItineraryWarningOnly,
        array $segmentEvaluation,
        bool $paymentPass,
        bool $passengerContactPass,
        array $fareEvaluation,
        bool $liveTicketingSupported,
        bool $isSabreGds = false,
        bool $pnrCancelledOrReleased = false,
    ): array {
        if ($isSabreGds && $pnrCancelledOrReleased) {
            return [self::OVERALL_MANUAL_REVIEW_WITH_WARNINGS, 'PNR released/cancelled — handle refund/credit manually or close booking'];
        }

        if (! $hasPnr && $provider !== SupplierProvider::Iati->value) {
            return [self::OVERALL_BLOCKED_MISSING_PNR, app(SabreReadinessReasonPresenter::class)->messageForCode('missing_sabre_pnr')];
        }

        if ($provider === SupplierProvider::Iati->value && ! $hasPnr && trim((string) ($booking->supplier_reference ?? '')) === '') {
            return [self::OVERALL_BLOCKED_MISSING_PNR, 'Blocked — IATI order not created yet'];
        }

        if (! $itinerarySynced && $provider !== SupplierProvider::Iati->value && ! $piaNdcItineraryWarningOnly) {
            return [self::OVERALL_BLOCKED_ITINERARY_NOT_SYNCED, 'Blocked — PNR itinerary not synced'];
        }

        if ($segmentEvaluation['blocking'] && $provider !== SupplierProvider::PiaNdc->value) {
            return [self::OVERALL_BLOCKED_SEGMENT_STATUS, 'Blocked — segment status not HK'];
        }

        if (! $paymentPass) {
            return [self::OVERALL_BLOCKED_PAYMENT, 'Blocked — payment not verified'];
        }

        if (! $passengerContactPass) {
            return [self::OVERALL_BLOCKED_PASSENGER_DATA, 'Blocked — passenger or contact data incomplete'];
        }

        if ($fareEvaluation['blocking']) {
            return [self::OVERALL_BLOCKED_MISSING_FARE, 'Blocked — fare / customer total missing'];
        }

        if (! self::providerIsKnownForTicketingChecklist($provider) && $provider !== '') {
            return [self::OVERALL_BLOCKED_SUPPLIER_NOT_SUPPORTED, 'Blocked — supplier ticketing not supported'];
        }

        if ($piaNdcItineraryWarningOnly) {
            return [self::OVERALL_MANUAL_REVIEW_WITH_WARNINGS, 'Manual review — verify itinerary with supplier before ticketing'];
        }

        if ($liveTicketingSupported) {
            return [self::OVERALL_READY_EXCEPT_TICKETING_DISABLED, 'Ready except live ticketing (adapter available but disabled)'];
        }

        return [self::OVERALL_READY_EXCEPT_TICKETING_DISABLED, 'Ready for manual ticketing review — live API ticketing remains disabled.'];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array{status: string, message: string, blocking: bool}
     */
    protected static function evaluateSegmentStatuses(array $segments): array
    {
        if ($segments === []) {
            return [
                'status' => 'fail',
                'message' => 'No PNR itinerary segments to evaluate.',
                'blocking' => true,
            ];
        }

        $bad = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                $bad[] = 'unknown';

                continue;
            }
            $status = strtoupper(trim((string) ($segment['segment_status'] ?? '')));
            if ($status === 'HK') {
                continue;
            }
            if ($status === '' || ! in_array($status, ['HK'], true)) {
                $bad[] = $status !== '' ? $status : 'empty';
            }
        }

        if ($bad === []) {
            return [
                'status' => 'pass',
                'message' => 'All synced segments are HK (confirmed).',
                'blocking' => false,
            ];
        }

        $sample = implode(', ', array_unique(array_slice($bad, 0, 3)));

        return [
            'status' => 'fail',
            'message' => 'Non-active segment status detected: '.$sample.'.',
            'blocking' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{status: string, message: string, blocking: bool}
     */
    protected static function evaluateFareTotal(Booking $booking, array $meta): array
    {
        $breakdown = $booking->fareBreakdown;
        $total = (float) ($breakdown?->total ?? 0);
        if ($total <= 0) {
            $total = (float) ($booking->selected_fare_total ?? $booking->revalidated_fare_total ?? 0);
        }
        if ($total <= 0) {
            $total = (float) ($meta['customer_total'] ?? $meta['supplier_total'] ?? 0);
        }

        if ($total <= 0) {
            return [
                'status' => 'fail',
                'message' => 'No customer total or fare amount is stored on this booking.',
                'blocking' => true,
            ];
        }

        $baseFare = (float) ($breakdown?->base_fare ?? 0);
        $taxes = (float) ($breakdown?->taxes ?? 0);
        $supplierTotal = (float) ($meta['supplier_total'] ?? 0);
        $unreliable = $breakdown !== null
            && BookingItineraryOverviewPresenter::adminStoredFareLineItemsLookUnreliable(
                $baseFare,
                $taxes,
                $supplierTotal,
                $total,
            );

        if ($unreliable || ! isset($meta['passenger_pricing'])) {
            return [
                'status' => 'warning',
                'message' => 'Customer total is present; stored base/tax line items may be snapshot-only — verify before ticketing.',
                'blocking' => false,
            ];
        }

        return [
            'status' => 'pass',
            'message' => 'Customer total is stored on the booking.',
            'blocking' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $paymentSummary
     */
    protected static function paymentVerified(Booking $booking, array $paymentSummary): bool
    {
        $paymentStatus = strtolower(trim((string) ($booking->payment_status ?? 'unpaid')));
        if ($paymentStatus === 'paid') {
            return true;
        }

        if ((bool) ($paymentSummary['show_verified'] ?? false)) {
            return true;
        }

        return (float) ($paymentSummary['balance_due'] ?? 0) <= 0
            && (float) ($paymentSummary['total'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $paymentSummary
     */
    protected static function paymentBlockMessage(array $paymentSummary): string
    {
        $code = (string) ($paymentSummary['status_code'] ?? 'unpaid');

        return match ($code) {
            'proof_under_review', 'submitted' => 'Payment proof is submitted and awaiting verification.',
            'rejected' => 'Latest payment proof was rejected; balance may still be due.',
            'partial' => 'Booking is only partially paid; balance remains due.',
            default => 'Payment is not verified or a balance remains due.',
        };
    }

    protected static function providerSupportsLiveTicketing(string $provider): bool
    {
        return in_array($provider, [
            SupplierProvider::Iati->value,
            SupplierProvider::PiaNdc->value,
        ], true);
    }

    protected static function providerIsKnownForTicketingChecklist(string $provider): bool
    {
        return in_array($provider, [
            SupplierProvider::Sabre->value,
            SupplierProvider::Duffel->value,
            SupplierProvider::Iati->value,
            'pia_ndc',
            'airline_direct',
        ], true);
    }

    protected static function supplierTicketingMessage(
        string $provider,
        bool $liveSupported,
        ?Booking $booking = null,
        ?array $sabreGdsReadiness = null,
    ): string {
        if ($provider === SupplierProvider::Iati->value) {
            return 'IATI confirm/book uses /book or /option/{orderId}/book (no classic ticket endpoint).';
        }

        if ($provider === SupplierProvider::PiaNdc->value) {
            return 'PIA NDC live ticketing uses DoTicketPreview + DoOrderChange (admin-triggered).';
        }

        if ($provider === SupplierProvider::Sabre->value) {
            if (is_array($sabreGdsReadiness)
                && ($sabreGdsReadiness['action_state'] ?? '') === SabreGdsTicketingReadiness::ACTION_ISSUE_TICKET) {
                return (string) ($sabreGdsReadiness['admin_message']
                    ?? 'Unticketed Sabre GDS PNR is ready for Enhanced Air Ticket issuance.');
            }

            if ($booking !== null
                && app(SupplierLifecycleContextResolver::class)->isHandler($booking, SupplierLifecycleContextResolver::HANDLER_SABRE_GDS)) {
                return 'Sabre GDS Enhanced Air Ticket issuance is available when readiness gates pass.';
            }

            return 'Sabre live ticketing adapter is not implemented (expected). Use manual ticketing.';
        }

        if ($liveSupported) {
            return 'Live supplier ticketing could be enabled when configuration allows.';
        }

        return 'Automated supplier ticketing is not available for this provider.';
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    protected static function item(string $key, string $label, string $status, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
        ];
    }
}
