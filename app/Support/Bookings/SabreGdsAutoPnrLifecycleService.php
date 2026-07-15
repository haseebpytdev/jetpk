<?php

namespace App\Support\Bookings;

use App\Enums\SabreGdsAutoPnrLifecycleStatus;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabrePnrItinerarySyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sabre GDS auto-PNR lifecycle: refresh → PNR create → getBooking sync → ticketing pending.
 * GDS only — excludes Sabre NDC.
 */
final class SabreGdsAutoPnrLifecycleService
{
    public const META_KEY = 'sabre_gds_auto_pnr_lifecycle';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
    ) {}

    public static function appliesTo(Booking $booking): bool
    {
        return app(SupplierLifecycleContextResolver::class)->isHandler(
            $booking,
            SupplierLifecycleContextResolver::HANDLER_SABRE_GDS,
        );
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    public function reconcileObsoleteIatiWaiverFlags(array $decision, ?Booking $booking = null): array
    {
        if (! $this->hasRefreshOrRevalidationSuccess($booking, $decision)) {
            return $decision;
        }

        unset(
            $decision['iati_like_expects_revalidation_waiver_or_refresh'],
            $decision['iati_style_expects_revalidation_waiver_or_refresh'],
        );

        if (($decision['revalidation_skip_reason'] ?? '') === 'iati_cpnr_revalidation_waived') {
            $decision['revalidation_skip_reason'] = 'iati_cpnr_refresh_satisfied';
        }

        if (($decision['iati_freshness_ready_reason'] ?? '') === 'iati_cpnr_context_ready_without_revalidation_linkage') {
            $decision['iati_freshness_ready_reason'] = 'offer_refresh_satisfied_without_bfm_revalidation';
        }

        $decision['refresh_satisfied_revalidation_waiver'] = true;

        return $decision;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    public function reconcileCheckoutOutcomeRevalidationFlags(Booking $booking, array $outcome): array
    {
        if (! self::appliesTo($booking)) {
            return $outcome;
        }

        if (! $this->hasRefreshOrRevalidationSuccess($booking)) {
            return $outcome;
        }

        if (($outcome['revalidation_skipped_by_config'] ?? false) === true) {
            $outcome['revalidation_skipped_by_config'] = false;
            $outcome['prebooking_revalidation_skipped_reason'] = 'offer_refresh_satisfied';
        }

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $result  Successful live createBooking result
     */
    public function persistPnrCreateArtifacts(Booking $booking, array $result): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $pnr = trim((string) ($result['pnr'] ?? $result['record_locator'] ?? ''));
        if ($pnr === '' && trim((string) ($booking->pnr ?? '')) === '') {
            return;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $block = $this->block($meta);

        $block['pnr_created'] = true;
        $block['pnr_created_at'] = $block['pnr_created_at'] ?? now()->toIso8601String();
        $block['ticketing_pending'] = true;
        $block['ticketing_pending_at'] = $block['ticketing_pending_at'] ?? now()->toIso8601String();

        $segmentStatus = $this->segmentStatusFromCreateResult($result);
        if ($segmentStatus !== null) {
            $block['airline_segment_status'] = $segmentStatus;
        }

        $airlineLocator = trim((string) ($result['airline_locator'] ?? data_get($result, 'booking_diagnostics.airline_locator_value', '')));
        if ($airlineLocator !== '') {
            $block['airline_locator'] = strtoupper(substr($airlineLocator, 0, 32));
        }

        $expiry = $this->certificationSupport->extractExpiryFromCreateResult($result);
        if ($expiry['iso'] !== null) {
            $block['supplier_pnr_expires_at'] = $expiry['iso'];
            $block['supplier_pnr_expiry_source'] = $expiry['source'];
        }

        $meta[self::META_KEY] = $block;
        $booking->forceFill(['meta' => $meta])->save();

        $this->certificationSupport->persistExpiryFromCreateResult($booking->fresh(), $result);
    }

    public function recordOfferRefreshed(Booking $booking): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $status = trim((string) ($meta['offer_refresh_status'] ?? ''));
        if (! in_array($status, ['refreshed', 'success'], true)
            && $booking->fare_revalidated_at === null
            && strtolower(trim((string) ($meta['revalidation_status'] ?? ''))) !== 'success') {
            return;
        }

        $block = $this->block($meta);
        $block[SabreGdsAutoPnrLifecycleStatus::OfferRefreshed->value] = true;
        $block['offer_refreshed_at'] = $block['offer_refreshed_at'] ?? now()->toIso8601String();
        $meta[self::META_KEY] = $block;
        $booking->forceFill(['meta' => $meta])->save();
    }

    public function recordItinerarySynced(Booking $booking): void
    {
        if (! self::appliesTo($booking)) {
            return;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $sync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $synced = ($sync['status'] ?? '') === 'synced';
        if (! $synced) {
            return;
        }

        $block = $this->block($meta);
        $block[SabreGdsAutoPnrLifecycleStatus::ItinerarySynced->value] = true;
        $block['itinerary_synced_at'] = (string) ($sync['synced_at'] ?? now()->toIso8601String());

        $airlineLocator = trim((string) ($sync['airline_locator_value'] ?? ''));
        if ($airlineLocator !== '') {
            $block['airline_locator'] = strtoupper(substr($airlineLocator, 0, 32));
        }

        $meta[self::META_KEY] = $block;
        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * Safe one-shot getBooking sync after any successful PNR persistence (public or staff).
     */
    public function maybeAutoSyncPnrItineraryAfterPnrCreate(Booking $booking): void
    {
        try {
            $booking->refresh();
            if (! self::appliesTo($booking)) {
                return;
            }

            $pnr = trim((string) ($booking->pnr ?? ''));
            if ($pnr === '') {
                return;
            }

            $meta = is_array($booking->meta) ? $booking->meta : [];
            if (($meta['scenario_runner'] ?? false) === true
                || (string) ($meta['origin_channel'] ?? '') === 'scenario_runner') {
                Log::info('sabre.gds_auto_pnr.itinerary_sync_skipped', [
                    'booking_id' => $booking->id,
                    'reason_code' => 'scenario_runner_explicit_retrieve_only',
                ]);

                return;
            }

            $existingSync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
            if ($existingSync !== []
                && (isset($existingSync['status']) || isset($existingSync['attempted_at']) || isset($existingSync['synced_at']))) {
                Log::info('sabre.gds_auto_pnr.itinerary_sync_skipped', [
                    'booking_id' => $booking->id,
                    'reason_code' => 'pnr_itinerary_sync_already_present',
                ]);

                return;
            }

            $lock = Cache::lock('ota:pnr-itinerary-sync:'.$booking->id, 120);
            if (! $lock->get()) {
                Log::info('sabre.gds_auto_pnr.itinerary_sync_skipped', [
                    'booking_id' => $booking->id,
                    'reason_code' => 'sync_lock_busy',
                ]);

                return;
            }

            try {
                $syncResult = app(SabrePnrItinerarySyncService::class)->sync($booking, false);
            } finally {
                $lock->release();
            }

            $booking->refresh();
            $this->recordItinerarySynced($booking);

            Log::info('sabre.gds_auto_pnr.itinerary_sync_completed', [
                'booking_id' => $booking->id,
                'synced' => ($syncResult['synced'] ?? false) === true,
                'reason_code' => (string) ($syncResult['reason_code'] ?? ''),
                'partial_sync' => ($syncResult['partial_sync'] ?? false) === true,
            ]);
        } catch (Throwable $e) {
            Log::warning('sabre.gds_auto_pnr.itinerary_sync_failed', [
                'booking_id' => $booking->id,
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *     applies: bool,
     *     offer_refreshed: bool,
     *     pnr_created: bool,
     *     itinerary_synced: bool,
     *     ticketing_pending: bool,
     *     airline_locator: ?string,
     *     airline_segment_status: ?string,
     *     supplier_pnr_expires_at: ?string,
     *     rows: list<array{key: string, label: string, value: string, reached: bool}>
     * }
     */
    public function resolveForAdmin(Booking $booking): array
    {
        if (! self::appliesTo($booking)) {
            return [
                'applies' => false,
                'offer_refreshed' => false,
                'pnr_created' => false,
                'itinerary_synced' => false,
                'ticketing_pending' => false,
                'airline_locator' => null,
                'airline_segment_status' => null,
                'supplier_pnr_expires_at' => null,
                'rows' => [],
            ];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $block = $this->block($meta);
        $sync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $pnrCancelledOrReleased = app(SabreGdsPnrCancellationStateResolver::class)->isPnrCancelledOrReleased($booking, $meta);

        $offerRefreshed = ($block[SabreGdsAutoPnrLifecycleStatus::OfferRefreshed->value] ?? false) === true
            || in_array(trim((string) ($meta['offer_refresh_status'] ?? '')), ['refreshed', 'success'], true)
            || $booking->fare_revalidated_at !== null;
        $pnrCreated = ($block[SabreGdsAutoPnrLifecycleStatus::PnrCreated->value] ?? false) === true
            || trim((string) ($booking->pnr ?? '')) !== '';
        $itinerarySynced = ($block[SabreGdsAutoPnrLifecycleStatus::ItinerarySynced->value] ?? false) === true
            || ($sync['status'] ?? '') === 'synced'
            || ($sync['synced'] ?? false) === true
            || app(SabreGdsPnrItinerarySyncResolver::class)->isSynced($booking, $meta);
        $ticketingPending = ! $pnrCancelledOrReleased
            && (($block[SabreGdsAutoPnrLifecycleStatus::TicketingPending->value] ?? false) === true
            || ($pnrCreated && (string) ($booking->supplier_booking_status ?? '') === 'pending_payment_or_ticketing'));

        $airlineLocator = trim((string) ($block['airline_locator'] ?? $sync['airline_locator_value'] ?? ''));
        $segmentStatus = trim((string) ($block['airline_segment_status'] ?? ''));
        $expiresAt = trim((string) ($block['supplier_pnr_expires_at'] ?? $meta[SabrePnrCertificationSupport::META_EXPIRES_AT] ?? ''));

        $rows = [];
        foreach ([
            SabreGdsAutoPnrLifecycleStatus::OfferRefreshed,
            SabreGdsAutoPnrLifecycleStatus::PnrCreated,
            SabreGdsAutoPnrLifecycleStatus::ItinerarySynced,
            SabreGdsAutoPnrLifecycleStatus::TicketingPending,
        ] as $status) {
            $reached = match ($status) {
                SabreGdsAutoPnrLifecycleStatus::OfferRefreshed => $offerRefreshed,
                SabreGdsAutoPnrLifecycleStatus::PnrCreated => $pnrCreated,
                SabreGdsAutoPnrLifecycleStatus::ItinerarySynced => $itinerarySynced,
                SabreGdsAutoPnrLifecycleStatus::TicketingPending => $ticketingPending,
            };
            $rows[] = [
                'key' => $status->value,
                'label' => $status->label(),
                'value' => $reached ? 'Yes' : 'No',
                'reached' => $reached,
            ];
        }

        return [
            'applies' => true,
            'offer_refreshed' => $offerRefreshed,
            'pnr_created' => $pnrCreated,
            'itinerary_synced' => $itinerarySynced,
            'ticketing_pending' => $ticketingPending,
            'airline_locator' => $airlineLocator !== '' ? $airlineLocator : null,
            'airline_segment_status' => $segmentStatus !== '' ? $segmentStatus : null,
            'supplier_pnr_expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function block(array $meta): array
    {
        $existing = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        return $existing;
    }

    /**
     * @param  array<string, mixed>|null  $decision
     */
    protected function hasRefreshOrRevalidationSuccess(?Booking $booking, ?array $decision = null): bool
    {
        if ($booking !== null && BookingSupplierConfirmationNoticeResolver::sabreHasRevalidationSuccessIndicators($booking)) {
            return true;
        }

        if ($decision !== null) {
            $refreshStatus = trim((string) ($decision['refresh_status'] ?? ''));
            $refreshResult = trim((string) ($decision['refresh_result'] ?? ''));
            if (in_array($refreshStatus, ['refreshed', 'success'], true)
                || in_array($refreshResult, ['ok', 'refresh_offer_before_pnr', 'refresh_not_available_allowed_by_config'], true)) {
                return true;
            }

            if (($decision['iati_context_ready_for_booking_payload'] ?? false) === true
                && (($decision['refresh_attempted'] ?? false) === true || ($decision['refresh_required'] ?? false) !== true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function segmentStatusFromCreateResult(array $result): ?string
    {
        foreach ([
            $result['airline_segment_status'] ?? null,
            $result['segment_status'] ?? null,
            data_get($result, 'booking_diagnostics.airline_segment_status'),
        ] as $candidate) {
            $status = strtoupper(trim((string) $candidate));
            if ($status !== '') {
                return $status;
            }
        }

        return null;
    }
}
