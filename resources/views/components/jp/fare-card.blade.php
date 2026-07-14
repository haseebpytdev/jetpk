{{-- Fare card (mini boarding pass). Drive from your live search results. --}}
@props(['airline', 'from', 'to', 'depart', 'arrive', 'dur', 'stops' => 0, 'price'])
<div class="card fare tilt">
  <div class="f-top">
    <div class="airline"><span class="lg"><x-jp.icon name="plane" /></span>{{ $airline }}</div>
    <span class="tag">{{ (int) $stops === 0 ? 'Direct' : $stops . ' stop' }}</span>
  </div>
  <div class="route">
    <div class="pt"><div class="code">{{ $from }}</div><div class="time">{{ $depart }}</div></div>
    <div class="arc">
      <span class="dur">{{ $dur }}</span>
      <svg viewBox="0 0 120 30" preserveAspectRatio="none"><path d="M2 26 Q60 -6 118 26"/><g transform="translate(96,7) rotate(28)"><path class="plane" d="M9 6 5 5 3 2 2.4 2.2 3.6 5 2 5.6 1 5 .6 5.2 1.4 7 .6 8.7l.4.3L3 8l2 .7 2 2.3.6-.2L7 9l2.5-.3c.5 0 .7-.3.7-.7s-.2-1-1.2-1z" stroke="none" fill="currentColor"/></g></svg>
    </div>
    <div class="pt r"><div class="code">{{ $to }}</div><div class="time">{{ $arrive }}</div></div>
  </div>
  <div class="perf"></div>
  <div class="f-bot">
    <div class="pr"><small>Round trip / person</small><b><span class="cur">PKR</span>{{ number_format($price) }}</b></div>
    <button class="btn btn-gold pick">Hold fare</button>
  </div>
</div>
