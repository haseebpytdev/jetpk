@extends(client_layout('dashboard', 'admin'))

@section('title', 'Edit access')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">
                <a href="{{ route('admin.users.show', $userModel) }}" class="text-secondary">User access</a>
            </div>
            <h1 class="jp-page-title">Edit access: {{ $userModel->name }}</h1>
        </div>
    </div>
@endsection

@section('content')
    <div class="jp-card">
        <div class="jp-card__body">
            @if($errors->any())
                <div class="jp-alert jp-alert--danger mb-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <form method="post" action="{{ route('admin.users.update', $userModel) }}">
                @csrf
                @method('PATCH')
                @include('dashboard.admin.users.form')
                <div class="jp-action-bar mt-4">
                    <button class="jp-btn jp-btn--primary" type="submit"><i class="ti ti-device-floppy me-1"></i>Save access changes</button>
                    <a class="jp-btn jp-btn--ghost" href="{{ route('admin.users.show', $userModel) }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
