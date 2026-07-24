@props(['messages'])

<div class="vstack gap-3" data-testid="support-ticket-thread">
    @forelse($messages as $message)
        @include('dashboard.support._message', ['message' => $message])
    @empty
        <x-dashboard.empty-state title="No messages yet" description="Replies will appear here." />
    @endforelse
</div>



