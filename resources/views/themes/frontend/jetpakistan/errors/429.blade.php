@extends('themes.frontend.jetpakistan.layouts.error')

@section('title', 'Too many requests — JetPakistan')

@section('content')
    @include('themes.frontend.jetpakistan.errors.partials.shell', [
        'errorHeading' => 'Too many requests',
        'errorMessage' => 'Please wait a moment and try again.',
        'showRetry' => true,
    ])
@endsection
