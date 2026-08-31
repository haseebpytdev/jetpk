@extends(client_layout('dashboard', 'staff'))
@section('title', 'AI conversation')
@section('page-header')
    <x-dashboard.section-header title="AI conversation" subtitle="{{ $conversation->public_id }}">
        <x-slot:actions>
            <a href="{{ route('staff.support.ai-queue.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </x-slot:actions>
    </x-dashboard.section-header>
@endsection
@section('content')
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <div class="d-flex flex-wrap gap-2 mb-3">
        <form method="post" action="{{ route('staff.support.ai-queue.takeover', $conversation->public_id) }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary">Take over</button>
        </form>
        <form method="post" action="{{ route('staff.support.ai-queue.return-to-ai', $conversation->public_id) }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">Return to AI</button>
        </form>
        <span class="badge bg-secondary align-self-center">{{ e($conversation->state) }}</span>
    </div>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body" data-testid="staff-ai-transcript" style="max-height: 28rem; overflow-y: auto;">
            @forelse($messages as $message)
                <div class="mb-3">
                    <div class="small text-secondary">{{ e($message->role) }} · {{ $message->created_at?->format('Y-m-d H:i') }}</div>
                    <div class="ota-r-text-safe">{{ e($message->body) }}</div>
                </div>
            @empty
                <p class="text-secondary mb-0">No messages yet.</p>
            @endforelse
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('staff.support.ai-queue.reply', $conversation->public_id) }}" data-testid="staff-ai-reply-form">
                @csrf
                <label class="form-label" for="ai-reply-body">Reply</label>
                <textarea id="ai-reply-body" name="body" class="form-control" rows="3" required maxlength="4000"></textarea>
                <button type="submit" class="btn btn-primary mt-2">Send reply</button>
            </form>
        </div>
    </div>
@endsection
