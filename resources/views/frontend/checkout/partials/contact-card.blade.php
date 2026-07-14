@php
    $contactName = old('contact_name', auth()->user()->name ?? '');
    $contactEmail = old('contact_email', auth()->user()->email ?? '');
    $contactPhone = old('contact_phone', '');
@endphp
<div class="ota-checkout-card ota-checkout-card--section ota-checkout-contact-card">
    <h2 class="ota-checkout-section-title">Contact details</h2>
    <div class="ota-pax-grid ota-pax-grid--contact">
        <div class="ota-pax-field ota-pax-field--contact-name">
            <div class="ota-form-group">
                <label class="ota-label" for="gt-contact-name">Contact name</label>
                <input class="form-control ota-input @error('contact_name') is-invalid @enderror" id="gt-contact-name" type="text" name="contact_name" value="{{ $contactName }}" required autocomplete="name">
                @error('contact_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="ota-pax-field ota-pax-field--email">
            <div class="ota-form-group">
                <label class="ota-label" for="gt-contact-email">Email</label>
                <input class="form-control ota-input @error('contact_email') is-invalid @enderror" id="gt-contact-email" type="email" name="contact_email" value="{{ $contactEmail }}" required autocomplete="email">
                @error('contact_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="ota-pax-field ota-pax-field--phone">
            <div class="ota-form-group">
                <label class="ota-label" for="gt-contact-phone">Phone</label>
                <input class="form-control ota-input @error('contact_phone') is-invalid @enderror" id="gt-contact-phone" type="tel" name="contact_phone" value="{{ $contactPhone }}" required autocomplete="tel">
                @error('contact_phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
