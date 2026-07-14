<div class="jp-provider-panel {{ $isSabre ? '' : 'jp-is-hidden' }}" data-provider-panel="sabre">
    <div class="jp-form-grid jp-form-grid--2">
        <div class="jp-field">
            <label class="jp-label" for="sabre-environment">Environment</label>
            <select id="sabre-environment" name="environment" class="jp-control" required data-sabre-environment @disabled(! $isSabre)>
                <option value="sandbox" @selected($sabreEnv === 'sandbox')>CERT</option>
                <option value="live" @selected($sabreEnv === 'live')>LIVE</option>
            </select>
            <p class="form-hint">CERT uses Sabre certification host; LIVE uses production.</p>
        </div>
        <div class="jp-field jp-field--full">
            <span class="jp-endpoint-summary__label">Base URL (auto-derived)</span>
            <div class="jp-endpoint-summary">
                <span data-sabre-base-url-preview>{{ $sabreBaseUrl }}</span>
            </div>
            <input type="hidden" name="base_url" value="{{ old('base_url', $sabreBaseUrl) }}" data-sabre-base-url-input @disabled(! $isSabre)>
            <p class="form-hint">Override in Advanced configuration if required.</p>
        </div>
    </div>
    <input type="hidden" name="sabre_gds_enabled" value="{{ $sabreGdsEnabled ? '1' : '0' }}" data-sabre-gds-enabled-input>
    <input type="hidden" name="sabre_ndc_enabled" value="{{ $sabreNdcEnabled ? '1' : '0' }}" data-sabre-ndc-enabled-input>
</div>
