@extends(client_layout('dashboard', 'admin'))

@section('title', 'Go-live checklist')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Delivery</div>
            <h1 class="jp-page-title">Go-live checklist</h1>
            <div class="text-secondary mt-1">Track launch readiness before production deployment.</div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row g-3">
        @foreach ($items as $item)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 {{ !empty($item['done']) ? 'border-success' : '' }}">
                    <div class="jp-card__body">
                        <div class="d-flex align-items-start gap-2">
                            <span class="avatar {{ !empty($item['done']) ? 'bg-success' : 'bg-secondary' }}" style="flex-shrink:0;">
                                @if (!empty($item['done']))
                                    <i class="ti ti-check text-white"></i>
                                @else
                                    <i class="ti ti-circle text-white"></i>
                                @endif
                            </span>
                            <div>
                                <h3 class="jp-card__title mb-1 ota-title-card">{{ $item['label'] ?? '' }}</h3>
                                <p class="text-secondary small mb-0">{{ $item['note'] ?? '' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="text-secondary small mt-3 mb-0">Use this checklist as a final release gate for operations, supplier readiness, and deployment safety.</p>
@endsection
