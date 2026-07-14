@props([
    'href',
    'label',
    'count' => 0,
    'helper' => '',
    'icon' => 'ti-flag',
    'tone' => 'muted',
    'priority' => false,
    'cta' => 'Review',
    'testKey' => '',
])

@php
    $count = (int) $count;
    $isPriority = (bool) $priority;
    $isHot = $isPriority && $count > 0;
    $cardClass = 'ota-dash-action-card ota-dash-action-card--'.$tone;
    if ($isPriority) {
        $cardClass .= ' ota-dash-action-card--priority';
    }
    if ($isHot) {
        $cardClass .= ' ota-dash-action-card--hot';
    }
    $defaults = [
        'class' => $cardClass,
    ];
    if ($testKey !== '' && ! $attributes->has('data-testid')) {
        $defaults['data-testid'] = 'ota-action-card-'.$testKey;
    }
@endphp

<a href="{{ $href }}" {{ $attributes->merge($defaults) }}>
    <div class="ota-dash-action-card__icon">
        <i class="ti {{ $icon }}"></i>
    </div>
    <div class="ota-dash-action-card__body">
        <div class="ota-dash-action-card__count">{{ number_format($count) }}</div>
        <div class="ota-dash-action-card__label">{{ $label }}</div>
        @if ($helper !== '')
            <p class="ota-dash-action-card__helper">{{ $helper }}</p>
        @endif
    </div>
    <span class="ota-dash-action-card__cta btn btn-sm {{ $isHot ? 'ota-dash-action-card__cta--primary' : ($isPriority ? 'ota-dash-action-card__cta--soft' : 'btn-outline-secondary') }}">
        {{ $cta }}
    </span>
</a>
