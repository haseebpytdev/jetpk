@extends('layouts.customer-account')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ota-public.css') }}?v=101" />
    <link rel="stylesheet" href="{{ asset('css/jetpk-typography-authority.css') }}" />
@endpush

@section('title', 'Profile settings')
@section('account_title', 'Profile settings')
@section('account_subtitle', 'Manage your account, contact, and travel details for faster bookings.')

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('customer.dashboard')],
        ['label' => 'Profile settings'],
    ]" />

    @include('profile.partials.universal-settings', [
        'user' => $user,
        'userProfile' => $userProfile,
        'dashboardUrl' => $dashboardUrl,
        'countries' => $countries,
    ])
@endsection
