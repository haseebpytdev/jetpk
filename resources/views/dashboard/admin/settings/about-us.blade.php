@extends(client_layout('dashboard', 'admin'))

@section('title', 'About Us Settings')

@section('page-header')
    <h1 class="jp-page-title">Branding / About Us</h1>
@endsection

@section('content')
    @php
        $aboutUs = $aboutUs ?? [];
        $htmlActive = (bool) old('html_active', $aboutUs['html_active'] ?? false);
    @endphp

    @if (session('status') === 'about-us-settings-updated')
        <div class="jp-alert jp-alert--success py-2 mb-2">About Us settings saved.</div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger py-2 mb-2"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="alert alert-light border py-2 mb-3 small">
        <a href="{{ route('about') }}" target="_blank" rel="noopener">Preview public About Us</a>
        · Footer “about” blurb remains on <a href="{{ route('admin.settings.branding.footer.edit') }}">Footer settings</a>.
    </div>

    <form method="post" action="{{ route('admin.settings.branding.about-us.update') }}" x-data="{ htmlActive: @json($htmlActive) }">
        @csrf
        @method('PATCH')

        <div class="card mb-2">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Editing mode</h3>
            </div>
            <div class="card-body py-3">
                <div class="form-check mb-2">
                    <input type="hidden" name="html_active" value="0">
                    <input class="form-check-input" type="checkbox" name="html_active" value="1" id="about_us_html_active"
                        x-model="htmlActive"
                        @checked($htmlActive)>
                    <label class="form-check-label" for="about_us_html_active">Use HTML override on the public About Us page</label>
                </div>
                <div class="jp-alert jp-alert--warn py-2 mb-0 small" x-show="htmlActive" x-cloak>
                    HTML override replaces the normal About Us content on the public page.
                </div>
                <p class="form-text mb-0 mt-2" x-show="!htmlActive">
                    Plain mode publishes formatted text (paragraphs, bold, italic, lists, safe headings). Leave both fields empty to keep the built-in default About Us page.
                </p>
            </div>
        </div>

        <div class="card mb-2" x-show="!htmlActive">
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">Plain / content editor</h3>
            </div>
            <div class="card-body py-3">
                <label class="jp-label" for="about_us_plain">About Us content</label>
                <textarea class="jp-control font-monospace" name="plain" id="about_us_plain" rows="14"
                    placeholder="Write About Us copy. Basic HTML is allowed: &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;h2&gt;, &lt;h3&gt;.">{{ old('plain', $aboutUs['plain'] ?? '') }}</textarea>
                <p class="form-text mb-0">Scripts and unsafe markup are stripped on save. Plain text is line-broken on display.</p>
            </div>
        </div>

        <div class="card mb-2" x-show="htmlActive" x-cloak>
            <div class="card-header py-2">
                <h3 class="jp-card__title mb-0">HTML override</h3>
            </div>
            <div class="card-body py-3">
                <label class="jp-label" for="about_us_html_override">HTML content</label>
                <textarea class="jp-control font-monospace" name="html_override" id="about_us_html_override" rows="16"
                    placeholder="Advanced HTML for the public About Us body.">{{ old('html_override', $aboutUs['html_override'] ?? '') }}</textarea>
                <p class="form-text mb-0">
                    Allowed: common layout tags, links, tables. Blocked on save: <code>&lt;script&gt;</code>, <code>iframe</code>/<code>object</code>/<code>embed</code>, inline event handlers, <code>javascript:</code> URLs.
                </p>
            </div>
        </div>

        @if (!empty($aboutUs['updated_at']))
            <p class="text-secondary small mb-2">Last saved: {{ $aboutUs['updated_at'] }}</p>
        @endif

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="jp-btn jp-btn--primary">Save &amp; publish</button>
            <a href="{{ route('admin.settings.branding.edit') }}" class="jp-btn jp-btn--ghost">Back to branding</a>
        </div>
    </form>
@endsection
