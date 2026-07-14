@php
    $supportBg = $assets->firstWhere('asset_key', 'support_cta_background');
    $supportBgMobile = $assets->firstWhere('asset_key', 'support_cta_background_mobile');
@endphp
<div class="jp-card jp-page-section jp-is-hidden" id="section-support-cta" data-jp-section-panel="support-cta">
    <div class="jp-between jp-section-toggle">
        <h2 class="jp-card__title" style="margin:0;">Support callout (pre-footer)</h2>
        <div class="jp-toggle">
            <input type="hidden" name="content[support_cta][enabled]" value="0">
            <input type="checkbox" id="support-cta-enabled" name="content[support_cta][enabled]" value="1" @checked(data_get($content, 'support_cta.enabled', '1') == '1')>
            <label for="support-cta-enabled">Enabled</label>
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="support-eyebrow">Eyebrow</label>
        <input id="support-eyebrow" class="jp-control" name="content[support_cta][eyebrow]" value="{{ data_get($content, 'support_cta.eyebrow') }}">
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="support-title">Heading</label>
        <input id="support-title" class="jp-control" name="content[support_cta][title]" value="{{ data_get($content, 'support_cta.title') }}">
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="support-body">Body</label>
        <textarea id="support-body" class="jp-control jp-control--textarea" rows="2" name="content[support_cta][subtitle]">{{ data_get($content, 'support_cta.subtitle') }}</textarea>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-toggle">
            <input type="hidden" name="content[support_cta][call_enabled]" value="0">
            <input type="checkbox" id="support-call-enabled" name="content[support_cta][call_enabled]" value="1" @checked(data_get($content, 'support_cta.call_enabled', '1') == '1')>
            <label for="support-call-enabled">Call Support button enabled</label>
        </div>
        <div class="jp-toggle">
            <input type="hidden" name="content[support_cta][chat_enabled]" value="0">
            <input type="checkbox" id="support-chat-enabled" name="content[support_cta][chat_enabled]" value="1" @checked(data_get($content, 'support_cta.chat_enabled', '1') == '1')>
            <label for="support-chat-enabled">Live Chat button enabled</label>
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="support-call-label">Call Support label</label>
            <input id="support-call-label" class="jp-control" name="content[support_cta][call_label]" value="{{ data_get($content, 'support_cta.call_label', data_get($content, 'support_cta.phone_label')) }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="support-phone">Call Support phone</label>
            <input id="support-phone" class="jp-control" name="content[support_cta][phone_value]" value="{{ data_get($content, 'support_cta.phone_value') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="support-call-url">Call Support URL (optional)</label>
            <input id="support-call-url" class="jp-control" name="content[support_cta][call_url]" value="{{ data_get($content, 'support_cta.call_url') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="support-chat-label">Live Chat label</label>
            <input id="support-chat-label" class="jp-control" name="content[support_cta][chat_label]" value="{{ data_get($content, 'support_cta.chat_label', data_get($content, 'support_cta.cta_label')) }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="support-chat-url">Live Chat URL</label>
            <input id="support-chat-url" class="jp-control" name="content[support_cta][chat_url]" value="{{ data_get($content, 'support_cta.chat_url', data_get($content, 'support_cta.cta_link')) }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="support-bg-mode">Background mode</label>
            <select id="support-bg-mode" class="jp-control jp-control--select" name="content[support_cta][background_mode]">
                @foreach (['gradient' => 'Gradient only', 'uploaded' => 'Uploaded image', 'uploaded_overlay' => 'Uploaded image + overlay'] as $value => $label)
                    <option value="{{ $value }}" @selected(data_get($content, 'support_cta.background_mode', 'gradient') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="support-overlay">Overlay strength</label>
            <select id="support-overlay" class="jp-control jp-control--select" name="content[support_cta][overlay_strength]">
                @foreach (['light', 'medium', 'strong'] as $level)
                    <option value="{{ $level }}" @selected(data_get($content, 'support_cta.overlay_strength', 'medium') === $level)>{{ ucfirst($level) }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="support-align">Text alignment</label>
            <select id="support-align" class="jp-control jp-control--select" name="content[support_cta][text_alignment]">
                @foreach (['left', 'center'] as $align)
                    <option value="{{ $align }}" @selected(data_get($content, 'support_cta.text_alignment', 'left') === $align)>{{ ucfirst($align) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="jp-media-inline jp-stack">
        <h3 class="jp-card__title" style="font-size:1rem;">Banner image (desktop)</h3>
        @if ($supportBg?->public_url)
            <img src="{{ $supportBg->public_url }}" alt="" class="jp-media-inline__preview" loading="lazy">
        @endif
        <div class="jp-field">
            <label class="jp-field__label">Upload desktop banner</label>
            <input type="file" name="support_cta_background_file" accept="image/jpeg,image/png,image/webp" class="jp-control">
        </div>
        @if ($supportBg)
            <label class="jp-toggle"><input type="checkbox" name="support_cta_background_remove" value="1"> Remove desktop banner on save</label>
        @endif
    </div>

    <div class="jp-media-inline jp-stack">
        <h3 class="jp-card__title" style="font-size:1rem;">Banner image (mobile)</h3>
        @if ($supportBgMobile?->public_url)
            <img src="{{ $supportBgMobile->public_url }}" alt="" class="jp-media-inline__preview" loading="lazy">
        @endif
        <div class="jp-field">
            <label class="jp-field__label">Upload mobile banner</label>
            <input type="file" name="support_cta_background_mobile_file" accept="image/jpeg,image/png,image/webp" class="jp-control">
        </div>
        @if ($supportBgMobile)
            <label class="jp-toggle"><input type="checkbox" name="support_cta_background_mobile_remove" value="1"> Remove mobile banner on save</label>
        @endif
    </div>
</div>
