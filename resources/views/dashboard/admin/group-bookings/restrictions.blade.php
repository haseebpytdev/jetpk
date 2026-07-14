@extends(client_layout('dashboard', 'admin'))

@section('title', 'Restricted group booking users')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.group-bookings.index') }}">Group bookings</a></div>
            <h1 class="jp-page-title">Restricted group booking users</h1>
        </div>
    </div>
@endsection

@section('content')
    @if (session('success'))
        <div class="jp-alert jp-alert--success">{{ session('success') }}</div>
    @endif

    <div class="jp-card">
        <div class="table-responsive">
            <table class="jp-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Unpaid releases</th>
                        <th>Blocked at</th>
                        <th>Last release</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($restrictions as $restriction)
                        <tr>
                            <td>
                                {{ $restriction->user?->name }}<br>
                                <span class="text-secondary">{{ $restriction->user?->email }}</span>
                            </td>
                            <td>{{ $restriction->unpaid_release_count }}</td>
                            <td>{{ $restriction->blocked_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>{{ $restriction->last_release_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.group-bookings.restrictions.reset', $restriction->user_id) }}">
                                    @csrf
                                    <input type="text" name="reset_note" class="jp-control jp-control-sm mb-1" placeholder="Reset note (optional)">
                                    <button type="submit" class="jp-btn jp-btn--sm jp-btn--primary">Reset limit</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No blocked users.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($restrictions->hasPages())
            <div class="card-footer">{{ $restrictions->links() }}</div>
        @endif
    </div>
@endsection
