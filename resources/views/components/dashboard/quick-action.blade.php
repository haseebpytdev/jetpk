@props([
    'href',
    'icon',
    'title',
    'helper' => '',
])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'ota-quick-action-link text-reset text-decoration-none']) }}>
    <div class="card h-100 ota-quick-action-card">
        <div class="card-body">
            <div class="ota-quick-icon"><i class="ti {{ $icon }}"></i></div>
            <div class="fw-bold">{{ $title }}</div>
            @if ($helper !== '')
                <div class="text-secondary small mt-1">{{ $helper }}</div>
            @endif
        </div>
    </div>
</a>
