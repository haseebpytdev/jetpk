{{--
  Responsive table wrapper (new — Phase 0 gap T-1). Phase 1 · JETPK-DASHBOARD-UI-FOUNDATION

  Wrap an existing <table> so it is usable at 360px WITHOUT hiding any column, action,
  filter, sort, export, financial amount or booking status (hard rule from Phase 0).

    collapse="scroll" (default): horizontal scroll on small screens (safe for dense
        operational/finance tables — Internal Staff / Platform Admin).
    collapse="cards": table stacks to one card per row below md. For card mode, add
        data-label="Column name" to each <td> so labels show — see the manifest.

  Usage:
    <x-dashboard.responsive-table :scroll-hint="true">
        <table class="ota-account-table"> ... </table>
    </x-dashboard.responsive-table>
--}}
@props([
    'collapse' => 'scroll',     // scroll | cards
    'scrollHint' => false,
    'hint' => 'Swipe horizontally to see more →',
])

<div
    {{ $attributes->class(['ota-dashboard-table-wrap']) }}
    data-collapse="{{ $collapse === 'cards' ? 'cards' : 'scroll' }}"
    @if ($scrollHint) data-scroll-hint="true" @endif
    role="region"
    tabindex="0"
    aria-label="{{ $attributes->get('aria-label', 'Data table') }}"
>
    {{ $slot }}
</div>
@if ($scrollHint)
    <p class="ota-dashboard-table-scroll-hint" aria-hidden="true">{{ $hint }}</p>
@endif
