<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .title { font-size: 20px; font-weight: 700; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
@php
    $bs = $booking->agency->agencySetting;
    $brandName = $bs?->display_name ?: $booking->agency->name;
    $supportEmail = $bs?->support_email ?: ($booking->agency->settings['support_email'] ?? 'support@example.com');
    $supportPhone = $bs?->support_phone ?: ($booking->agency->settings['support_phone'] ?? 'N/A');
    $total = (float) ($booking->fareBreakdown?->total ?? 0);
    $paid = (float) ($booking->amount_paid ?? 0);
    $balance = max(0, $total - $paid);
@endphp
<div class="title">{{ $brandName }}</div>
<div>{{ $supportEmail }} | {{ $supportPhone }}</div>
<h2>Payment Receipt</h2>
<div class="muted">Number: {{ $documentNumber }}</div>
<p><strong>Booking Reference:</strong> {{ $booking->booking_reference ?? 'N/A' }}</p>
<p><strong>Route:</strong> {{ $booking->route ?? 'N/A' }}</p>
<p><strong>Amount:</strong> {{ number_format((float) ($payment?->amount ?? 0), 2) }} {{ $payment?->currency ?? $booking->currency }}</p>
<p><strong>Method:</strong> {{ isset($payment) ? str_replace('_', ' ', $payment->method->value) : 'N/A' }}</p>
<p><strong>Payment reference:</strong> {{ $payment?->payment_reference ?? 'N/A' }}</p>
<p><strong>Verified date:</strong> {{ $payment?->verified_at?->format('Y-m-d H:i') ?? 'N/A' }}</p>
<p><strong>Remaining balance:</strong> {{ number_format($balance, 2) }} {{ $booking->currency }}</p>
<div class="muted">
    Generated on {{ $generatedAt->format('Y-m-d H:i') }}
</div>
</body>
</html>
