@props(['booking'])

@php
    $gatewayTx = \App\Support\Payments\PaymentGatewayTransactionPresenter::latestForBooking($booking);
@endphp

@if ($gatewayTx)
    <div class="card mb-3" data-testid="booking-gateway-payment-status">
        <div class="card-header">
            <h3 class="card-title mb-0">Online gateway payment</h3>
        </div>
        <div class="card-body small">
            <div class="row g-2">
                <div class="col-sm-6"><strong>Gateway</strong><div>{{ strtoupper($gatewayTx['gateway']) }}</div></div>
                <div class="col-sm-6"><strong>Amount</strong><div>{{ number_format($gatewayTx['amount'], 2) }} {{ $gatewayTx['currency'] }}</div></div>
                <div class="col-sm-6"><strong>Status</strong><div class="text-capitalize">{{ str_replace('_', ' ', $gatewayTx['status']) }}</div></div>
                <div class="col-sm-6"><strong>Transaction ref</strong><div><code>{{ $gatewayTx['client_transaction_id'] }}</code></div></div>
                @if (! empty($gatewayTx['gateway_order_id']))
                    <div class="col-sm-6"><strong>Gateway order</strong><div><code>{{ $gatewayTx['gateway_order_id'] }}</code></div></div>
                @endif
                @if (! empty($gatewayTx['paid_at']))
                    <div class="col-sm-6"><strong>Paid at</strong><div>{{ $gatewayTx['paid_at'] }}</div></div>
                @endif
                @if (! empty($gatewayTx['verified_at']))
                    <div class="col-sm-6"><strong>Verified at</strong><div>{{ $gatewayTx['verified_at'] }}</div></div>
                @endif
                @if (! empty($gatewayTx['masked_card']))
                    <div class="col-sm-6"><strong>Card</strong><div>{{ $gatewayTx['masked_card'] }}</div></div>
                @endif
            </div>
        </div>
    </div>
@endif
