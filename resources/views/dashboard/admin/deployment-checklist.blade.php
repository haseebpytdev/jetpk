@extends(client_layout('dashboard', 'admin'))

@section('title', 'Deployment Checklist')

@section('page-header')
    <h1 class="jp-page-title">Deployment Checklist</h1>
@endsection

@section('content')
    <div class="jp-card">
        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Pre-deployment readiness</h3></div>
        <div class="jp-card__body">
            @foreach($items as $item)
                <div class="d-flex justify-content-between border-bottom py-2 jp-between jp-between--compact">
                    <span>{{ $item['label'] }}</span>
                    <span class="jp-badge jp-badge--{{ $item['ok'] ? 'success' : 'warn' }}">{{ $item['ok'] ? 'Ready' : 'Review' }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endsection

