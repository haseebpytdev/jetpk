<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\OtaNotificationEvent;
use App\Models\Booking;
use App\Services\Communication\OtaNotificationService;
use App\Services\Suppliers\Sabre\SabrePnrItinerarySyncService;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Sabre\SabreReadinessReasonPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait HandlesSabrePnrItinerarySync
{
    public function syncPnrItinerary(
        Request $request,
        Booking $booking,
        SabrePnrItinerarySyncService $syncService,
    ): RedirectResponse|JsonResponse {
        Gate::authorize('createSupplierBooking', $booking);

        $lock = Cache::lock('ota:pnr-itinerary-sync:'.$booking->id, 120);
        if (! $lock->get()) {
            $message = 'PNR itinerary sync is already in progress.';
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($message, 409, 'pnr_sync_in_progress');
            }

            return back()->withErrors([
                'pnr_itinerary_sync' => $message,
            ]);
        }

        $blockReason = app(AdminBookingSupplierActions::class)->assertSyncPnrItineraryPostAllowed($booking);
        if ($blockReason !== null) {
            $lock->release();

            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($blockReason, 409, 'pnr_sync_blocked');
            }

            return back()->withErrors([
                'pnr_itinerary_sync' => $blockReason,
            ]);
        }

        try {
            $result = $syncService->sync($booking, false);
        } catch (\Throwable $e) {
            Log::warning('pnr_itinerary_sync_failed', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);

            $message = 'Could not sync PNR itinerary from Sabre. Please try again later or verify manually.';
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($message, 409, 'pnr_sync_failed');
            }

            return back()->withErrors([
                'pnr_itinerary_sync' => $message,
            ]);
        } finally {
            $lock->release();
        }

        $this->notifyPnrItinerarySyncResult($booking, $result);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->jsonFromPnrItinerarySyncResult($result);
        }

        return $this->redirectFromPnrItinerarySyncResult($result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function notifyPnrItinerarySyncResult(Booking $booking, array $result): void
    {
        $booking->loadMissing('agency');
        $agency = $booking->agency;
        if ($agency === null) {
            return;
        }

        $synced = ($result['synced'] ?? false) === true;
        $event = $synced
            ? OtaNotificationEvent::PnrItinerarySynced
            : OtaNotificationEvent::PnrItinerarySyncFailed;

        $payload = [
            'booking_reference' => $booking->reference_code,
            'pnr' => $booking->pnr,
            'reason_code' => (string) ($result['reason_code'] ?? ''),
        ];

        if (! $synced) {
            unset($payload['pnr']);
        }

        app(OtaNotificationService::class)->send(
            agency: $agency,
            eventKey: $event->value,
            booking: $booking,
            payload: $payload,
            fallbackSubject: $synced
                ? 'PNR itinerary synced'
                : 'PNR itinerary sync needs review',
            fallbackBody: $synced
                ? 'PNR itinerary was synced for booking '.$booking->reference_code.'.'
                : 'PNR itinerary sync requires manual review for booking '.$booking->reference_code.'.',
            templateVariables: ['booking_reference' => (string) $booking->reference_code],
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function jsonFromPnrItinerarySyncResult(array $result): JsonResponse
    {
        if (($result['synced'] ?? false) === true) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'PNR itinerary retrieve/sync completed successfully.',
                'synced' => true,
            ]);
        }

        $message = $this->messageFromPnrItinerarySyncResult($result);

        return $this->backOfficeJsonError($message, 409, 'pnr_sync_incomplete');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function redirectFromPnrItinerarySyncResult(array $result): RedirectResponse
    {
        if (($result['synced'] ?? false) === true) {
            return back()->with('status', 'PNR itinerary retrieve/sync completed successfully.');
        }

        $reason = (string) ($result['reason_code'] ?? '');
        if ($reason === 'partial_resource_unavailable') {
            $hasLocator = (bool) data_get($result, 'airline_locator_observability.airline_locator_present', false);

            return back()->with(
                'status',
                $hasLocator
                    ? 'PNR retrieve returned partial verification: carrier locator detected. Full itinerary was not synced — verify with airline/carrier before ticketing.'
                    : 'PNR retrieve returned partial verification data. Full itinerary was not synced — verify manually before ticketing.',
            );
        }

        return back()->withErrors(['pnr_itinerary_sync' => $this->messageFromPnrItinerarySyncResult($result)]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function messageFromPnrItinerarySyncResult(array $result): string
    {
        $presenter = app(SabreReadinessReasonPresenter::class);
        $reason = (string) ($result['reason_code'] ?? '');

        if ($reason === 'partial_resource_unavailable') {
            $hasLocator = (bool) data_get($result, 'airline_locator_observability.airline_locator_present', false);

            return $hasLocator
                ? 'PNR retrieve returned partial verification: carrier locator detected. Full itinerary was not synced — verify with airline/carrier before ticketing.'
                : 'PNR retrieve returned partial verification data. Full itinerary was not synced — verify manually before ticketing.';
        }

        return match ($reason) {
            'blocked_resource_unavailable' => 'Sabre returned resource unavailable; final itinerary could not be retrieved/synced. Verify manually.',
            'blocked_segment_status' => 'PNR itinerary contains non-active segment status; verify manually.',
            'unmappable' => 'Unable to map Sabre PNR itinerary safely.',
            default => match ((string) ($result['error'] ?? '')) {
                'booking_missing_pnr' => $presenter->messageForCode('missing_sabre_pnr'),
                default => 'Could not retrieve/sync PNR itinerary from Sabre. Please try again later or verify manually.',
            },
        };
    }
}
