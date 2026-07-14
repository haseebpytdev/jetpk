@php
    $selectedMethod = old('payment_method', 'bank_transfer');
    $statusLabel = isset($statusLabel) ? (string) $statusLabel : null;
    $methods = [
        'bank_transfer' => [
            'title' => 'Bank transfer',
            'hint' => 'Transfer the total amount and include your booking reference in the payment note.',
        ],
        'office' => [
            'title' => 'Pay at office / consultant',
            'hint' => 'Visit our office or speak with your travel consultant to complete payment.',
        ],
        'cash' => [
            'title' => 'Cash deposit',
            'hint' => 'Deposit cash at our office and submit your receipt reference below.',
        ],
    ];
@endphp
@if ($statusLabel !== null && $statusLabel !== '')
    <div class="ota-checkout-card ota-group-payment-status" role="status">
        <p class="ota-group-payment-status__label mb-0">{{ e($statusLabel) }}</p>
    </div>
@endif

<div class="ota-checkout-card ota-review-method-card">
    <h2 class="ota-checkout-section-title">Payment method</h2>
    <p class="ota-checkout-section-hint">Choose how you paid or will pay for this group booking.</p>

    <div class="ota-review-method-list">
        @foreach ($methods as $value => $meta)
            <label class="ota-method-card">
                <input type="radio" name="payment_method" value="{{ $value }}" class="ota-method-card__input" @checked($selectedMethod === $value) required>
                <span class="ota-method-card__body">
                    <span class="ota-method-card__title">{{ $meta['title'] }}</span>
                    <span class="ota-method-card__hint">{{ $meta['hint'] }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('payment_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
