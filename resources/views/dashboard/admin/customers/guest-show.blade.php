@extends(client_layout('dashboard', 'admin'))

@section('title', 'Guest customer')

@section('page-header')
    <x-dashboard.section-header
        title="{{ $guestIdentifier }}"
        subtitle="Read-only guest booker profile aggregated from checkout bookings."
    >
        <x-slot:actions>
            <a href="{{ route('admin.customers.index', ['segment' => 'guests']) }}" class="jp-btn jp-btn--ghost btn-sm">Back to guest customers</a>
        </x-slot:actions>
    </x-dashboard.section-header>
@endsection

@section('content')
    <div class="card border-0 shadow-sm mb-3">
        <div class="jp-card__body">
            <div class="row g-3">
                <div class="col-md-4"><strong>Guest ID</strong><div>{{ $guestIdentifier }}</div></div>
                <div class="col-md-4"><strong>Email</strong><div>{{ $guest['email'] !== '' ? $guest['email'] : '—' }}</div></div>
                <div class="col-md-4"><strong>Phone</strong><div>{{ $guest['phone'] !== '' ? $guest['phone'] : '—' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header border-0 pb-0">
            <h3 class="jp-card__title mb-0">Guest bookings</h3>
        </div>
        <div class="table-responsive ota-r-table-wrap">
            <table class="jp-table mb-0">
                <thead><tr><th>Reference</th><th>Route</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr>
                            <td><a href="{{ route('admin.bookings.show', $booking['id']) }}">{{ $booking['booking_reference'] }}</a></td>
                            <td>{{ $booking['route'] }}</td>
                            <td>{{ $booking['status'] }}</td>
                            <td>{{ $booking['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-secondary">No bookings matched this guest contact.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
