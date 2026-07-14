@props([
    'status',
    'label' => null,
])

@php
    $display = $label ?? (is_object($status) && enum_exists($status::class)
        ? str_replace('_', ' ', (string) ($status->value ?? $status->name))
        : str_replace('_', ' ', (string) $status));
    $stStr = strtolower(trim($display));
    $tone = match (true) {
        str_contains($stStr, 'cancel') => 'cancelled',
        str_contains($stStr, 'ticket') || str_contains($stStr, 'paid') || str_contains($stStr, 'confirm') || str_contains($stStr, 'issued') => 'positive',
        str_contains($stStr, 'pending') || str_contains($stStr, 'review') || str_contains($stStr, 'partial') || str_contains($stStr, 'proof') => 'pending',
        default => 'muted',
    };
@endphp

<span {{ $attributes->merge(['class' => 'ota-mobile-customer__pill ota-mobile-customer__pill--'.$tone]) }}>
    {{ ucwords($display) }}
</span>
