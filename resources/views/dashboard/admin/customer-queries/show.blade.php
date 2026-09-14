@extends(client_layout('dashboard', 'admin'))
@section('title', $query->query_reference)
@section('page-header')
    <x-dashboard.section-header :title="$query->query_reference" :subtitle="e($query->name)">
        <x-slot:actions><a href="{{ route('admin.customer-queries.index') }}" class="jp-btn jp-btn--ghost btn-sm">Back</a></x-slot:actions>
    </x-dashboard.section-header>
@endsection
@section('content')
    <div class="row g-3">
        <div class="col-lg-4 vstack gap-3">
            <div class="card border-0 shadow-sm"><div class="card-body vstack gap-2">
                <div><span class="text-secondary small">Customer</span><br>{{ e($query->name) }}</div>
                <div><span class="text-secondary small">Email</span><br>{{ e($query->email) }} @if(!$query->email_verified)<span class="text-secondary small">(format only)</span>@endif</div>
                <div><span class="text-secondary small">Phone</span><br>{{ e($query->phone_e164 ?? $query->phone_raw) }} @if(!$query->phone_verified)<span class="text-secondary small">(format only)</span>@endif</div>
                <div><span class="text-secondary small">Consent</span><br>{{ $query->contact_consent ? 'Yes' : 'No' }} @if($query->consent_timestamp)<span class="text-secondary small">· {{ $query->consent_timestamp->diffForHumans() }}</span>@endif</div>
                <div><span class="text-secondary small">Source</span><br>{{ e($query->source) }}</div>
            </div></div>

            <div class="card border-0 shadow-sm"><div class="card-body vstack gap-2">
                <div><span class="text-secondary small">Route</span><br>{{ e($query->origin ?? '—') }} → {{ e($query->destination ?? '—') }}</div>
                <div><span class="text-secondary small">Dates</span><br>{{ $query->departure_date?->format('j M Y') ?? '—' }} @if($query->return_date) / {{ $query->return_date->format('j M Y') }}@endif</div>
                <div><span class="text-secondary small">Passengers</span><br>{{ $query->adult_count ?? '—' }} adults @if($query->child_count), {{ $query->child_count }} child @endif</div>
                <div><span class="text-secondary small">AI summary</span><br>{{ e($query->ai_summary ?? '—') }}</div>
            </div></div>

            <form method="post" action="{{ route('admin.customer-queries.status', $query) }}" class="card border-0 shadow-sm"><div class="card-body vstack gap-2">
                @csrf @method('patch')
                <label class="jp-label small" for="status">Status</label>
                <select name="status" id="status" class="jp-control jp-control-sm">
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected($query->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <label class="jp-label small" for="assigned_to_user_id">Assign to</label>
                <select name="assigned_to_user_id" id="assigned_to_user_id" class="jp-control jp-control-sm">
                    <option value="">— Unassigned —</option>
                    @foreach($assignees as $assignee)
                        <option value="{{ $assignee->id }}" @selected($query->assigned_to_user_id === $assignee->id)>{{ e($assignee->name) }}</option>
                    @endforeach
                </select>
                <label class="jp-label small" for="internal_notes">Internal notes</label>
                <textarea name="internal_notes" id="internal_notes" rows="4" class="jp-control">{{ old('internal_notes', $query->internal_notes) }}</textarea>
                <button type="submit" class="jp-btn jp-btn--primary btn-sm">Save</button>
            </div></form>
        </div>

        <div class="col-lg-8 vstack gap-3">
            @if($query->conversation)
                <div class="card border-0 shadow-sm"><div class="card-body">
                    <h3 class="h6 mb-3">Conversation transcript</h3>
                    <div class="vstack gap-2">
                        @foreach($query->conversation->messages as $message)
                            <div class="border rounded p-2">
                                <div class="small text-secondary text-capitalize">{{ e($message->role) }} · {{ $message->created_at?->diffForHumans() }}</div>
                                <div>{{ e($message->body) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div></div>
            @endif
        </div>
    </div>
@endsection
