@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Flight Details')

@section('content')
    @php
        $o = is_array($offer ?? null) ? $offer : null;
        $cr = is_array($criteria ?? null) ? $criteria : [];
        $routeLabel = trim((string) ($routeLabel ?? ''));
        if ($routeLabel === '' && $o !== null) {
            $routeLabel = trim((string) ($o['route'] ?? ''));
        }
        if ($routeLabel === '') {
            $routeLabel = strtoupper(trim((string) ($cr['origin'] ?? ''))).' → '.strtoupper(trim((string) ($cr['destination'] ?? '')));
        }
        $expired = (bool) ($expired ?? false);
        $errorMessage = trim((string) ($errorMessage ?? ''));
        $canBook = $o !== null && (bool) ($o['can_book'] ?? false);
        $selectUrl = $canBook ? trim((string) ($o['select_url'] ?? '')) : '';
        $disabledReason = trim((string) ($o['disabled_reason'] ?? 'This fare cannot be selected online.'));
        $backUrl = trim((string) ($backUrl ?? route('home')));
        $displayedPrice = isset($o['displayed_price']) ? (float) $o['displayed_price'] : null;
        $hasPkrQuote = $o !== null && (bool) ($o['has_confirmed_pkr_quote'] ?? false);
    @endphp

    <div class="ota-mobile-flight-details" data-testid="ota-mobile-flight-details">
        <header class="ota-mobile-flight-details__header">
            <div class="ota-mobile-flight-details__header-row">
                <a href="{{ $backUrl }}" class="ota-mobile-flight-details__back" aria-label="Back to results">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                </a>
                <div class="ota-mobile-flight-details__header-copy">
                    <h1 class="ota-mobile-flight-details__title">Flight Details</h1>
                    @if ($routeLabel !== '')
                        <p class="ota-mobile-flight-details__route">{{ $routeLabel }}</p>
                    @endif
                </div>
            </div>
        </header>

        @if ($expired || $o === null)
            <div class="ota-mobile-flight-details__body">
                <article class="ota-mobile-flight-details__card ota-mobile-flight-details__card--empty">
                    <p class="ota-mobile-flight-details__empty-title">Flight details unavailable</p>
                    <p class="ota-mobile-flight-details__fallback">
                        {{ $errorMessage !== '' ? $errorMessage : 'This fare search has expired or the selected flight is no longer available. Please search again.' }}
                    </p>
                    <a href="{{ route('home') }}" class="ota-mobile-flight-details__cta ota-mobile-flight-details__cta--secondary">Search again</a>
                </article>
            </div>
        @else
            <div class="ota-mobile-flight-details__body">
                @include('mobile.flights.partials.details-fare-summary', [
                    'offer' => $o,
                    'criteria' => $cr,
                    'routeLabel' => $routeLabel,
                ])
            </div>

            <div class="ota-mobile-flight-details__sticky-cta ota-mobile-flight-details__sticky-cta--summary">
                @if ($hasPkrQuote && $displayedPrice !== null && $displayedPrice > 0)
                    <div class="ota-mobile-flight-details__sticky-total">
                        <span class="ota-mobile-flight-details__sticky-total-label">Grand total</span>
                        <span class="ota-mobile-flight-details__sticky-total-value">PKR {{ number_format($displayedPrice, 0, '.', ',') }}</span>
                    </div>
                @endif
                @if ($canBook && $selectUrl !== '')
                    <a href="{{ $selectUrl }}" class="ota-mobile-flight-details__cta" data-testid="ota-mobile-flight-details-select">Select</a>
                @else
                    <button type="button" class="ota-mobile-flight-details__cta is-disabled" disabled title="{{ $disabledReason }}">
                        Select
                    </button>
                    @if ($disabledReason !== '')
                        <p class="ota-mobile-flight-details__disabled-note">{{ $disabledReason }}</p>
                    @endif
                @endif
            </div>
        @endif
    </div>
@endsection
