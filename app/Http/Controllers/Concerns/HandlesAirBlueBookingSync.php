<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

trait HandlesAirBlueBookingSync
{
    public function syncAirBlueBooking(Booking $booking, AirBlueRetrieveService $retrieveService): RedirectResponse
    {
        Gate::authorize('createSupplierBooking', $booking);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::Airblue->value) {
            return back()->withErrors(['airblue_sync' => 'This booking is not an AirBlue supplier booking.']);
        }

        $lock = Cache::lock('ota:airblue-booking-sync:'.$booking->id, 120);
        if (! $lock->get()) {
            return back()->withErrors(['airblue_sync' => 'AirBlue sync is already in progress.']);
        }

        try {
            $connection = SupplierConnection::query()->find((int) ($meta['supplier_connection_id'] ?? 0));
            if ($connection === null) {
                $connection = $booking->latestSupplierBooking?->supplierConnection;
            }
            if ($connection === null) {
                return back()->withErrors(['airblue_sync' => 'AirBlue supplier connection not found.']);
            }

            $result = $retrieveService->retrieveAndSync($booking, $connection);
            if (($result['synced'] ?? false) !== true) {
                return back()->withErrors([
                    'airblue_sync' => 'Could not sync AirBlue booking. '.Str::limit((string) ($result['reason'] ?? 'unknown'), 120, ''),
                ]);
            }
        } catch (\Throwable $e) {
            return back()->withErrors([
                'airblue_sync' => 'Could not sync AirBlue booking. '.Str::limit($e->getMessage(), 120, ''),
            ]);
        } finally {
            $lock->release();
        }

        return back()->with('status', 'AirBlue booking synced successfully.');
    }
}
