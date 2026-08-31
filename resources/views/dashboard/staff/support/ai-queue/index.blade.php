@extends(client_layout('dashboard', 'staff'))
@section('title', 'AI support queue')
@section('page-header')
    <x-dashboard.section-header title="AI support queue" subtitle="Visitor chats waiting for a human agent." />
@endsection
@section('content')
    <div class="card border-0 shadow-sm ota-admin-table">
        <div class="table-responsive ota-r-table-wrap">
            <table class="table card-table table-vcenter mb-0" data-testid="staff-ai-queue-table">
                <thead class="table-light">
                    <tr>
                        <th>Conversation</th>
                        <th>State</th>
                        <th>Last message</th>
                        <th class="text-end w-1">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($conversations as $conversation)
                    <tr>
                        <td class="font-monospace small">{{ e(\Illuminate\Support\Str::limit($conversation->public_id, 13, '…')) }}</td>
                        <td>{{ e($conversation->state) }}</td>
                        <td class="small text-secondary">{{ $conversation->last_message_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('staff.support.ai-queue.show', $conversation->public_id) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-dashboard.empty-state title="No AI handoffs" description="Waiting and active AI chats will appear here." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($conversations->hasPages())
            <div class="card-footer">{{ $conversations->links() }}</div>
        @endif
    </div>
@endsection
