<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cancellation Confirmation</title>
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
    $cancel = $booking->cancellationRequests->first(fn ($c) => (string) $c->status->value === 'processed');
@endphp
<div class="title">{{ $brandName }}</div>
<div>{{ $supportEmail }} | {{ $supportPhone }}</div>
<h2>Cancellation Confirmation</h2>
<div class="muted">Number: {{ $documentNumber }}</div>
<p><strong>Booking Reference:</strong> {{ $booking->booking_reference ?? 'N/A' }}</p>
<p><strong>Customer:</strong> {{ $booking->contact?->meta['contact_name'] ?? $booking->customer?->name ?? 'Passenger' }}</p>
<table>
    <tbody>
    <tr><th>Cancellation ID</th><td>{{ $cancel?->id ?? 'N/A' }}</td></tr>
    <tr><th>Type</th><td>{{ $cancel?->cancellation_type->value ?? 'N/A' }}</td></tr>
    <tr><th>Status</th><td>{{ $cancel?->status->value ?? 'N/A' }}</td></tr>
    <tr><th>Processed at</th><td>{{ $cancel?->processed_at?->format('Y-m-d H:i') ?? 'N/A' }}</td></tr>
    </tbody>
</table>
<div class="muted">Generated on {{ $generatedAt->format('Y-m-d H:i') }}</div>
</body>
</html>
