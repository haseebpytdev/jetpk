{{-- MC-8D: diagnostic view — proves client_layout() resolves theme layout shells (tests only) --}}
@extends(client_layout('frontend', 'frontend'))

@section('title', 'Layout resolution smoke')

@section('content')
    <div id="mc8d-layout-smoke">MC-8D: theme layout delegate active</div>
@endsection
