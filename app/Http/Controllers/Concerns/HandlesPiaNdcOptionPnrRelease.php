<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingStatusRefreshService;
use App\Services\Suppliers\PiaNdc\PiaNdcReleaseOptionPnrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

trait HandlesPiaNdcOptionPnrRelease
{
    public function releasePiaNdcOptionPnr(Request $request, Booking $booking, PiaNdcReleaseOptionPnrService $releaseService, PiaNdcBookingStatusRefreshService $refreshService): RedirectResponse
    {
        Gate::authorize('releasePiaNdcOptionPnr', $booking);

        $validated = $request->validate([
            'admin_confirm_reviewed' => ['accepted'],
            'operator_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return back()->withErrors(['pia_ndc_release' => 'This booking is not a PIA NDC supplier booking.']);
        }

        if (! $releaseService->canReleaseBooking($booking)) {
            return back()->withErrors(['pia_ndc_release' => 'Release is not available for this PIA NDC booking.']);
        }

        $lock = Cache::lock('ota:pia-ndc-release-option-pnr:'.$booking->id, 120);
        if (! $lock->get()) {
            return back()->withErrors(['pia_ndc_release' => 'PIA NDC release is already in progress for this booking.']);
        }

        try {
            $result = $releaseService->runReleaseForBooking(
                booking: $booking,
                actor: $request->user(),
                confirmPhrase: PiaNdcReleaseOptionPnrService::RELEASE_CONFIRM_PHRASE,
                reason: trim((string) $validated['operator_reason']),
            );
            if (($result['success'] ?? false) !== true) {
                return back()->withErrors(['pia_ndc_release' => 'PIA NDC option PNR release did not succeed.']);
            }
        } catch (PiaNdcValidationException $exception) {
            return back()->withErrors(['pia_ndc_release' => $exception->safeMessage]);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'pia_ndc_release' => 'PIA NDC option PNR release failed. '.Str::limit($e->getMessage(), 120, ''),
            ]);
        } finally {
            $lock->release();
        }

        return back()->with('status', 'PIA NDC option PNR released successfully.');
    }
}
