@php
    $isJetpkCheckout = current_client_slug() === 'jetpk';
    $cardAvailable = ! empty($abhiPayCheckout['show_review_option']);
@endphp

<div class="ota-review-method-list">
    @if ($isJetpkCheckout)
        <label class="ota-method-card jp-checkout-method-card">
            <input type="radio" name="booking_method" value="pay_later" class="ota-method-card__input" checked>
            <span class="ota-method-card__body">
                <span class="ota-method-card__title">Manual Payment</span>
                <span class="ota-method-card__hint">Submit your booking request and pay using the instructions on the confirmation page.</span>
            </span>
        </label>
        @if ($cardAvailable)
            <label class="ota-method-card jp-checkout-method-card" data-testid="abhipay-review-option">
                <input type="radio" name="booking_method" value="online_card" class="ota-method-card__input">
                <span class="ota-method-card__body">
                    <span class="ota-method-card__title">Pay by Card</span>
                    <span class="ota-method-card__hint">Pay securely online by debit or credit card after submitting your booking.</span>
                </span>
            </label>
        @else
            <div class="jp-checkout-method-unavailable" role="status">
                <p class="jp-checkout-method-unavailable__title">Pay by Card</p>
                <p class="jp-checkout-method-unavailable__hint">Online card payment is temporarily unavailable. Please use Manual Payment or contact support.</p>
            </div>
        @endif
    @else
        <label class="ota-method-card">
            <input type="radio" name="booking_method" value="pay_later" class="ota-method-card__input" checked>
            <span class="ota-method-card__body">
                <span class="ota-method-card__title">Booking request — pay after confirmation</span>
                <span class="ota-method-card__hint">Submit your request; payment instructions follow once confirmed.</span>
            </span>
        </label>
        <label class="ota-method-card">
            <input type="radio" name="booking_method" value="bank_transfer" class="ota-method-card__input">
            <span class="ota-method-card__body">
                <span class="ota-method-card__title">Bank transfer</span>
                <span class="ota-method-card__hint">Pay via bank transfer using instructions from your consultant.</span>
            </span>
        </label>
        <label class="ota-method-card">
            <input type="radio" name="booking_method" value="office" class="ota-method-card__input">
            <span class="ota-method-card__body">
                <span class="ota-method-card__title">Confirm with travel consultant</span>
                <span class="ota-method-card__hint">Complete ticketing with your travel consultant in-office or by phone.</span>
            </span>
        </label>
        @if ($cardAvailable)
            <label class="ota-method-card" data-testid="abhipay-review-option">
                <input type="radio" name="booking_method" value="online_card" class="ota-method-card__input">
                <span class="ota-method-card__body">
                    <span class="ota-method-card__title">Pay online by card / AbhiPay</span>
                    <span class="ota-method-card__hint">
                        @if (!empty($isPiaNdcReview))
                            Secure card payment after your airline reservation is created on submit.
                        @else
                            Pay the booking balance securely online by debit or credit card.
                        @endif
                    </span>
                </span>
            </label>
        @endif
    @endif
</div>
