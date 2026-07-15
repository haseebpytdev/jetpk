@props([
    'booking',
    'summary',
    'audience' => 'customer',
    'guest' => false,
    'guestToken' => null,
    'proofAction' => null,
    'viewerMode' => 'customer',
    'allowGuestProofUpload' => false,
    'loginUrl' => null,
    'shell' => 'account',
])

@php
    $isAccount = $shell === 'account';
    $currency = $summary['currency'] ?? ($booking->currency ?? 'PKR');
    $proofAction = $proofAction ?? ($guest
        ? route('guest.bookings.payment-proof', ['booking' => $booking, 'token' => $guestToken])
        : ($audience === 'agent'
            ? route('agent.bookings.payment-proof', $booking)
            : route('customer.bookings.payment-proof', $booking)));
    $isGuest = $viewerMode === 'guest';
    $canShowProofForm = $summary['can_upload_proof'] && (! $isGuest || $allowGuestProofUpload);
    $proofBadgeClass = match ($summary['proof_status'] ?? 'none') {
        'verified' => 'ota-account-badge--success',
        'under_review' => 'ota-account-badge--warning',
        'rejected' => 'ota-account-badge--danger',
        default => 'ota-account-badge--muted',
    };
    $paymentService = app(\App\Services\Payments\PaymentTransactionService::class);
    $abhiPayAvailable = $abhiPayAvailable ?? $paymentService->isAbhiPayAvailableForBooking($booking);
    $abhiPayStartUrl = $abhiPayStartUrl ?? ($guest && filled($guestToken)
        ? route('guest.bookings.abhipay.start', ['booking' => $booking, 'token' => $guestToken])
        : route('payments.abhipay.start', $booking));
    $showAbhiPay = $abhiPayAvailable
        && ! $summary['show_verified']
        && (float) ($summary['balance_due'] ?? 0) > 0;
@endphp

<div class="{{ $isAccount ? 'ota-account-card mb-3' : 'card mb-3 border-0 shadow-sm' }}" id="payment">
    <div class="{{ $isAccount ? 'ota-account-card__head' : 'card-header border-0' }} d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="{{ $isAccount ? 'ota-account-card__title' : 'card-title mb-0' }}">Payment summary</h3>
        <span class="{{ $isAccount ? 'ota-account-badge' : 'badge bg-secondary-lt' }}" data-testid="booking-payment-status-badge">{{ $summary['status_label'] }}</span>
    </div>
    <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}" data-testid="booking-payment-summary">
        @if (! empty($summary['promo_code']))
            <div class="ota-booking-detail-payment-grid mb-3" data-testid="booking-promo-summary-lines">
                <div class="ota-booking-detail-payment-grid__item">
                    <div class="ota-booking-detail-payment-grid__label">Fare total</div>
                    <div class="ota-booking-detail-payment-grid__value">{{ number_format((float) ($summary['fare_total'] ?? $summary['total']), 2) }} {{ $currency }}</div>
                </div>
                <div class="ota-booking-detail-payment-grid__item">
                    <div class="ota-booking-detail-payment-grid__label">Promo ({{ e($summary['promo_code']) }})</div>
                    <div class="ota-booking-detail-payment-grid__value">−{{ number_format((float) ($summary['promo_discount'] ?? 0), 2) }} {{ $currency }}</div>
                </div>
                <div class="ota-booking-detail-payment-grid__item">
                    <div class="ota-booking-detail-payment-grid__label">Final payable</div>
                    <div class="ota-booking-detail-payment-grid__value fw-semibold">{{ number_format((float) ($summary['customer_payable'] ?? $summary['total']), 2) }} {{ $currency }}</div>
                </div>
            </div>
        @endif
        <div class="ota-booking-detail-payment-grid mb-3">
            <div class="ota-booking-detail-payment-grid__item">
                <div class="ota-booking-detail-payment-grid__label">Total</div>
                <div class="ota-booking-detail-payment-grid__value">{{ number_format((float) ($summary['fare_total'] ?? $summary['total']), 2) }} {{ $currency }}</div>
            </div>
            <div class="ota-booking-detail-payment-grid__item">
                <div class="ota-booking-detail-payment-grid__label">Paid</div>
                <div class="ota-booking-detail-payment-grid__value">{{ number_format((float) $summary['amount_paid'], 2) }} {{ $currency }}</div>
            </div>
            <div class="ota-booking-detail-payment-grid__item">
                <div class="ota-booking-detail-payment-grid__label">Balance due</div>
                <div class="ota-booking-detail-payment-grid__value ota-booking-detail-payment-grid__value--due">{{ number_format((float) $summary['balance_due'], 2) }} {{ $currency }}</div>
            </div>
            <div class="ota-booking-detail-payment-grid__item">
                <div class="ota-booking-detail-payment-grid__label">Proof status</div>
                <div>
                    <span class="{{ $isAccount ? 'ota-account-badge '.$proofBadgeClass : 'badge bg-secondary-lt' }} text-capitalize">{{ str_replace('_', ' ', $summary['proof_status']) }}</span>
                </div>
            </div>
        </div>

        <p class="small text-secondary mb-2">{{ $summary['status_meaning'] }}</p>

        @if (! empty($summary['last_activity_at']))
            <p class="small text-secondary mb-2" data-testid="booking-payment-last-activity">
                <strong>Last activity:</strong> {{ $summary['last_activity_label'] }} — {{ $summary['last_activity_at'] }}
            </p>
        @endif

        @if ($summary['show_verified'])
            <div class="{{ $isAccount ? 'ota-account-alert ota-account-alert--success' : 'alert alert-success' }} py-2 small mb-2" data-testid="booking-payment-verified">Payment verified. No further action required.</div>
        @elseif ($summary['show_awaiting_review'])
            <div class="{{ $isAccount ? 'ota-account-alert ota-account-alert--info' : 'alert alert-info' }} py-2 small mb-2" data-testid="booking-payment-awaiting-review">
                Payment proof submitted — awaiting verification. Upload is disabled until review completes.
            </div>
        @elseif ($summary['show_rejected_resubmit'])
            <div class="{{ $isAccount ? 'ota-account-alert ota-account-alert--danger' : 'alert alert-danger' }} py-2 small mb-2" data-testid="booking-payment-rejected">
                Payment proof was rejected. You may submit a new proof below or contact support.
            </div>
        @endif

        @if (! empty($summary['latest_proof']) && in_array($summary['latest_proof']['status'], ['submitted', 'pending', 'rejected', 'verified'], true))
            <div class="ota-booking-detail-proof-meta mb-2 small" data-testid="booking-payment-proof-metadata">
                <div class="fw-semibold mb-1">Latest proof</div>
                <div>{{ ucfirst($summary['latest_proof']['method']) }} — {{ number_format((float) $summary['latest_proof']['amount'], 2) }} {{ $summary['latest_proof']['currency'] }}</div>
                @if (! empty($summary['latest_proof']['payment_reference']))
                    <div class="text-secondary">Reference: {{ $summary['latest_proof']['payment_reference'] }}</div>
                @endif
                @if (! empty($summary['latest_proof']['submitted_at']))
                    <div class="text-secondary">Submitted: {{ $summary['latest_proof']['submitted_at'] }}</div>
                @endif
                @if ($summary['latest_proof']['has_proof_file'])
                    <div class="text-secondary">File attached</div>
                @endif
            </div>
        @endif

        @if ($isGuest && ! $allowGuestProofUpload && ! $summary['show_verified'] && ! $summary['show_awaiting_review'] && ((float) ($summary['balance_due'] ?? 0) > 0 || $summary['show_rejected_resubmit']))
            <p class="small text-secondary mb-2" data-testid="guest-payment-proof-login-hint">
                Login or contact support to upload payment proof.
            </p>
            <div class="d-flex flex-wrap gap-2">
                @if ($loginUrl)
                    <a href="{{ $loginUrl }}" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--primary ota-account-btn--sm' : 'btn btn-primary btn-sm' }}">Login</a>
                @endif
                <a href="{{ route('support') }}" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--secondary ota-account-btn--sm' : 'btn btn-outline-secondary btn-sm' }}">Contact support</a>
            </div>
        @elseif ($canShowProofForm)
            @if ($showAbhiPay)
                <div class="mb-3 p-3 border rounded" data-testid="abhipay-checkout-option">
                    <div class="fw-semibold mb-1">Pay online — debit/credit card via AbhiPay</div>
                    <p class="small text-secondary mb-2">Pay the balance due securely with your card. Manual proof upload remains available below.</p>
                    <form method="post" action="{{ $abhiPayStartUrl }}">
                        @csrf
                        <button type="submit" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--primary' : 'btn btn-primary' }}">
                            Pay {{ number_format((float) $summary['balance_due'], 2) }} {{ $currency }} with AbhiPay
                        </button>
                    </form>
                </div>
            @endif
            <p class="small text-secondary mb-2">Upload bank transfer or wallet proof so our team can verify your payment.</p>
            <form method="post" action="{{ $proofAction }}" enctype="multipart/form-data" data-testid="{{ $isGuest ? 'guest-payment-proof-form' : 'customer-payment-proof-form' }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label">Method</label>
                    <select name="method" class="form-select" required>
                        @foreach (['bank_transfer', 'cash', 'card_manual', 'easypaisa', 'jazzcash', 'other'] as $m)
                            <option value="{{ $m }}">{{ str_replace('_', ' ', $m) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required
                        value="{{ $summary['balance_due'] > 0 ? $summary['balance_due'] : '' }}">
                </div>
                <div class="mb-2">
                    <label class="form-label">Reference (optional)</label>
                    <input type="text" name="payment_reference" class="form-control" placeholder="Transfer reference">
                </div>
                <div class="mb-2">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="{{ $audience === 'agent' ? '' : 'Any details for our finance team' }}"></textarea>
                </div>
                <button class="{{ $isAccount ? 'ota-account-btn ota-account-btn--primary ota-account-btn--block' : 'btn btn-primary w-100' }}" type="submit">
                    <i class="ti ti-upload me-1" aria-hidden="true"></i>
                    {{ $audience === 'agent' ? 'Submit payment proof' : 'Upload payment proof' }}
                </button>
            </form>
        @elseif (! $summary['show_verified'] && ! $summary['show_awaiting_review'])
            <p class="small text-secondary mb-0" data-testid="booking-payment-no-action">No payment action required at this time.</p>
        @endif
    </div>
</div>
