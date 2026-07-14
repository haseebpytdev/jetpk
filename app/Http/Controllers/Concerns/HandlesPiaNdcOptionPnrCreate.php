<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcOptionPnrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

trait HandlesPiaNdcOptionPnrCreate
{
    public function createPiaNdcOptionPnr(
        Request $request,
        Booking $booking,
        PiaNdcOptionPnrService $createService,
    ): RedirectResponse {
        Gate::authorize('createPiaNdcOptionPnr', $booking);

        $validated = $request->validate([
            'confirm_phrase' => ['required', 'string'],
            'operator_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return back()->withErrors(['pia_ndc_create' => 'This booking is not a PIA NDC supplier booking.']);
        }

        if (! $createService->canCreateForBooking($booking)) {
            $eligibility = $createService->evaluateCreateEligibility($booking);

            return back()->withErrors(['pia_ndc_create' => $eligibility['reason'] ?: 'PIA NDC option PNR creation is not available for this booking.']);
        }

        $lock = Cache::lock('ota:pia-ndc-create-option-pnr:'.$booking->id, 120);
        if (! $lock->get()) {
            return back()->withErrors(['pia_ndc_create' => 'PIA NDC option PNR creation is already in progress for this booking.']);
        }

        try {
            $result = $createService->createOptionPnrForBooking(
                booking: $booking,
                actor: $request->user(),
                confirmPhrase: trim((string) $validated['confirm_phrase']),
                reason: trim((string) $validated['operator_reason']),
            );

            if (($result['success'] ?? false) !== true) {
                return back()->withErrors(['pia_ndc_create' => 'PIA NDC option PNR creation did not succeed.']);
            }
        } catch (PiaNdcValidationException|PiaNdcException $exception) {
            return back()->withErrors(['pia_ndc_create' => $exception->safeMessage]);
        } catch (\Throwable) {
            return back()->withErrors(['pia_ndc_create' => 'PIA NDC option PNR creation failed.']);
        } finally {
            $lock->release();
        }

        return back()->with('status', 'PIA NDC option PNR created successfully.');
    }
}
