@extends(client_layout('dashboard', 'admin'))

@section('title', 'Branding Settings')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Branding / Company profile</h1>
            <p>Brand identity, colors, contact details, and media assets.</p>
        </div>
    </div>
@endsection

@section('content')
@php
    $headerLogoHeight = (int) old('header_logo_height', $headerLogoHeight ?? $defaultHeaderLogoHeight ?? 36);
    $defaultHeaderLogoHeight = (int) ($defaultHeaderLogoHeight ?? 36);
    $minHeaderLogoHeight = (int) ($minHeaderLogoHeight ?? 24);
    $maxHeaderLogoHeight = (int) ($maxHeaderLogoHeight ?? 72);
@endphp

<div class="jp-alert jp-alert--info">
    Footer layout is managed on <a href="{{ client_route('admin.settings.branding.footer.edit') }}">Branding → Footer</a>.
    The public <a href="{{ client_route('about') }}" target="_blank" rel="noopener">About Us</a> page is edited on
    <a href="{{ client_route('admin.settings.branding.about-us.edit') }}">Branding → About Us</a>.
</div>

@include('themes.admin.jetpakistan.partials.flash')

<form method="post" action="{{ client_route('admin.settings.branding.update') }}" enctype="multipart/form-data" class="jp-branding-page" data-jp-branding-form>
    @csrf
    @method('PATCH')

    <section class="jp-card jp-branding-section">
        <div class="jp-card__head">
            <h2 class="jp-card__title">Brand identity</h2>
            <p class="jp-help">Core names shown across the public site, emails, and portals.</p>
        </div>
        <div class="jp-branding-grid jp-form-grid">
            <div class="jp-field">
                <label class="jp-label" for="display_name">App / company name</label>
                <input class="jp-control jp-input" id="display_name" name="display_name" value="{{ old('display_name', $settings->display_name) }}" placeholder="e.g. JetPakistan">
                <p class="jp-help">Shown in the header, page titles, and customer-facing portals.</p>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="mail_from_name">Email sender name</label>
                <input class="jp-control jp-input" id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', $communication->mail_from_name ?? $settings->display_name) }}" placeholder="e.g. JetPakistan">
                <p class="jp-help">Gmail / inbox "From" display name.</p>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="legal_name">Legal name</label>
                <input class="jp-control jp-input" id="legal_name" name="legal_name" value="{{ old('legal_name', $settings->legal_name) }}">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="tagline">Tagline</label>
                <input class="jp-control jp-input" id="tagline" name="tagline" value="{{ old('tagline', $settings->tagline) }}">
            </div>
        </div>
    </section>

    <section class="jp-card jp-branding-section">
        <div class="jp-card__head">
            <h2 class="jp-card__title">Booking reference prefixes</h2>
            <p class="jp-help">Customer bookings: <strong>{company}-{customer}-{suffix}</strong>. Agent bookings: <strong>{company}-{agent}-{suffix}</strong>.</p>
        </div>
        <div class="jp-branding-grid jp-form-grid">
            <div class="jp-field">
                <label class="jp-label" for="company_prefix">Company prefix</label>
                <input class="jp-control jp-input text-uppercase" id="company_prefix" name="company_prefix" maxlength="4" pattern="[A-Z0-9]{2,4}" value="{{ old('company_prefix', $companyPrefix ?? 'OTA') }}" placeholder="PR">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="customer_reference_prefix">Customer booking prefix</label>
                <input class="jp-control jp-input text-uppercase" id="customer_reference_prefix" name="customer_reference_prefix" maxlength="4" pattern="[A-Z0-9]{2,4}" value="{{ old('customer_reference_prefix', $customerReferencePrefix ?? 'CU') }}" placeholder="CU">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="agent_reference_prefix">Agent booking prefix</label>
                <input class="jp-control jp-input text-uppercase" id="agent_reference_prefix" name="agent_reference_prefix" maxlength="4" pattern="[A-Z0-9]{2,4}" value="{{ old('agent_reference_prefix', $agentReferencePrefix ?? 'AG') }}" placeholder="AG">
            </div>
        </div>
    </section>

    <section class="jp-card jp-branding-section">
        <div class="jp-card__head"><h2 class="jp-card__title">Contact details</h2></div>
        <div class="jp-branding-grid jp-form-grid">
            <div class="jp-field">
                <label class="jp-label" for="support_phone">Support phone</label>
                <input class="jp-control jp-input" id="support_phone" name="support_phone" value="{{ old('support_phone', $settings->support_phone) }}">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="support_whatsapp">Support WhatsApp</label>
                <input class="jp-control jp-input" id="support_whatsapp" name="support_whatsapp" value="{{ old('support_whatsapp', $settings->support_whatsapp) }}">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="support_email">Support email</label>
                <input class="jp-control jp-input" id="support_email" name="support_email" value="{{ old('support_email', $settings->support_email) }}">
            </div>
        </div>
    </section>

    @php
        $slimTopbar = $slimTopbar ?? [];
        $slimTopbarBg = old('slim_topbar_background_color', $slimTopbar['background_color'] ?? '');
        $slimTopbarText = old('slim_topbar_text_color', $slimTopbar['text_color'] ?? '');
        $slimTopbarAccent = old('slim_topbar_accent_color', $slimTopbar['accent_color'] ?? '');
        $slimTopbarBgPicker = is_string($slimTopbarBg) && preg_match('/^#[0-9A-Fa-f]{6}$/', $slimTopbarBg) ? $slimTopbarBg : '#0f172a';
        $slimTopbarTextPicker = is_string($slimTopbarText) && preg_match('/^#[0-9A-Fa-f]{6}$/', $slimTopbarText) ? $slimTopbarText : '#94a3b8';
        $slimTopbarAccentPicker = is_string($slimTopbarAccent) && preg_match('/^#[0-9A-Fa-f]{6}$/', $slimTopbarAccent) ? $slimTopbarAccent : '#f59e0b';
    @endphp
    <section class="jp-card jp-branding-section">
        <div class="jp-card__head"><h2 class="jp-card__title">Public slim topbar</h2></div>
        <div class="jp-field jp-field--full">
            <label class="jp-check">
                <input type="hidden" name="slim_topbar_enabled" value="0">
                <input type="checkbox" name="slim_topbar_enabled" id="slim_topbar_enabled" value="1"
                    @checked(old('slim_topbar_enabled', ($slimTopbar['is_enabled'] ?? true) ? '1' : '0') == '1' || old('slim_topbar_enabled', $slimTopbar['is_enabled'] ?? true) === true)>
                <span>Show slim topbar on public pages</span>
            </label>
        </div>
        <div class="jp-field jp-field--full">
            <label class="jp-label" for="slim_topbar_message">Topbar message</label>
            <input class="jp-control jp-input" id="slim_topbar_message" name="slim_topbar_message" maxlength="255"
                value="{{ old('slim_topbar_message', $slimTopbar['message'] ?? '') }}"
                placeholder="Optional promo line (e.g. 24/7 flight support)">
        </div>
        <div class="jp-branding-checks">
            <label class="jp-check">
                <input type="hidden" name="slim_topbar_show_phone" value="0">
                <input type="checkbox" name="slim_topbar_show_phone" id="slim_topbar_show_phone" value="1"
                    @checked(old('slim_topbar_show_phone', ($slimTopbar['show_phone'] ?? true) ? '1' : '0') == '1' || old('slim_topbar_show_phone', $slimTopbar['show_phone'] ?? true) === true)>
                <span>Show support phone</span>
            </label>
            <label class="jp-check">
                <input type="hidden" name="slim_topbar_show_email" value="0">
                <input type="checkbox" name="slim_topbar_show_email" id="slim_topbar_show_email" value="1"
                    @checked(old('slim_topbar_show_email', ($slimTopbar['show_email'] ?? true) ? '1' : '0') == '1' || old('slim_topbar_show_email', $slimTopbar['show_email'] ?? true) === true)>
                <span>Show support email</span>
            </label>
            <label class="jp-check">
                <input type="hidden" name="slim_topbar_show_whatsapp" value="0">
                <input type="checkbox" name="slim_topbar_show_whatsapp" id="slim_topbar_show_whatsapp" value="1"
                    @checked(old('slim_topbar_show_whatsapp', ($slimTopbar['show_whatsapp'] ?? true) ? '1' : '0') == '1' || old('slim_topbar_show_whatsapp', $slimTopbar['show_whatsapp'] ?? true) === true)>
                <span>Show WhatsApp</span>
            </label>
        </div>
        <div class="jp-branding-grid jp-form-grid">
            <div class="jp-field jp-color-field">
                <label class="jp-label" for="slim_topbar_background_color">Background color</label>
                <div class="jp-color-field__row">
                    <input type="color" class="jp-color-field__swatch" id="slim_topbar_background_color_picker" value="{{ $slimTopbarBgPicker }}" data-slim-topbar-color-picker="slim_topbar_background_color">
                    <input type="text" class="jp-control jp-input" name="slim_topbar_background_color" id="slim_topbar_background_color" value="{{ $slimTopbarBg }}" placeholder="#0F172A" maxlength="7">
                </div>
            </div>
            <div class="jp-field jp-color-field">
                <label class="jp-label" for="slim_topbar_text_color">Text color</label>
                <div class="jp-color-field__row">
                    <input type="color" class="jp-color-field__swatch" id="slim_topbar_text_color_picker" value="{{ $slimTopbarTextPicker }}" data-slim-topbar-color-picker="slim_topbar_text_color">
                    <input type="text" class="jp-control jp-input" name="slim_topbar_text_color" id="slim_topbar_text_color" value="{{ $slimTopbarText }}" placeholder="#94A3B8" maxlength="7">
                </div>
            </div>
            <div class="jp-field jp-color-field">
                <label class="jp-label" for="slim_topbar_accent_color">Icon / accent color</label>
                <div class="jp-color-field__row">
                    <input type="color" class="jp-color-field__swatch" id="slim_topbar_accent_color_picker" value="{{ $slimTopbarAccentPicker }}" data-slim-topbar-color-picker="slim_topbar_accent_color">
                    <input type="text" class="jp-control jp-input" name="slim_topbar_accent_color" id="slim_topbar_accent_color" value="{{ $slimTopbarAccent }}" placeholder="#F59E0B" maxlength="7">
                </div>
            </div>
        </div>
    </section>

    <section class="jp-card jp-branding-section">
        <div class="jp-card__head">
            <h2 class="jp-card__title">Day / Night theme palette</h2>
            <p class="jp-help">Configure JetPakistan primary actions, accents, surfaces and text separately for Day and Night themes.</p>
        </div>
        <p class="jp-help">Operational status colors (warnings, errors) remain semantically controlled.</p>
        <a href="{{ client_route('admin.settings.theme-palette.edit') }}" class="jp-btn jp-btn--outline jp-btn--sm">Open Day / Night palette settings</a>
    </section>

    <section class="jp-card jp-branding-section">
        <div class="jp-card__head"><h2 class="jp-card__title">Brand colors (legacy quick presets)</h2></div>
        <div class="jp-field jp-field--full">
            <label class="jp-label" for="color_scheme">Color scheme preset</label>
            <select class="jp-control jp-select" id="color_scheme" name="color_scheme" data-brand-scheme-select>
                @foreach ($colorSchemeOptions as $schemeKey => $scheme)
                    @if (! str_starts_with($schemeKey, 'logo_auto_'))
                        <option value="{{ $schemeKey }}" @selected(old('color_scheme', $colorScheme ?? 'blue_travel') === $schemeKey)>{{ $scheme['label'] ?? $schemeKey }}</option>
                    @endif
                @endforeach
                @foreach ($colorSchemeOptions as $schemeKey => $scheme)
                    @if (str_starts_with($schemeKey, 'logo_auto_'))
                        <option value="{{ $schemeKey }}" hidden @selected(old('color_scheme', $colorScheme ?? '') === $schemeKey)>{{ $scheme['label'] ?? $schemeKey }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="jp-branding-grid jp-form-grid" data-brand-custom-colors>
            @php
                $primaryHex = old('primary_color', $settings->primary_color);
                $primaryHex = is_string($primaryHex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryHex) ? $primaryHex : '#1d4ed8';
                $secondaryHex = old('secondary_color', $settings->secondary_color);
                $secondaryHex = is_string($secondaryHex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $secondaryHex) ? $secondaryHex : '#0ea5e9';
                $accentHex = old('accent_color', $settings->accent_color);
                $accentHex = is_string($accentHex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $accentHex) ? $accentHex : '#f59e0b';
            @endphp
            <div class="jp-field jp-color-field">
                <label class="jp-label" for="primary_color">Primary color</label>
                <div class="jp-color-field__row">
                    <input type="color" class="jp-color-field__swatch" id="primary_color" name="primary_color" value="{{ $primaryHex }}" data-brand-color-picker data-brand-color-preview="primary_color_preview">
                    <input type="text" class="jp-control jp-input" id="primary_color_preview" value="{{ $primaryHex }}" readonly tabindex="-1" aria-hidden="true">
                </div>
            </div>
            <div class="jp-field jp-color-field">
                <label class="jp-label" for="secondary_color">Secondary color</label>
                <div class="jp-color-field__row">
                    <input type="color" class="jp-color-field__swatch" id="secondary_color" name="secondary_color" value="{{ $secondaryHex }}" data-brand-color-picker data-brand-color-preview="secondary_color_preview">
                    <input type="text" class="jp-control jp-input" id="secondary_color_preview" value="{{ $secondaryHex }}" readonly tabindex="-1" aria-hidden="true">
                </div>
            </div>
            <div class="jp-field jp-color-field">
                <label class="jp-label" for="accent_color">Accent color</label>
                <div class="jp-color-field__row">
                    <input type="color" class="jp-color-field__swatch" id="accent_color" name="accent_color" value="{{ $accentHex }}" data-brand-color-picker data-brand-color-preview="accent_color_preview">
                    <input type="text" class="jp-control jp-input" id="accent_color_preview" value="{{ $accentHex }}" readonly tabindex="-1" aria-hidden="true">
                </div>
            </div>
        </div>
    </section>

    <section class="jp-card jp-branding-section">
        <div class="jp-card__head"><h2 class="jp-card__title">Header CTA &amp; website</h2></div>
        <div class="jp-branding-grid jp-form-grid">
            <div class="jp-field">
                <label class="jp-label" for="header_cta_label">Header CTA label</label>
                <input class="jp-control jp-input" id="header_cta_label" name="header_cta_label" value="{{ old('header_cta_label', $settings->header_cta_label) }}">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="header_cta_url">Header CTA URL</label>
                <input class="jp-control jp-input" id="header_cta_url" name="header_cta_url" value="{{ old('header_cta_url', $settings->header_cta_url) }}">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="website_url">Website URL</label>
                <input class="jp-control jp-input" id="website_url" name="website_url" value="{{ old('website_url', $settings->website_url) }}">
            </div>
        </div>
    </section>

    <section class="jp-card jp-branding-section">
        <div class="jp-card__head"><h2 class="jp-card__title">Location</h2></div>
        <div class="jp-branding-grid jp-form-grid">
            <div class="jp-field jp-field--full">
                <label class="jp-label" for="office_address">Office address</label>
                <input class="jp-control jp-input" id="office_address" name="office_address" value="{{ old('office_address', $settings->office_address) }}">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="city">City</label>
                <input class="jp-control jp-input" id="city" name="city" value="{{ old('city', $settings->city) }}">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="country">Country</label>
                <input class="jp-control jp-input" id="country" name="country" value="{{ old('country', $settings->country) }}">
            </div>
        </div>
    </section>

    <section class="jp-card jp-branding-section jp-branding-media">
        <div class="jp-card__head"><h2 class="jp-card__title">Media assets</h2></div>
        <div class="jp-branding-grid jp-form-grid">
            <div class="jp-field jp-field--full jp-branding-logo-bg" data-jp-logo-background>
                <label class="jp-label" for="logo">Logo</label>
                <div class="jp-file-control">
                    <input type="file" id="logo" name="logo" class="jp-file-control__input" accept="image/png,image/jpeg,image/webp,image/svg+xml" data-jp-file-input data-jp-logo-file>
                    <label for="logo" class="jp-file-control__btn">Choose logo</label>
                    <span class="jp-file-control__name" data-jp-file-name>No file chosen</span>
                </div>
                <p class="jp-help">PNG, JPG, WebP, or SVG. Max 5 MB. SVG uses the sanitized SVG workflow and bypasses AI background removal.</p>
                @if (!empty($logoUrl))
                    <div class="jp-branding-media__preview">
                        <img src="{{ $logoUrl }}" alt="Current logo" width="120" height="48" loading="lazy">
                        <span class="jp-help">Current active logo</span>
                    </div>
                @endif

                <div class="jp-logo-bg-panel" data-jp-logo-bg-panel @if(!$backgroundRemovalEnabled) hidden @endif>
                    <label class="jp-check">
                        <input type="checkbox" id="remove_logo_background" data-jp-logo-bg-toggle @checked($backgroundRemovalDefaultForLogos ?? false)>
                        <span>Remove image background automatically</span>
                    </label>
                    <p class="jp-help">Creates a transparent PNG. Review the processed result before applying it.</p>
                    <p class="jp-help jp-logo-bg-privacy" data-jp-logo-bg-privacy hidden>This image will be sent to the configured background-removal provider for processing.</p>
                    <p class="jp-help"><a href="{{ $backgroundRemovalSettingsUrl ?? '#' }}">Configure background removal provider</a></p>

                    <div class="jp-logo-bg-previews" data-jp-logo-bg-previews hidden>
                        <div class="jp-logo-bg-preview-col">
                            <span class="jp-help">Original upload</span>
                            <div class="jp-logo-bg-surface jp-logo-bg-surface--neutral"><img alt="" data-jp-logo-bg-original-preview></div>
                        </div>
                        <div class="jp-logo-bg-preview-col" data-jp-logo-bg-processed-wrap hidden>
                            <span class="jp-help">Processed transparent PNG</span>
                            <div class="jp-logo-bg-surface jp-logo-bg-surface--checker"><img alt="" data-jp-logo-bg-processed-preview></div>
                            <div class="jp-logo-bg-surface jp-logo-bg-surface--white"><img alt="" data-jp-logo-bg-processed-white></div>
                            <div class="jp-logo-bg-surface jp-logo-bg-surface--dark"><img alt="" data-jp-logo-bg-processed-dark></div>
                        </div>
                    </div>

                    <div class="jp-logo-bg-status jp-help" data-jp-logo-bg-status aria-live="polite"></div>
                    <div class="jp-logo-bg-actions">
                        <button type="button" class="jp-btn jp-btn--ghost jp-btn--sm" data-jp-logo-bg-process>Process background</button>
                        <button type="button" class="jp-btn jp-btn--sm" data-jp-logo-bg-accept hidden>Use processed logo</button>
                        <button type="button" class="jp-btn jp-btn--ghost jp-btn--sm" data-jp-logo-bg-keep hidden>Keep original</button>
                        <button type="button" class="jp-btn jp-btn--ghost jp-btn--sm" data-jp-logo-bg-retry hidden>Retry</button>
                        <button type="button" class="jp-btn jp-btn--ghost jp-btn--sm" data-jp-logo-bg-cancel hidden>Cancel processed result</button>
                    </div>
                </div>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="favicon">Favicon</label>
                <div class="jp-file-control">
                    <input type="file" id="favicon" name="favicon" class="jp-file-control__input" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml" data-jp-file-input>
                    <label for="favicon" class="jp-file-control__btn">Choose favicon</label>
                    <span class="jp-file-control__name" data-jp-file-name>No file chosen</span>
                </div>
                <p class="jp-help">ICO, PNG, or SVG. Max 1 MB.</p>
            </div>
            <div class="jp-field jp-field--full">
                <label class="jp-label" for="hero_image">Hero image (fallback)</label>
                <div class="jp-file-control">
                    <input type="file" id="hero_image" name="hero_image" class="jp-file-control__input" accept="image/jpeg,image/png,image/webp" data-jp-file-input>
                    <label for="hero_image" class="jp-file-control__btn">Choose hero image</label>
                    <span class="jp-file-control__name" data-jp-file-name>No file chosen</span>
                </div>
                <p class="jp-help">Used when Homepage hero has no image. Recommended 1920×700 px.</p>
            </div>
        </div>
    </section>

    <section class="jp-card jp-branding-section">
        <div class="jp-card__head">
            <h2 class="jp-card__title">Logo appearance</h2>
            <p class="jp-help">Controls the public header and mobile drawer logo height. Aspect ratio is preserved.</p>
        </div>
        <div class="jp-logo-size-control" data-jp-logo-size-control data-default-height="{{ $defaultHeaderLogoHeight }}">
            <div class="jp-field jp-field--full">
                <div class="jp-logo-size-control__head">
                    <label class="jp-label" for="header_logo_height">Header logo size</label>
                    <output class="jp-logo-size-control__value" for="header_logo_height" data-jp-logo-size-value>{{ $headerLogoHeight }}px</output>
                </div>
                <input
                    type="range"
                    class="jp-logo-size-control__slider"
                    id="header_logo_height"
                    name="header_logo_height"
                    min="{{ $minHeaderLogoHeight }}"
                    max="{{ $maxHeaderLogoHeight }}"
                    step="1"
                    value="{{ $headerLogoHeight }}"
                    data-jp-logo-size-slider
                >
                <p class="jp-help">Affects the public desktop header, mobile drawer, and Page Settings preview.</p>
            </div>
            @if (!empty($logoUrl))
                <div class="jp-logo-size-control__preview" aria-hidden="true">
                    <img src="{{ $logoUrl }}" alt="" data-jp-logo-size-preview style="height: {{ $headerLogoHeight }}px; width: auto; max-width: 220px; object-fit: contain;">
                </div>
            @endif
            <div class="jp-logo-size-control__actions">
                <button type="button" class="jp-btn jp-btn--ghost jp-btn--sm" data-jp-logo-size-reset>Reset to default ({{ $defaultHeaderLogoHeight }}px)</button>
            </div>
        </div>
    </section>

    <section class="jp-card jp-branding-section" id="logo-palette-root" data-logo-url="{{ $logoUrl ?? '' }}">
        <div class="jp-card__head"><h2 class="jp-card__title">Logo-based color suggestions</h2></div>
        <p id="logo-palette-status" class="jp-help"></p>
        <div id="logo-palette-cards" class="jp-branding-palette-grid" aria-live="polite"></div>
    </section>

    <div class="jp-action-bar jp-branding-action-bar">
        <div class="jp-action-bar__primary">
            <button type="submit" class="jp-btn jp-btn--primary" data-jp-branding-save>Save branding</button>
        </div>
    </div>
</form>

<script src="{{ ui_asset('js/admin-branding-logo-palette.js') }}" defer></script>
<script src="{{ ui_asset('js/admin-branding-logo-background.js') }}" defer></script>
<script>
    window.__jpLogoBackground = {
        stageUrl: @json(client_route('admin.settings.branding.logo-background.stage')),
        csrf: @json(csrf_token()),
        enabled: @json($backgroundRemovalEnabled ?? false),
    };
    document.querySelectorAll('[data-slim-topbar-color-picker]').forEach(function (picker) {
        var target = document.getElementById(picker.getAttribute('data-slim-topbar-color-picker') || '');
        if (!target) return;
        picker.addEventListener('input', function () { target.value = picker.value.toUpperCase(); });
        target.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(target.value.trim())) picker.value = target.value.trim();
        });
    });
    document.querySelectorAll('[data-brand-color-picker]').forEach(function (picker) {
        var preview = document.getElementById(picker.getAttribute('data-brand-color-preview') || '');
        var sync = function () { if (preview) preview.value = picker.value; };
        picker.addEventListener('input', sync);
        sync();
    });
    (function () {
        var schemeSelect = document.querySelector('[data-brand-scheme-select]');
        var customColors = document.querySelector('[data-brand-custom-colors]');
        if (!schemeSelect || !customColors) return;
        var isEditableScheme = function (value) { return value === 'custom' || (typeof value === 'string' && value.indexOf('logo_auto_') === 0); };
        var toggleCustom = function () {
            var editable = isEditableScheme(schemeSelect.value);
            customColors.querySelectorAll('input').forEach(function (input) { input.disabled = !editable; });
            customColors.style.opacity = editable ? '1' : '0.72';
        };
        schemeSelect.addEventListener('change', toggleCustom);
        toggleCustom();
        window.__otaBrandingToggleScheme = toggleCustom;
    })();
    document.querySelectorAll('[data-jp-file-input]').forEach(function (input) {
        var wrap = input.closest('.jp-file-control');
        var label = wrap ? wrap.querySelector('[data-jp-file-name]') : null;
        input.addEventListener('change', function () {
            if (label) label.textContent = input.files && input.files[0] ? input.files[0].name : 'No file chosen';
        });
    });
    (function () {
        var root = document.querySelector('[data-jp-logo-size-control]');
        if (!root) return;
        var slider = root.querySelector('[data-jp-logo-size-slider]');
        var valueEl = root.querySelector('[data-jp-logo-size-value]');
        var preview = root.querySelector('[data-jp-logo-size-preview]');
        var resetBtn = root.querySelector('[data-jp-logo-size-reset]');
        var defaultHeight = parseInt(root.getAttribute('data-default-height') || '36', 10);
        var sync = function () {
            if (!slider) return;
            var px = slider.value + 'px';
            if (valueEl) valueEl.textContent = px;
            if (preview) preview.style.height = px;
        };
        if (slider) slider.addEventListener('input', sync);
        if (resetBtn && slider) {
            resetBtn.addEventListener('click', function () {
                slider.value = String(defaultHeight);
                sync();
            });
        }
        sync();
    })();
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.OtaAdminBrandingLogoPalette === 'undefined') return;
        window.OtaAdminBrandingLogoPalette.init({
            logoUrl: @json($logoUrl),
            activeScheme: @json(old('color_scheme', $colorScheme ?? '')),
            onSchemeEditableChange: function () {
                if (typeof window.__otaBrandingToggleScheme === 'function') window.__otaBrandingToggleScheme();
            },
        });
    });
</script>
@endsection
