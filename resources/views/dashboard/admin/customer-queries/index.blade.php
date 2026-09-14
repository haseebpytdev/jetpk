@extends(client_layout('dashboard', 'admin'))
@section('title', 'Customer Queries')
@section('page-header')
    <x-dashboard.section-header title="Customer Queries" subtitle="Ask JetPakistan commercial inquiries and callback leads." />
@endsection
@section('content')
    <form method="get" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label class="jp-label small" for="q">Search</label>
                <input id="q" name="q" class="jp-control jp-control-sm" value="{{ request('q') }}" placeholder="Name, email, route, ref">
            </div>
            <div class="col-md-2">
                <label class="jp-label small" for="status">Status</label>
                <select id="status" name="status" class="jp-control jp-control-sm">
                    <option value="">All</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="jp-label small" for="assigned_to">Assigned</label>
                <select id="assigned_to" name="assigned_to" class="jp-control jp-control-sm">
                    <option value="">All</option>
                    <option value="unassigned" @selected(request('assigned_to') === 'unassigned')>Unassigned</option>
                    @foreach($assignees as $assignee)
                        <option value="{{ $assignee->id }}" @selected((string) request('assigned_to') === (string) $assignee->id)>{{ e($assignee->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="jp-btn jp-btn--outline btn-sm">Filter</button>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm ota-admin-table">
        <div class="table-responsive ota-r-table-wrap">
            <table class="table card-table table-vcenter mb-0" data-testid="admin-customer-queries-table">
                <thead class="table-light"><tr>
                    <th>Reference</th><th>Customer</th><th>Contact</th><th>Source</th><th>Travel request</th><th>Status</th><th>Priority</th><th>Last activity</th><th class="text-end">Action</th>
                </tr></thead>
                <tbody>
                @forelse($queries as $item)
                    <tr>
                        <td class="fw-semibold">{{ e($item->query_reference) }}</td>
                        <td>{{ e($item->name) }}</td>
                        <td class="small">
                            <div>{{ e($item->email) }}</div>
                            <div class="text-secondary">{{ e($item->phone_e164 ?? $item->phone_raw) }}</div>
                        </td>
                        <td>{{ e($item->source) }}</td>
                        <td class="small">
                            @if($item->origin && $item->destination)
                                {{ e($item->origin) }} → {{ e($item->destination) }}
                            @else
                                {{ e(\Illuminate\Support\Str::limit($item->ai_summary ?? '—', 60)) }}
                            @endif
                        </td>
                        <td>{{ e($item->status->label()) }}</td>
                        <td>{{ $item->callback_required ? 'Callback' : e($item->priority ?? '—') }}</td>
                        <td class="small text-secondary text-nowrap">{{ $item->last_activity_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.customer-queries.show', $item) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-dashboard.empty-state title="No customer queries" description="Ask JetPakistan leads will appear here." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($queries->hasPages())
            <div class="card-footer">{{ $queries->links() }}</div>
        @endif
    </div>
@endsection
