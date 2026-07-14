@extends(client_layout('dashboard', 'admin'))

@section('title', 'Create Supplier Connection')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Integrations</div>
            <h1 class="jp-page-title">Create supplier connection</h1>
        </div>
    </div>
@endsection

@section('content')
    @include('dashboard.admin.api-settings.form')
@endsection

