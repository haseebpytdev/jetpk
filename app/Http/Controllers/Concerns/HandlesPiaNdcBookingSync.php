<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcRetrieveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

trait HandlesPiaNdcBookingSync
{
    public function syncPiaNdcBooking(Booking $booking, PiaNdcRetrieveService $retrieveService): RedirectResponse
    {
        Gate::authorize('createSupplierBooking', $booking);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return back()->withErrors(['pia_ndc_sync' => 'This booking is not a PIA NDC supplier booking.']);
        }

        $lock = Cache::lock('ota:pia-ndc-booking-sync:'.$booking->id, 120);
        if (! $lock->get()) {
            return back()->withErrors(['pia_ndc_sync' => 'PIA NDC sync is already in progress.']);
        }

        try {
            $connection = SupplierConnection::query()->find((int) ($meta['supplier_connection_id'] ?? 0));
            if ($connection === null) {
                $connection = $booking->latestSupplierBooking?->supplierConnection;
            }
            if ($connection === null) {
                return back()->withErrors(['pia_ndc_sync' => 'PIA NDC supplier connection not found.']);
            }

            $result = $retrieveService->retrieveAndSync($booking, $connection);
            if (($result['synced'] ?? false) !== true) {
                return back()->withErrors([
                    'pia_ndc_sync' => 'Could not sync PIA NDC booking. '.Str::limit((string) ($result['reason'] ?? 'unknown'), 120, ''),
                ]);
            }
        } catch (\Throwable $e) {
            return back()->withErrors([
                'pia_ndc_sync' => 'Could not sync PIA NDC booking. '.Str::limit($e->getMessage(), 120, ''),
            ]);
        } finally {
            $lock->release();
        }

        return back()->with('status', 'PIA NDC booking synced successfully.');
    }
}
