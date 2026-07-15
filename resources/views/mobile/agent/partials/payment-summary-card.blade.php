@props([
    'booking',
    'summary',
])

@php
    $currency = $summary['currency'] ?? ($booking->currency ?? 'PKR');
    $proofAction = route('agent.bookings.payment-proof', $booking);
@endphp

<section class="ota-mobile-agent__card" id="payment" data-testid="ota-mobile-agent-payment">
    <h2 class="ota-mobile-agent__card-title">Payment</h2>
    <div class="ota-mobile-agent__card-head">
        <span class="ota-mobile-agent__muted">Status</span>
        @include('mobile.agent.partials.agent-status-pill', ['label' => $summary['status_label'] ?? 'Unknown'])
    </div>

    <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
        <div>
            <dt>Total</dt>
            <dd class="ota-mobile-agent__amount">{{ number_format((float) $summary['total'], 2) }} {{ $currency }}</dd>
        </div>
        <div>
            <dt>Paid</dt>
            <dd class="ota-mobile-agent__amount">{{ number_format((float) $summary['amount_paid'], 2) }} {{ $currency }}</dd>
        </div>
        <div>
            <dt>Balance due</dt>
            <dd class="ota-mobile-agent__amount">{{ number_format((float) $summary['balance_due'], 2) }} {{ $currency }}</dd>
        </div>
        <div>
            <dt>Proof status</dt>
            <dd>{{ str_replace('_', ' ', $summary['proof_status']) }}</dd>
        </div>
    </dl>

    @if (! empty($summary['status_meaning']))
        <p class="ota-mobile-agent__note">{{ $summary['status_meaning'] }}</p>
    @endif

    @if ($summary['show_verified'])
        @include('mobile.components.alert', ['type' => 'success', 'message' => 'Payment verified. No further action required.'])
    @elseif ($summary['show_awaiting_review'])
        @include('mobile.components.alert', ['type' => 'info', 'message' => 'Payment proof submitted — awaiting verification.'])
    @elseif ($summary['show_rejected_resubmit'])
        @include('mobile.components.alert', ['type' => 'danger', 'message' => 'Payment proof was rejected. You may submit a new proof below or contact support.'])
    @endif

    @if ($summary['can_upload_proof'])
        <form method="post" action="{{ $proofAction }}" class="ota-mobile-agent__form" data-testid="agent-payment-proof-form">
            @csrf
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="payment_method">Method</label>
                <select name="method" id="payment_method" class="ota-mobile-agent__input" required>
                    @foreach (['bank_transfer', 'cash', 'card_manual', 'easypaisa', 'jazzcash', 'other'] as $m)
                        <option value="{{ $m }}">{{ str_replace('_', ' ', $m) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="payment_amount">Amount</label>
                <input type="number" step="0.01" name="amount" id="payment_amount" class="ota-mobile-agent__input" required
                    value="{{ $summary['balance_due'] > 0 ? $summary['balance_due'] : '' }}">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="payment_reference">Reference (optional)</label>
                <input type="text" name="payment_reference" id="payment_reference" class="ota-mobile-agent__input" placeholder="Transfer reference">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="payment_notes">Notes (optional)</label>
                <textarea name="notes" id="payment_notes" rows="2" class="ota-mobile-agent__input"></textarea>
            </div>
            <button class="ota-mobile-agent__btn ota-mobile-agent__btn--primary ota-mobile-agent__btn--block" type="submit">Submit payment proof</button>
        </form>
    @elseif (! $summary['show_verified'] && ! $summary['show_awaiting_review'])
        <p class="ota-mobile-agent__note">No payment action required at this time.</p>
    @endif
</section>

<section class="ota-mobile-agent__card" data-testid="ota-mobile-agent-documents">
    <h2 class="ota-mobile-agent__card-title">Documents</h2>
    @php $hasAnyDoc = false; @endphp
    @foreach ($summary['documents'] as $docRow)
        <div class="ota-mobile-agent__doc-row">
            <div class="ota-mobile-agent__doc-head">
                <span>{{ $docRow['label'] }}</span>
                @if ($docRow['available'])
                    @php $hasAnyDoc = true; @endphp
                    <span class="ota-mobile-agent__pill ota-mobile-agent__pill--positive">Available</span>
                @else
                    <span class="ota-mobile-agent__pill ota-mobile-agent__pill--muted">Not available</span>
                @endif
            </div>
            @if ($docRow['available'] && ! empty($docRow['agent_note']))
                <p class="ota-mobile-agent__note">{{ $docRow['agent_note'] }}</p>
            @elseif (! $docRow['available'] && ! empty($docRow['unavailable_message']))
                <p class="ota-mobile-agent__note">{{ $docRow['unavailable_message'] }}</p>
            @endif
        </div>
    @endforeach
    @if (! $hasAnyDoc)
        <p class="ota-mobile-agent__note">No customer documents are available yet for this booking.</p>
    @endif
</section>
