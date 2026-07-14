@extends('layouts.developer')

@section('title', 'Security Events')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Security events</h1>
    <p class="text-secondary mb-0">Login, password, module, and Dev CP audit trail.</p>
@endsection

@section('content')
    <form method="GET" class="row g-2 mb-3">
        <div class="col-auto">
            <input type="text" name="event_type" value="{{ $filters['event_type'] ?? '' }}" class="form-control form-control-sm" placeholder="Event type">
        </div>
        <div class="col-auto">
            <select name="outcome" class="form-select form-select-sm">
                <option value="">All outcomes</option>
                <option value="success" @selected(($filters['outcome'] ?? '') === 'success')>success</option>
                <option value="failure" @selected(($filters['outcome'] ?? '') === 'failure')>failure</option>
            </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Filter</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-sm">
                <thead>
                    <tr><th>Time</th><th>Type</th><th>Outcome</th><th>IP</th><th>Metadata</th></tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ $event->created_at?->toDateTimeString() }}</td>
                            <td><code>{{ $event->event_type }}</code></td>
                            <td>{{ $event->outcome }}</td>
                            <td>{{ $event->ip_address ?? '—' }}</td>
                            <td class="small text-secondary">{{ json_encode($event->metadata ?? []) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No security events recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $events->links() }}</div>
    </div>
@endsection
