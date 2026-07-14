@php
    $isGeneric = ! $isSabre && ! $isIati && ! $isPiaNdc && ! $isAirblue;
@endphp
<div class="jp-provider-panel {{ $isGeneric ? '' : 'jp-is-hidden' }}" data-provider-panel="generic">
    <div class="jp-form-grid jp-form-grid--2">
        <div class="jp-field">
            <label class="jp-label" for="generic-environment">Environment</label>
            <select id="generic-environment" name="environment" class="jp-control" required @disabled(! $isGeneric)>
                @foreach ($environments as $environment)
                    <option value="{{ $environment->value }}" @selected(old('environment', $connection->environment?->value ?? 'demo') === $environment->value)>
                        {{ ucfirst($environment->value) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="jp-field jp-field--full">
            <label class="jp-label" for="generic-base-url">Base URL</label>
            <input id="generic-base-url" type="url" name="base_url" class="jp-control" value="{{ old('base_url', $connection->base_url) }}" placeholder="https://api.example.com" @disabled(! $isGeneric)>
            <p class="form-hint">API base URL for this provider (optional for token-only APIs such as Duffel).</p>
        </div>
    </div>
</div>
