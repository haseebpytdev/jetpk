@props([
    'loginUrl' => '',
    'shell' => 'account',
])

@php $isAccount = $shell === 'account'; @endphp

<div class="{{ $isAccount ? 'ota-account-alert ota-account-alert--info mb-3' : 'alert alert-info mb-3' }}" data-testid="guest-linked-account-cta">
    <p class="mb-2">This booking may be linked to an account. Login to manage full booking details.</p>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ $loginUrl }}" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--primary ota-account-btn--sm' : 'btn btn-primary btn-sm' }}">Login</a>
        <a href="{{ route('booking.lookup') }}" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--secondary ota-account-btn--sm' : 'btn btn-outline-secondary btn-sm' }}">Lookup another booking</a>
    </div>
</div>
