<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiRetrieveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

trait HandlesIatiBookingSync
{
    public function syncIatiBooking(Booking $booking, IatiRetrieveService $retrieveService): RedirectResponse
    {
        Gate::authorize('createSupplierBooking', $booking);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::Iati->value) {
            return back()->withErrors(['iati_sync' => 'This booking is not an IATI supplier booking.']);
        }

        $lock = Cache::lock('ota:iati-booking-sync:'.$booking->id, 120);
        if (! $lock->get()) {
            return back()->withErrors(['iati_sync' => 'IATI sync is already in progress.']);
        }

        try {
            $connection = SupplierConnection::query()->find((int) ($meta['supplier_connection_id'] ?? 0));
            if ($connection === null) {
                return back()->withErrors(['iati_sync' => 'IATI supplier connection not found.']);
            }

            $retrieveService->syncBooking($booking, $connection, request()->user());
        } catch (\Throwable $e) {
            return back()->withErrors([
                'iati_sync' => 'Could not sync IATI booking. '.Str::limit($e->getMessage(), 120, ''),
            ]);
        } finally {
            $lock->release();
        }

        return back()->with('status', 'IATI booking synced successfully.');
    }
}
