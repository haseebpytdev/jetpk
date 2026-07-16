@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Profile settings')

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

<div class="jp-portal-page-head">
    <div>
        <h1>Profile settings</h1>
        <p>Manage your personal account details. Agency settings are managed separately.</p>
    </div>
</div>

@include('themes.frontend.jetpakistan.components.portal.profile-settings', [
    'user' => $user,
    'userProfile' => $userProfile,
    'dashboardUrl' => $dashboardUrl,
    'countries' => $countries,
])
@endsection
