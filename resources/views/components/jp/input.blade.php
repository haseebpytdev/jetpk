@props([
    'type' => 'text',
])

<input
  type="{{ $type }}"
  {{ $attributes->class(['jp-input']) }}
/>
