@extends(client_layout('dashboard', 'admin'))

@section('title', 'Email Event Content')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-backlink"><a href="{{ client_route('admin.settings.communications.index') }}">← Communications</a></p>
            <h1>Email Event Content</h1>
            <p>One universal JetPK shell · grouped event-content editors for {{ $agency->name }}.</p>
        </div>
        <div class="jp-toolbar">
            <a href="{{ client_route('admin.settings.communications.notification-events.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Notification routing</a>
            <a href="{{ client_route('admin.settings.communications.delivery-log.index') }}" class="jp-btn jp-btn--sm jp-btn--outline">Delivery log</a>
        </div>
    </div>
@endsection

@section('content')
    @include('themes.admin.jetpakistan.partials.flash')

    <div class="jp-alert jp-alert--info jp-stack-sm" data-testid="email-event-content-context">
        <p class="jp-help" style="margin:0;">Sender: <strong>{{ $companyProfile->mail_from_name }}</strong> &lt;{{ $companyProfile->mail_from_email }}&gt;</p>
        <p class="jp-help" style="margin:0;">All emails use the canonical JetPK shell. Events supply subject, preheader, heading, body, status, CTA, and detail schema only. Full HTML override is disabled by default.</p>
    </div>

    @php
        $activeCategory = $filters['category'] ?: 'all';
        $visibleGroups = $eventContentGroups;
        if ($activeCategory !== '' && $activeCategory !== 'all') {
            $visibleGroups = array_filter($eventContentGroups, fn ($items, $key) => $key === $activeCategory, ARRAY_FILTER_USE_BOTH);
        }
    @endphp

    <div class="jp-card">
        <div class="jp-card__body">
            <form method="get" action="{{ client_route('admin.settings.communications.templates.index') }}" class="jp-form-grid jp-form-grid--filter" data-jp-email-event-content-filters>
                <div class="jp-field">
                    <label class="jp-label">Search</label>
                    <input aria-label="Search" class="jp-control" name="q" value="{{ $filters['q'] }}" placeholder="Name or event key">
                </div>
                <div class="jp-field">
                    <label class="jp-label">Audience</label>
                    <select aria-label="Audience" class="jp-control" name="audience">
                        <option value="">All audiences</option>
                        @foreach(['customer', 'admin', 'agent', 'staff', 'finance', 'mixed'] as $audience)
                            <option value="{{ $audience }}" @selected($filters['audience'] === $audience)>{{ \App\Support\Emails\EmailTemplateRegistry::audienceLabel($audience) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="jp-field">
                    <label class="jp-label">State</label>
                    <select aria-label="State" class="jp-control" name="enabled">
                        <option value="">All states</option>
                        <option value="enabled" @selected($filters['enabled'] === 'enabled')>Enabled</option>
                        <option value="disabled" @selected($filters['enabled'] === 'disabled')>Disabled</option>
                    </select>
                </div>
                <div class="jp-field">
                    <label class="jp-label">Saved copy</label>
                    <select aria-label="Saved copy" class="jp-control" name="db">
                        <option value="">All</option>
                        <option value="saved" @selected($filters['db'] === 'saved')>Customized</option>
                        <option value="missing" @selected($filters['db'] === 'missing')>Universal default</option>
                    </select>
                </div>
                <input type="hidden" name="category" value="{{ $filters['category'] }}" data-jp-email-category-input>
                <div class="jp-field jp-field--actions">
                    <button type="submit" class="jp-btn jp-btn--primary">Apply filters</button>
                </div>
            </form>
        </div>
    </div>

    <nav class="jp-page-editor__nav" aria-label="Event content categories" data-jp-email-category-tabs>
        <a href="{{ request()->fullUrlWithQuery(['category' => '']) }}" class="jp-queue-tab {{ $activeCategory === 'all' || $activeCategory === '' ? 'is-active' : '' }}">All ({{ $eventContentTotal }})</a>
        @foreach($categories as $category)
            <a href="{{ request()->fullUrlWithQuery(['category' => $category['value']]) }}" class="jp-queue-tab {{ $activeCategory === $category['value'] ? 'is-active' : '' }}">
                {{ $category['label'] }} ({{ $categoryCounts[$category['value']] ?? 0 }})
            </a>
        @endforeach
    </nav>

    <div class="jp-email-event-content" data-testid="email-event-content-list">
        @forelse($visibleGroups as $categoryKey => $rows)
            <section class="jp-card jp-stack-sm" data-jp-event-content-group="{{ $categoryKey }}">
                <div class="jp-card__head">
                    <h2 class="jp-card__title">{{ \App\Support\Emails\EmailTemplateRegistry::categoryLabel($categoryKey) }}</h2>
                </div>
                <div class="jp-card__body jp-stack-sm">
                    @foreach($rows as $row)
                        @php
                            $content = $row['content'];
                            $registry = $row['registry'];
                        @endphp
                        <details class="jp-card jp-email-registry__row" data-jp-email-row data-category="{{ $content->category }}" data-audience="{{ $content->audience }}">
                            <summary class="jp-email-registry__summary">
                                <div class="jp-email-registry__main">
                                    <strong>{{ $content->name }}</strong>
                                    <code class="jp-muted">{{ $content->eventKey }}</code>
                                </div>
                                <div class="jp-email-registry__meta">
                                    <span class="jp-badge jp-badge--muted">{{ \App\Support\Emails\EmailTemplateRegistry::audienceLabel($content->audience) }}</span>
                                    @if($row['has_override'])
                                        <span class="jp-badge jp-badge--success">Customized</span>
                                    @else
                                        <span class="jp-badge jp-badge--muted">Default</span>
                                    @endif
                                    @if($row['is_enabled'] === false)
                                        <span class="jp-badge jp-badge--warn">Disabled</span>
                                    @endif
                                </div>
                            </summary>
                            <div class="jp-card__body jp-email-registry__body">
                                <dl class="jp-dl-compact">
                                    <dt>Subject</dt><dd class="ota-r-text-safe">{{ $row['subject'] }}</dd>
                                    <dt>Preheader</dt><dd class="ota-r-text-safe">{{ $row['preheader'] }}</dd>
                                    <dt>Heading</dt><dd class="ota-r-text-safe">{{ $row['heading'] }}</dd>
                                    <dt>Status</dt><dd>{{ $row['status_label'] }} <span class="jp-muted">({{ $row['status_type'] }})</span></dd>
                                    <dt>CTA</dt><dd>{{ $row['cta_label'] ?: display_unknown() }} @if($content->ctaUrlKey)<code class="jp-muted">{{ $content->ctaUrlKey }}</code>@endif</dd>
                                    <dt>Detail fields</dt><dd><code class="jp-muted">{{ implode(', ', $content->detailFields) ?: '—' }}</code></dd>
                                    <dt>Content blocks</dt><dd><code class="jp-muted">{{ implode(', ', $content->contentBlocks) }}</code></dd>
                                </dl>
                                <div class="jp-toolbar">
                                    @if($registry)
                                        <a class="jp-btn jp-btn--sm jp-btn--ghost" href="{{ client_route('admin.settings.communications.templates.preview', ['registryKey' => $registry->key]) }}">Preview</a>
                                    @endif
                                    <a class="jp-btn jp-btn--sm jp-btn--primary" href="{{ client_route('admin.settings.communications.templates.edit', ['event' => $content->eventKey, 'channel' => 'email']) }}">
                                        {{ $row['has_override'] ? 'Edit content' : 'Customize content' }}
                                    </a>
                                    @if($row['has_override'])
                                        <form method="post" action="{{ client_route('admin.settings.communications.templates.reset', ['event' => $content->eventKey, 'channel' => 'email']) }}" class="jp-inline-form" onsubmit="return confirm('Reset to universal default?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Reset to default</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="jp-empty-state">
                <p>No event content matches your filters.</p>
            </div>
        @endforelse
    </div>
@endsection
