@props([
    'timeline' => [],
    'shell' => 'account',
])

@php $isAccount = $shell === 'account'; @endphp

<div class="{{ $isAccount ? 'ota-account-card mb-3' : 'card mb-3 border-0 shadow-sm' }}">
    <div class="{{ $isAccount ? 'ota-account-card__head' : 'card-header border-0' }}">
        <h3 class="{{ $isAccount ? 'ota-account-card__title' : 'card-title mb-0' }}">Booking progress</h3>
    </div>
    <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}" data-testid="customer-booking-timeline">
        <ol class="ota-booking-detail-timeline list-unstyled mb-0">
            @foreach ($timeline as $step)
                @php
                    $state = $step['state'] ?? 'pending';
                    $icon = match ($state) {
                        'completed' => 'ti-check',
                        'active' => 'ti-clock',
                        'warning' => 'ti-alert-triangle',
                        default => 'ti-circle',
                    };
                @endphp
                <li class="ota-booking-detail-timeline__item ota-booking-detail-timeline__item--{{ $state }}">
                    <span class="ota-booking-detail-timeline__marker" aria-hidden="true">
                        <i class="ti {{ $icon }}"></i>
                    </span>
                    <div class="ota-booking-detail-timeline__content">
                        <div class="ota-booking-detail-timeline__label">{{ $step['label'] }}</div>
                        @if (! empty($step['detail']))
                            <div class="ota-booking-detail-timeline__detail">{{ $step['detail'] }}</div>
                        @endif
                        @if (! empty($step['at']))
                            <div class="ota-booking-detail-timeline__time">
                                <x-time.local :value="$step['at']" context="public" :show-utc="false" />
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</div>
