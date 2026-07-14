{{--
  Canonical dashboard page header. Phase 1 · JETPK-DASHBOARD-UI-FOUNDATION
  Extends the inline `ota-account-header` pattern and adds breadcrumbs.
  Slots: `breadcrumbs` (optional, use <x-dashboard.breadcrumbs>), `actions` (optional).
--}}
@props([
    'title',
    'pretitle' => null,
    'subtitle' => null,
])

<div {{ $attributes->class(['ota-dashboard-page-header']) }}>
    <div class="ota-dashboard-page-header__main">
        @isset($breadcrumbs)
            {{ $breadcrumbs }}
        @endisset
        @if ($pretitle !== null && trim((string) $pretitle) !== '')
            <p class="ota-dashboard-page-header__pretitle">{{ $pretitle }}</p>
        @endif
        <h1 class="ota-dashboard-page-header__title">{{ $title }}</h1>
        @if ($subtitle !== null && trim((string) $subtitle) !== '')
            <p class="ota-dashboard-page-header__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="ota-dashboard-page-header__actions">{{ $actions }}</div>
    @endisset
</div>
