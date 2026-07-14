{{--
  Canonical dashboard sidebar (inner content).
  Phase 1 · JETPK-DASHBOARD-UI-FOUNDATION

  Two modes:
    1. Structured nav (Customer / Agent): pass :items (already permission/module gated
       by the caller) + identity/mini props. Renders the existing ota-dashboard-sidebar
       identity / nav / mini markup verbatim so no visual regression.
    2. Slot passthrough (Internal Staff / Platform Admin): pass the existing sidebar
       partial as the default slot; it is rendered as-is (preserves their Tabler nav,
       gating, data-testid and ui_preserve_route()).

  The <aside> wrapper is owned by <x-dashboard.shell> / <x-dashboard.mobile-drawer>.
  All URLs use client_route(); visibility uses PlatformModuleGate + role permission
  (computed by the caller and passed in $items).
--}}
@props([
    'items' => [],          // array of ['href','icon','label','match', 'testid' => null]
    'eyebrow' => null,
    'identityName' => null,
    'identityInitial' => null,
    'navAriaLabel' => 'Sections',
    'navTestid' => null,
    'miniIcon' => null,
    'miniTitle' => null,
    'miniText' => null,
])

@if (trim($slot) !== '')
    {{-- Slot mode: render the caller-provided sidebar partial unchanged --}}
    {{ $slot }}
@else
    @if ($identityName !== null)
        <div class="ota-dashboard-sidebar__identity">
            <span class="ota-dashboard-sidebar__avatar">{{ $identityInitial ?? mb_strtoupper(mb_substr(trim((string) $identityName) ?: '•', 0, 1)) }}</span>
            <span>
                @if ($eyebrow !== null)
                    <span class="ota-dashboard-sidebar__eyebrow">{{ $eyebrow }}</span>
                @endif
                <strong>{{ trim((string) $identityName) !== '' ? $identityName : 'Account' }}</strong>
            </span>
        </div>
    @endif

    <nav class="ota-dashboard-sidebar__nav" aria-label="{{ $navAriaLabel }}" @if ($navTestid) data-testid="{{ $navTestid }}" @endif>
        @foreach ($items as $item)
            <a
                href="{{ $item['href'] }}"
                @class([
                    'ota-dashboard-sidebar__link',
                    'is-active' => ! empty($item['match']) && request()->routeIs($item['match']),
                ])
                @if (! empty($item['testid'])) data-testid="{{ $item['testid'] }}" @endif
                @if (! empty($item['current'])) aria-current="page" @endif
            >
                @if (! empty($item['icon']))
                    <i class="ti {{ $item['icon'] }}" aria-hidden="true"></i>
                @endif
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($miniTitle !== null || $miniText !== null)
        <div class="ota-dashboard-sidebar__mini">
            @if ($miniIcon !== null)
                <div class="ota-dashboard-sidebar__mini-icon"><i class="ti {{ $miniIcon }}" aria-hidden="true"></i></div>
            @endif
            <div>
                @if ($miniTitle !== null)<strong>{{ $miniTitle }}</strong>@endif
                @if ($miniText !== null)<span>{{ $miniText }}</span>@endif
            </div>
        </div>
    @endif
@endif
