@php
    use App\Support\Branding\PublicAgencyContact;
    use App\Support\Branding\PublicAgencyContactResolver;

    $jpIsPlaceholderContact = static function (?string $value): bool {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return $normalized === ''
            || (bool) preg_match('/^(123|\+92\s*300\s*0{6}|\+92\s*21\s*111\s*000\s*000)$/i', $normalized);
    };

    $jpBrand = trim(client_branding()->companyName());
    if ($jpBrand === '') {
        $jpBrand = client_branding()->companyName();
    }

    $masterContact = $publicAgencyContact ?? PublicAgencyContactResolver::resolve($agencySettings ?? null);
    $jpPhone = trim(client_branding()->phone());
    $jpEmail = trim(client_branding()->email());

    if ($jpIsPlaceholderContact($jpPhone)) {
        $jpPhone = '';
    }
    $resolvedPhone = $jpPhone !== '' ? $jpPhone : $masterContact->phone;
    if ($jpIsPlaceholderContact($resolvedPhone)) {
        $resolvedPhone = '';
    }

    $resolvedWhatsapp = $masterContact->whatsapp;
    if ($jpIsPlaceholderContact($resolvedWhatsapp)) {
        $resolvedWhatsapp = '';
    }

    $resolvedEmail = $jpEmail !== '' ? $jpEmail : $masterContact->email;
    if ($jpIsPlaceholderContact($resolvedEmail)) {
        $resolvedEmail = '';
    }

    $jpContact = new PublicAgencyContact(
        agencyName: $jpBrand,
        phone: $resolvedPhone,
        email: $resolvedEmail,
        whatsapp: $resolvedWhatsapp,
        city: $masterContact->city,
        address: trim(client_branding()->address()) !== '' ? trim(client_branding()->address()) : $masterContact->address,
    );
    $waUrl = $jpContact->whatsappUrl();
@endphp

<aside class="jp-checkout-card jp-checkout-card--support" data-jp-support-card>
    <h2 class="jp-checkout-card__title">Questions?</h2>
    <p class="jp-checkout-card__lead">Reach {{ $jpBrand }} for help with this itinerary.</p>
    @if ($jpContact->hasWhatsapp())
        <a href="{{ $waUrl }}" class="jp-btn jp-btn--wa" target="_blank" rel="noopener noreferrer">
            <i class="fa fa-whatsapp" aria-hidden="true"></i> Chat on WhatsApp
        </a>
        @if ($jpContact->hasPhone())
            <p class="jp-support-phone">{{ $jpContact->phone }}</p>
        @endif
    @elseif ($jpContact->hasEmail())
        <a href="mailto:{{ $jpContact->email }}" class="jp-btn jp-btn--wa jp-btn--email">
            <i class="fa fa-envelope" aria-hidden="true"></i> Email support
        </a>
    @else
        <p class="jp-checkout-card__muted">Support contact is not configured yet.</p>
    @endif
</aside>
