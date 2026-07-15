@props(['shell' => 'account'])

@php $isAccount = $shell === 'account'; @endphp

<div class="{{ $isAccount ? 'ota-account-card mb-0' : 'card border-0 shadow-sm mb-0' }}" data-testid="booking-help-card">
    <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}">
        <div class="ota-booking-detail-help">
            <div>
                <div class="ota-booking-detail-help__title">Need help?</div>
                <p class="ota-booking-detail-help__text mb-0">Our support team can help with payments, documents, and itinerary questions.</p>
            </div>
            <a href="{{ route('support') }}" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--secondary' : 'btn btn-outline-primary' }}">
                <i class="ti ti-headset me-1" aria-hidden="true"></i> Contact support
            </a>
        </div>
    </div>
</div>
