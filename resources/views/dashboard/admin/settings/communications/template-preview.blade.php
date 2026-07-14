@extends(client_layout('dashboard', 'admin'))

@section('title', 'Template Preview')

@section('page-header')
    <h1 class="jp-page-title">Email Template Preview</h1>
@endsection

@section('content')
    <div class="text-secondary small mb-3">
        <a href="{{ route('admin.settings.communications.templates.index') }}">← Back to registry</a>
    </div>

    @if($preview->notConnectedToLiveSending)
        <div class="jp-alert jp-alert--warn border small mb-3" data-testid="preview-not-connected-warning">
            <strong>Not connected to live sending.</strong>
            This template is catalogued for a future migration. The preview below uses sample data and the modern layout only — it does not change what customers or staff receive today.
        </div>
    @endif

    @foreach($preview->warnings as $warning)
        <div class="alert alert-secondary border small mb-3" data-testid="preview-warning">{{ $warning }}</div>
    @endforeach

    <div class="jp-alert jp-alert--info border small mb-4">
        Preview only — no email is sent. Sample placeholders are substituted with realistic demo values and escaped for safe display.
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="jp-card">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h3 class="jp-card__title mb-0">{{ $definition->name }}</h3>
                    @if($definition->editableNow)
                        <a class="jp-btn jp-btn--sm jp-btn--primary" href="{{ route('admin.settings.communications.templates.edit', ['event' => $definition->event, 'channel' => $definition->channel]) }}">Edit template</a>
                    @endif
                </div>
                <div class="jp-card__body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-3">Registry key</dt>
                        <dd class="col-sm-9"><code>{{ $definition->key }}</code></dd>
                        <dt class="col-sm-3">Event</dt>
                        <dd class="col-sm-9"><code>{{ $definition->event }}</code></dd>
                        <dt class="col-sm-3">Category</dt>
                        <dd class="col-sm-9">{{ \App\Support\Emails\EmailTemplateRegistry::categoryLabel($definition->category) }}</dd>
                        <dt class="col-sm-3">Audience</dt>
                        <dd class="col-sm-9">{{ \App\Support\Emails\EmailTemplateRegistry::audienceLabel($definition->audience) }}</dd>
                        <dt class="col-sm-3">Send path</dt>
                        <dd class="col-sm-9">{{ \App\Support\Emails\EmailTemplateRegistry::sendPathLabel($definition->sendPath) }}</dd>
                        <dt class="col-sm-3">Editable</dt>
                        <dd class="col-sm-9">{{ $definition->editableNow ? 'Yes — editable now' : 'No — future migration' }}</dd>
                        <dt class="col-sm-3">Template source</dt>
                        <dd class="col-sm-9"><code>{{ $definition->templateSource }}</code></dd>
                        <dt class="col-sm-3">Saved copy</dt>
                        <dd class="col-sm-9">{{ $preview->usedDbTemplate ? 'Saved' : 'Needs setup (default preview copy)' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="jp-card">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Subject preview</h3></div>
                <div class="jp-card__body">
                    <div class="fw-semibold" data-testid="preview-subject">{{ $preview->subject }}</div>
                </div>
            </div>

            <div class="jp-card">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Rendered email preview</h3></div>
                <div class="jp-card__body jp-card__body--flush">
                    <div class="border-bottom bg-light px-3 py-2 small text-secondary">
                        Modern layout · {{ $companyProfile->name }}
                    </div>
                    <div class="p-2 bg-secondary-lt" data-testid="preview-email-frame">
                        <iframe
                            title="Email preview"
                            srcdoc="{!! \App\Support\Emails\EmailTemplatePreviewRenderer::srcdocAttribute($preview->html) !!}"
                            class="w-100 border-0 bg-white rounded"
                            style="min-height: 520px; height: 70vh; max-height: 900px;"
                            sandbox="allow-same-origin"
                            data-testid="preview-email-iframe"
                        ></iframe>
                    </div>
                </div>
            </div>

            <details class="card mt-3">
                <summary class="card-header cursor-pointer">
                    <h3 class="jp-card__title mb-0 d-inline">View HTML source</h3>
                </summary>
                <div class="jp-card__body">
                    <pre class="bg-light p-3 rounded small mb-0 overflow-auto" data-testid="preview-html-source">{{ $preview->html }}</pre>
                </div>
            </details>

            @if($dbTemplate)
                <div class="card mt-3">
                    <div class="jp-card__head"><h3 class="jp-card__title mb-0">Saved template source</h3></div>
                    <div class="jp-card__body">
                        <pre class="bg-light p-3 rounded small mb-0">{{ $dbTemplate->body }}</pre>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="jp-card">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Company identity (I2)</h3></div>
                <div class="card-body small">
                    <div><strong>{{ $companyProfile->name }}</strong></div>
                    <div class="text-secondary">{{ $companyProfile->mail_from_name }} &lt;{{ $companyProfile->mail_from_email }}&gt;</div>
                    @if($companyProfile->reply_to_email)
                        <div class="mt-2">Reply-to: {{ $companyProfile->reply_to_email }}</div>
                    @endif
                    @if($companyProfile->support_email)
                        <div>Support: {{ $companyProfile->support_email }}</div>
                    @endif
                    @if($companyProfile->primary_color)
                        <div class="mt-2">Brand colors: <span class="d-inline-block rounded border" style="width:14px;height:14px;background:{{ $companyProfile->primary_color }};vertical-align:middle;"></span> {{ $companyProfile->primary_color }}</div>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Sample variables used</h3></div>
                <div class="card-body small" data-testid="preview-sample-variables">
                    @forelse($preview->sampleVariables as $key => $value)
                        <div class="mb-2">
                            <code>{{ '{' . '{' . $key . '}' . '}' }}</code>
                            <div class="text-secondary text-truncate" title="{{ $value }}">{{ $value }}</div>
                        </div>
                    @empty
                        <span class="text-secondary">None</span>
                    @endforelse
                </div>
            </div>

            <div class="card mt-3">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Documented placeholders</h3></div>
                <div class="jp-card__body">
                    @forelse($definition->variables as $var)
                        <div><code>{{ '{' . '{' . $var . '}' . '}' }}</code></div>
                    @empty
                        <span class="text-secondary">None documented</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
