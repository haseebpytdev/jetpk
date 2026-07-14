@extends(client_layout('dashboard', 'admin'))

@section('title', 'Branding Settings')

@section('page-header')
    <h1 class="jp-page-title">Branding / Company profile</h1>
@endsection

@section('content')
    <div class="alert alert-light border mb-2 py-2 small">
        Footer layout, menu links, social icons, and footer styling are managed on
        <a href="{{ route('admin.settings.branding.footer.edit') }}">Branding ? Footer</a>.
        The public <a href="{{ route('about') }}" target="_blank" rel="noopener">About Us</a> page is edited on
        <a href="{{ route('admin.settings.branding.about-us.edit') }}">Branding ? About Us</a>.
    </div>
    @if (session('status'))
        <div class="jp-alert jp-alert--success mb-2">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger mb-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Brand identity</h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-2">
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">App / company name</label>
                        <input class="jp-control" name="display_name" value="{{ old('display_name', $settings->display_name) }}" placeholder="e.g. JetPakistan">
                        <div class="form-text">Shown in the header, page titles, and customer-facing portals.</div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">Email sender name</label>
                        <input class="jp-control" name="mail_from_name" value="{{ old('mail_from_name', $communication->mail_from_name ?? $settings->display_name) }}" placeholder="e.g. JetPakistan">
                        <div class="form-text">Gmail / inbox "From" display name. Address stays in Communication settings.</div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">Legal name</label>
                        <input class="jp-control" name="legal_name" value="{{ old('legal_name', $settings->legal_name) }}">
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">Tagline</label>
                        <input class="jp-control" name="tagline" value="{{ old('tagline', $settings->tagline) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Booking reference prefixes</h3>
            </div>
            <div class="card-body py-3">
                <p class="small text-secondary mb-2">Customer bookings display as <strong>{company}-{customer}-{suffix}</strong> and agent bookings as <strong>{company}-{agent}-{suffix}</strong>. Stored references are not rewritten for existing bookings.</p>
                <div class="row g-2">
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">Company prefix</label>
                        <input class="jp-control text-uppercase" name="company_prefix" maxlength="4" pattern="[A-Z0-9]{2,4}" value="{{ old('company_prefix', $companyPrefix ?? 'OTA') }}" placeholder="PR">
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">Customer booking prefix</label>
                        <input class="jp-control text-uppercase" name="customer_reference_prefix" maxlength="4" pattern="[A-Z0-9]{2,4}" value="{{ old('customer_reference_prefix', $customerReferencePrefix ?? 'CU') }}" placeholder="CU">
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">Agent booking prefix</label>
                        <input class="jp-control text-uppercase" name="agent_reference_prefix" maxlength="4" pattern="[A-Z0-9]{2,4}" value="{{ old('agent_reference_prefix', $agentReferencePrefix ?? 'AG') }}" placeholder="AG">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Contact details</h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-2">
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">Support phone</label>
                        <input class="jp-control" name="support_phone" value="{{ old('support_phone', $settings->support_phone) }}">
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">Support WhatsApp</label>
                        <input class="jp-control" name="support_whatsapp" value="{{ old('support_whatsapp', $settings->support_whatsapp) }}">
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label">Support email</label>
                        <input class="jp-control" name="support_email" value="{{ old('support_email', $settings->support_email) }}">
                    </div>
                </div>
                <p class="form-text mb-0 mt-2">The public slim topbar can show phone, email, and WhatsApp from these fields (see Slim topbar below).</p>
            </div>
        </div>

        @php
            $slimTopbar = $slimTopbar ?? [];
            $slimTopbarBg = old('slim_topbar_background_color', $slimTopbar['background_color'] ?? '');
            $slimTopbarText = old('slim_topbar_text_color', $slimTopbar['text_color'] ?? '');
            $slimTopbarAccent = old('slim_topbar_accent_color', $slimTopbar['accent_color'] ?? '');
            $slimTopbarBgPicker = is_string($slimTopbarBg) && preg_match('/^#[0-9A-Fa-f]{6}$/', $slimTopbarBg) ? $slimTopbarBg : '#0f172a';
            $slimTopbarTextPicker = is_string($slimTopbarText) && preg_match('/^#[0-9A-Fa-f]{6}$/', $slimTopbarText) ? $slimTopbarText : '#94a3b8';
            $slimTopbarAccentPicker = is_string($slimTopbarAccent) && preg_match('/^#[0-9A-Fa-f]{6}$/', $slimTopbarAccent) ? $slimTopbarAccent : '#f59e0b';
        @endphp
        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Public slim topbar</h3>
            </div>
            <div class="card-body py-3">
                <div class="form-check mb-2">
                    <input type="hidden" name="slim_topbar_enabled" value="0">
                    <input class="form-check-input" type="checkbox" name="slim_topbar_enabled" id="slim_topbar_enabled" value="1"
                        @checked(old('slim_topbar_enabled', ($slimTopbar['is_enabled'] ?? true) ? '1' : '0') == '1' || old('slim_topbar_enabled', $slimTopbar['is_enabled'] ?? true) === true)>
                    <label class="form-check-label" for="slim_topbar_enabled">Show slim topbar on public pages</label>
                </div>
                <div class="mb-2">
                    <label class="jp-label" for="slim_topbar_message">Topbar message</label>
                    <input class="jp-control" id="slim_topbar_message" name="slim_topbar_message" maxlength="255"
                        value="{{ old('slim_topbar_message', $slimTopbar['message'] ?? '') }}"
                        placeholder="Optional promo line (e.g. 24/7 flight support)">
                    <div class="form-text">If empty and no contact chips are shown, the default trust lines appear.</div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="hidden" name="slim_topbar_show_phone" value="0">
                            <input class="form-check-input" type="checkbox" name="slim_topbar_show_phone" id="slim_topbar_show_phone" value="1"
                                @checked(old('slim_topbar_show_phone', ($slimTopbar['show_phone'] ?? true) ? '1' : '0') == '1' || old('slim_topbar_show_phone', $slimTopbar['show_phone'] ?? true) === true)>
                            <label class="form-check-label" for="slim_topbar_show_phone">Show support phone</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="hidden" name="slim_topbar_show_email" value="0">
                            <input class="form-check-input" type="checkbox" name="slim_topbar_show_email" id="slim_topbar_show_email" value="1"
                                @checked(old('slim_topbar_show_email', ($slimTopbar['show_email'] ?? true) ? '1' : '0') == '1' || old('slim_topbar_show_email', $slimTopbar['show_email'] ?? true) === true)>
                            <label class="form-check-label" for="slim_topbar_show_email">Show support email</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="hidden" name="slim_topbar_show_whatsapp" value="0">
                            <input class="form-check-input" type="checkbox" name="slim_topbar_show_whatsapp" id="slim_topbar_show_whatsapp" value="1"
                                @checked(old('slim_topbar_show_whatsapp', ($slimTopbar['show_whatsapp'] ?? true) ? '1' : '0') == '1' || old('slim_topbar_show_whatsapp', $slimTopbar['show_whatsapp'] ?? true) === true)>
                            <label class="form-check-label" for="slim_topbar_show_whatsapp">Show WhatsApp</label>
                        </div>
                    </div>
                </div>
                <p class="form-text mb-2">Contact chips use <strong>Support phone</strong>, <strong>Support email</strong>, and <strong>Support WhatsApp</strong> from Contact details above - not duplicated here.</p>
                <div class="row g-2">
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label" for="slim_topbar_background_color">Background color</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="jp-control jp-control-color flex-shrink-0" id="slim_topbar_background_color_picker"
                                value="{{ $slimTopbarBgPicker }}" data-slim-topbar-color-picker="slim_topbar_background_color" title="Topbar background">
                            <input type="text" class="jp-control font-monospace" name="slim_topbar_background_color" id="slim_topbar_background_color"
                                value="{{ $slimTopbarBg }}" placeholder="#0F172A (optional)" maxlength="7">
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label" for="slim_topbar_text_color">Text color</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="jp-control jp-control-color flex-shrink-0" id="slim_topbar_text_color_picker"
                                value="{{ $slimTopbarTextPicker }}" data-slim-topbar-color-picker="slim_topbar_text_color" title="Topbar text">
                            <input type="text" class="jp-control font-monospace" name="slim_topbar_text_color" id="slim_topbar_text_color"
                                value="{{ $slimTopbarText }}" placeholder="#94A3B8 (optional)" maxlength="7">
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label" for="slim_topbar_accent_color">Icon / accent color</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="jp-control jp-control-color flex-shrink-0" id="slim_topbar_accent_color_picker"
                                value="{{ $slimTopbarAccentPicker }}" data-slim-topbar-color-picker="slim_topbar_accent_color" title="Topbar icons">
                            <input type="text" class="jp-control font-monospace" name="slim_topbar_accent_color" id="slim_topbar_accent_color"
                                value="{{ $slimTopbarAccent }}" placeholder="#F59E0B (optional)" maxlength="7">
                        </div>
                    </div>
                </div>
                <div class="form-text">Leave color fields empty to use brand secondary / accent defaults. Invalid hex values are ignored on save.</div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Brand colors</h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-2 mb-2">
                    <div class="col-lg-6">
                        <label class="jp-label" for="color_scheme">Color scheme preset</label>
                        <select class="jp-control" id="color_scheme" name="color_scheme" data-brand-scheme-select>
                            @foreach ($colorSchemeOptions as $schemeKey => $scheme)
                                @if (! str_starts_with($schemeKey, 'logo_auto_'))
                                    <option value="{{ $schemeKey }}" @selected(old('color_scheme', $colorScheme ?? 'blue_travel') === $schemeKey)>
                                        {{ $scheme['label'] ?? $schemeKey }}
                                    </option>
                                @endif
                            @endforeach
                            @foreach ($colorSchemeOptions as $schemeKey => $scheme)
                                @if (str_starts_with($schemeKey, 'logo_auto_'))
                                    <option value="{{ $schemeKey }}" hidden @selected(old('color_scheme', $colorScheme ?? '') === $schemeKey)>
                                        {{ $scheme['label'] ?? $schemeKey }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <div class="form-text">Presets fill primary, secondary, and accent. Choose Custom or a logo suggestion to set your own hex values.</div>
                    </div>
                </div>
                <div class="row g-2" data-brand-custom-colors>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label" for="primary_color">Primary color</label>
                        <div class="d-flex align-items-center gap-2">
                            @php
                                $primaryHex = old('primary_color', $settings->primary_color);
                                $primaryHex = is_string($primaryHex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryHex) ? $primaryHex : '#1d4ed8';
                            @endphp
                            <input
                                type="color"
                                class="jp-control jp-control-color flex-shrink-0"
                                id="primary_color"
                                name="primary_color"
                                value="{{ $primaryHex }}"
                                data-brand-color-picker
                                data-brand-color-preview="primary_color_preview"
                                title="Choose primary color"
                            >
                            <input
                                type="text"
                                class="jp-control font-monospace"
                                id="primary_color_preview"
                                value="{{ $primaryHex }}"
                                readonly
                                tabindex="-1"
                                aria-label="Primary color hex value"
                            >
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label" for="secondary_color">Secondary color</label>
                        <div class="d-flex align-items-center gap-2">
                            @php
                                $secondaryHex = old('secondary_color', $settings->secondary_color);
                                $secondaryHex = is_string($secondaryHex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $secondaryHex) ? $secondaryHex : '#0ea5e9';
                            @endphp
                            <input
                                type="color"
                                class="jp-control jp-control-color flex-shrink-0"
                                id="secondary_color"
                                name="secondary_color"
                                value="{{ $secondaryHex }}"
                                data-brand-color-picker
                                data-brand-color-preview="secondary_color_preview"
                                title="Choose secondary color"
                            >
                            <input
                                type="text"
                                class="jp-control font-monospace"
                                id="secondary_color_preview"
                                value="{{ $secondaryHex }}"
                                readonly
                                tabindex="-1"
                                aria-label="Secondary color hex value"
                            >
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="jp-label" for="accent_color">Accent color</label>
                        <div class="d-flex align-items-center gap-2">
                            @php
                                $accentHex = old('accent_color', $settings->accent_color);
                                $accentHex = is_string($accentHex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $accentHex) ? $accentHex : '#f59e0b';
                            @endphp
                            <input
                                type="color"
                                class="jp-control jp-control-color flex-shrink-0"
                                id="accent_color"
                                name="accent_color"
                                value="{{ $accentHex }}"
                                data-brand-color-picker
                                data-brand-color-preview="accent_color_preview"
                                title="Choose accent color"
                            >
                            <input
                                type="text"
                                class="jp-control font-monospace"
                                id="accent_color_preview"
                                value="{{ $accentHex }}"
                                readonly
                                tabindex="-1"
                                aria-label="Accent color hex value"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Header CTA &amp; website</h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="jp-label">Header CTA label</label>
                        <input class="jp-control" name="header_cta_label" value="{{ old('header_cta_label', $settings->header_cta_label) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="jp-label">Header CTA URL</label>
                        <input class="jp-control" name="header_cta_url" value="{{ old('header_cta_url', $settings->header_cta_url) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="jp-label">Website URL</label>
                        <input class="jp-control" name="website_url" value="{{ old('website_url', $settings->website_url) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Location</h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="jp-label">Office address</label>
                        <input class="jp-control" name="office_address" value="{{ old('office_address', $settings->office_address) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="jp-label">City</label>
                        <input class="jp-control" name="city" value="{{ old('city', $settings->city) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="jp-label">Country</label>
                        <input class="jp-control" name="country" value="{{ old('country', $settings->country) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Media assets</h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="jp-label">Logo</label>
                        <input class="jp-control" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                        @if (!empty($logoUrl))
                            <div class="mt-2">
                                <img
                                    src="{{ $logoUrl }}"
                                    alt="Current logo"
                                    class="logo-palette-current-preview"
                                    width="120"
                                    height="48"
                                >
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="jp-label">Favicon</label>
                        <input class="jp-control" type="file" name="favicon">
                    </div>
                    <div class="col-12">
                        <label class="jp-label">Hero image (fallback)</label>
                        <input class="jp-control" type="file" name="hero_image" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Used when Homepage hero has no image. Recommended 1920x700 px, center-focused.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Logo-based color suggestions</h3>
            </div>
            <div class="card-body py-3" id="logo-palette-root" data-logo-url="{{ $logoUrl ?? '' }}">
                <p id="logo-palette-status" class="small text-muted mb-2"></p>
                <div id="logo-palette-cards" class="row g-2" aria-live="polite"></div>
            </div>
        </div>

        <div class="jp-card">
            <div class="card-footer bg-transparent d-flex justify-content-end py-3">
                <button type="submit" class="jp-btn jp-btn--primary">Save branding</button>
            </div>
        </div>
    </form>

    <style>
        .logo-palette-card {
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .logo-palette-card:hover {
            border-color: var(--tblr-primary, #206bc4) !important;
        }
        .logo-palette-card:focus-visible {
            outline: 2px solid var(--tblr-primary, #206bc4);
            outline-offset: 2px;
        }
        .logo-palette-swatch {
            display: inline-block;
            width: 1.75rem;
            height: 1.75rem;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }
        .logo-palette-header-strip {
            min-height: 1.75rem;
            line-height: 1.75rem;
        }
        .logo-palette-current-preview {
            max-height: 48px;
            max-width: 160px;
            width: auto;
            height: auto;
            object-fit: contain;
            background: transparent;
        }
        .card.border-dashed {
            border-style: dashed !important;
            min-height: 8rem;
        }
        @media (max-width: 575.98px) {
            .logo-palette-card .card-body {
                padding: 0.65rem !important;
            }
        }
    </style>

    <script src="{{ ui_asset('js/admin-branding-logo-palette.js') }}" defer></script>
    <script>
        document.querySelectorAll('[data-slim-topbar-color-picker]').forEach(function (picker) {
            var target = document.getElementById(picker.getAttribute('data-slim-topbar-color-picker') || '');
            if (!target) {
                return;
            }
            var syncToText = function () {
                target.value = picker.value.toUpperCase();
            };
            var syncToPicker = function () {
                if (/^#[0-9A-Fa-f]{6}$/.test(target.value.trim())) {
                    picker.value = target.value.trim();
                }
            };
            picker.addEventListener('input', syncToText);
            picker.addEventListener('change', syncToText);
            target.addEventListener('input', syncToPicker);
            target.addEventListener('change', syncToPicker);
        });

        document.querySelectorAll('[data-brand-color-picker]').forEach(function (picker) {
            var preview = document.getElementById(picker.getAttribute('data-brand-color-preview') || '');
            var sync = function () {
                if (preview) {
                    preview.value = picker.value;
                }
            };
            picker.addEventListener('input', sync);
            picker.addEventListener('change', sync);
            sync();
        });

        (function () {
            var schemeSelect = document.querySelector('[data-brand-scheme-select]');
            var customColors = document.querySelector('[data-brand-custom-colors]');
            if (!schemeSelect || !customColors) {
                return;
            }
            var isEditableScheme = function (value) {
                return value === 'custom' || (typeof value === 'string' && value.indexOf('logo_auto_') === 0);
            };
            var toggleCustom = function () {
                var editable = isEditableScheme(schemeSelect.value);
                customColors.querySelectorAll('input').forEach(function (input) {
                    input.disabled = !editable;
                });
                customColors.style.opacity = editable ? '1' : '0.72';
            };
            schemeSelect.addEventListener('change', toggleCustom);
            toggleCustom();

            window.__otaBrandingToggleScheme = toggleCustom;
        })();

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.OtaAdminBrandingLogoPalette === 'undefined') {
                return;
            }
            window.OtaAdminBrandingLogoPalette.init({
                logoUrl: @json($logoUrl),
                activeScheme: @json(old('color_scheme', $colorScheme ?? '')),
                onSchemeEditableChange: function () {
                    if (typeof window.__otaBrandingToggleScheme === 'function') {
                        window.__otaBrandingToggleScheme();
                    }
                },
            });
        });
    </script>
@endsection
