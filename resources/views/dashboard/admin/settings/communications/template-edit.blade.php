@extends(client_layout('dashboard', 'admin'))

@section('title', 'Edit Event Content')

@section('page-header')
    <h1 class="jp-page-title">Edit Event Content</h1>
@endsection

@section('content')
    <div class="text-secondary small mb-3">
        <a href="{{ route('admin.settings.communications.templates.index') }}">&larr; Back to event content</a>
    </div>

    @if($eventContent ?? null)
        <div class="alert alert-light border small mb-3" data-testid="event-content-defaults">
            <strong>{{ $eventContent->name }}</strong>{{ display_sep_dot() }}
            Universal shell blocks: <code>{{ implode(', ', $eventContent->contentBlocks) }}</code>
        </div>
    @endif

    @if($isNewTemplate ?? false)
        <div class="jp-alert jp-alert--info border small mb-3" data-testid="template-edit-create-notice">
            <strong>Needs setup.</strong> Default event content is pre-filled below. Save to create your override for this notification.
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-8">
            <div class="jp-card">
                <div class="jp-card__body">
                    <form method="post" action="{{ route('admin.settings.communications.templates.update', ['event' => $event, 'channel' => $channel]) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-2">
                            <label class="jp-label">Subject</label>
                            <input class="jp-control" name="subject" value="{{ old('subject', $contentOverride['subject'] ?? $resolvedContent['subject'] ?? $template->subject) }}">
                        </div>
                        <div class="mb-2">
                            <label class="jp-label">Preheader</label>
                            <input class="jp-control" name="preheader" value="{{ old('preheader', $contentOverride['preheader'] ?? $resolvedContent['preheader'] ?? '') }}">
                        </div>
                        <div class="mb-2">
                            <label class="jp-label">Heading</label>
                            <input class="jp-control" name="heading" value="{{ old('heading', $contentOverride['heading'] ?? $resolvedContent['heading'] ?? '') }}">
                        </div>
                        <div class="mb-2">
                            <label class="jp-label">Intro / body</label>
                            <textarea class="jp-control" name="body" rows="8">{{ old('body', $contentOverride['intro'] ?? $contentOverride['body'] ?? $resolvedContent['intro'] ?? $template->body) }}</textarea>
                        </div>
                        <div class="jp-form-grid jp-form-grid--2">
                            <div class="mb-2">
                                <label class="jp-label">Status label</label>
                                <input class="jp-control" name="status_label" value="{{ old('status_label', $contentOverride['status_label'] ?? $resolvedContent['status_label'] ?? '') }}">
                            </div>
                            <div class="mb-2">
                                <label class="jp-label">Status type</label>
                                <select class="jp-control" name="status_type">
                                    @foreach(['info', 'success', 'warning', 'error', 'neutral'] as $tone)
                                        <option value="{{ $tone }}" @selected(old('status_type', $contentOverride['status_type'] ?? $resolvedContent['status_type'] ?? 'info') === $tone)>{{ ucfirst($tone) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="jp-form-grid jp-form-grid--2">
                            <div class="mb-2">
                                <label class="jp-label">CTA label</label>
                                <input class="jp-control" name="cta_label" value="{{ old('cta_label', $contentOverride['cta_label'] ?? $resolvedContent['cta_label'] ?? '') }}">
                            </div>
                            <div class="mb-2">
                                <label class="jp-label">CTA URL key</label>
                                <input class="jp-control" name="cta_url_key" value="{{ old('cta_url_key', $contentOverride['cta_url_key'] ?? $eventContent?->ctaUrlKey ?? '') }}" placeholder="booking_url">
                            </div>
                        </div>

                        <label class="form-check mb-3">
                            <input class="form-check-input" name="is_enabled" type="checkbox" value="1" {{ old('is_enabled', $template->is_enabled) ? 'checked' : '' }}>
                            <span class="form-check-label">Event content enabled</span>
                        </label>

                        <details class="jp-card jp-stack-sm mb-3" data-testid="full-html-override-panel">
                            <summary class="jp-card__head" style="cursor:pointer;">
                                <h3 class="jp-card__title mb-0">Advanced: full HTML override</h3>
                            </summary>
                            <div class="jp-card__body">
                                <p class="jp-help">Disabled by default. When enabled, replaces the universal shell body with custom HTML. Use only for exceptional cases.</p>
                                <label class="form-check mb-2">
                                    <input class="form-check-input" name="full_html_override_enabled" type="checkbox" value="1" {{ old('full_html_override_enabled', $fullHtmlOverrideEnabled ?? false) ? 'checked' : '' }}>
                                    <span class="form-check-label">Enable full HTML override</span>
                                </label>
                                <textarea class="jp-control" name="full_html" rows="6" placeholder="Custom HTML (only used when override is enabled)">{{ old('full_html', $contentOverride['full_html'] ?? '') }}</textarea>
                            </div>
                        </details>

                        <button type="submit" class="jp-btn jp-btn--primary">Save event content</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="jp-card">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Detail field schema</h3></div>
                <div class="jp-card__body">
                    @forelse($eventContent?->detailFields ?? [] as $field)
                        <div><code>{{ $field }}</code></div>
                    @empty
                        <p class="jp-muted">No detail fields for this event.</p>
                    @endforelse
                </div>
            </div>
            <div class="jp-card">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Allowed placeholders</h3></div>
                <div class="jp-card__body">
                    @foreach($allowedVariables as $var)
                        <div><code>{{ '{' . '{' . $var . '}' . '}' }}</code></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
