@php
    $toneClass = match ($tone ?? '') {
        'green', 'success' => 'jp-portal-badge--green',
        'amber', 'warn', 'warning' => 'jp-portal-badge--amber',
        'blue', 'info' => 'jp-portal-badge--blue',
        'danger', 'error' => 'jp-portal-badge--danger',
        default => '',
    };
@endphp
<span class="jp-portal-badge {{ $toneClass }}">{{ $label ?? '' }}</span>
