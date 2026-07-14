@props([
    'status',
])

@php
    $display = is_object($status) && enum_exists($status::class)
        ? str_replace('_', ' ', (string) ($status->value ?? $status->name))
        : str_replace('_', ' ', (string) $status);
    $stStr = strtolower(trim($display));
    if ($stStr === '' || $stStr === '—' || $stStr === '-') {
        $display = 'Unknown';
        $stStr = 'unknown';
    }
    $class = match (true) {
        str_contains($stStr, 'inactive') || str_contains($stStr, 'suspend') || str_contains($stStr, 'reject') || str_contains($stStr, 'cancel') => 'ota-bstat ota-bstat--cancelled',
        str_contains($stStr, 'active') && ! str_contains($stStr, 'inactive') => 'ota-bstat ota-bstat--ticketed',
        str_contains($stStr, 'approved') || str_contains($stStr, 'converted') || str_contains($stStr, 'ticket') => 'ota-bstat ota-bstat--ticketed',
        str_contains($stStr, 'pending') || str_contains($stStr, 'review') || str_contains($stStr, 'invited') || str_contains($stStr, 'needs') => 'ota-bstat ota-bstat--pending',
        str_contains($stStr, 'confirm') => 'ota-bstat ota-bstat--confirmed',
        default => 'ota-bstat ota-bstat--muted',
    };
@endphp

<span {{ $attributes->merge(['class' => $class]) }}>{{ ucwords($display) }}</span>
