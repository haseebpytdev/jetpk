@extends('themes.frontend.jetpakistan.layouts.error')

@section('title', 'Server error — JetPakistan')

@section('content')
    @include('themes.frontend.jetpakistan.errors.partials.shell', [
        'errorHeading' => 'Something went wrong',
        'errorMessage' => 'We could not complete your request right now. Please try again or contact JetPakistan support.',
        'showRetry' => true,
    ])
@endsection
