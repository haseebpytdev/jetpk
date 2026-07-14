@extends(client_layout('dashboard', 'admin'))

@section('title', 'Email delivery log')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.settings.communications.index') }}">Communications</a></div>
            <h1 class="jp-page-title">Email delivery log</h1>
            <p class="text-secondary mb-0">Failed and skipped messages can be retried after fixing SMTP or routing.</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('admin.settings.communications.notification-events.index') }}" class="jp-btn jp-btn--outline btn-sm">Notification routing</a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status') === 'communication-resend-queued')
        <div class="jp-alert jp-alert--success">Resend attempted; check the newest row for status.</div>
    @endif
    @if ($errors->has('resend'))
        <div class="jp-alert jp-alert--danger">{{ $errors->first('resend') }}</div>
    @endif

    <div class="btn-group mb-3" role="group">
        <a href="{{ route('admin.settings.communications.delivery-log.index', ['status' => 'issues']) }}" class="btn btn-sm {{ $filter === 'issues' ? 'btn-primary' : 'btn-outline-primary' }}">Needs attention</a>
        <a href="{{ route('admin.settings.communications.delivery-log.index', ['status' => 'failed']) }}" class="btn btn-sm {{ $filter === 'failed' ? 'btn-primary' : 'btn-outline-primary' }}">Failed</a>
        <a href="{{ route('admin.settings.communications.delivery-log.index', ['status' => 'skipped']) }}" class="btn btn-sm {{ $filter === 'skipped' ? 'btn-primary' : 'btn-outline-primary' }}">Skipped</a>
        <a href="{{ route('admin.settings.communications.delivery-log.index', ['status' => 'sent']) }}" class="btn btn-sm {{ $filter === 'sent' ? 'btn-primary' : 'btn-outline-primary' }}">Sent</a>
        <a href="{{ route('admin.settings.communications.delivery-log.index', ['status' => 'all']) }}" class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
    </div>

    <div class="jp-card">
        <div class="table-responsive">
            <table class="jp-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Status</th>
                        <th>Event</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Error</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-secondary small"><x-time.local :value="$log->created_at" context="operator" /></td>
                            <td><span class="badge bg-{{ $log->status === 'sent' ? 'success' : ($log->status === 'failed' ? 'danger' : 'secondary') }}">{{ $log->status }}</span></td>
                            <td><code class="small">{{ $log->event }}</code></td>
                            <td class="small">{{ $log->recipient_email ? \Illuminate\Support\Str::limit($log->recipient_email, 48) : display_unknown() }}</td>
                            <td class="small">{{ $log->subject ? \Illuminate\Support\Str::limit($log->subject, 40) : display_unknown() }}</td>
                            <td class="small text-danger">{{ \Illuminate\Support\Str::limit($log->error_message ?? '', 120) }}</td>
                            <td>
                                @can('resend', $log)
                                    <form method="post" action="{{ route('admin.settings.communications.delivery-log.resend', $log) }}" class="d-inline" onsubmit="return confirm('Resend this message to the original recipients?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Resend</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary">No log rows for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $logs->links() }}</div>
    </div>
@endsection

