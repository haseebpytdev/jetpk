{{-- JP-PORTAL-3 TASK 2 · Customer travelers — create (JetPK theme)
     Resolved by client_view('travelers.create', 'customer'); dashboard.travelers.create remains
     the fallback for default/Parwaaz clients and is NOT modified.

     Preserved verbatim from the legacy $isPortalAccount branch of dashboard.travelers.create:
       • controller vars: $traveler (new SavedTraveler), $routePrefix, $countries
       • title 'Add traveler'; account_title 'Add traveler'
       • account_subtitle 'Save passenger details for faster future bookings.'
       • Back action -> $routePrefix.'.index'
       • form: method="post" action=route($routePrefix.'.store'), @csrf, NO @method spoof
       • actions: "Save traveler" submit + "Cancel" -> index
       • data-testids: saved-traveler-form-card, saved-traveler-form
     Field set delegated to the shared JetPK portal traveler-form component, which reproduces the
     legacy _form useAccountForm===true branch exactly.
--}}
@extends(client_layout('customer-account', 'customer'))

@section('title', 'Add traveler')

@section('account_title', 'Add traveler')
@section('account_subtitle', 'Save passenger details for faster future bookings.')

@section('account_actions')
    <a href="{{ route($routePrefix.'.index') }}" class="jp-btn jp-btn--ghost">Back</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('customer.dashboard')],
        ['label' => 'Travelers', 'href' => route($routePrefix.'.index')],
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
