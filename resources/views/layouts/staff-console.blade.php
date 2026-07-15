{{--
  layouts/staff-console.blade.php — NEW Phase 1 ADOPTION-TARGET scaffold.
  JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4

  Purpose: show Internal Staff adopting the SHARED shell chrome (canonical shell grid,
  foundation tokens, :focus-visible system, page-header, flash, responsive tables) while
  keeping the EXISTING Tabler sidebar partial verbatim (dashboard-sidebar-staff), which
  preserves all staff permission gating + ui_preserve_route() + data-testid untouched.

  SCOPE NOTE: full visual parity of the Tabler sidebar inside the portal-style
  ota-dashboard-sidebar aside is completed during Phase 7 (see
  PHASE1-DASHBOARD-DECOMPOSITION-PLAN.md). This scaffold is structurally complete but is
  NOT a drop-in replacement for the current layouts/dashboard monolith yet — wire it in
  per the decomposition plan. drawer=false because Staff mobile nav continues via the
  existing mechanism until the sidebar port lands.

  Page contract (for pages that adopt this layout):
    @section('content_body') … @endsection
    optional: @section('page_title'), @section('page_subtitle'), @section('page_actions')
--}}
@extends('layouts.frontend')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.40.0/dist/tabler-icons.min.css"/>
    <link rel="stylesheet" href="{{ ui_asset('css/ota-dashboard-foundation.css') }}" />
    {{-- Keep the existing staff/admin console stylesheet linked when integrating via the
         monolith decomposition so the Tabler sidebar styles resolve. --}}
@endpush

@section('content')
    <x-dashboard.shell
        role="staff"
        wrap-class="ota-account-page ota-account-page-wrap ota-staff-console ota-portal-console"
        inner-class="ota-account-wrap"
        nav-aria-label="Staff navigation"
        :drawer="false"
    >
        <x-slot:sidebar>
            @include('layouts.partials.dashboard-sidebar-staff')
        </x-slot:sidebar>

        @if (trim($__env->yieldContent('page_title')) !== '' || trim($__env->yieldContent('page_actions')) !== '')
            <x-dashboard.page-header
                :title="trim($__env->yieldContent('page_title'))"
                :subtitle="trim($__env->yieldContent('page_subtitle')) !== '' ? trim($__env->yieldContent('page_subtitle')) : null"
            >
                @hasSection('page_actions')
                    <x-slot:actions>@yield('page_actions')</x-slot:actions>
                @endif
            </x-dashboard.page-header>
        @endif

        <x-dashboard.flash />

        @yield('content_body')
    </x-dashboard.shell>
@endsection

@push('scripts')
    <script src="{{ ui_asset('js/ota-dashboard-foundation.js') }}" defer></script>
@endpush
