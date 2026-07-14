@props([
    'variant' => 'desktop',
])

<x-account-dropdown :variant="$variant" {{ $attributes }} />
