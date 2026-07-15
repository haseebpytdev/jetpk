{{--
  Canonical dashboard shell — the single shell all authenticated roles compose.
  Phase 1 · JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4

  Consolidates the near-identical customer-account / agent-portal shells and gives
  Internal Staff / Platform Admin a shared shell chrome. Reuses the EXISTING
  ota-dashboard-shell / ota-dashboard-sidebar / ota-dashboard-main structure
  (owned by ota-public.css) and adds only foundation chrome (drawer + focus system
  via ota-dashboard-foundation.css). Does NOT add a duplicate top header: the
  frontend layout (portal) and the admin/staff navbar already provide account chrome.

  Usage — structured nav (Customer / Agent):
    <x-dashboard.shell role="customer" wrap-class="ota-customer-dashboard ota-customer-portal"
        eyebrow="Customer Portal" :identity-name="$accountName" :nav-items="$items"
        nav-aria-label="Customer dashboard navigation" nav-testid="customer-account-subnav"
        mini-icon="ti-stars" mini-title="Travel account" mini-text="...">
        ...page content...
    </x-dashboard.shell>

  Usage — slot nav (Internal Staff / Platform Admin), drawer handled by existing layout:
    <x-dashboard.shell role="admin" wrap-class="ota-admin-dashboard" :drawer="false"
        nav-aria-label="Admin navigation">
        <x-slot:sidebar>@include('layouts.partials.dashboard-sidebar-admin')</x-slot:sidebar>
        ...page content...
    </x-dashboard.shell>
--}}
@props([
    'role' => 'customer',
    'wrapClass' => '',
    'container' => true,
    'navAriaLabel' => 'Dashboard navigation',
    'drawer' => true,               // off-canvas drawer for small screens (structured nav)
    'innerClass' => null,           // extra classes for the inner container (preserve existing hooks)
    // structured-nav sidebar config (ignored when a `sidebar` slot is supplied)
    'eyebrow' => null,
    'identityName' => null,
    'identityInitial' => null,
    'navItems' => [],
    'navTestid' => null,
    'miniIcon' => null,
    'miniTitle' => null,
    'miniText' => null,
])

@php
    $hasSidebarSlot = isset($sidebar) && trim($sidebar) !== '';
    $containerClass = trim(($container ? 'container ota-account-page-inner' : 'ota-account-page-inner').' '.(string) $innerClass);
@endphp

<div
    x-data="otaDashboardShell()"
    @keydown.escape.window="closeDrawer()"
    @class([
        'ota-dashboard-foundation',
        'ota-page-wrap',
        'ota-dashboard-shell',
        'ota-dashboard-shell--'.$role,
        $wrapClass,
    ])
    data-testid="dashboard-shell-{{ $role }}"
>
    <div class="{{ $containerClass }}">
        <div class="ota-dashboard-shell__grid">
            <aside class="ota-dashboard-sidebar" aria-label="{{ $navAriaLabel }}">
                @if ($hasSidebarSlot)
                    <x-dashboard.sidebar>{{ $sidebar }}</x-dashboard.sidebar>
                @else
                    <x-dashboard.sidebar
                        :items="$navItems"
                        :eyebrow="$eyebrow"
                        :identity-name="$identityName"
                        :identity-initial="$identityInitial"
                        :nav-aria-label="$navAriaLabel"
                        :nav-testid="$navTestid"
                        :mini-icon="$miniIcon"
                        :mini-title="$miniTitle"
                        :mini-text="$miniText"
                    />
                @endif
            </aside>

            <main class="ota-dashboard-main" id="ota-dashboard-main">
                @if ($drawer)
                    <button
                        type="button"
                        class="ota-dashboard-topbar__menu ota-dashboard-navtoggle"
                        @click="openDrawer()"
                        aria-label="Open navigation"
                        aria-controls="ota-dashboard-drawer-{{ $role }}"
                        :aria-expanded="drawerOpen ? 'true' : 'false'"
                    >
                        <i class="ti ti-menu-2" aria-hidden="true"></i>
                    </button>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @if ($drawer && ! $hasSidebarSlot)
        {{-- Off-canvas drawer mirrors the sidebar for small screens (Customer / Agent).
             Distinct data-testid suffix so it never collides with the inline sidebar. --}}
        <template x-if="drawerOpen">
            <div>
                <div class="ota-dashboard-drawer-overlay" @click="closeDrawer()" x-transition.opacity></div>
                <aside
                    id="ota-dashboard-drawer-{{ $role }}"
                    class="ota-dashboard-drawer"
                    aria-label="{{ $navAriaLabel }}"
                    x-ref="drawer"
                    x-transition
                >
                    <div class="ota-dashboard-drawer__head">
                        <strong>Menu</strong>
                        <button type="button" class="ota-dashboard-drawer__close" @click="closeDrawer()" aria-label="Close navigation">
                            <i class="ti ti-x" aria-hidden="true"></i>
                        </button>
                    </div>
                    <x-dashboard.sidebar
                        :items="$navItems"
                        :eyebrow="$eyebrow"
                        :identity-name="$identityName"
                        :identity-initial="$identityInitial"
                        :nav-aria-label="$navAriaLabel"
                        :nav-testid="$navTestid ? $navTestid.'-drawer' : null"
                        :mini-icon="$miniIcon"
                        :mini-title="$miniTitle"
                        :mini-text="$miniText"
                    />
                </aside>
            </div>
        </template>
    @endif
</div>
