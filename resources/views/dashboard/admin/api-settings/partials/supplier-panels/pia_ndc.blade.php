<div class="jp-provider-panel {{ $isPiaNdc ? '' : 'jp-is-hidden' }}" data-provider-panel="pia_ndc">
    <div class="jp-form-grid jp-form-grid--2">
        <div class="jp-field">
            <label class="jp-label" for="pia-ndc-environment">Environment</label>
            <select id="pia-ndc-environment" name="environment" class="jp-control" required data-pia-ndc-environment @disabled(! $isPiaNdc)>
                <option value="sandbox" @selected($piaNdcEnv === 'sandbox')>Cert</option>
                <option value="live" @selected($piaNdcEnv === 'live')>Live</option>
            </select>
        </div>
        <div class="jp-field jp-field--full">
            <label class="jp-label" for="pia-ndc-base-url">SOAP endpoint URL</label>
            <input id="pia-ndc-base-url" type="url" name="base_url" class="jp-control" value="{{ old('base_url', $connection->base_url) }}" required data-pia-ndc-base-url placeholder="https://.../CraneNDCService" @disabled(! $isPiaNdc)>
            <p class="form-hint">PIA NDC SOAP endpoint from Hitit/PIA credentials pack.</p>
        </div>
    </div>
</div>
