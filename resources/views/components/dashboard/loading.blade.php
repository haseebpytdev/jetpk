{{--
  Loading / skeleton state (new — Phase 0 gap). Phase 1 · JETPK-DASHBOARD-UI-FOUNDATION
  type="spinner" → inline spinner + optional label.
  type="skeleton" → N shimmer lines (for async panels / lazy content).
  Respects prefers-reduced-motion (see ota-dashboard-foundation.css).
--}}
@props([
    'type' => 'spinner',      // spinner | skeleton
    'label' => 'Loading…',
    'lines' => 3,
])

@if ($type === 'skeleton')
    <div {{ $attributes->class(['ota-dashboard-skeleton']) }} role="status" aria-live="polite" aria-busy="true">
        @for ($i = 0; $i < max(1, (int) $lines); $i++)
            <span class="ota-dashboard-skeleton__line"></span>
        @endfor
        <span class="visually-hidden">{{ $label }}</span>
    </div>
@else
    <div {{ $attributes->class(['ota-dashboard-loading']) }} role="status" aria-live="polite" aria-busy="true">
        <span class="ota-dashboard-spinner" aria-hidden="true"></span>
        @if ($label !== null && trim((string) $label) !== '')
            <span>{{ $label }}</span>
        @endif
    </div>
@endif
