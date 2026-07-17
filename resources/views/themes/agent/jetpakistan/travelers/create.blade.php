{{-- JP-PORTAL-3 TASK 3 · Agent / Agent Staff travelers — create (JetPK theme)
     Resolved by client_view('travelers.create', 'agent'); dashboard.travelers.create remains the
     fallback for standalone mode is off\.
     Agent Staff reuses this view; route access is gated by
     agent.permission:TravelersManage + platform.module:saved_travelers.

     Preserved verbatim from the legacy $isPortalAccount branch of dashboard.travelers.create:
       • controller vars: $traveler (new SavedTraveler), $routePrefix, $countries
       • title/account_title 'Add traveler'
       • account_subtitle 'Save passenger details for faster future bookings.'
       • form: method="post" action=route($routePrefix.'.store'), @csrf, NO @method spoof
       • actions: "Save traveler" submit + "Cancel"/Back -> $routePrefix.'.index'
       • data-testids: saved-traveler-form-card, saved-traveler-form
     Field set delegated to the shared JetPK portal traveler-form component.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Add traveler')

@section('account_title', 'Add traveler')
@section('account_subtitle', 'Save passenger details for faster future bookings.')

@section('account_actions')
    <a href="{{ route($routePrefix.'.index') }}" class="jp-btn jp-btn--ghost">Back</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Saved travelers', 'href' => route($routePrefix.'.index')],
        ['label' => 'Add traveler'],
    ]" />

    <x-jp.card class="jp-portal__panel" data-testid="saved-traveler-form-card">
        <form method="post" action="{{ route($routePrefix.'.store') }}" data-testid="saved-traveler-form">
            @csrf
            @include('themes.frontend.jetpakistan.components.portal.traveler-form', [
                'traveler' => $traveler,
                'countries' => $countries,
            ])
            <div class="jp-form__actions">
                <button type="submit" class="jp-btn jp-btn--primary">Save traveler</button>
                <a href="{{ route($routePrefix.'.index') }}" class="jp-btn jp-btn--ghost">Cancel</a>
            </div>
        </form>
    </x-jp.card>
@endsection
