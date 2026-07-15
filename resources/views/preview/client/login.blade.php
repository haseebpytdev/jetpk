@extends('preview.client.layout')

@section('title', $portalLabel)

@section('content')
    <div class="preview-card">
        <h1>{{ $portalLabel }} preview</h1>
        <p class="preview-meta">
            Login shell preview for <strong>{{ $profile->name }}</strong>.
            Production login remains at <code>/login</code>.
        </p>
    </div>

    @include('preview.client.partials.context-card')
@endsection
