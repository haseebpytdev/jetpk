@extends(client_layout('dashboard', auth()->user()?->isStaff() ? 'staff' : 'admin'))

@section('title', 'Profile settings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ota-public.css') }}?v=101" />
    <style>
        .page .ota-profile-page .ota-profile-wrap,
        .page .ota-profile-page .ota-profile-form,
        .page .ota-profile-page .ota-profile-main {
            min-width: 0;
            max-width: 100%;
        }
        .page .ota-profile-page .ota-profile-section-actions {
            width: 100%;
        }
        .page .ota-profile-page .ota-profile-section-actions.ota-r-action-bar .ota-btn,
        .page .ota-profile-page .ota-profile-save-btn,
        .page .ota-profile-page .ota-profile-password-btn {
            max-width: 100%;
            width: 100%;
        }
        @media (min-width: 576px) {
            .page .ota-profile-page .ota-profile-section-actions.ota-r-action-bar .ota-btn,
            .page .ota-profile-page .ota-profile-save-btn,
            .page .ota-profile-page .ota-profile-password-btn {
                width: auto;
            }
        }
    </style>
@endpush

@section('page-header')
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Account</div>
            <h1 class="page-title">Profile settings</h1>
            <div class="text-secondary mt-1">Manage your account, contact, and travel details for faster bookings.</div>
        </div>
    </div>
@endsection

@section('content')
    <div class="ota-profile-page">
        @include('profile.partials.universal-settings', [
            'user' => $user,
            'userProfile' => $userProfile,
            'dashboardUrl' => $dashboardUrl,
            'countries' => $countries,
        ])
    </div>
@endsection
