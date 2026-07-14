@extends('preview.client.layout')

@section('title', $portalLabel)

@section('content')
    <div class="preview-card">
        <h1>{{ $portalLabel }} preview</h1>
        <p class="preview-meta">
            Admin portal preview for <strong>{{ $profile->name }}</strong>.
            Authenticated admin dashboard remains at <code>/admin</code>.
        </p>
    </div>

    @include('preview.client.partials.context-card')
@endsection
