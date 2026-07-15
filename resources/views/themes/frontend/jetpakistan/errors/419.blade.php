@extends('themes.frontend.jetpakistan.layouts.error')

@section('title', 'Session expired — JetPakistan')

@section('content')
    @include('themes.frontend.jetpakistan.errors.partials.shell', [
        'errorHeading' => 'Session expired',
        'errorMessage' => 'Your session has expired. Please refresh the page and try again.',
        'showRetry' => true,
    ])
@endsection
