@extends(client_layout('dashboard', 'admin'))
@section('title', 'Ticket #' . $ticket->id)
@section('page-header')
    <x-dashboard.section-header :title="'Ticket #' . $ticket->id" :subtitle="e($ticket->subject)">
        <x-slot:actions><a href="{{ route('admin.support.tickets.index') }}" class="jp-btn jp-btn--ghost btn-sm">Back</a></x-slot:actions>
    </x-dashboard.section-header>
@endsection
@section('content')
    <div class="row g-3">
        <div class="col-lg-4 vstack gap-3">
            <x-support.ticket-timeline :ticket="$ticket" audience="internal" variant="dashboard" />
            <div class="card border-0 shadow-sm"><div class="card-body vstack gap-2">
            <div><span class="text-secondary small">Status</span><br><x-dashboard.status-badge :status="$ticket->status" /></div>
            <div><span class="text-secondary small">From</span><br>{{ e($ticket->createdBy?->name) }} ({{ e($ticket->createdBy?->email) }})</div>
            <div><span class="text-secondary small">Category</span><br>{{ e($ticket->category->label()) }}</div>
            @if($ticket->booking)<div><span class="text-secondary small">Booking</span><br>{{ e($ticket->booking->booking_reference) }}</div>@endif
            @if($ticket->assignedTo)<div><span class="text-secondary small">Assigned to</span><br>{{ e($ticket->assignedTo->name) }}</div>@endif
            @if($ticket->forwardedToAgent)<div><span class="text-secondary small">Forwarded to</span><br>{{ e($ticket->forwardedToAgent->user?->name ?? $ticket->forwardedToAgent->code) }}@if($ticket->forwarded_at)<span class="text-secondary small"> · {{ $ticket->forwarded_at->diffForHumans() }}</span>@endif</div>@endif
            <form method="post" action="{{ route('admin.support.tickets.assign', $ticket) }}" class="vstack gap-2 mb-2" data-testid="admin-support-assign-form">
                @csrf @method('patch')
                <label class="jp-label small mb-0" for="assigned_to_user_id">Assign to</label>
                <select name="assigned_to_user_id" id="assigned_to_user_id" class="jp-control jp-control-sm">
                    <option value="">— Unassigned —</option>
                    @foreach($assignees as $u)<option value="{{ $u->id }}" @selected($ticket->assigned_to_user_id === $u->id)>{{ e($u->name) }}</option>@endforeach
                </select>
                <button type="submit" class="jp-btn jp-btn--ghost btn-sm">Save assignment</button>
            </form>
            <form method="post" action="{{ route('admin.support.tickets.forward', $ticket) }}" class="vstack gap-2 mb-2" data-testid="admin-support-forward-form">
                @csrf @method('patch')
                <label class="jp-label small mb-0" for="forwarded_to_agent_id">Forward to agent</label>
                <select name="forwarded_to_agent_id" id="forwarded_to_agent_id" class="jp-control jp-control-sm">
                    <option value="">— Not forwarded —</option>
                    @foreach($agents as $agentOption)
                        <option value="{{ $agentOption->id }}" @selected($ticket->forwarded_to_agent_id === $agentOption->id)>
                            {{ e($agentOption->code) }} — {{ e($agentOption->user?->name ?? 'Agent') }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="jp-btn jp-btn--ghost btn-sm">Save forward</button>
            </form>
            <form method="post" action="{{ route('admin.support.tickets.status', $ticket) }}" class="vstack gap-2" data-testid="admin-support-status-form">
                @csrf @method('patch')
                <label class="jp-label small mb-0" for="status">Change status</label>
                <select name="status" id="status" class="jp-control jp-control-sm">
                    @foreach($statuses as $st)<option value="{{ $st->value }}" @selected($ticket->status === $st)>{{ ucfirst($st->value) }}</option>@endforeach
                </select>
                <button type="submit" class="jp-btn jp-btn--outline btn-sm">Update status</button>
            </form>
        </div></div>
        </div>
        <div class="col-lg-8 vstack gap-3">
            @include('dashboard.support._thread', ['messages' => $ticket->messages])
            <div class="card border-0 shadow-sm"><div class="jp-card__body">
                <form method="post" action="{{ route('admin.support.tickets.reply', $ticket) }}" data-testid="admin-support-reply-form">
                    @csrf
                    <label class="jp-label" for="body">Reply</label>
                    <textarea name="body" id="body" rows="4" class="jp-control" required maxlength="5000">{{ old('body') }}</textarea>
                    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="visibility" value="internal" id="visibility_internal" @checked(old('visibility') === 'internal')><label class="form-check-label" for="visibility_internal">Internal note (not visible to customer/agent)</label></div>
                    <button type="submit" class="jp-btn jp-btn--primary mt-2">Send</button>
                </form>
            </div></div>
        </div>
    </div>
@endsection