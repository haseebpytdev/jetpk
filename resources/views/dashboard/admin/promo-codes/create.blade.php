@extends(client_layout('dashboard', 'admin'))

@section('title', 'Create promo code')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.promo-codes.index') }}">Promo codes</a></div>
            <h1 class="jp-page-title">Create promo code</h1>
        </div>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @include('dashboard.admin.promo-codes.form')
@endsection
