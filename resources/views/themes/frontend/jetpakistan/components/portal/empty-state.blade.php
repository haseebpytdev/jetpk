<div class="jp-portal-empty">
    <p class="jp-portal-empty__title">{{ $title ?? 'Nothing here yet' }}</p>
    @if (! empty($message))
        <p class="jp-portal-empty__msg">{{ $message }}</p>
    @endif
    @if (! empty($actionUrl) && ! empty($actionLabel))
        <p style="margin-top:var(--sp-4)">
            <a href="{{ $actionUrl }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">{{ $actionLabel }}</a>
        </p>
    @endif
</div>
