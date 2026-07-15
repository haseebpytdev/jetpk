@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'My bookings')

@section('mobile_app_title', 'My bookings')

@section('content')
    @php
        $filters = [
            'all' => 'All',
            'pending_payment' => 'Pending payment',
            'pnr_created' => 'PNR created',
            'needs_action' => 'Needs action',
            'cancelled' => 'Cancelled',
        ];
    @endphp

    <div class="ota-mobile-customer" data-testid="ota-mobile-customer-bookings">
        <nav class="ota-mobile-customer__filters" aria-label="Booking filters" data-testid="customer-bookings-filters">
            @foreach ($filters as $key => $label)
                <a
                    href="{{ route('customer.bookings.index', ['filter' => $key]) }}"
                    class="ota-mobile-customer__filter {{ ($filter ?? 'all') === $key ? 'is-active' : '' }}"
                >{{ $label }}</a>
            @endforeach
        </nav>

        @if ($bookings->isEmpty())
            <div class="ota-mobile-customer__empty" data-testid="ota-mobile-customer-bookings-empty">
                <p class="ota-mobile-customer__empty-title">No bookings yet</p>
                <p class="ota-mobile-customer__empty-help">Try another filter or search for new flights.</p>
                <a href="{{ route('flights.search') }}" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary">Search flights</a>
            </div>
        @else
            <div class="ota-mobile-customer__list">
                @foreach ($bookings as $booking)
                    @include('mobile.customer.partials.booking-summary-card', [
                        'booking' => $booking,
                        'showUrl' => route('customer.bookings.show', $booking),
                    ])
                @endforeach
            </div>
            @if ($bookings->hasPages())
                <div class="ota-mobile-customer__pagination">{{ $bookings->links() }}</div>
            @endif
        @endif
    </div>
@endsection
