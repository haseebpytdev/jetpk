@extends('errors.layout')

@section('title', 'Module unavailable')
@section('heading', 'Module unavailable')
@section('message')
    {{ $message ?? 'This module is disabled for this deployment.' }}
@endsection

@push('after-message')
    @if (! empty($moduleLabel))
        <p class="support">
            {{ $moduleLabel }}
        </p>
    @endif
@endpush

@section('actions')
    <a href="{{ route('home') }}" class="btn btn-primary">Back to Home</a>
    @auth
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Go to Dashboard</a>
    @else
        <a href="{{ route('login') }}" class="btn btn-secondary">Sign In</a>
    @endauth
    @if (! empty($showSupportLink))
        <a href="{{ route('support') }}" class="btn btn-secondary">Contact Support</a>
    @endif
@endsection
