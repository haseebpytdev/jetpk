@extends('layouts.frontend')

@section('title', $title)

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5 text-center">
                        <h1 class="h3 mb-3">{{ $title }}</h1>
                        <p class="text-secondary mb-4">{{ $message }}</p>

                        @if (! empty($bookingReference))
                            <p class="mb-1"><strong>Booking reference:</strong> {{ $bookingReference }}</p>
                        @endif
                        @if (! empty($paymentReference))
                            <p class="mb-1"><strong>Payment reference:</strong> {{ $paymentReference }}</p>
                        @endif
                        @if (! empty($gatewayOrderId))
                            <p class="mb-1"><strong>Gateway order:</strong> {{ $gatewayOrderId }}</p>
                        @endif
                        @if (! empty($paymentStatus))
                            <p class="mb-1"><strong>Status:</strong> {{ str_replace('_', ' ', ucfirst($paymentStatus)) }}</p>
                        @endif
                        @if (! empty($paidAt))
                            <p class="mb-3"><strong>Paid at:</strong> {{ $paidAt }}</p>
                        @endif

                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <a href="{{ client_route('booking.lookup') }}" class="btn btn-primary">Lookup booking</a>
                            <a href="{{ client_route('home') }}" class="btn btn-outline-secondary">Back to home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
