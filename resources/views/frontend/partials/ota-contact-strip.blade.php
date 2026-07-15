@php
    $contact = $publicAgencyContact ?? \App\Support\Branding\PublicAgencyContactResolver::resolve($agencySettings ?? null);
@endphp
@if ($contact->hasEmail() || $contact->hasWhatsapp())
<section class="ota-contact-strip">
    <span>Need travel assistance?</span>
    @if ($contact->hasEmail())
        <a href="{{ $contact->mailtoHref() }}?subject={{ rawurlencode($contact->agencyName.' support inquiry') }}">Email support</a>
    @endif
    @if ($contact->hasEmail() && $contact->hasWhatsapp())
        <span aria-hidden="true">·</span>
    @endif
    @if ($contact->hasWhatsapp())
        <a href="{{ $contact->whatsappUrl() }}" target="_blank" rel="noopener">WhatsApp</a>
    @endif
    <span aria-hidden="true">·</span>
    <a href="{{ client_route('login') }}">Customer login</a>
</section>
@endif
