{{-- Popular route (compact) --}}
@props(['from', 'to', 'airlines', 'href' => null, 'badge' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="card rt @if($href) rt--link @endif">
  <div class="info">
    <div class="pair">{{ $from }} <span class="mini-plane"><x-jp.icon name="arrow-right" class="ic-xs" /></span> {{ $to }}</div>
    <div class="air">{{ $airlines }}</div>
    @if($badge)<div class="rt-badge">{{ $badge }}</div>@endif
  </div>
  <span class="go-sm"><x-jp.icon name="arrow-right" /></span>
</{{ $tag }}>
