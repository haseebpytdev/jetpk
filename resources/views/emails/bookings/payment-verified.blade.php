<x-mail::message>
# Payment Verified

Hello {{ $payment->booking->contact?->meta['name'] ?? 'Customer' }},

We have verified your payment.

- Booking reference: **{{ $payment->booking->booking_reference ?? 'N/A' }}**
- Amount: **{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}**
- Method: **{{ str_replace('_', ' ', $payment->method->value) }}**
- Status: **Verified**

Thank you for your payment.

Thanks,<br>
{{ $payment->booking->agency->name }}
</x-mail::message>
