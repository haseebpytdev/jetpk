{{--
  layouts/admin-console.blade.php — NEW Phase 1 ADOPTION-TARGET scaffold.
  JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4

  Purpose: show Platform Admin adopting the SHARED shell chrome while keeping the
  EXISTING Tabler admin sidebar partial verbatim (dashboard-sidebar-admin) — preserving
  its collapsible groups, active-state logic, module gating, data-bs-toggle and
  ui_preserve_route() exactly.

  SCOPE NOTE: the admin sidebar is the most complex nav in the app (collapsible groups +
  nested submenus). Its full port onto the canonical sidebar is staged across Phases
  8–10 (see PHASE1-DASHBOARD-DECOMPOSITION-PLAN.md). This scaffold is structurally
  complete but NOT a drop-in replacement for the current layouts/dashboard monolith yet.
  drawer=false because admin mobile nav continues via the existing Tabler offcanvas until
  the port lands. Bootstrap collapse JS (already loaded by the admin console) is required
  for the sidebar groups to toggle.

  Page contract:
    @section('content_body') … @endsection
    optional: @section('page_title'), @section('page_subtitle'), @section('page_actions')
--}}
@extends('layouts.frontend')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.40.0/dist/tabler-icons.min.css"/>
    <link rel="stylesheet" href="{{ ui_asset('css/ota-dashboard-foundation.css') }}" />
    {{-- Keep ota-admin-console.css (and Tabler/Bootstrap JS) linked when integrating via
         the monolith decomposition so the admin sidebar groups render + toggle. --}}
@endpush

@section('content')
    <x-dashboard.shell
        role="admin"
        wrap-class="ota-account-page ota-account-page-wrap ota-admin-console ota-portal-console"
        inner-class="ota-account-wrap"
        nav-aria-label="Admin navigation"
        :drawer="false"
    >
        <x-slot:sidebar>
            @include('layouts.partials.dashboard-sidebar-admin')
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
