@extends(client_layout('dashboard', 'admin'))

@section('title', 'Create Markup Rule')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Pricing Control</div>
            <h1 class="jp-page-title">Create markup rule</h1>
        </div>
        <div class="col-auto">
            <a href="{{ route('admin.markups') }}" class="jp-btn jp-btn--ghost jp-btn--sm">Back to list</a>
        </div>
    </div>
@endsection

@section('content')
    @include('dashboard.admin.markups.form')
@endsection
