@extends('layouts.mobile-app')

@section('title', 'My bookings')

@section('mobile_app_title', 'Bookings')

@section('mobile_app_top_actions')
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::BookingsCreate))
        <a href="{{ route('agent.bookings.create') }}" class="ota-mobile-app__top-action" data-testid="ota-mobile-agent-bookings-create-link">New</a>
    @endif
@endsection

@section('content')
    @php
        $filters = [
            'all' => 'All',
            'pending_payment' => 'Pending payment',
            'pnr_created' => 'PNR created',
            'needs_action' => 'Needs action',
            'cancelled' => 'Cancelled',
        ];
        $canCreate = auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::BookingsCreate) ?? false;
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-bookings">
        <nav class="ota-mobile-agent__filters" aria-label="Booking filters" data-testid="agent-bookings-filters">
            @foreach ($filters as $key => $label)
                <a
                    href="{{ route('agent.bookings.index', ['filter' => $key]) }}"
                    class="ota-mobile-agent__filter {{ ($filter ?? 'all') === $key ? 'is-active' : '' }}"
                >{{ $label }}</a>
            @endforeach
        </nav>

        @if ($bookings->isEmpty())
            <div class="ota-mobile-agent__empty" data-testid="ota-mobile-agent-bookings-empty">
                <p class="ota-mobile-agent__empty-title">No bookings yet</p>
                <p class="ota-mobile-agent__empty-help">Try another filter or create a new booking request.</p>
                @if ($canCreate)
                    <a href="{{ route('agent.bookings.create') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">New booking</a>
                @endif
            </div>
        @else
            <div class="ota-mobile-agent__list">
                @foreach ($bookings as $booking)
                    @include('mobile.agent.partials.agent-booking-card', [
                        'booking' => $booking,
                        'showUrl' => route('agent.bookings.show', $booking),
                    ])
                @endforeach
            </div>
            @if ($bookings->hasPages())
                <div class="ota-mobile-agent__pagination">{{ $bookings->links() }}</div>
            @endif
        @endif
    </div>
@endsection
