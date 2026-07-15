@props(['steps', 'variant' => 'dashboard'])

@if ($variant === 'mobile')
    @php
        $mobileCard = ($audience ?? '') === 'agent' ? 'ota-mobile-agent' : 'ota-mobile-customer';
        $mobileTimeline = 'ota-mobile-customer';
    @endphp
    <section class="{{ $mobileCard }}__card" data-testid="support-ticket-timeline">
        <h2 class="{{ $mobileCard }}__card-title">Ticket progress</h2>
        <ul class="{{ $mobileTimeline }}__timeline">
            @foreach ($steps as $step)
                @php
                    $state = $step['state'] ?? 'pending';
                    $tone = match ($state) {
                        'completed' => 'positive',
                        'active' => 'pending',
                        'warning' => 'cancelled',
                        default => 'muted',
                    };
                @endphp
                <li class="{{ $mobileTimeline }}__timeline-item" data-timeline-step="{{ $step['key'] ?? '' }}">
                    <span class="{{ $mobileTimeline }}__pill {{ $mobileTimeline }}__pill--{{ $tone }}">{{ ucfirst($state) }}</span>
                    <div>
                        <p class="{{ $mobileTimeline }}__timeline-label">{{ $step['label'] }}</p>
                        @if (! empty($step['detail']))
                            <p class="{{ $mobileTimeline }}__note">{{ $step['detail'] }}</p>
                        @endif
                        @if (! empty($step['at']))
                            <p class="{{ $mobileTimeline }}__note">{{ $step['at'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@elseif ($variant === 'account')
    <div class="ota-account-card" data-testid="support-ticket-timeline">
        <div class="ota-account-card__head">
            <h3 class="ota-account-card__title">Ticket progress</h3>
        </div>
        <div class="ota-account-card__body">
            <ul class="list-unstyled mb-0">
                @foreach ($steps as $step)
                    @php
                        $icon = match ($step['state'] ?? 'pending') {
                            'completed' => 'ti-check text-success',
                            'active' => 'ti-clock text-primary',
                            'warning' => 'ti-alert-triangle text-warning',
                            default => 'ti-circle text-secondary',
                        };
                    @endphp
                    <li class="d-flex gap-3 mb-3" data-timeline-step="{{ $step['key'] ?? '' }}">
                        <span class="mt-1"><i class="ti {{ $icon }}" aria-hidden="true"></i></span>
                        <div>
                            <div class="fw-semibold">{{ $step['label'] }}</div>
                            @if (! empty($step['detail']))
                                <div class="small text-secondary">{{ $step['detail'] }}</div>
                            @endif
                            @if (! empty($step['at']))
                                <div class="small text-secondary">{{ $step['at'] }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@else
    <div class="card border-0 shadow-sm mb-3" data-testid="support-ticket-timeline">
        <div class="card-header border-0">
            <h3 class="card-title mb-0">Ticket progress</h3>
        </div>
        <div class="card-body pt-0">
            <ul class="list-unstyled mb-0">
                @foreach ($steps as $step)
                    @php
                        $icon = match ($step['state'] ?? 'pending') {
                            'completed' => 'ti-check text-success',
                            'active' => 'ti-clock text-primary',
                            'warning' => 'ti-alert-triangle text-warning',
                            default => 'ti-circle text-secondary',
                        };
                    @endphp
                    <li class="d-flex gap-3 mb-3" data-timeline-step="{{ $step['key'] ?? '' }}">
                        <span class="mt-1"><i class="ti {{ $icon }}" aria-hidden="true"></i></span>
                        <div>
                            <div class="fw-semibold">{{ $step['label'] }}</div>
                            @if (! empty($step['detail']))
                                <div class="small text-secondary">{{ $step['detail'] }}</div>
                            @endif
                            @if (! empty($step['at']))
                                <div class="small text-secondary">{{ $step['at'] }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
