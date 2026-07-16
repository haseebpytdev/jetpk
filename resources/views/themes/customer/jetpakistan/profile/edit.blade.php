@extends(client_layout('customer-account', 'customer'))

@section('title', 'Profile settings')

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

<div class="jp-portal-page-head">
    <div>
        <h1>Profile settings</h1>
        <p>Manage your account, contact, and travel details for faster bookings.</p>
    </div>
</div>

@include('themes.frontend.jetpakistan.components.portal.profile-settings', [
    'user' => $user,
    'userProfile' => $userProfile,
    'dashboardUrl' => $dashboardUrl,
    'countries' => $countries,
])
@endsection
