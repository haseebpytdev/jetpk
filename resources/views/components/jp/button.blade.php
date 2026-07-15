@props([
    'variant' => 'primary',
    'type' => 'button',
    'block' => false,
])

<button
  type="{{ $type }}"
  {{ $attributes->class([
    'jp-btn',
    'jp-btn--'.$variant,
    'jp-btn--block' => $block,
  ]) }}
>{{ $slot }}</button>
