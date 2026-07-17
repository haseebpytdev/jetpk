@extends(client_layout('dashboard', 'admin'))

@section('title', 'Edit '.$pageLabel)

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-backlink"><a href="{{ client_route('admin.page-settings.index') }}">← Page settings</a></p>
            <h1>{{ $pageLabel }}</h1>
            <p>Edit draft content, preview on device widths, then publish when ready.</p>
            @if (! empty($editorMeta))
                <p class="jp-muted jp-muted--sm">
                    Form source: {{ str_replace('_', ' ', $editorMeta['form_source'] ?? 'unknown') }}.
                    @if (! empty($editorMeta['draft']))
                        Draft saved.
                    @elseif (! empty($editorMeta['published']))
                        Published content loaded.
                    @endif
                    @if (! empty($editorMeta['updated_at']))
                        Last updated {{ $editorMeta['updated_at'] }}.
                    @endif
                </p>
            @endif
            {{-- JETPK-HOMEPAGE-CMS Task 12: saved-default metadata --}}
            <p class="jp-muted jp-muted--sm" data-jp-default-metadata>
                @if ($activeDefault)
                    Saved default: {{ $activeDefault->label ?: 'Untitled' }}
                    (saved {{ $activeDefault->created_at?->diffForHumans() }}@if($activeDefault->createdBy) by {{ $activeDefault->createdBy->name }}@endif).
                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-default-toggle-form>Save current published as new default</button>
                @else
                    No saved default yet for this page —
                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-default-toggle-form>Save current published state as the default</button>
                @endif
            </p>
            <form method="post" action="{{ client_route('admin.page-settings.save-as-default', ['pageKey' => $pageKey]) }}" class="jp-card jp-is-hidden" data-jp-default-form>
                @csrf
                <p class="jp-muted jp-muted--sm">This becomes the target for "Reset to Default." Only save this after visually reviewing the live published page — not just the editor form.</p>
                <div class="jp-field">
                    <label class="jp-field__label" for="default-label">Label (optional)</label>
                    <input id="default-label" class="jp-control" name="label" maxlength="255" placeholder="e.g. Launch v1">
                </div>
                <div class="jp-field">
                    <label class="jp-field__label" for="default-note">Note (optional)</label>
                    <textarea id="default-note" class="jp-control jp-control--textarea" rows="2" name="note" maxlength="2000"></textarea>
                </div>
                <label class="jp-toggle">
                    <input type="checkbox" name="visual_approval_confirmed" value="1" required>
                    I have viewed the live published page and confirm it is correct.
                </label>
                <button type="submit" class="jp-btn jp-btn--sm">Save as default</button>
            </form>

            {{-- JETPK-HOMEPAGE-CMS Task 13: reset to default --}}
            @if ($activeDefault)
                <div class="jp-toolbar">
                    <form method="post" action="{{ client_route('admin.page-settings.reset.preview', ['pageKey' => $pageKey]) }}">
                        @csrf
                        <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Preview reset to default</button>
                    </form>
                    <form method="post" action="{{ client_route('admin.page-settings.reset.draft', ['pageKey' => $pageKey]) }}" onsubmit="return confirm('Reset the DRAFT to the saved default? Published is not affected. A revision of the current draft will be kept.');">
                        @csrf
                        <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Reset draft to default</button>
                    </form>
                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-reset-publish-toggle>Reset and publish…</button>
                </div>
                <form method="post" action="{{ client_route('admin.page-settings.reset.publish', ['pageKey' => $pageKey]) }}" class="jp-card jp-is-hidden" data-jp-reset-publish-form>
                    @csrf
                    <p class="jp-muted jp-muted--sm"><strong>This overwrites the LIVE page immediately.</strong> A revision of the current published state is kept and can be reviewed afterward, but visitors will see the default content right away.</p>
                    <label class="jp-toggle">
                        <input type="checkbox" name="reset_and_publish_confirmed" value="1" required>
                        I understand this replaces the live published page right now, and I want to proceed.
                    </label>
                    <button type="submit" class="jp-btn jp-btn--sm">Reset draft and publish immediately</button>
                </form>
            @endif
        </div>
        <div class="jp-toolbar">
            <form method="post" action="{{ client_route('admin.page-settings.preview.begin', ['pageKey' => $pageKey]) }}">
                @csrf
                <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Open preview tab</button>
            </form>
            <form method="post" action="{{ client_route('admin.page-settings.publish', ['pageKey' => $pageKey]) }}">
                @csrf
                <button type="submit" class="jp-btn jp-btn--sm">Publish</button>
            </form>
            @if ($pageKey === 'home')
            <form method="post" action="{{ client_route('admin.page-settings.home.refresh-fares') }}" onsubmit="return confirm('Refresh dynamic fares for all active routes now?');">
                @csrf
                <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Refresh route fares</button>
            </form>
            @endif
        </div>
    </div>
@endsection

@section('content')
    @if (session('status'))
        <div class="jp-alert jp-alert--info" data-jp-flash-status>{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--warn">
            <ul class="jp-list-plain">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="jp-page-editor" data-jp-page-editor data-preview-url="{{ $previewUrl }}">
        <div class="jp-page-editor__workspace">
            <div class="jp-page-editor__tabs" role="tablist" aria-label="Editor mode">
                <button type="button" class="jp-queue-tab is-active" data-jp-editor-tab="content" role="tab" aria-selected="true">Content</button>
                <button type="button" class="jp-queue-tab" data-jp-editor-tab="media" role="tab" aria-selected="false">Media</button>
            </div>

            <div class="jp-page-editor__panel" data-jp-editor-panel="content">
                <form method="post" action="{{ client_route('admin.page-settings.update', ['pageKey' => $pageKey]) }}" class="jp-stack jp-form-shell" data-jp-content-form @if($pageKey === 'home') enctype="multipart/form-data" @endif>
                    @csrf
                    @method('PATCH')
                    <div id="jp-submitted-sections" style="display:none" aria-hidden="true"></div>

                    @if ($pageKey === 'home')
                        <nav class="jp-page-editor__nav" aria-label="Home sections" data-jp-section-nav>
                            <button type="button" class="jp-queue-tab is-active" data-jp-section="hero">Hero</button>
                            <button type="button" class="jp-queue-tab" data-jp-section="trust-chips">Trust badges</button>
                            <button type="button" class="jp-queue-tab" data-jp-section="feature-board">Stats strip</button>
                            <button type="button" class="jp-queue-tab" data-jp-section="trust">Trust cards</button>
                            <button type="button" class="jp-queue-tab" data-jp-section="group-cards">Group cards</button>
                            <button type="button" class="jp-queue-tab" data-jp-section="featured-deals">Deals</button>
                            <button type="button" class="jp-queue-tab" data-jp-section="routes">Routes</button>
                            <button type="button" class="jp-queue-tab" data-jp-section="destinations">Destinations</button>
                            <button type="button" class="jp-queue-tab" data-jp-section="why-book">Why us</button>
                            <button type="button" class="jp-queue-tab" data-jp-section="support-cta">Support CTA</button>
                        </nav>
                        <div class="jp-page-editor__sections" data-jp-section-panels>
                            @include('themes.admin.jetpakistan.page-settings.partials.home-sections', [
                                'content' => $content,
                                'pageKey' => $pageKey,
                                'assets' => $assets,
                            ])
                        </div>
                    @elseif (in_array($pageKey, ['about', 'support', 'group-search', 'booking-lookup', 'agent-registration'], true))
                        <div class="jp-card">
                            <h2 class="jp-card__title">Page hero</h2>
                            @if ($pageKey === 'agent-registration')
                                <label class="jp-label">Kicker</label>
                                <input aria-label="Kicker" class="jp-control" name="content[hero][kicker]" value="{{ data_get($content, 'hero.kicker') }}" placeholder="Guidance only — leave empty to hide on publish">
                            @elseif ($pageKey !== 'booking-lookup')
                                <label class="jp-label">Kicker</label>
                                <input aria-label="Kicker" class="jp-control" name="content[hero][kicker]" value="{{ data_get($content, 'hero.kicker') }}" placeholder="Guidance only — leave empty to hide on publish">
                            @endif
                            <label class="jp-label">Title</label>
                            <input aria-label="Title" class="jp-control" name="content[hero][title]" value="{{ data_get($content, 'hero.title') }}" placeholder="Guidance only — leave empty to hide on publish">
                            <label class="jp-label">Description</label>
                            <textarea aria-label="Description" class="jp-control jp-control--textarea" rows="4" name="content[hero][description]" placeholder="Guidance only — leave empty to hide on publish">{{ data_get($content, 'hero.description') }}</textarea>
                            @if ($pageKey === 'booking-lookup')
                                <label class="jp-label">Help text</label>
                                <input aria-label="Help text" class="jp-control" name="content[hero][help_text]" value="{{ data_get($content, 'hero.help_text') }}" placeholder="Guidance only — leave empty to hide on publish">
                            @endif
                            @if ($pageKey === 'agent-registration')
                                <label class="jp-label">CTA text</label>
                                <input aria-label="CTA text" class="jp-control" name="content[hero][cta_text]" value="{{ data_get($content, 'hero.cta_text') }}" placeholder="Guidance only — leave empty to hide on publish">
                            @endif
                        </div>
                        @if (in_array($pageKey, ['about', 'support'], true))
                            <div class="jp-card">
                                <h2 class="jp-card__title">Contact details</h2>
                                <p class="jp-muted jp-muted--sm">Clear a field and publish to intentionally hide that detail on the public page.</p>
                                <label class="jp-label">Phone</label>
                                <input aria-label="Phone" class="jp-control" name="content[contact][phone]" value="{{ data_get($content, 'contact.phone') }}">
                                <label class="jp-label">Email</label>
                                <input aria-label="Email" class="jp-control" name="content[contact][email]" value="{{ data_get($content, 'contact.email') }}">
                                @if ($pageKey === 'support')
                                    <label class="jp-label">WhatsApp number (digits only)</label>
                                    <input aria-label="WhatsApp number (digits only)" class="jp-control" name="content[contact][whatsapp]" value="{{ data_get($content, 'contact.whatsapp') }}">
                                @endif
                                <label class="jp-label">Website URL</label>
                                <input aria-label="Website URL" class="jp-control" name="content[contact][website]" value="{{ data_get($content, 'contact.website') }}">
                                @if ($pageKey === 'about')
                                    <label class="jp-label">Office address</label>
                                    <textarea aria-label="Office address" class="jp-control jp-control--textarea" rows="3" name="content[contact][office]">{{ data_get($content, 'contact.office') }}</textarea>
                                @endif
                                <label class="jp-label">Support hours</label>
                                <input aria-label="Support hours" class="jp-control" name="content[contact][hours]" value="{{ data_get($content, 'contact.hours') }}">
                            </div>
                            @if ($pageKey === 'support')
                                <div class="jp-card">
                                    <h2 class="jp-card__title">Contact form</h2>
                                    <label class="jp-label">Helper text</label>
                                    <textarea aria-label="Helper text" class="jp-control jp-control--textarea" rows="2" name="content[form][helper_text]">{{ data_get($content, 'form.helper_text') }}</textarea>
                                </div>
                            @endif
                        @endif
                    @elseif ($pageKey === 'footer')
                        @include('themes.admin.jetpakistan.page-settings.partials.footer-sections')
                    @elseif ($pageKey === 'global')
                        @include('themes.admin.jetpakistan.page-settings.partials.global-sections')
                        @include('themes.admin.jetpakistan.page-settings.partials.branding-ownership')
                    @elseif (in_array($pageKey, ['terms', 'privacy', 'faq'], true))
                        @include('themes.admin.jetpakistan.page-settings.partials.legal-sections')
                    @else
                        <div class="jp-card">
                            <p class="jp-muted">Structured fields for this page use safe JSON draft storage.</p>
                            <textarea class="jp-control jp-control--textarea" rows="8" name="content[raw_note]" placeholder="Optional notes">{{ data_get($content, 'raw_note') }}</textarea>
                        </div>
                    @endif

                    <div class="jp-action-bar jp-action-bar--sticky">
                        <a href="{{ client_route('admin.page-settings.index') }}" class="jp-btn jp-btn--ghost">Back</a>
                        <button type="submit" class="jp-btn">Save draft</button>
                    </div>
                </form>
            </div>

            <div class="jp-page-editor__panel jp-is-hidden" data-jp-editor-panel="media">
                @if ($pageKey === 'global')
                    @include('themes.admin.jetpakistan.page-settings.partials.branding-ownership')
                @else
                    @include('themes.admin.jetpakistan.page-settings.partials.media-sections', [
                        'pageKey' => $pageKey,
                        'assets' => $assets,
                    ])
                @endif
            </div>
        </div>

        <aside class="jp-page-editor__preview" aria-label="Live preview">
            <div class="jp-preview-toolbar">
                <span class="jp-preview-toolbar__label">Preview</span>
                <div class="jp-preview-devices" data-jp-preview-devices>
                    <button type="button" class="jp-queue-tab is-active" data-width="100%" data-preview-mode="desktop">Desktop</button>
                    <button type="button" class="jp-queue-tab" data-width="768px" data-preview-mode="tablet">Tablet</button>
                    <button type="button" class="jp-queue-tab" data-width="390px" data-preview-mode="mobile">Mobile</button>
                </div>
                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-preview-refresh>Refresh</button>
            </div>
            <div class="jp-preview-frame-wrap" data-jp-preview-frame-wrap data-preview-mode="desktop" style="--jp-preview-w:100%;">
                <div class="jp-preview-loading jp-is-hidden" data-jp-preview-loading>Loading preview…</div>
                <iframe
                    title="Live page preview"
                    src="{{ $previewUrl }}"
                    class="jp-preview-frame"
                    data-jp-preview-frame
                    loading="lazy"
                ></iframe>
            </div>
            <p class="jp-muted jp-preview-note">Save draft, then refresh preview. Use “Open preview tab” for full draft content in the public page (admin session only).</p>
        </aside>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('themes/admin/jetpakistan/js/page-settings-editor.js') }}?v=3"></script>
@endpush
