<details class="jp-advanced-panel" data-supplier-advanced>
    <summary>Advanced configuration</summary>
    <div class="jp-advanced-panel__body">
        <div class="jp-provider-panel {{ $isSabre ? '' : 'jp-is-hidden' }}" data-provider-panel="sabre" data-advanced-only="sabre">
            <p class="jp-form-section__hint" style="margin:0 0 8px;">Sabre content channels and optional endpoint override.</p>
            <div class="jp-form-grid jp-form-grid--2">
                <label class="jp-check">
                    <input class="form-check-input" type="checkbox" value="1" data-sabre-gds-enabled-switch @checked($sabreGdsEnabled) @disabled(! $isSabre)>
                    <span>Enable Sabre GDS</span>
                </label>
                <label class="jp-check">
                    <input class="form-check-input" type="checkbox" value="1" data-sabre-ndc-enabled-switch @checked($sabreNdcEnabled) @disabled(! $isSabre)>
                    <span>Enable Sabre NDC</span>
                </label>
            </div>
            <p class="form-hint jp-is-hidden" data-sabre-channels-off-warning>Both channels are off. Sabre search and booking will be disabled.</p>
            <label class="jp-check" style="margin-top:8px;">
                <input class="form-check-input" type="checkbox" name="advanced_base_url_override" value="1" @checked($baseUrlOverride) data-sabre-base-url-override @disabled(! $isSabre)>
                <span>Override Sabre base URL manually</span>
            </label>
            <div class="jp-field jp-field--full jp-is-hidden" data-sabre-base-url-override-wrap>
                <label class="jp-label" for="sabre-base-url-override">Base URL override</label>
                <input id="sabre-base-url-override" type="url" class="jp-control" name="base_url" value="{{ old('base_url', $connection->base_url ?: $sabreBaseUrl) }}" data-sabre-base-url-override-input @disabled(! $baseUrlOverride || ! $isSabre)>
            </div>
        </div>
        <div class="jp-field jp-field--full">
            <label class="jp-label" for="settings-json">Settings (JSON)</label>
            <textarea id="settings-json" name="settings_json" class="jp-control" rows="4" placeholder="{}">{{ $settingsJsonDefault }}</textarea>
            <p class="form-hint">Optional provider-specific settings. Leave as <code>{}</code> unless documented by operations.</p>
        </div>
    </div>
</details>
