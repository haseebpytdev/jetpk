@props([
    'icon' => 'ti-inbox',
    'title',
    'help' => null,
])

<div {{ $attributes->merge(['class' => 'ota-empty-state']) }}>
    <div class="ota-empty-state-icon"><i class="ti {{ $icon }}"></i></div>
    <div class="ota-empty-state-title">{{ $title }}</div>
    @if ($help)
        <div class="ota-empty-state-help">{{ $help }}</div>
    @endif
    @if (isset($action))
        <div class="ota-empty-state-action">{{ $action }}</div>
    @endif
</div>
