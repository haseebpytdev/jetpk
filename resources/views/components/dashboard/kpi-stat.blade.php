@props([
    'label',
    'value',
    'accent' => null,
])

@php
    $accentClass = match ($accent) {
        'amber' => 'ota-kpi-accent-amber',
        'emerald' => 'ota-kpi-accent-emerald',
        'violet' => 'ota-kpi-accent-violet',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 'card card-sm ota-kpi-card '.$accentClass]) }}>
    <div class="card-body">
        <div class="text-secondary">{{ $label }}</div>
        <div class="h2 mb-0">{{ $value }}</div>
    </div>
</div>
