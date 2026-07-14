@extends(client_layout('dashboard', 'admin'))

@section('title', 'Email Template Registry')

@section('page-header')
    <h1 class="jp-page-title">Email Template Registry</h1>
@endsection

@section('content')
    <div class="text-secondary small mb-2">
        <a href="{{ route('admin.settings.communications.index') }}">Communication settings</a>
        <span class="text-muted mx-1">{{ display_sep_dot() }}</span>
        <a href="{{ route('admin.settings.communications.notification-events.index') }}">Notification routing</a>
        <span class="text-muted mx-1">{{ display_sep_dot() }}</span>
        <a href="{{ route('admin.settings.communications.delivery-log.index') }}">Delivery log</a>
    </div>

    <div class="jp-alert jp-alert--warn border small mb-4" data-testid="email-template-registry-warning">
        <div class="fw-semibold mb-1">Registry-only until migration completes</div>
        <p class="mb-1">This page catalogs every known system email from the I1 audit. Editing a row updates <code>agency_message_templates</code> for the platform company (<strong>{{ $agency->name }}</strong>) only.</p>
        <p class="mb-0">Customer Mailables, framework notifications, and marketing HTML still send their hardcoded layouts - those entries are listed as <strong>Future migration</strong> and are not connected to live body rendering yet.</p>
    </div>

  <div class="jp-card">
        <div class="jp-card__body">
            <form method="get" action="{{ route('admin.settings.communications.templates.index') }}" class="jp-form-grid jp-form-grid--filter">
                <div class="col-md-3">
                    <label class="jp-label">Search</label>
                    <input class="jp-control" name="q" value="{{ $filters['q'] }}" placeholder="Name or event key">
                </div>
                <div class="col-md-2">
                    <label class="jp-label">Category</label>
                    <select class="jp-control" name="category">
                        <option value="">All</option>
                        @foreach($categories as $category)
                            <option value="{{ $category['value'] }}" @selected($filters['category'] === $category['value'])>{{ $category['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="jp-label">Audience</label>
                    <select class="jp-control" name="audience">
                        <option value="">All</option>
                        @foreach(['customer', 'admin', 'agent', 'staff', 'finance', 'mixed'] as $audience)
                            <option value="{{ $audience }}" @selected($filters['audience'] === $audience)>{{ \App\Support\Emails\EmailTemplateRegistry::audienceLabel($audience) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="jp-label">Connection</label>
                    <select class="jp-control" name="connection">
                        <option value="">All</option>
                        <option value="editable" @selected($filters['connection'] === 'editable')>Editable now</option>
                        <option value="future" @selected($filters['connection'] === 'future')>Future migration</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="jp-label">Saved copy</label>
                    <select class="jp-control" name="db">
                        <option value="">All</option>
                        <option value="saved" @selected($filters['db'] === 'saved')>Saved</option>
                        <option value="missing" @selected($filters['db'] === 'missing')>Needs setup</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="jp-label">Status</label>
                    <select class="jp-control" name="enabled">
                        <option value="">All</option>
                        <option value="enabled" @selected($filters['enabled'] === 'enabled')>Enabled</option>
                        <option value="disabled" @selected($filters['enabled'] === 'disabled')>Disabled</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="jp-btn jp-btn--primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="jp-card">
        <div class="jp-card__head">
            <h3 class="jp-card__title mb-0">{{ count($entries) }} template{{ count($entries) === 1 ? '' : 's' }}</h3>
            <div class="jp-card__subtitle text-secondary small">
                Sender preview context: {{ $companyProfile->mail_from_name }} &lt;{{ $companyProfile->mail_from_email }}&gt;
            </div>
        </div>
        <div class="jp-card__body jp-card__body--flush">
            <div class="table-responsive">
                <table class="jp-table mb-0" data-testid="email-template-registry-table">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Category</th>
                            <th>Audience</th>
                            <th>Send path</th>
                            <th>Saved copy</th>
                            <th>Status</th>
                            <th>Subject</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($entries as $row)
                        @php($definition = $row['definition'])
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $definition->name }}</div>
                                <div class="text-secondary small"><code>{{ $definition->event }}</code></div>
                                @if($definition->riskNote)
                                    <div class="text-warning small mt-1">{{ $definition->riskNote }}</div>
                                @endif
                            </td>
                            <td>{{ \App\Support\Emails\EmailTemplateRegistry::categoryLabel($definition->category) }}</td>
                            <td>{{ \App\Support\Emails\EmailTemplateRegistry::audienceLabel($definition->audience) }}</td>
                            <td>
                                <span class="badge bg-azure-lt">{{ \App\Support\Emails\EmailTemplateRegistry::sendPathLabel($definition->sendPath) }}</span>
                            </td>
                            <td>
                                @if($row['has_db_row'])
                                    <span class="badge bg-green-lt">Saved</span>
                                @else
                                    <span class="badge bg-secondary-lt">Needs setup</span>
                                @endif
                            </td>
                            <td>
                                @if($definition->editableNow)
                                    <span class="badge bg-green">{{ $row['connection_label'] }}</span>
                                @else
                                    <span class="badge bg-yellow">{{ $row['connection_label'] }}</span>
                                @endif
                                <div class="small text-secondary mt-1">
                                    @if($row['has_db_row'])
                                        {{ $row['is_enabled'] === false ? 'Disabled' : 'Enabled' }}
                                    @elseif($definition->editableNow)
                                        Default copy available
                                    @else
                                        Preview only
                                    @endif
                                </div>
                            </td>
                            <td class="ota-r-text-safe">
                                @if($row['subject'])
                                    {{ $row['subject'] }}
                                @else
                                    <span class="text-secondary">{{ display_unknown() }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="jp-btn jp-btn--sm jp-btn--ghost" href="{{ route('admin.settings.communications.templates.preview', ['registryKey' => $definition->key]) }}">Preview</a>
                                @if($definition->editableNow)
                                    <a class="jp-btn jp-btn--sm jp-btn--outline" href="{{ route('admin.settings.communications.templates.edit', ['event' => $definition->event, 'channel' => $definition->channel]) }}">
                                        {{ $row['has_db_row'] ? 'Edit' : 'Create editable template' }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-4">No templates match your filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
