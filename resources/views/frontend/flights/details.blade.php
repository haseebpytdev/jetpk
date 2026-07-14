@extends(client_layout('frontend', 'frontend'))

@section('title', 'Flight details')

@section('content')
    <section class="ota-section ota-flight-detail-wrap">
        <div class="ota-container ota-container-narrow">
            <header class="ota-section-head">
                <p class="ota-section-kicker">Flight details</p>
                <h1 class="ota-section-title">Review your selected flight</h1>
                <p class="ota-section-desc">{{ $criteria['origin'] }} → {{ $criteria['destination'] }} · {{ \Illuminate\Support\Carbon::parse($offer['depart_at'])->toFormattedDateString() }}</p>
            </header>
            <div class="ota-checkout-card">
                <div class="row">
                    <div class="col-sm-2 text-center">
                        @if(!empty($airlineLogo))
                            <div class="ota-airline-logo ota-airline-logo--img"><img src="{{ $airlineLogo }}" alt="{{ $offer['airline_name'] }} logo"></div>
                        @else
                            <div class="ota-airline-logo">{{ $offer['airline_code'] }}</div>
                        @endif
                        <div class="small text-muted" style="margin-top:6px;">{{ $offer['airline_name'] }}</div>
                    </div>
                    <div class="col-sm-7">
                        <dl class="dl-horizontal">
                            <dt>Flight</dt>
                            <dd>{{ $offer['carrier_code'] }}{{ $offer['flight_number'] }}</dd>
                            <dt>Times</dt>
                            <dd>{{ \Illuminate\Support\Carbon::parse($offer['depart_at'])->format('H:i') }} – {{ \Illuminate\Support\Carbon::parse($offer['arrive_at'])->format('H:i') }} ({{ $offer['duration_h'] }}h {{ str_pad((string) $offer['duration_m'], 2, '0', STR_PAD_LEFT) }}m)</dd>
                            <dt>Baggage</dt>
                            <dd>{{ $offer['baggage'] }}</dd>
                            <dt>Fare</dt>
                            <dd>{{ !empty($offer['refundable']) ? 'Refundable' : 'Non-refundable' }} · {{ $offer['fare_family'] }}</dd>
                            <dt>Total (PKR)</dt>
                            <dd><strong>Rs {{ number_format((float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0), 0) }} PKR</strong></dd>
                        </dl>
                    </div>
                    <div class="col-sm-3 text-right">
                        @if ($canContinueBooking ?? false)
                            <a class="btn btn-primary btn-block ota-select-primary"
                               href="{{ route('booking.passengers', array_merge(['flight_id' => $offer['id']], request()->only(['from', 'to', 'depart', 'trip_type', 'return_date', 'cabin', 'adults', 'children', 'infants']))) }}">Continue to passenger details</a>
                        @else
                            <p class="small text-muted" style="margin-bottom:10px;">{{ $bookingBlockedReason ?? 'Online booking is not available for this fare.' }}</p>
                            <a class="btn btn-default btn-block" href="{{ client_route('home') }}#jp-flight-search">New flight search</a>
                        @endif
                    </div>
                </div>
            </div>
            <p><a href="{{ client_route('flights.results', ['from' => $criteria['origin'], 'to' => $criteria['destination'], 'depart' => $criteria['depart_date']]) }}">← Back to results</a></p>
        </div>
    </section>
@endsection
