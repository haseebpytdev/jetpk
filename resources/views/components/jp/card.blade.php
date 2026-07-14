@props(['title' => null, 'as' => 'section'])

<{{ $as }} {{ $attributes->class(['jp-card']) }}>
  @if($title)
    <h2 class="jp-card__title">{{ $title }}</h2>
  @endif
  {{ $slot }}
</{{ $as }}>
