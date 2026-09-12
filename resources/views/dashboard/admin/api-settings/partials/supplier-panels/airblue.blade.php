<div class="jp-provider-panel {{ $isAirblue ? '' : 'jp-is-hidden' }}" data-provider-panel="airblue">
    <input type="hidden" name="credentials[api_channel]" value="zapways_ota" data-airblue-channel>
    <div class="jp-form-grid jp-form-grid--2">
        <div class="jp-field">
            <label class="form-label" for="airblue-environment">Environment</label>
            <select id="airblue-environment" name="environment" class="form-select" required data-airblue-environment @disabled(! $isAirblue)>
                <option value="sandbox" @selected($airblueEnv === 'sandbox')>Cert</option>
                <option value="live" @selected($airblueEnv === 'live')>Live</option>
            </select>
        </div>
        <div class="jp-field jp-field--full">
            <label class="form-label" for="airblue-base-url">Zapways endpoint URL</label>
            <input id="airblue-base-url" type="url" name="base_url" class="form-control" value="{{ old('base_url', $connection->base_url) }}" required data-airblue-base-url placeholder="https://ota3.zapways.com/v2.0/OTAAPI.asmx" @disabled(! $isAirblue)>
            <p class="form-hint">AirBlue inventory via Zapways API / NDC (cert uses ota.qa.zapways.com).</p>
        </div>
    </div>
</div>
