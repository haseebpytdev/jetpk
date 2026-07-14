@extends('preview.client.layout')

@section('title', $portalLabel)

@section('content')
    <div class="preview-card">
        <h1>{{ $portalLabel }} preview</h1>
        <p class="preview-meta">
            Staff portal preview for <strong>{{ $profile->name }}</strong>.
            Authenticated staff dashboard remains at <code>/staff</code>.
        </p>
    </div>

    @include('preview.client.partials.context-card')
@endsection
