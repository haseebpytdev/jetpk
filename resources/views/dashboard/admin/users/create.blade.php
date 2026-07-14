@extends(client_layout('dashboard', 'admin'))

@section('title', 'Create user')

@section('page-header')
    <h1 class="jp-page-title">Create user</h1>
@endsection

@section('content')
    @if($errors->any())<div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <div class="jp-card">
        <div class="jp-card__body">
            <form method="post" action="{{ route('admin.users.store') }}">
                @csrf
                @include('dashboard.admin.users.form')
                <div class="mt-3"><button class="jp-btn jp-btn--primary" type="submit">Create user</button></div>
            </form>
        </div>
    </div>
@endsection

