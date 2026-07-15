<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Refund Note</title>
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
    $refund = $booking->refunds->first(fn ($r) => in_array((string) $r->status->value, ['approved', 'paid'], true));
@endphp
<div class="title">{{ $brandName }}</div>
<div>{{ $supportEmail }} | {{ $supportPhone }}</div>
<h2>Refund Note</h2>
<div class="muted">Number: {{ $documentNumber }}</div>
<p><strong>Booking Reference:</strong> {{ $booking->booking_reference ?? 'N/A' }}</p>
<p><strong>Customer:</strong> {{ $booking->contact?->meta['contact_name'] ?? $booking->customer?->name ?? 'Passenger' }}</p>
<table>
    <tbody>
    <tr><th>Refund amount</th><td>{{ number_format((float) ($refund?->amount ?? 0), 2) }} {{ $booking->currency }}</td></tr>
    <tr><th>Refund status</th><td>{{ $refund?->status->value ?? 'N/A' }}</td></tr>
    <tr><th>Method</th><td>{{ $refund?->method ?? 'N/A' }}</td></tr>
    <tr><th>Reference</th><td>{{ $refund?->reference ?? 'N/A' }}</td></tr>
    </tbody>
</table>
<div class="muted">Generated on {{ $generatedAt->format('Y-m-d H:i') }}</div>
</body>
</html>
