<x-mail::message>
# Payment Update

Hello {{ $payment->booking->contact?->meta['name'] ?? 'Customer' }},

Your payment submission was reviewed and could not be verified.

- Booking reference: **{{ $payment->booking->booking_reference ?? 'N/A' }}**
- Amount: **{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}**
- Current status: **Rejected**

Please submit updated proof or contact support.

Thanks,<br>
{{ $payment->booking->agency->name }}
</x-mail::message>
