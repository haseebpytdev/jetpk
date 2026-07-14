@props([
    'airline' => null,
    'price' => null,
    'currency' => 'PKR',
    'stops' => null,
    'duration' => null,
])

<article {{ $attributes->class(['jp-result-card']) }}>
  <header class="jp-result-card__head">
    @if($airline)
      <span class="jp-result-card__airline">{{ $airline }}</span>
    @endif
    @if($price !== null)
      <span class="jp-result-card__price">{{ $currency }} {{ $price }}</span>
    @endif
  </header>
  <div class="jp-result-card__body">
    {{ $slot }}
  </div>
  @if($stops !== null || $duration)
    <footer class="jp-result-card__meta">
      @if($duration)<span>{{ $duration }}</span>@endif
      @if($stops !== null)<span>{{ $stops === 0 ? 'Non-stop' : $stops.' stop'.($stops > 1 ? 's' : '') }}</span>@endif
    </footer>
  @endif
</article>
