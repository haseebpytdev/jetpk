@extends('themes.frontend.jetpakistan.layouts.error')

@section('title', 'Page not found — JetPakistan')

@section('content')
    @include('themes.frontend.jetpakistan.errors.partials.shell', [
        'errorHeading' => 'Page not found',
        'errorMessage' => 'The page you requested is not available. Search flights or contact JetPakistan support for help.',
    ])
@endsection
