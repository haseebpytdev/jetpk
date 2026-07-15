@props([
    'caption' => null,
])

<div {{ $attributes->class(['jp-table-wrap']) }}>
  <table class="jp-table">
    {{ $slot }}
  </table>
  @if($caption)
    <p class="jp-table__caption">{{ $caption }}</p>
  @endif
</div>
