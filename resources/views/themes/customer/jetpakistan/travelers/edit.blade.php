{{-- JP-PORTAL-3 TASK 2 · Customer travelers — edit (JetPK theme)
     Resolved by client_view('travelers.edit', 'customer'); dashboard.travelers.edit remains the
     fallback for default/Parwaaz clients and is NOT modified.

     Preserved verbatim from the legacy $isPortalAccount branch of dashboard.travelers.edit:
       • controller vars: $traveler, $routePrefix, $countries
       • title 'Edit traveler'; account_title 'Edit traveler'
       • account_subtitle = $traveler->fullName()  (dynamic — NOT a static string)
       • incomplete warning: @unless($traveler->isComplete()) with the exact legacy copy
         "This profile is incomplete. Add missing fields before ticketing."
         and data-testid traveler-edit-completeness-warning
       • Back action -> $routePrefix.'.index'
       • form: method="post" + @method('PATCH') action=route($routePrefix.'.update', $traveler)
       • @csrf preserved
       • actions: "Save changes" submit + "Cancel" -> index
       • data-testids: saved-traveler-form-card, saved-traveler-form
     Field set delegated to the shared JetPK portal traveler-form component ($traveler->exists is
     true here, so it renders the edit-only masked hint + "leave blank" placeholder).
--}}
@extends(client_layout('customer-account', 'customer'))

@section('title', 'Edit traveler')

@section('account_title', 'Edit traveler')
@section('account_subtitle', $traveler->fullName())

@section('account_actions')
    <a href="{{ route($routePrefix.'.index') }}" class="jp-btn jp-btn--ghost">Back</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('customer.dashboard')],
        ['label' => 'Travelers', 'href' => route($routePrefix.'.index')],
        ['label' => 'Edit traveler'],
    ]" />

    @unless ($traveler->isComplete())
        <x-jp.alert variant="warning" data-testid="traveler-edit-completeness-warning">
            This profile is incomplete. Add missing fields before ticketing.
        </x-jp.alert>
    @endunless

    <x-jp.card class="jp-portal__panel" data-testid="saved-traveler-form-card">
        <form method="post" action="{{ route($routePrefix.'.update', $traveler) }}" data-testid="saved-traveler-form">
            @csrf
            @method('PATCH')
            @include('themes.frontend.jetpakistan.components.portal.traveler-form', [
                'traveler' => $traveler,
                'countries' => $countries,
            ])
            <div class="jp-form__actions">
                <button type="submit" class="jp-btn jp-btn--primary">Save changes</button>
                <a href="{{ route($routePrefix.'.index') }}" class="jp-btn jp-btn--ghost">Cancel</a>
            </div>
        </form>
    </x-jp.card>
@endsection
