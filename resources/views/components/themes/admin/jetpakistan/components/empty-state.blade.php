@props([
    'title' => 'Nothing here yet',
    'message' => '',
    'description' => '',
    'icon' => '',
    'actionLabel' => '',
    'actionUrl' => '',
    'actionRoute' => '',
    'variant' => 'default',
])

@php
    $body = trim((string) ($description !== '' ? $description : $message));
    $variantClass = match ((string) $variant) {
        'compact', 'small' => 'jp-empty--compact',
        'inline' => 'jp-empty--inline',
        default => '',
    };
    $actionHref = trim((string) $actionUrl);
    if ($actionHref === '' && $actionRoute !== '' && \Illuminate\Support\Facades\Route::has($actionRoute)) {
        $actionHref = route($actionRoute);
    }
@endphp

<div {{ $attributes->merge(['class' => 'jp-empty '.$variantClass]) }}>
    @if (trim((string) $icon) !== '')
        <div class="jp-empty__icon" aria-hidden="true">{!! $icon !!}</div>
    @endif
    <div class="jp-empty__title">{{ $title }}</div>
    @if ($body !== '')
        <p class="jp-empty__msg">{{ $body }}</p>
    @endif
    @isset($action)
        <div class="jp-empty__action">{{ $action }}</div>
    @elseif (trim((string) $actionLabel) !== '' && $actionHref !== '')
        <div class="jp-empty__action">
            <a href="{{ $actionHref }}" class="jp-btn jp-btn--secondary jp-btn--sm">{{ $actionLabel }}</a>
        </div>
    @endif
</div>
