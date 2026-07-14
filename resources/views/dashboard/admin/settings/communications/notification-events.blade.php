@extends(client_layout('dashboard', 'admin'))

@section('title', 'Notification routing')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.settings.communications.index') }}">Communications</a></div>
            <h1 class="jp-page-title">Notification routing</h1>
            <p class="text-secondary mb-0">Per-event delivery: enable/disable, default audience scope, and optional explicit email overrides (comma-separated).</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('admin.settings.communications.delivery-log.index') }}" class="jp-btn jp-btn--ghost btn-sm">Delivery log</a>
            <a href="{{ route('admin.settings.communications.templates.index') }}" class="jp-btn jp-btn--outline btn-sm">Message templates</a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status') === 'notification-routing-updated')
        <div class="jp-alert jp-alert--success">Notification routing saved.</div>
    @endif

    @if ($canUpdate)
        <form method="post" action="{{ route('admin.settings.communications.notification-events.update') }}" class="jp-card">
            @csrf
            @method('PATCH')
            <div class="card-body border-bottom py-3">
                <div class="text-secondary small">
                    Scope chooses how recipients are resolved when override lists are empty (admins, staff pool, booking agent, or customer contact).
                </div>
            </div>
            <div class="table-responsive">
                <table class="jp-table table-sm">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th class="w-1">On</th>
                            <th>Scope</th>
                            <th>To override</th>
                            <th>CC</th>
                            <th>BCC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            @php
                                $row = $notificationSettings[$event->value] ?? null;
                                $toOld = old('events.'.$event->value.'.recipient_emails');
                                $toVal = $toOld !== null ? $toOld : implode(', ', $row?->recipient_emails ?? []);
                                $ccOld = old('events.'.$event->value.'.cc_emails');
                                $ccVal = $ccOld !== null ? $ccOld : implode(', ', $row?->cc_emails ?? []);
                                $bccOld = old('events.'.$event->value.'.bcc_emails');
                                $bccVal = $bccOld !== null ? $bccOld : implode(', ', $row?->bcc_emails ?? []);
                                $scopeOld = old('events.'.$event->value.'.recipient_scope');
                                $scopeVal = $scopeOld !== null ? $scopeOld : (($row?->recipient_scope) ?? $event->defaultScope());
                                $enabledOld = old('events.'.$event->value.'.enabled');
                                $enabledVal = $enabledOld !== null ? filter_var($enabledOld, FILTER_VALIDATE_BOOLEAN) : ($row->enabled ?? true);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $event->value)) }}</div>
                                    <div class="text-secondary ota-text-xs">{{ $event->value }}</div>
                                </td>
                                <td>
                                    <input type="hidden" name="events[{{ $event->value }}][enabled]" value="0">
                                    <label class="form-check form-check-single m-0">
                                        <input class="form-check-input" type="checkbox" name="events[{{ $event->value }}][enabled]" value="1" @checked($enabledVal)>
                                    </label>
                                </td>
                                <td style="min-width: 9rem;">
                                    <select class="jp-control jp-control-sm" name="events[{{ $event->value }}][recipient_scope]" required>
                                        @foreach (['admin', 'staff', 'agent', 'customer'] as $scope)
                                            <option value="{{ $scope }}" @selected($scopeVal === $scope)>{{ ucfirst($scope) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="min-width: 14rem;">
                                    <textarea class="jp-control jp-control-sm" name="events[{{ $event->value }}][recipient_emails]" rows="2" placeholder="Optional">{{ $toVal }}</textarea>
                                </td>
                                <td style="min-width: 12rem;">
                                    <textarea class="jp-control jp-control-sm" name="events[{{ $event->value }}][cc_emails]" rows="2" placeholder="Optional">{{ $ccVal }}</textarea>
                                </td>
                                <td style="min-width: 12rem;">
                                    <textarea class="jp-control jp-control-sm" name="events[{{ $event->value }}][bcc_emails]" rows="2" placeholder="Optional">{{ $bccVal }}</textarea>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button type="submit" class="jp-btn jp-btn--primary">Save notification routing</button>
            </div>
        </form>
    @else
        <div class="jp-alert jp-alert--info">You can view routing rules; only agency admins may edit them.</div>
        <div class="jp-card"><div class="card-body table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Event</th><th>On</th><th>Scope</th></tr></thead>
                <tbody>
                    @foreach ($events as $event)
                        @php $row = $notificationSettings[$event->value] ?? null; @endphp
                        <tr>
                            <td>{{ $event->value }}</td>
                            <td>{{ ($row->enabled ?? true) ? 'Yes' : 'No' }}</td>
                            <td>{{ $row->recipient_scope ?? $event->defaultScope() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
    @endif
@endsection

