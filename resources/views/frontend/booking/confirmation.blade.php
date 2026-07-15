@extends(client_layout('frontend', 'frontend'))

@section('title', 'Booking request received')

@section('content')
    @include('frontend.booking.partials.confirmation-body')
@endsection
