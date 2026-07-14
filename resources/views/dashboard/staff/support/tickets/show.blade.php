@extends(client_layout('dashboard', 'staff'))
@section('title', 'Ticket #' . $ticket->id)
@section('page-header')
    <x-dashboard.section-header :title="'Ticket #' . $ticket->id" :subtitle="e($ticket->subject)">
        <x-slot:actions><a href="{{ route('staff.support.tickets.index') }}" class="btn btn-outline-secondary btn-sm">Back</a></x-slot:actions>
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
            @if($ticket->forwardedToAgent)<div><span class="text-secondary small">Forwarded to agent</span><br>{{ e($ticket->forwardedToAgent->code ?? '') }}</div>@endif
            <form method="post" action="{{ route('staff.support.tickets.status', $ticket) }}" class="vstack gap-2" data-testid="staff-support-status-form">
                @csrf @method('patch')
                <label class="form-label small mb-0" for="status">Change status</label>
                <select name="status" id="status" class="form-select form-select-sm">
                    @foreach($statuses as $st)<option value="{{ $st->value }}" @selected($ticket->status === $st)>{{ ucfirst($st->value) }}</option>@endforeach
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">Update status</button>
            </form>
        </div></div>
        </div>
        <div class="col-lg-8 vstack gap-3">
            @include('dashboard.support._thread', ['messages' => $ticket->messages])
            <div class="card border-0 shadow-sm"><div class="card-body">
                <form method="post" action="{{ route('staff.support.tickets.reply', $ticket) }}" data-testid="staff-support-reply-form">
                    @csrf
                    <label class="form-label" for="body">Reply</label>
                    <textarea name="body" id="body" rows="4" class="form-control" required maxlength="5000">{{ old('body') }}</textarea>
                    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="visibility" value="internal" id="visibility_internal" @checked(old('visibility') === 'internal')><label class="form-check-label" for="visibility_internal">Internal note (not visible to customer/agent)</label></div>
                    <button type="submit" class="btn btn-primary mt-2">Send</button>
                </form>
            </div></div>
        </div>
    </div>
@endsection