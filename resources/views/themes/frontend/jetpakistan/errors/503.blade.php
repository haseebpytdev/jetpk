@extends('themes.frontend.jetpakistan.layouts.error')

@section('title', 'Service unavailable — JetPakistan')

@section('content')
    @include('themes.frontend.jetpakistan.errors.partials.shell', [
        'errorHeading' => 'Service temporarily unavailable',
        'errorMessage' => 'JetPakistan is temporarily unavailable. Please try again shortly.',
        'showRetry' => true,
    ])
@endsection
