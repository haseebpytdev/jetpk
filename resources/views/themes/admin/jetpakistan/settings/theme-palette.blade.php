@extends(client_layout('dashboard', 'admin'))

@section('title', 'Day / Night Theme Palette')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-backlink"><a href="{{ client_route('admin.settings.branding.edit') }}">← Branding settings</a></p>
            <h1>Day / Night Theme Palette</h1>
            <p>Configure JetPakistan primary actions, accents, surfaces and text for each theme mode.</p>
        </div>
    </div>
@endsection

@section('content')
    @include('themes.admin.jetpakistan.partials.flash')

    @if (session('status'))
        <div class="jp-alert jp-alert--info">
            @if (session('status') === 'theme-palette-updated')
                Theme palette saved.
            @elseif (str_starts_with(session('status'), 'theme-palette-reset-'))
                {{ ucfirst(str_replace('theme-palette-reset-', '', session('status'))) }} theme reset to defaults.
            @else
                {{ session('status') }}
            @endif
        </div>
    @endif

    <form method="post" action="{{ client_route('admin.settings.theme-palette.update') }}" class="jp-theme-palette-form" data-jp-theme-palette-form>
        @csrf
        @method('PATCH')
        <input type="hidden" name="save_scope" value="both" data-jp-save-scope>

        @foreach (['day' => 'Day Theme Palette', 'night' => 'Night Theme Palette'] as $themeKey => $themeTitle)
            @php
                $themePalette = $palettes[$themeKey] ?? [];
                $themeDefaults = $defaults[$themeKey] ?? [];
                $isDay = $themeKey === 'day';
            @endphp
            <section class="jp-card jp-theme-palette-section" data-jp-palette-section="{{ $themeKey }}">
                <div class="jp-card__head jp-theme-palette-section__head">
                    <div>
                        <span class="jp-theme-badge jp-theme-badge--{{ $themeKey }}">{{ $isDay ? 'Day' : 'Night' }}</span>
                        <h2 class="jp-card__title">{{ $themeTitle }}</h2>
                        <p class="jp-help">
                            This palette affects JetPakistan public pages, booking pages and dashboards when
                            {{ $isDay ? 'Day' : 'Night' }} Theme is active.
                        </p>
                        <p class="jp-help jp-muted">
                            Operational status colors such as warnings, errors and success states remain semantically controlled.
                        </p>
                    </div>
                </div>

                <div class="jp-theme-palette-layout">
                    <div class="jp-theme-palette-fields">
                        @foreach ($keys as $fieldKey)
                            @php
                                $value = old($themeKey.'.'.$fieldKey, $themePalette[$fieldKey] ?? '');
                                $saved = $themePalette[$fieldKey] ?? '';
                                $default = $themeDefaults[$fieldKey] ?? '';
                                $picker = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) ? $value : $default;
                                $fieldErrors = $errors->get($themeKey.'.'.$fieldKey);
                            @endphp
                            <div class="jp-field jp-color-field" data-jp-palette-field="{{ $themeKey }}-{{ $fieldKey }}">
                                <label class="jp-label" for="{{ $themeKey }}_{{ $fieldKey }}">{{ $labels[$fieldKey] ?? ucfirst($fieldKey) }}</label>
                                <p class="jp-help">{{ $helpers[$fieldKey] ?? '' }}</p>
                                <div class="jp-color-field__row">
                                    <input type="color" class="jp-color-field__swatch"
                                        id="{{ $themeKey }}_{{ $fieldKey }}_picker"
                                        value="{{ $picker }}"
                                        data-jp-palette-picker="{{ $themeKey }}-{{ $fieldKey }}">
                                    <input type="text" class="jp-control jp-input"
                                        name="{{ $themeKey }}[{{ $fieldKey }}]"
                                        id="{{ $themeKey }}_{{ $fieldKey }}"
                                        value="{{ $value }}"
                                        maxlength="7"
                                        placeholder="{{ $default }}"
                                        data-jp-palette-input="{{ $themeKey }}-{{ $fieldKey }}"
                                        data-jp-palette-saved="{{ $saved }}"
                                        data-jp-palette-default="{{ $default }}">
                                </div>
                                <p class="jp-help jp-muted">
                                    Saved: <code data-jp-saved-indicator="{{ $themeKey }}-{{ $fieldKey }}">{{ $saved }}</code>
                                    @if ($saved !== $default)
                                        · Default: <code>{{ $default }}</code>
                                    @endif
                                </p>
                                @if ($fieldErrors)
                                    @foreach ($fieldErrors as $error)
                                        <p class="jp-field-error">{{ $error }}</p>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="jp-theme-palette-preview jp-theme-preview--{{ $themeKey }}" data-jp-palette-preview="{{ $themeKey }}">
                        <p class="jp-help">Live preview (unsaved changes)</p>
                        <div class="jp-theme-preview__surface">
                            <h3 class="jp-theme-preview__heading">Sample heading</h3>
                            <p class="jp-theme-preview__body">Body text and descriptions use the selected palette.</p>
                            <div class="jp-theme-preview__tabs">
                                <span class="jp-theme-preview__tab is-active">Active tab</span>
                                <span class="jp-theme-preview__tab">Tab</span>
                            </div>
                            <div class="jp-theme-preview__actions">
                                <button type="button" class="jp-theme-preview__btn jp-theme-preview__btn--primary">Primary action</button>
                                <button type="button" class="jp-theme-preview__btn jp-theme-preview__btn--secondary">Secondary</button>
                                <a href="#" class="jp-theme-preview__link" onclick="return false;">Text link</a>
                            </div>
                            <div class="jp-theme-preview__badges">
                                <span class="jp-theme-preview__badge jp-theme-preview__badge--success">Confirmed</span>
                                <span class="jp-theme-preview__badge jp-theme-preview__badge--warning">Pending</span>
                            </div>
                            <input type="text" class="jp-theme-preview__input" value="Sample input" readonly aria-label="Sample input">
                            <div class="jp-theme-preview__card">
                                <strong>Sample card</strong>
                                <p>Card and panel surface preview.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endforeach

        <div class="jp-action-bar">
            <div class="jp-action-bar__primary">
                <button type="submit" class="jp-btn jp-btn--primary">Save theme palette</button>
            </div>
            <div class="jp-action-bar__secondary" style="display:flex;gap:8px;flex-wrap:wrap;">
                @foreach (['day' => 'Day', 'night' => 'Night'] as $resetTheme => $resetLabel)
                    <form method="post" action="{{ client_route('admin.settings.theme-palette.reset', ['theme' => $resetTheme]) }}" class="jp-inline-form">
                        @csrf
                        <button type="submit" class="jp-btn jp-btn--ghost jp-btn--sm">Reset {{ $resetLabel }} to defaults</button>
                    </form>
                @endforeach
            </div>
        </div>
    </form>

    <style>
        .jp-theme-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; margin-bottom:8px; }
        .jp-theme-badge--day { background:#E8F4EF; color:#006B45; }
        .jp-theme-badge--night { background:#102A22; color:#46C96F; }
        .jp-theme-palette-section__head { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; }
        .jp-theme-palette-layout { display:grid; gap:24px; }
        @media (min-width: 1100px) { .jp-theme-palette-layout { grid-template-columns: minmax(0,1fr) minmax(280px,360px); } }
        .jp-theme-palette-fields { display:grid; gap:16px; }
        .jp-theme-palette-preview { border:1px solid var(--line); border-radius:14px; padding:16px; }
        .jp-theme-preview--day { background:#EDF3F7; }
        .jp-theme-preview--night { background:#070F18; }
        .jp-theme-preview__surface { border-radius:12px; padding:16px; background:var(--jp-preview-surface, #fff); color:var(--jp-preview-text, #0B1D2A); border:1px solid var(--jp-preview-border, #D7E2E9); }
        .jp-theme-preview__heading { margin:0 0 8px; font-size:18px; }
        .jp-theme-preview__body { margin:0 0 12px; color:var(--jp-preview-text-muted, #62788A); font-size:14px; }
        .jp-theme-preview__tabs { display:flex; gap:8px; margin-bottom:12px; }
        .jp-theme-preview__tab { padding:6px 12px; border-radius:999px; font-size:13px; border:1px solid var(--jp-preview-border, #D7E2E9); color:var(--jp-preview-text-muted, #62788A); }
        .jp-theme-preview__tab.is-active { background:var(--jp-preview-accent-soft, #E5F7F7); color:var(--jp-preview-accent, #19A7A6); border-color:var(--jp-preview-accent, #19A7A6); font-weight:600; }
        .jp-theme-preview__actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:12px; }
        .jp-theme-preview__btn { border:0; border-radius:999px; padding:8px 16px; font-size:13px; font-weight:600; cursor:default; }
        .jp-theme-preview__btn--primary { background:var(--jp-preview-primary, #006B45); color:#fff; }
        .jp-theme-preview__btn--secondary { background:transparent; color:var(--jp-preview-primary, #006B45); border:1px solid var(--jp-preview-primary-border, #B8D9CC); }
        .jp-theme-preview__link { color:var(--jp-preview-accent, #19A7A6); font-size:13px; font-weight:600; text-decoration:none; }
        .jp-theme-preview__badges { display:flex; gap:8px; margin-bottom:12px; }
        .jp-theme-preview__badge { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; }
        .jp-theme-preview__badge--success { background:var(--jp-preview-success-soft, #E8F4EF); color:var(--jp-preview-success, #63B32E); }
        .jp-theme-preview__badge--warning { background:#FEF3C7; color:#B45309; }
        .jp-theme-preview__input { width:100%; padding:8px 12px; border-radius:10px; border:1px solid var(--jp-preview-border, #D7E2E9); background:var(--jp-preview-surface, #fff); color:var(--jp-preview-text, #0B1D2A); margin-bottom:12px; }
        .jp-theme-preview__card { border:1px solid var(--jp-preview-border, #D7E2E9); border-radius:12px; padding:12px; background:var(--jp-preview-surface-muted, #F6FAFC); font-size:14px; }
        .jp-field-error { color:#DC2626; font-size:13px; margin-top:4px; }
        .jp-inline-form { margin:0; }
    </style>

    <script>
    (function () {
        var previewMap = {
            primary: '--jp-preview-primary',
            accent: '--jp-preview-accent',
            success: '--jp-preview-success',
            page_bg: '--jp-preview-page-bg',
            surface: '--jp-preview-surface',
            text: '--jp-preview-text',
            text_muted: '--jp-preview-text-muted',
            border: '--jp-preview-border'
        };

        function syncPicker(picker, input) {
            picker.addEventListener('input', function () {
                input.value = picker.value.toUpperCase();
                input.dispatchEvent(new Event('input'));
            });
            input.addEventListener('input', function () {
                if (/^#[0-9A-Fa-f]{6}$/.test(input.value.trim())) {
                    picker.value = input.value.trim();
                }
            });
        }

        function updatePreview(theme) {
            var preview = document.querySelector('[data-jp-palette-preview="' + theme + '"]');
            if (!preview) return;
            Object.keys(previewMap).forEach(function (key) {
                var input = document.querySelector('[data-jp-palette-input="' + theme + '-' + key + '"]');
                if (!input) return;
                var val = input.value.trim();
                if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                    preview.style.setProperty(previewMap[key], val);
                }
            });
            var surface = preview.querySelector('.jp-theme-preview__surface');
            var pageBg = document.querySelector('[data-jp-palette-input="' + theme + '-page_bg"]');
            if (surface && pageBg && /^#[0-9A-Fa-f]{6}$/.test(pageBg.value.trim())) {
                surface.style.background = pageBg.value.trim();
            }
        }

        document.querySelectorAll('[data-jp-palette-picker]').forEach(function (picker) {
            var key = picker.getAttribute('data-jp-palette-picker');
            var input = document.querySelector('[data-jp-palette-input="' + key + '"]');
            if (!input) return;
            syncPicker(picker, input);
            input.addEventListener('input', function () {
                updatePreview(key.split('-')[0]);
            });
        });

        ['day', 'night'].forEach(updatePreview);
    })();
    </script>
@endsection
