<div class="jp-provider-panel {{ $isAirblue ? '' : 'jp-is-hidden' }}" data-provider-panel="airblue">
    <div class="jp-form-grid jp-form-grid--2">
        <div class="jp-field">
            <label class="jp-label" for="airblue-channel">API channel</label>
            <select id="airblue-channel" name="credentials[api_channel]" class="jp-control" data-airblue-channel @disabled(! $isAirblue)>
                <option value="crane_ndc" @selected($airblueChannel === 'crane_ndc')>Crane NDC</option>
                <option value="zapways_ota" @selected($airblueChannel === 'zapways_ota')>Zapways OTA</option>
            </select>
        </div>
        <div class="jp-field">
            <label class="jp-label" for="airblue-environment">Environment</label>
            <select id="airblue-environment" name="environment" class="jp-control" required data-airblue-environment @disabled(! $isAirblue)>
                <option value="sandbox" @selected($airblueEnv === 'sandbox')>Cert</option>
                <option value="live" @selected($airblueEnv === 'live')>Live</option>
            </select>
        </div>
        <div class="jp-field jp-field--full">
            <label class="jp-label" for="airblue-base-url">Endpoint URL</label>
            <input id="airblue-base-url" type="url" name="base_url" class="jp-control" value="{{ old('base_url', $connection->base_url) }}" required data-airblue-base-url placeholder="https://app.crane.aero/cranendc/v20.1/CraneNDCService" @disabled(! $isAirblue)>
            <p class="form-hint" data-airblue-wsdl-hint>Crane NDC WSDL: https://app.crane.aero/cranendc/v20.1/CraneNDCService?wsdl</p>
        </div>
    </div>
</div>
