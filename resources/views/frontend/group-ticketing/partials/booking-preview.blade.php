@php
    /** @var array<string, mixed> $card */
    $summary = is_array($checkoutSummary ?? null)
        ? $checkoutSummary
        : (is_array($summary ?? null) ? $summary : []);
    if ($summary === [] && isset($card)) {
        $summary = $card;
    }
@endphp
@include('frontend.checkout.partials.summary-card', [
    'summary' => $summary,
    'seatCount' => $seatCount ?? ($summary['seat_count'] ?? 1),
    'totalAmount' => $totalAmount ?? null,
    'showPayNote' => $showPayNote ?? false,
    'summaryTitle' => $summaryTitle ?? 'Booking summary',
])
