@extends('preview.client.layout')

@section('title', $portalLabel)

@section('content')
    <div class="preview-card">
        <h1>{{ $portalLabel }} preview</h1>
        <p class="preview-meta">
            Agent portal preview for <strong>{{ $profile->name }}</strong>.
            Authenticated agent dashboard remains at <code>/agent</code>.
        </p>
    </div>

    @include('preview.client.partials.context-card')
@endsection
