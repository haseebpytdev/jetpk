@props([
    'booking',
    'viewerMode' => 'customer',
    'shell' => 'account',
])

@php
    $isAccount = $shell === 'account';
    $isGuest = $viewerMode === 'guest';
@endphp

<div class="{{ $isAccount ? 'ota-account-card mb-3' : 'card mb-3 border-0 shadow-sm' }}" data-testid="booking-passengers-contact">
    <div class="{{ $isAccount ? 'ota-account-card__head' : 'card-header border-0' }}">
        <h3 class="{{ $isAccount ? 'ota-account-card__title' : 'card-title mb-0' }}">Passengers and contact</h3>
    </div>
    <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}">
        @foreach($booking->passengers->sortBy('passenger_index') as $passenger)
            <div class="ota-booking-detail-passenger mb-3">
                <div class="ota-booking-detail-passenger__name">
                    @if ($isGuest)
                        {{ \App\Support\Travel\TravelDocumentFormatter::maskPersonName($passenger->title, $passenger->first_name, $passenger->last_name) }}
                    @else
                        {{ $passenger->title }} {{ $passenger->first_name }} {{ $passenger->last_name }}
                    @endif
                    <span class="{{ $isAccount ? 'ota-account-badge ota-account-badge--muted' : 'badge bg-secondary-lt' }} text-capitalize">{{ $passenger->passenger_type ?? 'adult' }}</span>
                    @if($passenger->is_lead_passenger)
                        <span class="{{ $isAccount ? 'ota-account-badge ota-account-badge--info' : 'badge bg-info-lt' }}">Lead</span>
                    @endif
                </div>
                @if ($passenger->passport_number)
                    <div class="small text-secondary" data-testid="guest-masked-passport">
                        Passport {{ \App\Support\Travel\TravelDocumentFormatter::maskPassport($passenger->passport_number) }}
                        @if (! $isGuest && $passenger->passport_expiry_date)
                            · expires {{ $passenger->passport_expiry_date->format('M j, Y') }}
                        @endif
                    </div>
                @elseif($passenger->national_id_number)
                    <div class="small text-secondary" data-testid="guest-masked-national-id">
                        National ID {{ \App\Support\Travel\TravelDocumentFormatter::maskPassport($passenger->national_id_number) }}
                    </div>
                @endif
            </div>
        @endforeach
        <div class="ota-booking-detail-contact">
            <div class="ota-booking-detail-contact__row">
                <i class="ti ti-mail" aria-hidden="true"></i>
                <span class="ota-r-text-safe" data-testid="guest-masked-email">
                    {{ $isGuest
                        ? (\App\Support\Travel\TravelDocumentFormatter::maskEmail($booking->contact?->email) ?? 'N/A')
                        : ($booking->contact?->email ?? 'N/A') }}
                </span>
            </div>
            <div class="ota-booking-detail-contact__row">
                <i class="ti ti-phone" aria-hidden="true"></i>
                <span class="ota-r-text-safe" data-testid="guest-masked-phone">
                    {{ $isGuest
                        ? (\App\Support\Travel\TravelDocumentFormatter::maskPhone($booking->contact?->phone) ?? 'N/A')
                        : ($booking->contact?->phone ?? 'N/A') }}
                </span>
            </div>
        </div>
    </div>
</div>
