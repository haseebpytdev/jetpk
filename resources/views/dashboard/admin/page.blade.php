@extends(client_layout('dashboard', 'admin'))

@section('title', $title)

@section('content')
    <div class="jp-card">
        <div class="jp-card__body">
            <h2 class="jp-card__title">{{ $title }}</h2>
            <p class="text-secondary">{{ $description }}</p>
            {{-- Dynamic: datatables, forms, charts per section --}}
        </div>
    </div>
@endsection

