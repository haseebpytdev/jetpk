<x-mail::message>
# Booking Status Updated

Hello {{ $booking->contact?->meta['name'] ?? 'Customer' }},

@php
    $brandName = $booking->agency->agencySetting?->display_name ?: $booking->agency->name;
    $supportEmail = $booking->agency->agencySetting?->support_email ?: null;
@endphp
Your booking status has been updated by {{ $brandName }}.

- Booking reference: **{{ $booking->booking_reference ?? 'N/A' }}**
- Route: **{{ $booking->route ?? 'N/A' }}**
- New status: **{{ $statusLabel }}**

If you need help, please contact our support team.

Thanks,<br>
{{ $brandName }}
@if($supportEmail)
Support: {{ $supportEmail }}
@endif
</x-mail::message>
