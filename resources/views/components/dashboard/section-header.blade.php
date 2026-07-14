@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'd-flex flex-wrap justify-content-between align-items-start gap-2']) }}>
    <div class="min-w-0">
        <h3 class="h5 fw-bold mb-0 ota-recent-head-title">{{ $title }}</h3>
        @if ($subtitle)
            <p class="text-secondary small mb-0 mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex-shrink-0">{{ $actions }}</div>
    @endisset
</div>
