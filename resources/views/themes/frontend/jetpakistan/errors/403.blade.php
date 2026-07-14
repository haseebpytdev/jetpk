@extends('themes.frontend.jetpakistan.layouts.error')

@section('title', 'Access restricted — JetPakistan')

@section('content')
    @include('themes.frontend.jetpakistan.errors.partials.shell', [
        'errorHeading' => 'Access restricted',
        'errorMessage' => $message ?? 'You do not have permission to access this area.',
    ])
@endsection
