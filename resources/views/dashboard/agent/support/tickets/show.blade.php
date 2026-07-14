@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Ticket #' . $ticket->id)

@section('account_title')
    Ticket #{{ $ticket->id }}
@endsection

@section('account_subtitle')
    {{ e($ticket->subject) }}
@endsection

@section('account_actions')
    <a href="{{ route('agent.support.tickets.index') }}" class="ota-account-btn ota-account-btn--secondary">Back to list</a>
@endsection

@section('account_content')
    <div class="ota-account-grid ota-account-grid--2">
        <div class="ota-account-stack">
            <x-support.ticket-timeline :ticket="$ticket" audience="agent" variant="account" />
            <div class="ota-account-card">
            <div class="ota-account-card__body vstack gap-2">
                <div><span class="text-secondary small">Status</span><br><x-dashboard.status-badge :status="$ticket->status" /></div>
                <div><span class="text-secondary small">Category</span><br><span class="text-capitalize">{{ e($ticket->category->label()) }}</span></div>
                @if($ticket->booking)
                    <div><span class="text-secondary small">Booking</span><br>
                        <a href="{{ route('agent.bookings.show', $ticket->booking) }}">{{ e($ticket->booking->booking_reference) }}</a>
                    </div>
                @endif
            </div>
        </div>
        </div>
        <div class="vstack gap-3">
            @include('dashboard.support._thread', ['messages' => $ticket->messages])
            @can('reply', $ticket)
                <div class="ota-account-card ota-account-form-card">
                    <div class="ota-account-card__body">
                        <form method="post" action="{{ route('agent.support.tickets.reply', $ticket) }}" data-testid="customer-support-reply-form">
                            @csrf
                            <label class="form-label" for="body">Your reply</label>
                            <textarea name="body" id="body" rows="4" class="form-control @error('body') is-invalid @enderror" required maxlength="5000">{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <button type="submit" class="ota-account-btn ota-account-btn--primary mt-2">Send reply</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection