@extends(client_layout('dashboard', 'admin'))

@section('title', 'Background removal')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Media / AI tools — Background removal</h1>
            <p>Configure optional logo background removal for Company Branding uploads.</p>
        </div>
        <a href="{{ client_route('admin.settings.branding.edit') }}" class="jp-btn jp-btn--ghost jp-btn--sm">Back to Branding</a>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<form method="post" action="{{ client_route('admin.settings.background-removal.update') }}" class="jp-branding-page">
    @csrf
    @method('PATCH')

    <section class="jp-card jp-branding-section">
        <div class="jp-card__head">
            <h2 class="jp-card__title">Provider</h2>
            <p class="jp-help">Credentials are encrypted at rest and never exposed to the public site.</p>
        </div>
        <div class="jp-branding-grid jp-form-grid">
            <div class="jp-field">
                <label class="jp-check">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $setting->is_enabled))>
                    <span>Enable background removal</span>
                </label>
            </div>
            <div class="jp-field">
                <label class="jp-check">
                    <input type="hidden" name="default_for_logos" value="0">
                    <input type="checkbox" name="default_for_logos" value="1" @checked(old('default_for_logos', $setting->default_for_logos))>
                    <span>Default ON for new logo uploads</span>
                </label>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="provider">Provider</label>
                <select class="jp-control jp-select" id="provider" name="provider">
                    <option value="disabled" @selected(old('provider', $setting->provider) === 'disabled')>Disabled</option>
                    <option value="remove_bg" @selected(old('provider', $setting->provider) === 'remove_bg')>remove.bg-compatible API</option>
                </select>
            </div>
            <div class="jp-field jp-field--full">
                <label class="jp-label" for="api_endpoint">API endpoint</label>
                <input class="jp-control jp-input" id="api_endpoint" name="api_endpoint" value="{{ old('api_endpoint', $setting->api_endpoint) }}" placeholder="https://api.remove.bg/v1.0/removebg">
            </div>
            <div class="jp-field jp-field--full">
                <label class="jp-label" for="api_key">API key</label>
                <input class="jp-control jp-input" id="api_key" name="api_key" type="password" value="{{ old('api_key', $maskedApiKey) }}" autocomplete="new-password" placeholder="Leave blank to keep existing key">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="timeout_seconds">Timeout (seconds)</label>
                <input class="jp-control jp-input" id="timeout_seconds" name="timeout_seconds" type="number" min="5" max="120" value="{{ old('timeout_seconds', $setting->timeout_seconds) }}">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="max_source_bytes">Max source size (bytes)</label>
                <input class="jp-control jp-input" id="max_source_bytes" name="max_source_bytes" type="number" min="102400" max="10485760" value="{{ old('max_source_bytes', $setting->max_source_bytes) }}">
            </div>
        </div>
        <div class="jp-actions" style="margin-top:16px;">
            <button type="button" class="jp-btn jp-btn--ghost jp-btn--sm" id="jp-bg-removal-test">Test connection</button>
            <span class="jp-help" id="jp-bg-removal-test-result" aria-live="polite"></span>
        </div>
    </section>

    <div class="jp-action-bar jp-branding-action-bar">
        <div class="jp-action-bar__primary">
            <button type="submit" class="jp-btn jp-btn--primary">Save settings</button>
        </div>
    </div>
</form>

<script>
document.getElementById('jp-bg-removal-test')?.addEventListener('click', function () {
  var out = document.getElementById('jp-bg-removal-test-result');
  if (out) out.textContent = 'Testing…';
  fetch(@json(client_route('admin.settings.background-removal.test')), {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': @json(csrf_token()),
      'Accept': 'application/json',
    },
  }).then(function (r) { return r.json(); }).then(function (data) {
    if (out) out.textContent = data.message || (data.ok ? 'OK' : 'Failed');
  }).catch(function () {
    if (out) out.textContent = 'Test failed';
  });
});
</script>
@endsection
