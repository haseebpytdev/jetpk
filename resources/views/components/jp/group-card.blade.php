{{-- Group / Umrah package card --}}
@props(['variant', 'badge', 'gold' => false, 'title', 'meta', 'price', 'image' => null, 'href' => null])
@php
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="gcard tilt {{ $variant }} @if($href) gcard--link @endif" @if($image) style="--jp-gcard-image: url('{{ e($image) }}')" @endif>
  <div class="bg"></div>
  <span class="badge {{ $gold ? 'gold' : '' }}">
    @if($gold)<svg viewBox="0 0 24 24" class="icon" style="width:13px;height:13px"><path d="M12 2 9.2 8.6 2 9.3l5.4 4.7L5.8 21 12 17.3 18.2 21l-1.6-7 5.4-4.7-7.2-.7z" stroke="none" fill="currentColor"/></svg>@endif
    {{ $badge }}
  </span>
  <h3>{{ $title }}</h3>
  <div class="meta">{{ $meta }}</div>
  <div class="g-foot">
    <div class="price"><small>From / person</small><b>PKR {{ number_format((int) $price) }}</b></div>
    <span class="go"><x-jp.icon name="arrow-right" /></span>
  </div>
</{{ $tag }}>
