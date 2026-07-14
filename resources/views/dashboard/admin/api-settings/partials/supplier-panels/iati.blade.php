<div class="jp-provider-panel {{ $isIati ? '' : 'jp-is-hidden' }}" data-provider-panel="iati">
    <div class="jp-form-grid jp-form-grid--2">
        <div class="jp-field">
            <label class="jp-label" for="iati-environment">Environment</label>
            <select id="iati-environment" name="environment" class="jp-control" required data-iati-environment @disabled(! $isIati)>
                <option value="sandbox" @selected($iatiEnv === 'sandbox')>Cert</option>
                <option value="live" @selected($iatiEnv === 'live')>Live</option>
            </select>
            <p class="form-hint">Cert uses IATI test endpoint. Live uses IATI production.</p>
        </div>
        <div class="jp-field jp-field--full">
            <span class="jp-endpoint-summary__label">Endpoint (auto-derived)</span>
            <div class="jp-endpoint-summary">
                <span data-iati-base-url-preview>{{ $iatiBaseUrl }}</span>
            </div>
            <input type="hidden" name="base_url" value="{{ old('base_url', $iatiBaseUrl) }}" data-iati-base-url-input @disabled(! $isIati)>
        </div>
    </div>
</div>
