@php
    use App\Support\Security\SensitiveDataRedactor;
    $summaryPrs = is_array($sa['pnr_retrieve_safety'] ?? null)
        ? $sa['pnr_retrieve_safety']
        : \App\Support\Bookings\PnrItinerarySyncSafetyPresenter::forBooking($booking);
    $summaryIsSabre = ($sa['is_sabre'] ?? false)
        || strtolower((string) ($provider ?? '')) === 'sabre';
    $summaryLastError = $latestAttempt?->error_message
        ? SensitiveDataRedactor::sanitizeErrorMessage((string) $latestAttempt->error_message)
        : null;
    $summaryLastSync = $sa['pnr_itinerary_synced_at'] ?? ($booking->updated_at?->format('Y-m-d H:i') ?? display_unknown());
@endphp
<h4 class="h6 mb-2">Supplier / PNR summary</h4>
<div class="overview-summary-grid mb-3">
    <div class="overview-kv"><span class="label">Supplier</span><span class="value">{{ $provider }}</span></div>
    <div class="overview-kv"><span class="label">Supplier booking status</span><span class="value text-capitalize">{{ str_replace('_', ' ', (string) ($booking->supplier_booking_status ?? 'not started')) }}</span></div>
    <div class="overview-kv"><span class="label">{{ $summaryIsSabre ? 'Sabre / GDS PNR' : 'PNR' }}</span><span class="value">{{ display_unknown($summaryPrs['sabre_pnr_label'] ?? $booking->pnr ?? null) }}</span></div>
    @if ($summaryIsSabre)
        <div class="overview-kv"><span class="label">Airline locator</span><span class="value">{{ $summaryPrs['airline_locator_display'] ?? 'Not recorded yet' }}</span></div>
    @else
        <div class="overview-kv"><span class="label">Supplier reference</span><span class="value">{{ display_unknown($booking->supplier_reference) }}</span></div>
    @endif
    <div class="overview-kv"><span class="label">Last sync</span><span class="value">{{ $summaryLastSync }}</span></div>
    <div class="overview-kv"><span class="label">Last error</span><span class="value text-end">{{ $summaryLastError ?? 'None' }}</span></div>
    <div class="overview-kv"><span class="label">Ticketing enabled</span><span class="value">No</span></div>
    <div class="overview-kv"><span class="label">Auto-PNR enabled</span><span class="value">{{ (is_array($sa['operational_pnr_readiness'] ?? null) && ($sa['operational_pnr_readiness']['operational_auto_pnr_enabled'] ?? false)) ? 'Yes (ops)' : 'No' }}</span></div>
    <div class="overview-kv"><span class="label">Cancellation enabled</span><span class="value">No</span></div>
    <div class="overview-kv"><span class="label">Offer validation</span><span class="value text-capitalize">@if (is_array($iatiDiagnostic['offer_validation'] ?? null) && ($iatiDiagnostic['show'] ?? false)){{ $iatiDiagnostic['offer_validation']['label'] }}@else{{ str_replace('_', ' ', (string) $validationStatus) }}@endif</span></div>
</div>
