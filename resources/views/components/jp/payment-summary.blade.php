@props([
    'currency' => 'PKR',
    'total' => null,
    'lines' => [],
])

<aside {{ $attributes->class(['jp-pay-summary']) }} aria-label="Payment summary">
  <h2 class="jp-pay-summary__title">Payment summary</h2>
  @if(count($lines) > 0)
    <dl class="jp-pay-summary__lines">
      @foreach($lines as $line)
        <div class="jp-pay-summary__row">
          <dt>{{ $line['label'] ?? '' }}</dt>
          <dd>{{ $line['amount'] ?? '' }}</dd>
        </div>
      @endforeach
    </dl>
  @else
    {{ $slot }}
  @endif
  @if($total !== null)
    <div class="jp-pay-summary__total">
      <span>Total</span>
      <strong>{{ $currency }} {{ $total }}</strong>
    </div>
  @endif
</aside>
