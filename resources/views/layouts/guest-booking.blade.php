@extends('layouts.frontend')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.40.0/dist/tabler-icons.min.css"/>
@endpush

@section('content')
    <div class="ota-page-wrap ota-account-page ota-account-page-wrap ota-guest-booking-page">
        <div class="container ota-account-page-inner ota-account-wrap">
            <header class="ota-account-header">
                <div class="ota-account-header-main">
                    @hasSection('guest_pretitle')
                        <p class="ota-account-pretitle">@yield('guest_pretitle')</p>
                    @endif
                    <h1 class="ota-account-title">@yield('guest_title')</h1>
                    @hasSection('guest_subtitle')
                        <p class="ota-account-subtitle">@yield('guest_subtitle')</p>
                    @endif
                </div>
                @hasSection('guest_actions')
                    <div class="ota-account-header-actions">@yield('guest_actions')</div>
                @endif
            </header>

            @yield('guest_content')
        </div>
    </div>
@endsection
