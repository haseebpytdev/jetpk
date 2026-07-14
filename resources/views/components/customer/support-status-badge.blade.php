@props(['status'])

@php
    $enum = $status instanceof \App\Enums\SupportTicketStatus
        ? $status
        : \App\Enums\SupportTicketStatus::tryFrom((string) $status);
@endphp

@if ($enum)
    <span {{ $attributes->merge(['class' => 'ota-account-badge '.$enum->customerBadgeClass()]) }}>
        {{ $enum->customerLabel() }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'ota-account-badge ota-account-badge--muted']) }}>
        {{ str_replace('_', ' ', (string) $status) }}
    </span>
@endif
