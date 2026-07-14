@extends(client_layout('dashboard', 'admin'))

@section('title', 'Edit CMS page')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.cms-pages.index') }}">CMS pages</a></div>
            <h1 class="jp-page-title">Edit: {{ $cmsPage->title }}</h1>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
            <a href="{{ route('admin.cms-pages.preview', $cmsPage) }}" class="jp-btn jp-btn--ghost jp-btn--sm" target="_blank" rel="noopener">Preview</a>
            @if ($cmsPage->isActive())
                <a href="{{ $cmsPage->route_url }}" class="jp-btn jp-btn--outline jp-btn--sm" target="_blank" rel="noopener">View public</a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @include('dashboard.admin.cms-pages.form')
@endsection
