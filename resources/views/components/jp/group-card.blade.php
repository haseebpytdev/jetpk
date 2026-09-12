{{-- Group / Umrah package card --}}
@props(['variant', 'badge', 'gold' => false, 'title', 'meta', 'price', 'image' => null, 'href' => null])
@php
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="gcard tilt {{ $variant }} @if($href) gcard--link @endif" @if($image) style="--jp-gcard-image: url('{{ e($image) }}')" @endif>
  <div class="bg"></div>
  <span class="badge {{ $gold ? 'gold' : '' }}">
    @if($gold)<x-jp.icon name="star" style="width:13px;height:13px" />@endif
    {{ $badge }}
  </span>
  <h3>{{ $title }}</h3>
  <div class="meta">{{ $meta }}</div>
  <div class="g-foot">
    <div class="price"><small>From / person</small><b>PKR {{ number_format((int) $price) }}</b></div>
    <span class="go"><x-jp.icon name="arrow-right" /></span>
  </div>
</{{ $tag }}>
