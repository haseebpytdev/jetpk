<x-mail::message>
# Booking Request Received

Hello {{ $booking->contact?->meta['name'] ?? $booking->customer?->name ?? 'Customer' }},

@php
    $brandName = $booking->agency->agencySetting?->display_name ?: $booking->agency->name;
    $supportEmail = $booking->agency->agencySetting?->support_email ?: null;
    $supportPhone = $booking->agency->agencySetting?->support_phone ?: null;
    $passengers = $booking->passengers ?? collect();
    $passengerLines = $passengers->take(6)->map(function ($p) {
        return trim((string) ($p->title ?? '').' '.(string) $p->first_name.' '.(string) $p->last_name);
    })->filter()->values();
    $total = $booking->fareBreakdown?->total;
    $currency = $booking->currency ?? 'PKR';
@endphp

Thank you — your booking request has been received by **{{ $brandName }}**. Our team will review and confirm the details before ticketing or final payment.

**Booking details**

- Reference: **{{ $booking->reference_code }}**
- Route: **{{ $booking->route ?? 'N/A' }}**
- Travel date: **{{ $booking->travel_date?->format('d M Y') ?? 'N/A' }}**
- Status: **{{ str_replace('_', ' ', $booking->status->value) }}**
@if ($total !== null && (float) $total > 0)
- Total: **{{ $currency }} {{ number_format((float) $total, 2) }}**
@endif

@if ($passengerLines->isNotEmpty())
**Passengers**

@foreach ($passengerLines as $line)
- {{ $line }}
@endforeach
@if ($passengers->count() > 6)
- …and {{ $passengers->count() - 6 }} more
@endif
@endif

@if ($booking->contact?->email || $booking->contact?->phone)
**Your contact details**

@if ($booking->contact?->email)
- Email: {{ $booking->contact->email }}
@endif
@if ($booking->contact?->phone)
- Phone: {{ $booking->contact->phone }}
@endif
@endif

We will email you again when your booking is updated, ticketed, or when documents are ready.

Thanks,<br>
{{ $brandName }}

@if ($supportEmail || $supportPhone)
Support: {{ $supportEmail ?? 'N/A' }}{{ $supportPhone ? ' · '.$supportPhone : '' }}
@endif
</x-mail::message>
