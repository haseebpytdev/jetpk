@props([
    'href',
    'label',
    'icon' => 'ti-bolt',
    'testKey' => '',
])

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'ota-dash-shortcut',
        'data-testid' => $testKey !== '' ? 'ota-quick-action-'.$testKey : null,
    ]) }}
>
    <i class="ti {{ $icon }}"></i>
    <span>{{ $label }}</span>
</a>
