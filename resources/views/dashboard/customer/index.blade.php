@extends(client_layout('dashboard', 'customer'))

@section('title', 'My trips')

@section('page-header')
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Traveller</div>
            <h1 class="page-title">{{ $brandName }} customer dashboard</h1>
            <div class="text-secondary mt-1">Manage your bookings, payment proofs, and travel documents from one trusted portal.</div>
        </div>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <p class="text-secondary mb-3">Your recent and upcoming trips will appear here once bookings are linked to your account.</p>
            <a href="{{ route('flights.search') }}" class="btn btn-primary">Search flights</a>
        </div>
    </div>
@endsection

