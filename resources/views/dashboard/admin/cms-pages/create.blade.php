@extends(client_layout('dashboard', 'admin'))

@section('title', 'Create CMS page')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.cms-pages.index') }}">CMS pages</a></div>
            <h1 class="jp-page-title">Create CMS page</h1>
        </div>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @include('dashboard.admin.cms-pages.form')
@endsection
