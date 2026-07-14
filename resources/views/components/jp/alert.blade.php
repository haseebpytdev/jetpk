@props(['variant' => 'info'])

<div {{ $attributes->class(['jp-alert', 'jp-alert--'.$variant]) }} role="alert">
  {{ $slot }}
</div>
