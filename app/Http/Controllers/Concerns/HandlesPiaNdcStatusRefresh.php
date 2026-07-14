<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingStatusRefreshService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

trait HandlesPiaNdcStatusRefresh
{
    public function refreshPiaNdcStatus(Booking $booking, PiaNdcBookingStatusRefreshService $refreshService): RedirectResponse
    {
        Gate::authorize('createSupplierBooking', $booking);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return back()->withErrors(['pia_ndc_status_refresh' => 'This booking is not a PIA NDC supplier booking.']);
        }

        if (! $refreshService->canRefreshBooking($booking)) {
            return back()->withErrors(['pia_ndc_status_refresh' => 'PIA NDC status refresh is not available for this booking.']);
        }

        $lock = Cache::lock('ota:pia-ndc-status-refresh:'.$booking->id, 120);
        if (! $lock->get()) {
            return back()->withErrors(['pia_ndc_status_refresh' => 'PIA NDC status refresh is already in progress for this booking.']);
        }

        try {
            $result = $refreshService->refreshBooking(
                booking: $booking,
                actor: auth()->user(),
                source: 'admin_manual',
            );
            if (($result['success'] ?? false) !== true) {
                return back()->withErrors(['pia_ndc_status_refresh' => 'PIA NDC status refresh did not succeed.']);
            }
        } catch (PiaNdcValidationException $exception) {
            return back()->withErrors(['pia_ndc_status_refresh' => $exception->safeMessage]);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'pia_ndc_status_refresh' => 'PIA NDC status refresh failed. '.Str::limit($e->getMessage(), 120, ''),
            ]);
        } finally {
            $lock->release();
        }

        return back()->with('status', 'PIA NDC supplier status refreshed successfully.');
    }
}
