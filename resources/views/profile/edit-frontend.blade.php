@extends(client_layout('customer-account', 'customer'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ota-public.css') }}?v=101" />
@endpush

@section('title', 'Profile settings')
@section('account_title', 'Profile settings')
@section('account_subtitle', 'Manage your account, contact, and travel details for faster bookings.')

@section('account_content')
    @include('profile.partials.universal-settings', [
        'user' => $user,
        'userProfile' => $userProfile,
        'dashboardUrl' => $dashboardUrl,
        'countries' => $countries,
    ])
@endsection
