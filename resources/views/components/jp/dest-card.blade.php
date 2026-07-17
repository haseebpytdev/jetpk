{{-- Trending destination card --}}
@props(['variant', 'country', 'city', 'price' => null, 'priceLabel' => null, 'image' => null, 'href' => null, 'alt' => null, 'badge' => null, 'text' => null])
@php
  $tag = $href ? 'a' : 'div';
  $label = $priceLabel ?? (($price !== null && (int) $price > 0) ? 'PKR '.number_format((int) $price) : \App\Support\Client\JetpkHomepageFareDisplay::neutralAvailabilityLabel());
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="dest tilt {{ $variant }} @if($href) dest--link @endif" @if($image) style="--jp-dest-image: url('{{ e($image) }}')" @endif aria-label="{{ $alt ?: ($city.' destination') }}">
  <div class="bg"></div>
  @if ($badge)<span class="badge">{{ $badge }}</span>@endif
  <div class="country">{{ $country }}</div>
  <h3>{{ $city }}</h3>
  @if ($text)<div class="dest-text">{{ $text }}</div>@endif
  <div class="from">from <b>{{ $label }}</b></div>
</{{ $tag }}>
