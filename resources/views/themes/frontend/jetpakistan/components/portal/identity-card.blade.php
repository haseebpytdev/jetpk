@php
    $eyebrow = ($variant ?? 'agent') === 'agent' ? 'Agent portal' : 'My account';
    $subtitle = ($variant ?? 'agent') === 'agent'
        ? 'Bookings, wallet, and agency tools'
        : 'Trips, payments, and support';
@endphp
<div class="jp-portal__identity">
  <span class="jp-portal__avatar" aria-hidden="true">{{ $initial ?? 'U' }}</span>
  <span>
    <span class="jp-portal__eyebrow">{{ $eyebrow }}</span>
    <strong>{{ $name ?? 'User' }}</strong>
    <span>{{ $subtitle }}</span>
  </span>
</div>
