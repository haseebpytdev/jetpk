<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket Itinerary</title>
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
@endphp
<div class="title">{{ $brandName }}</div>
<div>{{ $supportEmail }} | {{ $supportPhone }}</div>
<h2>Ticket Itinerary</h2>
<div class="muted">Number: {{ $documentNumber }}</div>
<p><strong>Booking Reference:</strong> {{ $booking->booking_reference ?? 'N/A' }}</p>
<p><strong>PNR:</strong> {{ $booking->pnr ?? 'N/A' }}</p>
<p><strong>Route:</strong> {{ $booking->route ?? 'N/A' }}</p>
<p><strong>Flight details:</strong> {{ $booking->airline ?? 'N/A' }} · {{ $booking->travel_date?->format('Y-m-d') ?? 'N/A' }}</p>
<p><strong>Baggage:</strong> As per fare rules / airline policy.</p>
<h3>Tickets</h3>
<table>
    <thead><tr><th>Passenger</th><th>Ticket Number</th><th>Airline</th><th>Issued At</th></tr></thead>
    <tbody>
    @foreach($booking->tickets as $ticket)
        <tr>
            <td>{{ $ticket->meta['passenger_name'] ?? ($ticket->passenger?->first_name.' '.$ticket->passenger?->last_name) }}</td>
            <td>{{ $ticket->ticket_number ?? 'N/A' }}</td>
            <td>{{ $ticket->airline_code ?? 'N/A' }}</td>
            <td>{{ $ticket->issued_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p><strong>Support contact:</strong> {{ $supportEmail }} / {{ $supportPhone }}</p>
<p><strong>Important travel notes:</strong> Carry valid travel documents and report at airport as per airline check-in policy.</p>
<div class="muted">
    Generated on {{ $generatedAt->format('Y-m-d H:i') }}
</div>
</body>
</html>
