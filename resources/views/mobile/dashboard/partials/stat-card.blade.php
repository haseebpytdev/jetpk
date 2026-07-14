@props([
    'label',
    'value',
    'tone' => 'default',
])

<div
    class="ota-mobile-dashboard__stat ota-mobile-dashboard__stat--{{ $tone }}"
    data-testid="ota-mobile-dashboard-stat"
>
    <span class="ota-mobile-dashboard__stat-label">{{ $label }}</span>
    <span class="ota-mobile-dashboard__stat-value">{{ $value }}</span>
</div>
