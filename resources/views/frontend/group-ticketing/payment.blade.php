@extends(client_layout('frontend', 'frontend'))

@section('title', 'Complete payment')

@section('content')
    @php
        $checkoutSummary = is_array($checkoutSummary ?? null) ? $checkoutSummary : [];
    @endphp
    <div class="ota-book-wrap ota-checkout-page ota-checkout-page--group">
        <div class="ota-container ota-container-wide">
            @include('frontend.checkout.partials.shell', [
                'productLabel' => 'Group Ticketing',
                'title' => 'Complete payment',
                'lead' => 'Reference: <strong>'.e($booking->reference).'</strong>',
                'activeStep' => $activeStep ?? 'payment',
            ])

            @include('frontend.checkout.partials.timer-card', ['expiresAt' => $booking->expires_at])

            @error('payment')
                <div class="alert alert-warning">{{ $message }}</div>
            @enderror

            <div class="ota-checkout-grid ota-booking-layout">
                <div class="ota-checkout-main">
                    <form method="POST" action="{{ route('group-ticketing.booking.payment.submit', $booking) }}" enctype="multipart/form-data" class="ota-checkout-form">
                        @csrf

                        @include('frontend.checkout.partials.payment-methods', [
                            'statusLabel' => 'Awaiting manual payment submission',
                        ])

                        <div class="ota-checkout-card ota-checkout-card--section">
                            <h2 class="ota-checkout-section-title">Payment details</h2>
                            <p class="ota-checkout-section-hint">
                                Amount due: <strong>{{ e($booking->currency) }} {{ number_format((float) $booking->total_amount, 0) }}</strong>.
                                Include reference <strong>{{ e($booking->reference) }}</strong> in your payment note.
                            </p>
                            <div class="ota-form-group">
                                <label class="ota-label" for="payment_reference">Payment reference / transaction ID</label>
                                <input type="text" id="payment_reference" name="payment_reference" class="form-control ota-input @error('payment_reference') is-invalid @enderror" value="{{ old('payment_reference') }}" required>
                                @error('payment_reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="ota-form-group mb-0">
                                <label class="ota-label" for="payment_proof">Upload payment proof (optional)</label>
                                <input type="file" id="payment_proof" name="payment_proof" class="form-control ota-input @error('payment_proof') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf">
                                @error('payment_proof')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="ota-checkout-submit-bar">
                            <button type="submit" class="ota-btn-primary-lg btn btn-lg btn-block">Submit payment for review</button>
                        </div>
                    </form>
                </div>

                @include('frontend.checkout.partials.summary-card', [
                    'summary' => $checkoutSummary,
                    'seatCount' => $booking->seat_count,
                    'totalAmount' => (float) $booking->total_amount,
                ])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var timer = document.getElementById('gt-reservation-timer');
    if (!timer) return;
    var display = timer.querySelector('[data-gt-timer-display]');
    var activeEl = timer.querySelector('[data-gt-timer-active]');
    var expiredEl = timer.querySelector('[data-gt-timer-expired]');
    var expires = timer.getAttribute('data-gt-expires-at');
    if (!display || !expires) return;
    var end = new Date(expires).getTime();
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    function tick() {
        var diff = end - Date.now();
        if (diff <= 0) {
            display.textContent = '00:00';
            timer.classList.add('ota-fare-session-timer--expired');
            if (activeEl) activeEl.hidden = true;
            if (expiredEl) expiredEl.hidden = false;
            return;
        }
        var mins = Math.floor(diff / 60000);
        var secs = Math.floor((diff % 60000) / 1000);
        display.textContent = pad(mins) + ':' + pad(secs);
        if (diff <= 60000) timer.classList.add('ota-fare-session-timer--urgent');
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
@endpush
