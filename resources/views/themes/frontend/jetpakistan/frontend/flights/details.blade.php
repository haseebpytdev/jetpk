@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    $jpBrandName = client_branding()->companyName();
    $price = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0);
    $provider = strtoupper((string) ($offer['supplier_provider'] ?? ''));
    $supplierLabel = match ($provider) {
        'SABRE' => 'Sabre GDS',
        'DUFFEL' => 'Duffel',
        'IATI' => 'IATI',
        default => $provider !== '' ? $provider : 'Supplier',
    };
    $departAt = \Illuminate\Support\Carbon::parse($offer['depart_at']);
    $arriveAt = \Illuminate\Support\Carbon::parse($offer['arrive_at']);
    $durationH = (int) ($offer['duration_h'] ?? 0);
    $durationM = (int) ($offer['duration_m'] ?? 0);
    $stops = (int) ($offer['stops'] ?? $offer['stop_count'] ?? 0);
    $bookingParams = array_merge(
        ['flight_id' => $offer['id']],
        request()->only(['from', 'to', 'depart', 'trip_type', 'return_date', 'cabin', 'adults', 'children', 'infants'])
    );
@endphp

@section('title', 'Flight details — '.$jpBrandName)
@section('jp_body_class', 'jp-flights-details')

@push('styles')
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/results.css?v=6">
@endpush

@section('content')
<section class="jp-page jp-page--flight-details" aria-labelledby="jp-flight-details-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-flight-details-heading"
      kicker="Flight details"
      title="Review your selected flight"
      :description="($criteria['origin'] ?? '').' → '.($criteria['destination'] ?? '').' · '.$departAt->toFormattedDateString()"
    />

    @if (session('offer_warning'))
      <x-jp.alert variant="warning">{{ session('offer_warning') }}</x-jp.alert>
    @endif

    <x-jp.result-card
      class="jp-flight-detail-card"
      :airline="$offer['airline_name'] ?? $offer['airline_code'] ?? 'Airline'"
      :price="number_format($price, 0)"
      currency="PKR"
      :stops="$stops"
      :duration="$durationH.'h '.str_pad((string) $durationM, 2, '0', STR_PAD_LEFT).'m'"
    >
      <div class="jp-flight-detail-grid">
        <div class="jp-flight-detail-brand">
          @if(!empty($airlineLogo))
            <img src="{{ $airlineLogo }}" alt="" class="jp-flight-detail-logo" width="56" height="56">
          @else
            <span class="jp-flight-detail-code">{{ $offer['airline_code'] ?? $offer['carrier_code'] ?? '—' }}</span>
          @endif
          <span class="jp-chip--supplier">{{ $supplierLabel }}</span>
        </div>

        <dl class="jp-flight-detail-facts">
          <div><dt>Flight</dt><dd>{{ $offer['carrier_code'] ?? '' }}{{ $offer['flight_number'] ?? '' }}</dd></div>
          <div><dt>Departure</dt><dd>{{ $departAt->format('H:i') }} · {{ $criteria['origin'] ?? '' }}</dd></div>
          <div><dt>Arrival</dt><dd>{{ $arriveAt->format('H:i') }} · {{ $criteria['destination'] ?? '' }}</dd></div>
          <div><dt>Baggage</dt><dd>{{ $offer['baggage'] ?? 'See fare rules' }}</dd></div>
          <div><dt>Fare</dt><dd>{{ !empty($offer['refundable']) ? 'Refundable' : 'Non-refundable' }} · {{ $offer['fare_family'] ?? 'Standard' }}</dd></div>
          <div><dt>Cabin</dt><dd>{{ ucfirst(str_replace('_', ' ', (string) ($offer['cabin'] ?? $criteria['cabin'] ?? 'economy'))) }}</dd></div>
        </dl>

        <aside class="jp-flight-detail-actions">
          <x-jp.payment-summary currency="PKR" :total="number_format($price, 0)">
            <div class="jp-pay-summary__row"><span>Base + taxes</span><span>PKR {{ number_format($price, 0) }}</span></div>
            <div class="jp-pay-summary__row"><span>Passengers</span><span>{{ (int) ($criteria['adults'] ?? 1) }} adult(s)</span></div>
          </x-jp.payment-summary>

          @if ($canContinueBooking ?? false)
            <a class="jp-btn jp-btn--primary jp-btn--block" href="{{ client_route('booking.passengers', $bookingParams) }}">Continue to passengers</a>
          @else
            <x-jp.alert variant="danger">{{ $bookingBlockedReason ?? 'Online booking is not available for this fare.' }}</x-jp.alert>
            <a class="jp-btn jp-btn--secondary jp-btn--block" href="{{ client_route('home') }}#jp-flight-search">New flight search</a>
          @endif
        </aside>
      </div>
    </x-jp.result-card>

    <div class="jp-page-actions">
      <a href="{{ client_route('flights.results', ['from' => $criteria['origin'], 'to' => $criteria['destination'], 'depart' => $criteria['depart_date'], 'trip_type' => $criteria['trip_type'] ?? 'one_way', 'return_date' => $criteria['return_date'] ?? null, 'cabin' => $criteria['cabin'] ?? 'economy', 'adults' => $criteria['adults'] ?? 1, 'children' => $criteria['children'] ?? 0, 'infants' => $criteria['infants'] ?? 0]) }}" class="jp-btn jp-btn--secondary">← Back to results</a>
      <a href="{{ client_route('support') }}" class="jp-btn jp-btn--secondary">Need help?</a>
    </div>
  </div>
</section>
@endsection
