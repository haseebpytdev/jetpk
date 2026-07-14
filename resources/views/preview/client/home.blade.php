@extends('preview.client.layout')

@section('title', $portalLabel)

@section('content')
    <div class="preview-card">
        <h1>{{ $portalLabel }} preview</h1>
        <p class="preview-meta">
            Placeholder for <strong>{{ $profile->name }}</strong> public home.
            Full cloned public routes are not wired in MC-4.
        </p>
    </div>

    @include('preview.client.partials.context-card')
@endsection
