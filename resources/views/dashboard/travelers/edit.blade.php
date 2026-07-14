@php
    $isCustomerAccount = str_starts_with($routePrefix ?? '', 'customer.');
    $isAgentAccount = str_starts_with($routePrefix ?? '', 'agent.');
    $isPortalAccount = $isCustomerAccount || $isAgentAccount;
    $portalLayout = match (true) {
        $isCustomerAccount => 'layouts.customer-account',
        $isAgentAccount => 'layouts.agent-portal',
        default => 'layouts.dashboard',
    };
@endphp
@extends($portalLayout)

@section('title', 'Edit traveler')

@if ($isPortalAccount)
    @section('account_title', 'Edit traveler')
    @section('account_subtitle', $traveler->fullName())
    @section('account_actions')
        <a href="{{ route($routePrefix.'.index') }}" class="ota-account-btn ota-account-btn--secondary">Back</a>
    @endsection
@else
    @section('page-header')
        <x-dashboard.section-header title="Edit traveler" :subtitle="$traveler->fullName()">
            <x-slot name="actions">
                <a href="{{ route($routePrefix.'.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            </x-slot>
        </x-dashboard.section-header>
    @endsection
@endif

@if ($isPortalAccount)
@section('account_content')
@else
@section('content')
@endif
    @unless ($traveler->isComplete())
        <div class="{{ $isPortalAccount ? 'ota-account-alert ota-account-alert--warning' : 'alert alert-warning' }}" data-testid="traveler-edit-completeness-warning">
            This profile is incomplete. Add missing fields before ticketing.
        </div>
    @endunless

    <div class="{{ $isPortalAccount ? 'ota-account-card ota-account-form-card' : 'card border-0 shadow-sm' }}" data-testid="saved-traveler-form-card">
        <div class="{{ $isPortalAccount ? 'ota-account-card__body' : 'card-body' }}">
            <form method="post" action="{{ route($routePrefix.'.update', $traveler) }}" data-testid="saved-traveler-form">
                @csrf
                @method('PATCH')
                @include('dashboard.travelers._form', ['useAccountForm' => $isPortalAccount])
                @if ($isPortalAccount)
                    <div class="ota-account-form-actions">
                        <button type="submit" class="ota-account-btn ota-account-btn--primary">Save changes</button>
                        <a href="{{ route($routePrefix.'.index') }}" class="ota-account-btn ota-account-btn--secondary">Cancel</a>
                    </div>
                @else
                    <button type="submit" class="btn btn-primary mt-3">Save traveler</button>
                @endif
            </form>
        </div>
    </div>
@endsection
