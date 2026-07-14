@php
    $p = $presentation;
    $currency = (string) ($p['fare_change_currency'] ?? 'PKR');
@endphp
@if (\App\Support\Bookings\IatiReservationLifecycleService::appliesTo($booking))
    <div class="alert {{ ($p['fare_change_requires_acceptance'] ?? false) ? 'alert-warning' : (($p['show_supplier_hold_active'] ?? false) ? 'alert-info' : 'alert-secondary') }} ota-iati-reservation-status" role="status">
        <div class="fw-semibold">{{ $p['customer_headline'] ?? 'Reservation status' }}</div>
        <div class="small mt-1">{{ $p['customer_detail'] ?? '' }}</div>
        @if (!empty($p['local_checkout_expires_at']) && ($p['show_not_reserved_yet'] ?? false))
            <div class="small mt-2 text-muted">
                {{ __('Complete payment by :time', ['time' => \Illuminate\Support\Carbon::parse($p['local_checkout_expires_at'])->timezone(config('app.timezone'))->format('M j, g:i A')]) }}
            </div>
        @endif
        @if (!empty($p['supplier_hold_expires_at']) && ($p['show_supplier_hold_active'] ?? false))
            <div class="small mt-2 text-muted">
                {{ __('Supplier hold expires :time', ['time' => \Illuminate\Support\Carbon::parse($p['supplier_hold_expires_at'])->timezone(config('app.timezone'))->format('M j, g:i A')]) }}
            </div>
        @endif
        @if ($p['fare_change_requires_acceptance'] ?? false)
            <div class="small mt-2">
                {{ __('Fare changed from :currency :old to :currency :new.', [
                    'currency' => $currency,
                    'old' => number_format((float) ($p['fare_change_old_total'] ?? 0), 0),
                    'new' => number_format((float) ($p['fare_change_new_total'] ?? 0), 0),
                ]) }}
                {{ __('Accept the new fare to continue.') }}
            </div>
        @endif
    </div>
@endif
