<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .title { font-size: 20px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
@php
    $bs = $booking->agency->agencySetting;
    $brandName = $bs?->display_name ?: $booking->agency->name;
    $supportEmail = $bs?->support_email ?: ($booking->agency->settings['support_email'] ?? 'support@example.com');
    $supportPhone = $bs?->support_phone ?: ($booking->agency->settings['support_phone'] ?? 'N/A');
    $contactName = $booking->contact?->meta['contact_name'] ?? $booking->customer?->name ?? 'Passenger';
    $passengerCount = $booking->passengers->count();
    $fare = $booking->fareBreakdown;
    $dueAt = $booking->payment_due_at?->format('Y-m-d') ?? now()->addDays(3)->format('Y-m-d');
@endphp
<div class="title">{{ $brandName }}</div>
<div>{{ $supportEmail }} | {{ $supportPhone }}</div>
<h2>Invoice</h2>
<div class="muted">Number: {{ $documentNumber }}</div>
<p><strong>Booking Reference:</strong> {{ $booking->booking_reference ?? 'N/A' }}</p>
<p><strong>Customer/contact:</strong> {{ $contactName }}</p>
<p><strong>Route/travel date:</strong> {{ $booking->route ?? 'N/A' }} · {{ $booking->travel_date?->format('Y-m-d') ?? 'N/A' }}</p>
<p><strong>Passenger count:</strong> {{ $passengerCount }}</p>
<h3>Fare Details</h3>
<table>
    <tbody>
    <tr><th>Base fare</th><td>{{ number_format((float) ($fare?->base_fare ?? 0), 2) }}</td></tr>
    <tr><th>Taxes</th><td>{{ number_format((float) ($fare?->taxes ?? 0), 2) }}</td></tr>
    <tr><th>Fees</th><td>{{ number_format((float) ($fare?->fees ?? 0), 2) }}</td></tr>
    <tr><th>Markup</th><td>{{ number_format((float) ($fare?->markup ?? 0), 2) }}</td></tr>
    <tr><th>Service fee</th><td>{{ number_format((float) ($fare?->fees ?? 0), 2) }}</td></tr>
    <tr><th>Total payable</th><td>{{ number_format((float) ($fare?->total ?? 0), 2) }} {{ $booking->currency }}</td></tr>
    </tbody>
</table>
<p><strong>Payment instructions:</strong> Pay via approved agency channels with booking reference as narration.</p>
<p><strong>Due date:</strong> {{ $dueAt }}</p>
<p><strong>Terms:</strong> Invoice is a request/record of amount payable and does not confirm payment.</p>
<div class="muted">
    Generated on {{ $generatedAt->format('Y-m-d H:i') }}
</div>
</body>
</html>
