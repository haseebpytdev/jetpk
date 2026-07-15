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

<x-bookings.promo-code-card
    :booking="$booking"
    :summary="$summary"
    :guest="$guest"
    :guest-token="$guestToken"
    :shell="$shell"
/>

<x-bookings.detail-payment-card
    :booking="$booking"
    :summary="$summary"
    :audience="$audience"
    :guest="$guest"
    :guest-token="$guestToken"
    :proof-action="$proofAction"
    :viewer-mode="$viewerMode"
    :allow-guest-proof-upload="$allowGuestProofUpload"
    :login-url="$loginUrl"
    :shell="$shell"
/>

<x-bookings.detail-documents-card
    :summary="$summary"
    :audience="$audience"
    :guest="$guest"
    :guest-token="$guestToken"
    :viewer-mode="$viewerMode"
    :shell="$shell"
/>
