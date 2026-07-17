{{-- JP-PORTAL-3 TASK 3 · Agent / Agent Staff travelers — edit (JetPK theme)
     Resolved by client_view('travelers.edit', 'agent'); dashboard.travelers.edit remains the
     fallback for standalone mode is off\.
     Agent Staff reuses this view; route access is gated by
     agent.permission:TravelersManage + platform.module:saved_travelers.

     Preserved verbatim from the legacy $isPortalAccount branch of dashboard.travelers.edit:
       • controller vars: $traveler, $routePrefix, $countries
       • title/account_title 'Edit traveler'
       • account_subtitle = $traveler->fullName()  (dynamic — NOT a static string)
       • incomplete warning @unless($traveler->isComplete()) with exact legacy copy +
         data-testid traveler-edit-completeness-warning
       • form: method="post" + @method('PATCH') action=route($routePrefix.'.update', $traveler)
       • @csrf preserved
       • actions: "Save changes" submit + "Cancel"/Back -> $routePrefix.'.index'
       • data-testids: saved-traveler-form-card, saved-traveler-form
     Field set delegated to the shared JetPK portal traveler-form component.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Edit traveler')

@section('account_title', 'Edit traveler')
@section('account_subtitle', $traveler->fullName())

@section('account_actions')
    <a href="{{ route($routePrefix.'.index') }}" class="jp-btn jp-btn--ghost">Back</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Saved travelers', 'href' => route($routePrefix.'.index')],
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
