<x-mail::message>
# Your ticket itinerary is ready

Hello {{ $booking->contact?->meta['name'] ?? $booking->customer?->name ?? 'Customer' }},

@php
    $brandName = $booking->agency->agencySetting?->display_name ?: $booking->agency->name;
    $supportEmail = $booking->agency->agencySetting?->support_email ?: null;
    $supportPhone = $booking->agency->agencySetting?->support_phone ?: null;
@endphp

Your ticket itinerary for booking **{{ $booking->reference_code }}** is ready.

- Route: **{{ $booking->route ?? 'N/A' }}**
- Travel date: **{{ $booking->travel_date?->format('d M Y') ?? 'N/A' }}**
- PNR: **{{ $booking->pnr ?? 'N/A' }}**

@if ($booking->tickets->isNotEmpty())
**Ticket numbers:**
@foreach ($booking->tickets as $ticket)
- {{ $ticket->ticket_number ?? 'N/A' }}@if(!empty($ticket->meta['passenger_name'])) ({{ $ticket->meta['passenger_name'] }})@endif
@endforeach
@endif

@if ($attachmentStoragePath)
The itinerary PDF is attached to this email.
@else
Please check your booking portal for full itinerary details.
@endif

@if ($staffNote)
**Note from our team:** {{ $staffNote }}
@endif

If you have questions, contact us using the details below.

Thanks,<br>
{{ $brandName }}

@if ($supportEmail || $supportPhone)
Support: {{ $supportEmail ?? 'N/A' }}{{ $supportPhone ? ' · '.$supportPhone : '' }}
@endif
</x-mail::message>
