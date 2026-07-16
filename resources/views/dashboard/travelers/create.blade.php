@php
    $isCustomerAccount = str_starts_with($routePrefix ?? '', 'customer.');
    $isAgentAccount = str_starts_with($routePrefix ?? '', 'agent.');
    $isPortalAccount = $isCustomerAccount || $isAgentAccount;
    $portalLayout = match (true) {
        // JP-PORTAL-1 TASK 1 — tenant-safe resolver migration.
        // Was the hardcoded 'layouts.customer-account', which bypassed the theme resolver: JetPK
        // customers were dropped into the legacy shell here while every other portal page renders
        // jp-portal (a full-shell transition mid-navigation).
        // SAFETY: client_layout() resolves the tenant theme layout and FALLS BACK to this exact
        // legacy name when the tenant has no theme layout, so Parwaaz/default behaviour is
        // unchanged. No JetPK classes, wording or colours are introduced in this shared file.
        $isCustomerAccount => client_layout('customer-account', 'customer'),
        $isAgentAccount => client_layout('agent-portal', 'agent'),   // JP-PORTAL-1 TASK 1 — see above
        default => 'layouts.dashboard',
    };
@endphp
@extends($portalLayout)

@section('title', 'Add traveler')

@if ($isPortalAccount)
    @section('account_title', 'Add traveler')
    @section('account_subtitle', 'Save passenger details for faster future bookings.')
    @section('account_actions')
        <a href="{{ route($routePrefix.'.index') }}" class="ota-account-btn ota-account-btn--secondary">Back</a>
    @endsection
@else
    @section('page-header')
        <x-dashboard.section-header title="Add traveler" subtitle="Save passenger details for reuse.">
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
    <div class="{{ $isPortalAccount ? 'ota-account-card ota-account-form-card' : 'card border-0 shadow-sm' }}" data-testid="saved-traveler-form-card">
        <div class="{{ $isPortalAccount ? 'ota-account-card__body' : 'card-body' }}">
            <form method="post" action="{{ route($routePrefix.'.store') }}" data-testid="saved-traveler-form">
                @csrf
                @include('dashboard.travelers._form', ['useAccountForm' => $isPortalAccount])
                @if ($isPortalAccount)
                    <div class="ota-account-form-actions">
                        <button type="submit" class="ota-account-btn ota-account-btn--primary">Save traveler</button>
                        <a href="{{ route($routePrefix.'.index') }}" class="ota-account-btn ota-account-btn--secondary">Cancel</a>
                    </div>
                @else
                    <button type="submit" class="btn btn-primary mt-3">Save traveler</button>
                @endif
            </form>
        </div>
    </div>
@endsection
