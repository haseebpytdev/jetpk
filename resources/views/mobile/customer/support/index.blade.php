@extends('layouts.mobile-app')

@section('title', 'Support tickets')

@section('mobile_app_title', 'Support')

@section('mobile_app_top_actions')
    <a href="{{ route('customer.support.tickets.create') }}" class="ota-mobile-app__top-action" data-testid="ota-mobile-customer-support-create-link">New</a>
@endsection

@section('content')
    <div class="ota-mobile-customer" data-testid="ota-mobile-customer-support-index">
        @if (session('status'))
            @include('mobile.components.alert', ['type' => 'success', 'message' => session('status')])
        @endif

        @if ($tickets->isEmpty())
            <div class="ota-mobile-customer__empty" data-testid="customer-support-tickets-empty">
                <p class="ota-mobile-customer__empty-title">No support tickets yet</p>
                <p class="ota-mobile-customer__empty-help">Create a ticket if you need help with a booking, payment, or document.</p>
                <a href="{{ route('customer.support.tickets.create') }}" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary">Create ticket</a>
            </div>
        @else
            <div class="ota-mobile-customer__list">
                @foreach ($tickets as $ticket)
                    @include('mobile.customer.partials.support-ticket-card', ['ticket' => $ticket])
                @endforeach
            </div>
            @if ($tickets->hasPages())
                <div class="ota-mobile-customer__pagination">{{ $tickets->links() }}</div>
            @endif
        @endif
    </div>
@endsection
