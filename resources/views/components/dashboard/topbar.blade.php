{{--
  Canonical dashboard topbar (OPTIONAL). Phase 1 · JETPK-DASHBOARD-UI-FOUNDATION

  Provided as the target top-bar for the DECOMPOSED Internal Staff / Platform Admin
  shell (see PHASE1-DASHBOARD-DECOMPOSITION-PLAN.md). It is NOT wired into Customer /
  Agent (their frontend layout already renders the site header + account menu) and is
  NOT wired into the current admin/staff monolith (which still renders its Tabler
  navbar). Adopt it only when replacing that navbar, to avoid duplicate chrome.

  Must live inside a <x-dashboard.shell> Alpine scope (uses openDrawer()).
  Reuses the existing role-aware <x-account-dropdown> for the profile menu — no new
  profile menu is introduced. Notifications are shown only if the caller passes a count
  and the `notifications` module is enabled for the tenant.
--}}
@props([
    'notificationsHref' => null,
    'notificationsCount' => null,
    'profileVariant' => 'desktop',
])

<header class="ota-dashboard-topbar" role="banner">
    <button
        type="button"
        class="ota-dashboard-topbar__menu"
        @click="openDrawer()"
        aria-label="Open navigation"
        :aria-expanded="drawerOpen ? 'true' : 'false'"
    >
        <i class="ti ti-menu-2" aria-hidden="true"></i>
    </button>

    <div class="ota-dashboard-topbar__spacer">{{ $slot }}</div>

    <div class="ota-dashboard-topbar__actions">
        @isset($actions)
            {{ $actions }}
        @endisset

        @if ($notificationsHref !== null)
            <a class="ota-dashboard-topbar__icon-btn" href="{{ $notificationsHref }}" aria-label="Notifications">
                <i class="ti ti-bell" aria-hidden="true"></i>
                @if ($notificationsCount !== null && (int) $notificationsCount > 0)
                    <span class="ota-dashboard-topbar__badge" aria-hidden="true">{{ (int) $notificationsCount > 99 ? '99+' : (int) $notificationsCount }}</span>
                    <span class="visually-hidden">{{ $notificationsCount }} unread notifications</span>
                @endif
            </a>
        @endif

        <x-account-dropdown :variant="$profileVariant" />
    </div>
</header>
