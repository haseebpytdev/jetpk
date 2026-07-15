@props([
    'itinerary' => null,
    'testIdPrefix' => 'customer',
    'shell' => 'account',
])

@php $isAccount = $shell === 'account'; @endphp

<div class="{{ $isAccount ? 'ota-account-card mb-3' : 'card mb-3 border-0 shadow-sm' }}">
    <div class="{{ $isAccount ? 'ota-account-card__head' : 'card-header border-0' }} d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="{{ $isAccount ? 'ota-account-card__title' : 'card-title mb-0' }}">Itinerary</h3>
        @if ($itinerary)
            <span class="{{ $isAccount ? 'ota-bstat ota-bstat--muted' : 'badge bg-azure-lt' }}" data-testid="{{ $testIdPrefix }}-itinerary-source">{{ $itinerary['itinerary_source_label'] }}</span>
        @endif
    </div>
    <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}">
        @if ($itinerary && ($itinerary['show_snapshot_itinerary_warning'] ?? false))
            <div class="{{ $isAccount ? 'ota-account-alert ota-account-alert--warning' : 'alert alert-warning' }} py-2 small mb-3" data-testid="{{ $testIdPrefix }}-itinerary-snapshot-warning">
                Search/checkout snapshot — final airline itinerary not yet synced.
            </div>
        @endif
        @if ($itinerary && ! empty($itinerary['segment_lines']))
            <p class="mb-2 text-secondary small ota-r-text-safe">{{ $itinerary['journey_od'] ?? '' }} · {{ $itinerary['stops_label'] ?? '' }}</p>
            @foreach ($itinerary['segment_lines'] as $line)
                <div class="mb-1 ota-r-text-safe {{ str_starts_with($line, '   —') ? 'small text-secondary ms-3' : '' }}">{{ $line }}</div>
            @endforeach
            @if (! empty($itinerary['total_duration_label']))
                <div class="ota-booking-detail-itinerary-duration mt-3">
                    <i class="ti ti-clock" aria-hidden="true"></i>
                    <span>{{ $itinerary['total_duration_label'] }}</span>
                </div>
            @endif
        @else
            <p class="mb-0 text-secondary">Itinerary details will appear when your flight segments are available.</p>
        @endif
    </div>
</div>
