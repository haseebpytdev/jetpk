{{--
  Session flash renderer. Phase 1 · JETPK-DASHBOARD-UI-FOUNDATION
  Reuses the existing <x-jp.alert> primitive (canonical alert) — does NOT introduce a
  new alert style. Renders standard session keys + validation summary. Place near the
  top of page content: <x-dashboard.flash />

  NOTE: this reads only conventional session keys; it changes no controller behaviour.
  If a page already renders its own flash, do not double-render.
--}}
@props([
    'showValidation' => true,
])

@php
    $map = [
        'success' => 'success',
        'status'  => 'success',
        'error'   => 'danger',
        'danger'  => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];
@endphp

@if (session()->hasAny(array_keys($map)) || ($showValidation && $errors->any()))
    <div class="ota-dashboard-flash" role="status" aria-live="polite">
        @foreach ($map as $key => $variant)
            @if (session()->has($key))
                <x-jp.alert :variant="$variant">{{ session($key) }}</x-jp.alert>
            @endif
        @endforeach

        @if ($showValidation && $errors->any())
            <x-jp.alert variant="danger">
                <strong>{{ __('Please review the highlighted fields.') }}</strong>
                <ul class="ota-dashboard-flash__errors">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-jp.alert>
        @endif
    </div>
@endif
