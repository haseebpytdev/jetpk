@php
    use App\Support\Branding\PublicAgencyContact;
    use App\Support\Branding\PublicAgencyContactResolver;

    $jpIsPlaceholderContact = static function (?string $value): bool {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return $normalized === ''
            || (bool) preg_match('/^(123|\+92\s*300\s*0{6}|\+92\s*21\s*111\s*000\s*000)$/i', $normalized);
    };

    $checkoutPageHeading = 'Passenger & contact details';
    $jpBrand = trim(client_branding()->companyName());
    if ($jpBrand === '') {
        $jpBrand = client_branding()->companyName();
    }
    $checkoutSupportAgencyName = $jpBrand;

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

    $publicAgencyContact = new PublicAgencyContact(
        agencyName: $jpBrand,
        phone: $resolvedPhone,
        email: $resolvedEmail,
        whatsapp: $resolvedWhatsapp,
        city: $masterContact->city,
        address: trim(client_branding()->address()) !== '' ? trim(client_branding()->address()) : $masterContact->address,
    );
@endphp
<div class="jp-checkout-body jp-checkout-body--passengers" data-jp-checkout-body data-jp-checkout-passengers>
@include('frontend.booking.partials.passenger-details-body')
</div>
@once
@push('theme-scripts')
<script src="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/js/booking.js?v={{ $jpCheckoutAssetVersion ?? 35 }}" defer></script>
@endpush
@endonce
