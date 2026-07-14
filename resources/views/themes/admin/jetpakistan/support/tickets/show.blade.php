@extends(client_layout('dashboard', 'admin'))

@section('title', 'Ticket #' . $ticket->id)

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-cell-sub"><a href="{{ client_route('admin.support.tickets.index') }}">Support tickets</a></p>
            <h1>Ticket #{{ $ticket->id }}</h1>
            <p>{{ e($ticket->subject) }}</p>
        </div>
        <a href="{{ client_route('admin.support.tickets.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Back</a>
    </div>
@endsection

@section('content')
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
    <div>
        <div class="jp-card">
            <x-support.ticket-timeline :ticket="$ticket" audience="internal" variant="dashboard" />
        </div>
        <div class="jp-card">
            <p><span class="jp-cell-sub">Status</span><br><x-themes.admin.jetpakistan.components.status-badge :label="$ticket->status" /></p>
            <p><span class="jp-cell-sub">From</span><br>{{ e($ticket->createdBy?->name) }} ({{ e($ticket->createdBy?->email) }})</p>
            <p><span class="jp-cell-sub">Category</span><br>{{ e($ticket->category->label()) }}</p>
            @if($ticket->booking)<p><span class="jp-cell-sub">Booking</span><br>{{ e($ticket->booking->booking_reference) }}</p>@endif
            @if($ticket->assignedTo)<p><span class="jp-cell-sub">Assigned to</span><br>{{ e($ticket->assignedTo->name) }}</p>@endif

            <form method="post" action="{{ client_route('admin.support.tickets.assign', $ticket) }}" data-testid="admin-support-assign-form" style="margin-top: 12px;">
                @csrf @method('patch')
                <label class="jp-label" for="assigned_to_user_id">Assign to</label>
                <select name="assigned_to_user_id" id="assigned_to_user_id" class="jp-select" style="margin-bottom: 8px;">
                    <option value="">— Unassigned —</option>
                    @foreach($assignees as $u)<option value="{{ $u->id }}" @selected($ticket->assigned_to_user_id === $u->id)>{{ e($u->name) }}</option>@endforeach
                </select>
                <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Save assignment</button>
            </form>

            <form method="post" action="{{ client_route('admin.support.tickets.forward', $ticket) }}" data-testid="admin-support-forward-form" style="margin-top: 12px;">
                @csrf @method('patch')
                <label class="jp-label" for="forwarded_to_agent_id">Forward to agent</label>
                <select name="forwarded_to_agent_id" id="forwarded_to_agent_id" class="jp-select" style="margin-bottom: 8px;">
                    <option value="">— Not forwarded —</option>
                    @foreach($agents as $agentOption)
                        <option value="{{ $agentOption->id }}" @selected($ticket->forwarded_to_agent_id === $agentOption->id)>
                            {{ e($agentOption->code) }} — {{ e($agentOption->user?->name ?? 'Agent') }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Save forward</button>
            </form>

            <form method="post" action="{{ client_route('admin.support.tickets.status', $ticket) }}" data-testid="admin-support-status-form" style="margin-top: 12px;">
                @csrf @method('patch')
                <label class="jp-label" for="status">Change status</label>
                <select name="status" id="status" class="jp-select" style="margin-bottom: 8px;">
                    @foreach($statuses as $st)<option value="{{ $st->value }}" @selected($ticket->status === $st)>{{ ucfirst($st->value) }}</option>@endforeach
                </select>
                <button type="submit" class="jp-btn jp-btn--sm">Update status</button>
            </form>
        </div>
    </div>

    <div>
        @include('dashboard.support._thread', ['messages' => $ticket->messages])
        <div class="jp-card">
            <form method="post" action="{{ client_route('admin.support.tickets.reply', $ticket) }}" data-testid="admin-support-reply-form">
                @csrf
                <label class="jp-label" for="body">Reply</label>
                <textarea name="body" id="body" rows="4" class="jp-input" required maxlength="5000" style="width: 100%; min-height: 100px;">{{ old('body') }}</textarea>
                <label style="display: flex; align-items: center; gap: 8px; margin: 12px 0;">
                    <input type="checkbox" name="visibility" value="internal" id="visibility_internal" @checked(old('visibility') === 'internal')>
                    <span>Internal note (not visible to customer/agent)</span>
                </label>
                <button type="submit" class="jp-btn jp-btn--sm">Send</button>
            </form>
        </div>
    </div>
</div>
@endsection
