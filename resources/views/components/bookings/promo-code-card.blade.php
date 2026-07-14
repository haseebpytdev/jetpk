@props([
    'booking',
    'summary',
    'guest' => false,
    'guestToken' => null,
    'applyAction' => null,
    'removeAction' => null,
    'shell' => 'account',
])

@php
    $isAccount = $shell === 'account';
    $currency = $summary['currency'] ?? ($booking->currency ?? 'PKR');
    $canChangePromo = ! ($summary['show_verified'] ?? false)
        && (float) ($summary['balance_due'] ?? 0) > 0
        && ! in_array((string) ($booking->status->value ?? ''), ['cancelled'], true);
    $applyAction = $applyAction ?? ($guest && filled($guestToken)
        ? route('guest.bookings.promo.apply', ['booking' => $booking, 'token' => $guestToken])
        : route('customer.bookings.promo.apply', $booking));
    $removeAction = $removeAction ?? ($guest && filled($guestToken)
        ? route('guest.bookings.promo.remove', ['booking' => $booking, 'token' => $guestToken])
        : route('customer.bookings.promo.remove', $booking));
    $hasPromo = filled($summary['promo_code'] ?? null);
@endphp

@if ($canChangePromo || $hasPromo)
    <div class="{{ $isAccount ? 'ota-account-card mb-3' : 'card mb-3 border-0 shadow-sm' }}" data-testid="booking-promo-card">
        <div class="{{ $isAccount ? 'ota-account-card__head' : 'card-header border-0' }}">
            <h3 class="{{ $isAccount ? 'ota-account-card__title' : 'card-title mb-0' }}">Promo code</h3>
        </div>
        <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}">
            @if (session('promo_status'))
                <div class="{{ $isAccount ? 'ota-account-alert ota-account-alert--success' : 'alert alert-success' }} py-2 small mb-2">{{ session('promo_status') }}</div>
            @endif
            @error('promo_code')
                <div class="{{ $isAccount ? 'ota-account-alert ota-account-alert--danger' : 'alert alert-danger' }} py-2 small mb-2">{{ $message }}</div>
            @enderror

            @if ($hasPromo)
                <div class="small mb-2" data-testid="booking-promo-applied">
                    <div><strong>Code:</strong> {{ e($summary['promo_code']) }}</div>
                    <div><strong>Discount:</strong> {{ number_format((float) ($summary['promo_discount'] ?? 0), 2) }} {{ $currency }}</div>
                    <div><strong>Payable after promo:</strong> {{ number_format((float) ($summary['customer_payable'] ?? 0), 2) }} {{ $currency }}</div>
                </div>
                @if ($canChangePromo)
                    <form method="post" action="{{ $removeAction }}" class="d-inline">
                        @csrf
                        <button type="submit" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--secondary ota-account-btn--sm' : 'btn btn-outline-secondary btn-sm' }}">Remove promo</button>
                    </form>
                @endif
            @elseif ($canChangePromo)
                <form method="post" action="{{ $applyAction }}" class="d-flex flex-wrap gap-2 align-items-end" data-testid="booking-promo-form">
                    @csrf
                    <div class="flex-grow-1" style="min-width: 12rem;">
                        <label class="form-label mb-1">Enter promo code</label>
                        <input type="text" name="code" class="form-control text-uppercase" maxlength="64" pattern="[A-Za-z0-9_-]+" required autocomplete="off" placeholder="e.g. SAVE10">
                    </div>
                    <button type="submit" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--primary' : 'btn btn-primary' }}">Apply</button>
                </form>
            @endif
        </div>
    </div>
@endif
